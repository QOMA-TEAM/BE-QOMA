<?php
namespace App\Services\Outlet;

use App\Models\{BahanMaster, BahanOutlet, StockMovement, StockOpname};
use App\Services\{ActivityLogService, LaporanKeuanganService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\StokMenipis;

class BahanOutletService
{
    public function __construct(
        private LaporanKeuanganService $laporanService
    ) {}

    // ============================================================
    // LIST & GET
    // ============================================================

    public function getList(string $outletId, array $filters = [])
    {
        $query = BahanOutlet::select('id', 'outlet_id', 'bahan_master_id', 'stok', 'stok_minimum')
                            ->where('outlet_id', $outletId)
                            ->with('bahanMaster:id,nama,satuan,harga_default,gambar');

        if (!empty($filters['search'])) {
            $query->whereHas('bahanMaster', fn($q) =>
                $q->where('nama', 'like', "%{$filters['search']}%")
            );
        }

        if (!empty($filters['menipis'])) {
            $query->whereRaw('stok <= stok_minimum');
        }

        $sortBy  = in_array($filters['sort_by'] ?? '', ['stok', 'created_at'])
                   ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($filters['per_page'] ?? 15);
    }

    // ============================================================
    // RESTOCK — FEFO: insert batch baru ke stock_movements
    // ============================================================

    public function tambah(string $outletId, array $data): BahanOutlet
    {
        $outlet      = \App\Models\Outlet::findOrFail($outletId);
        $bahanMaster = BahanMaster::where('id', $data['bahan_master_id'])
                                  ->where('usaha_id', $outlet->usaha_id)
                                  ->firstOrFail();

        $jumlah           = (float) $data['jumlah'];
        $totalPengeluaran = $jumlah * $bahanMaster->harga_default;

        return DB::transaction(function () use ($outletId, $data, $bahanMaster, $jumlah, $totalPengeluaran) {

            // 1. Pastikan row bahan_outlet ada (create jika belum ada)
            $bahanOutlet = BahanOutlet::firstOrCreate(
                ['outlet_id' => $outletId, 'bahan_master_id' => $bahanMaster->id],
                [
                    'id'           => Str::uuid(),
                    'stok'         => 0,
                    'stok_minimum' => $data['stok_minimum'] ?? 5,
                ]
            );

            // 2. Tambah total stok
            $bahanOutlet->increment('stok', $jumlah);

            // Update stok_minimum jika dikirim
            if (isset($data['stok_minimum'])) {
                $bahanOutlet->update(['stok_minimum' => $data['stok_minimum']]);
            }

            // 3. Insert batch baru ke stock_movements (FEFO)
            StockMovement::create([
                'id'                 => Str::uuid(),
                'outlet_id'          => $outletId,
                'bahan_master_id'    => $bahanMaster->id,
                'type'               => 'in',
                'quantity'           => $jumlah,
                'remaining_quantity' => $jumlah,              // ← sisa batch = jumlah awal
                'expired_date'       => $data['tanggal_kadaluarsa'] ?? null,
                'is_finished'        => false,
                'note'               => "Restock: {$bahanMaster->nama} +{$jumlah} {$bahanMaster->satuan}" .
                                        (isset($data['tanggal_kadaluarsa']) ? " (exp: {$data['tanggal_kadaluarsa']})" : ''),
            ]);

            // 4. Catat pengeluaran pembelian bahan
            \App\Models\Pengeluaran::create([
                'id'              => Str::uuid(),
                'outlet_id'       => $outletId,
                'bahan_master_id' => $bahanMaster->id,
                'sumber'          => "Restock {$bahanMaster->nama} {$jumlah} {$bahanMaster->satuan}",
                'total'           => $totalPengeluaran,
                'tanggal'         => now()->toDateString(),
            ]);

            // 5. Update laporan keuangan
            $this->laporanService->recalculate($outletId, now()->toDateString());

            // 6. Cek & broadcast alert
            $this->broadcastAlertJikaPerlu($outletId);

            ActivityLogService::log(
                'restock_bahan',
                "Restock {$bahanMaster->nama}: +{$jumlah} {$bahanMaster->satuan}" .
                (isset($data['tanggal_kadaluarsa']) ? " | expired: {$data['tanggal_kadaluarsa']}" : ''),
                ['bahan_master_id' => $bahanMaster->id, 'jumlah' => $jumlah],
                null,
                $outletId,
            );

            return $bahanOutlet->fresh('bahanMaster');
        });
    }

    // ============================================================
    // FEFO — Kurangi stok (dipakai oleh pesanan, opname, dll)
    // ============================================================

    /**
     * Kurangi stok pakai FEFO (First Expired First Out)
     * Potong dari batch yang paling dekat expired duluan
     */
    public function kurangiStokFEFO(
        string $outletId,
        string $bahanMasterId,
        float  $jumlahDikurangi,
        string $referenceId,
        string $note
    ): void {
        $bahanOutlet = BahanOutlet::where('outlet_id', $outletId)
                                  ->where('bahan_master_id', $bahanMasterId)
                                  ->firstOrFail();

        if ($bahanOutlet->stok < $jumlahDikurangi) {
            throw new \Exception(
                "Stok tidak cukup. Tersedia: {$bahanOutlet->stok} {$bahanOutlet->bahanMaster->satuan}, " .
                "dibutuhkan: {$jumlahDikurangi}"
            );
        }

        // Ambil batch aktif urut dari yang paling dekat expired (FEFO)
        // Batch tanpa expired_date (null) dipakai paling akhir
        $batches = StockMovement::where('outlet_id', $outletId)
                                ->where('bahan_master_id', $bahanMasterId)
                                ->where('type', 'in')
                                ->where('is_finished', false)
                                ->where('remaining_quantity', '>', 0)
                                ->orderByRaw('CASE WHEN expired_date IS NULL THEN 1 ELSE 0 END')
                                ->orderBy('expired_date', 'asc')
                                ->get();

        $sisaYangHarusDikurangi = $jumlahDikurangi;

        foreach ($batches as $batch) {
            if ($sisaYangHarusDikurangi <= 0) break;

            $diambilDariBatch = min($batch->remaining_quantity, $sisaYangHarusDikurangi);

            $remaining = $batch->remaining_quantity - $diambilDariBatch;

            $batch->update([
                'remaining_quantity' => $remaining,
                'is_finished'        => $remaining <= 0,
            ]);

            // Catat stock movement out per batch
            StockMovement::create([
                'id'                 => Str::uuid(),
                'outlet_id'          => $outletId,
                'bahan_master_id'    => $bahanMasterId,
                'type'               => 'out',
                'quantity'           => $diambilDariBatch,
                'remaining_quantity' => 0,
                'expired_date'       => $batch->expired_date,
                'is_finished'        => true,
                'reference_id'       => $referenceId,
                'note'               => "{$note} (dari batch exp: " .
                                        ($batch->expired_date?->format('d M Y') ?? 'tanpa exp') . ")",
            ]);

            $sisaYangHarusDikurangi -= $diambilDariBatch;
        }

        // Update total stok di bahan_outlet
        $bahanOutlet->decrement('stok', $jumlahDikurangi);

        // Cek & broadcast alert
        $this->broadcastAlertJikaPerlu($outletId);
    }

    // ============================================================
    // KONFIGURASI
    // ============================================================

    public function updateKonfigurasi(BahanOutlet $bahan, array $data): BahanOutlet
    {
        $bahan->update([
            'stok_minimum' => $data['stok_minimum'] ?? $bahan->stok_minimum,
        ]);

        return $bahan->fresh('bahanMaster');
    }

    // ============================================================
    // STOCK OPNAME — DRAFT SYSTEM
    // ============================================================

    public function buatDraftOpname(string $outletId, array $data, ?string $fotoPath = null): StockOpname
    {
        // Validasi bahan ada di outlet ini
        $bahanOutlet = BahanOutlet::where('outlet_id', $outletId)
                                  ->where('bahan_master_id', $data['bahan_master_id'])
                                  ->firstOrFail();

        // Update draft yang sudah ada untuk bahan yang sama
        $existing = StockOpname::where('outlet_id', $outletId)
                               ->where('bahan_master_id', $data['bahan_master_id'])
                               ->where('status', 'draft')
                               ->first();

        if ($existing) {
            if ($fotoPath && $existing->foto_bukti) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($existing->foto_bukti);
            }

            $existing->update([
                'tipe'       => $data['tipe'],
                'jumlah'     => $data['jumlah'],
                'foto_bukti' => $fotoPath ?? $existing->foto_bukti,
                'keterangan' => $data['keterangan'] ?? $existing->keterangan,
            ]);

            return $existing->fresh('bahanMaster');
        }

        return StockOpname::create([
            'id'              => Str::uuid(),
            'outlet_id'       => $outletId,
            'bahan_master_id' => $data['bahan_master_id'],
            'tipe'            => $data['tipe'],
            'jumlah'          => $data['jumlah'],
            'foto_bukti'      => $fotoPath,
            'keterangan'      => $data['keterangan'] ?? null,
            'status'          => 'draft',
        ]);
    }

    public function updateDraftOpname(StockOpname $opname, array $data, ?string $fotoPath = null): StockOpname
    {
        if ($opname->isFinal()) {
            throw new \Exception('Stock opname sudah final, tidak bisa diubah.');
        }

        if ($fotoPath && $opname->foto_bukti) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($opname->foto_bukti);
        }

        $opname->update([
            'tipe'       => $data['tipe']       ?? $opname->tipe,
            'jumlah'     => $data['jumlah']     ?? $opname->jumlah,
            'foto_bukti' => $fotoPath           ?? $opname->foto_bukti,
            'keterangan' => $data['keterangan'] ?? $opname->keterangan,
        ]);

        return $opname->fresh('bahanMaster');
    }

    public function hapusDraftOpname(StockOpname $opname): void
    {
        if ($opname->isFinal()) {
            throw new \Exception('Stock opname sudah final, tidak bisa dihapus.');
        }

        if ($opname->foto_bukti) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($opname->foto_bukti);
        }

        $opname->delete();
    }

    public function getDraftOpname(string $outletId)
    {
        return StockOpname::select('id', 'outlet_id', 'bahan_master_id', 'tipe', 'jumlah', 'foto_bukti', 'keterangan', 'status', 'created_at')
                          ->where('outlet_id', $outletId)
                          ->where('status', 'draft')
                          ->with('bahanMaster:id,nama,satuan,harga_default')
                          ->latest()
                          ->get();
    }

    /**
     * Finalisasi draft → stok dikurangi FEFO
     */
    public function finalisasiOpname(StockOpname $opname): StockOpname
    {
        if ($opname->isFinal()) {
            throw new \Exception('Stock opname sudah final sebelumnya.');
        }

        $bahanOutlet = BahanOutlet::where('outlet_id', $opname->outlet_id)
                                  ->where('bahan_master_id', $opname->bahan_master_id)
                                  ->firstOrFail();

        if ($bahanOutlet->stok < $opname->jumlah) {
            throw new \Exception(
                "Stok tidak cukup. Tersedia: {$bahanOutlet->stok} {$bahanOutlet->bahanMaster->satuan}, " .
                "dibutuhkan: {$opname->jumlah}"
            );
        }

        return DB::transaction(function () use ($opname, $bahanOutlet) {

            // Update status ke final
            $opname->update(['status' => 'final']);

            // Kurangi stok FEFO
            $this->kurangiStokFEFO(
                $opname->outlet_id,
                $opname->bahan_master_id,
                (float) $opname->jumlah,
                $opname->id,
                "Stock opname [{$opname->tipe}]: {$bahanOutlet->bahanMaster->nama}"
            );

            // Hitung dan catat kerugian
            $nilaiKerugian = $opname->jumlah * $bahanOutlet->bahanMaster->harga_default;

            \App\Models\Kerugian::create([
                'id'         => Str::uuid(),
                'outlet_id'  => $opname->outlet_id,
                'total_rugi' => $nilaiKerugian,
                'tanggal'    => now()->toDateString(),
            ]);

            $this->laporanService->recalculate($opname->outlet_id, now()->toDateString());

            ActivityLogService::log(
                'stock_opname_final',
                "Stock opname FINAL [{$opname->tipe}]: {$bahanOutlet->bahanMaster->nama} " .
                "-{$opname->jumlah} {$bahanOutlet->bahanMaster->satuan} " .
                "(Kerugian: Rp " . number_format($nilaiKerugian) . ")",
                ['opname_id' => $opname->id],
                null,
                $opname->outlet_id,
            );

            return $opname->fresh('bahanMaster');
        });
    }

    // ============================================================
    // ALERTS — cek dari stock_movements, bukan bahan_outlet
    // ============================================================

    public function getAlerts(string $outletId): array
    {
        // 1. Stok menipis — dari bahan_outlet
        $stokMenipis = BahanOutlet::select('id', 'outlet_id', 'bahan_master_id', 'stok', 'stok_minimum')
                                  ->where('outlet_id', $outletId)
                                  ->whereRaw('stok <= stok_minimum')
                                  ->where('stok', '>', 0)
                                  ->with('bahanMaster:id,nama,satuan')
                                  ->get()
                                  ->map(fn($b) => [
                                      'tipe'          => 'stok_menipis',
                                      'bahan'         => $b->bahanMaster->nama,
                                      'satuan'        => $b->bahanMaster->satuan,
                                      'stok_saat_ini' => (float) $b->stok,
                                      'stok_minimum'  => (float) $b->stok_minimum,
                                      'pesan'         => "Stok {$b->bahanMaster->nama} menipis! Sisa {$b->stok} {$b->bahanMaster->satuan}",
                                  ]);

        // 2. Batch mendekati expired (≤ 3 hari) — dari stock_movements
        $mendekatiExpired = StockMovement::where('outlet_id', $outletId)
                                         ->where('type', 'in')
                                         ->where('is_finished', false)
                                         ->where('remaining_quantity', '>', 0)
                                         ->whereNotNull('expired_date')
                                         ->whereDate('expired_date', '>', now())
                                         ->whereDate('expired_date', '<=', now()->addDays(3))
                                         ->with('bahanMaster:id,nama,satuan')
                                         ->get()
                                         ->map(fn($m) => [
                                             'tipe'               => 'mendekati_expired',
                                             'bahan'              => $m->bahanMaster->nama,
                                             'satuan'             => $m->bahanMaster->satuan,
                                             'remaining_quantity' => (float) $m->remaining_quantity,
                                             'expired_date'       => $m->expired_date->format('Y-m-d'),
                                             'sisa_hari'          => (int) now()->diffInDays($m->expired_date),
                                             'pesan'              => "{$m->remaining_quantity} {$m->bahanMaster->satuan} {$m->bahanMaster->nama} expired dalam " . now()->diffInDays($m->expired_date) . " hari!",
                                         ]);

        // 3. Batch sudah expired tapi masih ada stok — dari stock_movements
        $sudahExpired = StockMovement::where('outlet_id', $outletId)
                                     ->where('type', 'in')
                                     ->where('is_finished', false)
                                     ->where('remaining_quantity', '>', 0)
                                     ->whereNotNull('expired_date')
                                     ->whereDate('expired_date', '<', now())
                                     ->with('bahanMaster:id,nama,satuan')
                                     ->get()
                                     ->map(fn($m) => [
                                         'tipe'               => 'sudah_expired',
                                         'bahan'              => $m->bahanMaster->nama,
                                         'satuan'             => $m->bahanMaster->satuan,
                                         'remaining_quantity' => (float) $m->remaining_quantity,
                                         'expired_date'       => $m->expired_date->format('Y-m-d'),
                                         'pesan'              => "⚠️ {$m->remaining_quantity} {$m->bahanMaster->satuan} {$m->bahanMaster->nama} EXPIRED sejak {$m->expired_date->format('d M Y')}!",
                                     ]);

        return [
            'total_alert'       => $stokMenipis->count() + $mendekatiExpired->count() + $sudahExpired->count(),
            'stok_menipis'      => $stokMenipis->values(),
            'mendekati_expired' => $mendekatiExpired->values(),
            'sudah_expired'     => $sudahExpired->values(),
        ];
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function broadcastAlertJikaPerlu(string $outletId): void
    {
        try {
            $alerts = $this->getAlerts($outletId);
            if ($alerts['total_alert'] > 0) {
                broadcast(new StokMenipis($outletId, $alerts))->toOthers();
            }
        } catch (\Exception $e) {
            \Log::warning('Broadcast StokMenipis gagal: ' . $e->getMessage());
        }
    }
}