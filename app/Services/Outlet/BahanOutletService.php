<?php
namespace App\Services\Outlet;

use App\Models\{BahanMaster, BahanOutlet, StockMovement, StockOpname, StockOpnameSession};
use App\Services\{ActivityLogService, ImageService, LaporanKeuanganService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\StokMenipis;

class BahanOutletService
{
    public function __construct(
        private LaporanKeuanganService $laporanService,
        private ImageService $imageService,
    ) {
    }

    // ============================================================
    // LIST & GET
    // ============================================================

    public function getList(string $outletId, array $filters = [])
    {
        $query = BahanOutlet::select('id', 'outlet_id', 'bahan_master_id', 'stok', 'stok_minimum')
            ->where('outlet_id', $outletId)
            ->with('bahanMaster:id,nama,satuan,harga_default,gambar,satuan_dasar,konversi_ke_dasar');

        if (!empty($filters['search'])) {
            $query->whereHas(
                'bahanMaster',
                fn($q) =>
                $q->where('nama', 'ilike', "%{$filters['search']}%")
            );
        }

        if (!empty($filters['satuan']) && $filters['satuan'] !== 'all') {
            $query->whereHas(
                'bahanMaster',
                fn($q) =>
                $q->where('satuan', $filters['satuan'])
            );
        }

        if (!empty($filters['menipis'])) {
            $query->whereRaw('stok <= stok_minimum');
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['stok', 'created_at'])
            ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($filters['per_page'] ?? 15);
    }

    // ============================================================
    // RESTOCK — FEFO: insert batch baru ke stock_movements
    // ============================================================

    public function tambah(string $outletId, array $data): BahanOutlet
    {
        $outlet = \App\Models\Outlet::findOrFail($outletId);

        $bahanMaster = BahanMaster::where('id', $data['bahan_master_id'])
            ->where('usaha_id', $outlet->usaha_id)
            ->firstOrFail();

        $jumlahInput = (float) $data['jumlah'];
        $satuanInput = $data['satuan'] ?? $bahanMaster->satuan;

        // convert ke satuan dasar (gram/ml/pcs)
        $jumlahDasar = \App\Helpers\SatuanHelper::keSatuanDasar(
            $jumlahInput,
            $satuanInput
        );

        // harga per satuan dasar
        $hargaPerDasar = (float) $bahanMaster->harga_default / $bahanMaster->konversi_ke_dasar;
        $totalPengeluaran = isset($data['total_pengeluaran']) ? (float) $data['total_pengeluaran'] : ($jumlahDasar * $hargaPerDasar);

        return DB::transaction(function () use ($outletId, $data, $bahanMaster, $jumlahInput, $satuanInput, $jumlahDasar, $totalPengeluaran) {

            // 1. create / get stok outlet
            $bahanOutlet = BahanOutlet::firstOrCreate(
                [
                    'outlet_id' => $outletId,
                    'bahan_master_id' => $bahanMaster->id
                ],
                [
                    'id' => Str::uuid(),
                    'stok' => 0,
                    'stok_minimum' => $data['stok_minimum'] ?? 500,
                ]
            );

            // 2. tambah stok (dalam satuan dasar)
            $bahanOutlet->increment('stok', $jumlahDasar);

            // update stok minimum kalau ada
            if (isset($data['stok_minimum'])) {
                $satuanMinimum = $data['satuan_minimum'] ?? $satuanInput;

                $minimumDasar = \App\Helpers\SatuanHelper::keSatuanDasar(
                    (float) $data['stok_minimum'],
                    $satuanMinimum
                );

                $bahanOutlet->update([
                    'stok_minimum' => $minimumDasar
                ]);
            }

            // 3. stock movement (FEFO batch)
            StockMovement::create([
                'id' => Str::uuid(),
                'outlet_id' => $outletId,
                'bahan_master_id' => $bahanMaster->id,
                'type' => 'in',
                'quantity' => $jumlahDasar,
                'remaining_quantity' => $jumlahDasar,
                'expired_date' => $data['tanggal_kadaluarsa'] ?? null,
                'is_finished' => 'false',
                'note' => "Restock: {$jumlahInput} {$satuanInput} {$bahanMaster->nama} = {$jumlahDasar} {$bahanMaster->satuan_dasar}",
            ]);

            // 4. pengeluaran
            \App\Models\Pengeluaran::create([
                'id' => Str::uuid(),
                'outlet_id' => $outletId,
                'bahan_master_id' => $bahanMaster->id,
                'sumber' => "Restock {$bahanMaster->nama} {$jumlahInput} {$satuanInput}",
                'total' => $totalPengeluaran,
                'tanggal' => now()->toDateString(),
            ]);

            // 5. laporan
            $this->laporanService->recalculate($outletId, now()->toDateString());

            // 6. alert stok
            $this->broadcastAlertJikaPerlu($outletId);

            // 7. log
            ActivityLogService::log(
                'restock_bahan',
                "Restock {$bahanMaster->nama}: +{$jumlahInput} {$satuanInput} ({$jumlahDasar} {$bahanMaster->satuan_dasar})",
                ['bahan_master_id' => $bahanMaster->id],
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
        float $jumlahDikurangi,
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
            ->where('is_finished', 'false')
            ->where('remaining_quantity', '>', 0)
            ->orderByRaw('CASE WHEN expired_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expired_date', 'asc')
            ->get();

        $sisaYangHarusDikurangi = $jumlahDikurangi;

        foreach ($batches as $batch) {
            if ($sisaYangHarusDikurangi <= 0)
                break;

            $diambilDariBatch = min($batch->remaining_quantity, $sisaYangHarusDikurangi);

            $remaining = $batch->remaining_quantity - $diambilDariBatch;

            $batch->update([
                'remaining_quantity' => $remaining,
                'is_finished' => ($remaining <= 0) ? 'true' : 'false',
            ]);

            // Catat stock movement out per batch
            StockMovement::create([
                'id' => Str::uuid(),
                'outlet_id' => $outletId,
                'bahan_master_id' => $bahanMasterId,
                'type' => 'out',
                'quantity' => $diambilDariBatch,
                'remaining_quantity' => 0,
                'expired_date' => $batch->expired_date,
                'is_finished' => 'true',
                'reference_id' => $referenceId,
                'note' => "{$note} (dari batch exp: " .
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
    // STOCK OPNAME — SESSION BASED (1 hari = 1 sesi)
    // ============================================================

    /**
     * Ambil atau buat sesi stock opname hari ini
     * 1 outlet hanya bisa punya 1 sesi per hari
     */
    public function getAtauBuatSesiHariIni(string $outletId): StockOpnameSession
    {
        $today = now()->toDateString();

        // ← BARU: tutup otomatis sesi lama yang masih 'open' dari hari sebelumnya
        $this->autoTutupSesiLama($outletId);

        $sesi = StockOpnameSession::where('outlet_id', $outletId)
            ->where('tanggal', $today)
            ->first();

        if (!$sesi) {
            $sesi = StockOpnameSession::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'outlet_id' => $outletId,
                'tanggal' => $today,
                'status' => 'open',
            ]);
        }

        return $sesi;
    }

    /**
     * Ambil sesi hari ini beserta semua itemnya
     */
    public function getSesiHariIni(string $outletId): ?StockOpnameSession
    {
        // ← BARU: tutup otomatis sesi lama sebelum cek sesi hari ini
        $this->autoTutupSesiLama($outletId);

        return StockOpnameSession::where('outlet_id', $outletId)
            ->where('tanggal', now()->toDateString())
            ->with([
                'items.bahanMaster:id,nama,satuan,harga_default',
            ])
            ->first();
    }

    /**
     * List semua sesi (history) milik outlet
     */
    public function getListSesi(string $outletId, int $perPage = 10)
    {
        return StockOpnameSession::where('outlet_id', $outletId)
            ->with(['items.bahanMaster:id,nama,satuan,harga_default,gambar'])
            ->withCount([
                'items as total_item',
                'items as total_draft' => fn($q) => $q->where('status', 'draft'),
                'items as total_final' => fn($q) => $q->where('status', 'final'),
            ])
            ->addSelect([
                'total_kerugian' => \App\Models\StockOpname::selectRaw('COALESCE(SUM(stock_opname.jumlah * bahan_master.harga_default), 0)')
                    ->join('bahan_master', 'bahan_master.id', '=', 'stock_opname.bahan_master_id')
                    ->whereColumn('stock_opname.session_id', 'stock_opname_sessions.id')
                    ->where('stock_opname.status', 'final')
            ])
            ->orderByDesc('tanggal')
            ->paginate($perPage);
    }

    /**
     * Buat atau update DRAFT item dalam sesi hari ini
     * Outlet bebas CRUD selama status masih draft
     */
    public function buatDraftItem(
        string $outletId,
        array $data,
        ?string $fotoPath = null
    ): StockOpname {
        $sesi = $this->getAtauBuatSesiHariIni($outletId);

        if ($sesi->isClosed()) {
            throw new \Exception('Sesi stock opname hari ini sudah ditutup. Tidak bisa menambah item baru.');
        }

        // Validasi bahan ada di outlet ini
        $bahanOutlet = BahanOutlet::where('outlet_id', $outletId)
            ->where('bahan_master_id', $data['bahan_master_id'])
            ->firstOrFail();

        // Cek apakah sudah ada draft untuk bahan ini di sesi hari ini
        // (1 bahan hanya boleh 1 draft aktif per sesi)
        $existing = StockOpname::where('session_id', $sesi->id)
            ->where('bahan_master_id', $data['bahan_master_id'])
            ->where('status', 'draft')
            ->first();

        if ($existing) {
            // Update draft yang sudah ada
            if ($fotoPath && $existing->foto_bukti) {
                $this->imageService->delete($existing->foto_bukti);
            }

            $existing->update([
                'tipe' => $data['tipe'],
                'jumlah' => $data['jumlah'],
                'foto_bukti' => $fotoPath ?? $existing->foto_bukti,
                'keterangan' => $data['keterangan'] ?? $existing->keterangan,
            ]);

            return $existing->fresh('bahanMaster');
        }

        // Buat draft baru — stok BELUM dikurangi
        return StockOpname::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'outlet_id' => $outletId,
            'session_id' => $sesi->id,
            'bahan_master_id' => $data['bahan_master_id'],
            'tipe' => $data['tipe'],
            'jumlah' => $data['jumlah'],
            'foto_bukti' => $fotoPath,
            'keterangan' => $data['keterangan'] ?? null,
            'status' => 'draft',
        ]);
    }

    /**
     * Update draft item
     */
    public function updateDraftItem(
        StockOpname $item,
        array $data,
        ?string $fotoPath = null
    ): StockOpname {
        if ($item->isFinal()) {
            throw new \Exception('Item ini sudah difinalisasi, tidak bisa diubah.');
        }

        if ($item->session->isClosed()) {
            throw new \Exception('Sesi stock opname sudah ditutup.');
        }

        if ($fotoPath && $item->foto_bukti) {
            $this->imageService->delete($item->foto_bukti);
        }

        $item->update([
            'tipe' => $data['tipe'] ?? $item->tipe,
            'jumlah' => $data['jumlah'] ?? $item->jumlah,
            'foto_bukti' => $fotoPath ?? $item->foto_bukti,
            'keterangan' => $data['keterangan'] ?? $item->keterangan,
        ]);

        return $item->fresh('bahanMaster');
    }

    /**
     * Hapus draft item
     */
    public function hapusDraftItem(StockOpname $item): void
    {
        if ($item->isFinal()) {
            throw new \Exception('Item sudah difinalisasi, tidak bisa dihapus.');
        }

        if ($item->session->isClosed()) {
            throw new \Exception('Sesi stock opname sudah ditutup.');
        }

        if ($item->foto_bukti) {
            $this->imageService->delete($item->foto_bukti);
        }

        $item->delete();
    }

    /**
     * Finalisasi semua draft dalam sesi hari ini
     * 1x klik simpan
     */
    public function finalisasiSemuaDraft(string $outletId): array
    {
        $sesi = $this->getSesiHariIni($outletId);

        if (!$sesi) {
            throw new \Exception('Tidak ada sesi aktif.');
        }

        if ($sesi->isClosed()) {
            throw new \Exception('Sesi sudah ditutup.');
        }

        $draftItems = StockOpname::where('session_id', $sesi->id)
            ->where('status', 'draft')
            ->with('bahanMaster')
            ->get();

        if ($draftItems->isEmpty()) {
            throw new \Exception('Tidak ada draft.');
        }

        return DB::transaction(function () use ($outletId, $draftItems, $sesi) {

            $hasil = [];
            $totalKerugian = 0;

            foreach ($draftItems as $item) {

                $bahanOutlet = BahanOutlet::where(
                    'outlet_id',
                    $outletId
                )
                    ->where(
                        'bahan_master_id',
                        $item->bahan_master_id
                    )
                    ->with('bahanMaster')
                    ->firstOrFail();

                if (
                    (float) $bahanOutlet->stok <
                    (float) $item->jumlah
                ) {
                    throw new \Exception("Stok {$bahanOutlet->bahanMaster->nama} tidak mencukupi untuk difinalisasi. (Sisa sistem: " . (float) $bahanOutlet->stok . ")");
                }

                $item->update([
                    'status' => 'final',
                    'finalized_at' => now(),
                ]);

                $this->kurangiStokFEFO(
                    $outletId,
                    $item->bahan_master_id,
                    (float) $item->jumlah,
                    $item->id,
                    "Opname [{$item->tipe}]"
                );

                $kerugian =
                    (float) $item->jumlah *
                    (float) $bahanOutlet->bahanMaster->harga_default;

                $totalKerugian += $kerugian;

                \App\Models\Kerugian::create([
                    'id' => Str::uuid(),
                    'outlet_id' => $outletId,
                    'total_rugi' => $kerugian,
                    'tanggal' => now()->toDateString(),
                ]);

                $hasil[] = [
                    'bahan' => $item->bahanMaster->nama,
                    'jumlah' => $item->jumlah,
                    'status' => 'berhasil',
                ];
            }

            $this->laporanService->recalculate(
                $outletId,
                now()->toDateString()
            );

            return [
                'session_id' => $sesi->id,
                'total_item' => count($hasil),
                'total_kerugian' => $totalKerugian,
                'detail' => $hasil,
            ];
        });
    }

    /**
     * Tutup sesi hari ini (dipanggil manual oleh outlet)
     */
    public function tutupSesi(string $outletId): StockOpnameSession
    {
        $sesi = $this->getSesiHariIni($outletId);

        if (!$sesi) {
            throw new \Exception('Tidak ada sesi stock opname aktif hari ini.');
        }

        if ($sesi->isClosed()) {
            throw new \Exception('Sesi sudah ditutup sebelumnya.');
        }

        return $this->prosesTutupSesi($sesi);
    }

    /**
     * Auto-tutup sesi yang masih 'open' dari hari-hari sebelumnya
     * Dipanggil setiap kali getAtauBuatSesiHariIni() dijalankan
     */
    private function autoTutupSesiLama(string $outletId): void
    {
        $sesiLama = StockOpnameSession::where('outlet_id', $outletId)
            ->where('status', 'open')
            ->where('tanggal', '<', now()->toDateString())
            ->get();

        foreach ($sesiLama as $sesi) {
            $this->prosesTutupSesi($sesi, autoClose: true);
        }
    }

    /**
     * Logic inti penutupan sesi — dipakai manual & auto
     * Draft yang belum final akan AUTO-DISIMPAN (finalisasi), bukan dihapus
     */
    private function prosesTutupSesi(StockOpnameSession $sesi, bool $autoClose = false): StockOpnameSession
    {
        return DB::transaction(function () use ($sesi, $autoClose) {

            $draftItems = StockOpname::where('session_id', $sesi->id)
                ->where('status', 'draft')
                ->with('bahanMaster')
                ->get();

            $totalFinalized = 0;
            $totalGagal = 0;
            $totalKerugian = 0;

            foreach ($draftItems as $item) {
                $bahanOutlet = BahanOutlet::where('outlet_id', $sesi->outlet_id)
                    ->where('bahan_master_id', $item->bahan_master_id)
                    ->with('bahanMaster')
                    ->first();

                // Kalau bahan tidak ditemukan atau stok tidak cukup → tidak bisa diselamatkan, hapus
                if (!$bahanOutlet || (float) $bahanOutlet->stok < (float) $item->jumlah) {
                    $this->imageService->delete($item->foto_bukti);
                    $item->delete();
                    $totalGagal++;
                    continue;
                }

                // Finalisasi otomatis — sama seperti klik "Simpan"
                $item->update([
                    'status' => 'final',
                    'finalized_at' => now(),
                ]);

                $this->kurangiStokFEFO(
                    $sesi->outlet_id,
                    $item->bahan_master_id,
                    (float) $item->jumlah,
                    $item->id,
                    "Opname [{$item->tipe}]: {$bahanOutlet->bahanMaster->nama} (auto-simpan saat tutup sesi)"
                );

                $nilaiKerugian = (float) $item->jumlah * (float) $bahanOutlet->bahanMaster->harga_default;
                $totalKerugian += $nilaiKerugian;

                \App\Models\Kerugian::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'outlet_id' => $sesi->outlet_id,
                    'total_rugi' => $nilaiKerugian,
                    'tanggal' => now()->toDateString(),
                ]);

                $totalFinalized++;
            }

            if ($totalFinalized > 0) {
                $this->laporanService->recalculate($sesi->outlet_id, now()->toDateString());
            }

            $sesi->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $jenisTutup = $autoClose ? 'otomatis (ganti hari)' : 'manual';

            $pesanLog = "Sesi stock opname {$sesi->tanggal->format('d M Y')} ditutup {$jenisTutup}.";
            if ($totalFinalized > 0) {
                $pesanLog .= " {$totalFinalized} draft otomatis disimpan (Kerugian: Rp " . number_format($totalKerugian) . ").";
            }
            if ($totalGagal > 0) {
                $pesanLog .= " {$totalGagal} draft dihapus karena bahan tidak ditemukan/stok tidak cukup.";
            }

            ActivityLogService::log(
                'tutup_sesi_opname',
                $pesanLog,
                [
                    'session_id' => $sesi->id,
                    'auto' => $autoClose,
                    'total_finalized' => $totalFinalized,
                    'total_gagal' => $totalGagal,
                ],
                null,
                $sesi->outlet_id,
            );

            return $sesi->fresh(['items.bahanMaster']);
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
                'tipe' => 'stok_menipis',
                'bahan' => $b->bahanMaster->nama,
                'satuan' => $b->bahanMaster->satuan,
                'stok_saat_ini' => (float) $b->stok,
                'stok_minimum' => (float) $b->stok_minimum,
                'pesan' => "Stok {$b->bahanMaster->nama} menipis! Sisa " . (float) $b->stok . " {$b->bahanMaster->satuan}",
            ]);

        // 2. Batch mendekati expired (≤ 3 hari) — dari stock_movements
        $mendekatiExpired = StockMovement::where('outlet_id', $outletId)
            ->where('type', 'in')
            ->where('is_finished', 'false')
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '>', now())
            ->whereDate('expired_date', '<=', now()->addDays(3))
            ->with('bahanMaster:id,nama,satuan')
            ->get()
            ->map(function ($m) {
                $sisaHari = (int) now()->startOfDay()->diffInDays($m->expired_date->startOfDay());
                $qty = (float) $m->remaining_quantity;
                $pesan = $sisaHari === 0
                    ? "{$qty} {$m->bahanMaster->satuan} {$m->bahanMaster->nama} expired hari ini!"
                    : "{$qty} {$m->bahanMaster->satuan} {$m->bahanMaster->nama} expired dalam {$sisaHari} hari!";
                return [
                    'tipe' => 'mendekati_expired',
                    'bahan' => $m->bahanMaster->nama,
                    'satuan' => $m->bahanMaster->satuan,
                    'remaining_quantity' => (float) $m->remaining_quantity,
                    'expired_date' => $m->expired_date->format('Y-m-d'),
                    'sisa_hari' => $sisaHari,
                    'pesan' => $pesan,
                ];
            });

        // 3. Batch sudah expired tapi masih ada stok — dari stock_movements
        $sudahExpired = StockMovement::where('outlet_id', $outletId)
            ->where('type', 'in')
            ->where('is_finished', 'false')
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '<', now())
            ->with('bahanMaster:id,nama,satuan')
            ->get()
            ->map(function ($m) {
                $qty = (float) $m->remaining_quantity;
                return [
                    'tipe' => 'sudah_expired',
                    'bahan' => $m->bahanMaster->nama,
                    'satuan' => $m->bahanMaster->satuan,
                    'remaining_quantity' => $qty,
                    'expired_date' => $m->expired_date->format('Y-m-d'),
                    'pesan' => "{$qty} {$m->bahanMaster->satuan} {$m->bahanMaster->nama} EXPIRED sejak {$m->expired_date->format('d M Y')}!",
                ];
            });

        return [
            'total_alert' => $stokMenipis->count() + $mendekatiExpired->count() + $sudahExpired->count(),
            'stok_menipis' => $stokMenipis->values(),
            'mendekati_expired' => $mendekatiExpired->values(),
            'sudah_expired' => $sudahExpired->values(),
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
