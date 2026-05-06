<?php
namespace App\Services\Outlet;

use App\Models\{BahanMaster, BahanOutlet, StockMovement, StockOpname};
use App\Services\{ActivityLogService, LaporanKeuanganService};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use App\Events\StokMenipis;

class BahanOutletService
{
    public function __construct(
        private LaporanKeuanganService $laporanService
    ) {}

    /**
     * List bahan outlet + filter + sort
     */
    public function getList(string $outletId, array $filters = [])
    {
        $query = BahanOutlet::where('outlet_id', $outletId)
                            ->with('bahanMaster:id,nama,satuan,harga_default,gambar');

        // Filter
        if (!empty($filters['search'])) {
            $query->whereHas('bahanMaster', fn($q) =>
                $q->where('nama', 'like', "%{$filters['search']}%")
            );
        }

        if (!empty($filters['menipis'])) {
            // Hanya tampilkan yang stok <= stok_minimum
            $query->whereRaw('stok <= stok_minimum');
        }

        if (!empty($filters['mendekati_expired'])) {
            $query->whereNotNull('tanggal_kadaluarsa')
                  ->whereDate('tanggal_kadaluarsa', '<=', now()->addDays(3))
                  ->whereDate('tanggal_kadaluarsa', '>=', now());
        }

        // Sorting
        $sortBy  = $filters['sort_by']  ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $allowedSort = ['stok', 'tanggal_kadaluarsa', 'tanggal_masuk', 'created_at'];
        if (in_array($sortBy, $allowedSort)) {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Tambah bahan baku baru ke outlet
     * (pilih dari bahan_master milik owner)
     */
    public function tambah(string $outletId, array $data): BahanOutlet
    {
        // Validasi bahan master ada dan milik usaha yang sama
        $outletUsahaId = \App\Models\Outlet::find($outletId)->usaha_id;
        $bahanMaster   = BahanMaster::where('id', $data['bahan_master_id'])
                                    ->where('usaha_id', $outletUsahaId)
                                    ->firstOrFail();

        // Hitung total pengeluaran
        $totalPengeluaran = ($data['jumlah'] ?? 0) * $bahanMaster->harga_default;

        return DB::transaction(function () use ($outletId, $data, $bahanMaster, $totalPengeluaran) {

            // Cek apakah bahan sudah ada di outlet ini
            $existing = BahanOutlet::where('outlet_id', $outletId)
                                   ->where('bahan_master_id', $bahanMaster->id)
                                   ->first();

            if ($existing) {
                // Update stok (tambah)
                $existing->update([
                    'stok'               => $existing->stok + $data['jumlah'],
                    'tanggal_masuk'      => $data['tanggal_masuk'] ?? $existing->tanggal_masuk,
                    'tanggal_kadaluarsa' => $data['tanggal_kadaluarsa'] ?? $existing->tanggal_kadaluarsa,
                    'stok_minimum'       => $data['stok_minimum'] ?? $existing->stok_minimum,
                ]);
                $bahan = $existing->fresh('bahanMaster');
            } else {
                // Buat baru
                $bahan = BahanOutlet::create([
                    'id'                 => Str::uuid(),
                    'outlet_id'          => $outletId,
                    'bahan_master_id'    => $bahanMaster->id,
                    'stok'               => $data['jumlah'],
                    'stok_minimum'       => $data['stok_minimum'] ?? 5,
                    'tanggal_masuk'      => $data['tanggal_masuk'] ?? null,
                    'tanggal_kadaluarsa' => $data['tanggal_kadaluarsa'] ?? null,
                ]);
            }

            // Catat stock movement (in)
            StockMovement::create([
                'id'              => Str::uuid(),
                'outlet_id'       => $outletId,
                'bahan_master_id' => $bahanMaster->id,
                'type'            => 'in',
                'quantity'        => $data['jumlah'],
                'expired_date'    => $data['tanggal_kadaluarsa'] ?? null,
                'note'            => "Penambahan stok: {$bahanMaster->nama} {$data['jumlah']} {$bahanMaster->satuan}",
            ]);

            // Catat pengeluaran (pembelian bahan baku)
            \App\Models\Pengeluaran::create([
                'id'              => Str::uuid(),
                'outlet_id'       => $outletId,
                'bahan_master_id' => $bahanMaster->id,
                'sumber'          => "Beli {$bahanMaster->nama} {$data['jumlah']} {$bahanMaster->satuan}",
                'total'           => $totalPengeluaran,
                'tanggal'         => now()->toDateString(),
            ]);

            // Update laporan keuangan
            $this->laporanService->recalculate($outletId, now()->toDateString());
            
            $alerts = $this->getAlerts($outletId);
                if ($alerts['total_alert'] > 0) {
                    broadcast(new StokMenipis($outletId, $alerts))->toOthers();
                }
                
            ActivityLogService::log(
                'tambah_bahan_outlet',
                "Tambah stok {$bahanMaster->nama}: +{$data['jumlah']} {$bahanMaster->satuan}",
                ['bahan_master_id' => $bahanMaster->id, 'jumlah' => $data['jumlah']],
                null,
                $outletId,
            );


            return $bahan;
        });
    }

    /**
     * Update konfigurasi bahan outlet (stok_minimum, dll)
     */
    public function updateKonfigurasi(BahanOutlet $bahan, array $data): BahanOutlet
    {
        $bahan->update([
            'stok_minimum'       => $data['stok_minimum']       ?? $bahan->stok_minimum,
            'tanggal_kadaluarsa' => $data['tanggal_kadaluarsa'] ?? $bahan->tanggal_kadaluarsa,
        ]);

        return $bahan->fresh('bahanMaster');
    }

    /**
     * Stock Opname — pengurangan stok manual (bahan rusak, busuk, dll)
     */
    public function stockOpname(string $outletId, array $data, ?string $fotoPath = null): StockOpname
    {
        $bahan = BahanOutlet::where('outlet_id', $outletId)
                            ->where('bahan_master_id', $data['bahan_master_id'])
                            ->firstOrFail();

        if ($bahan->stok < $data['jumlah']) {
            throw new \Exception("Stok tidak cukup. Stok saat ini: {$bahan->stok} {$bahan->bahanMaster->satuan}");
        }

        return DB::transaction(function () use ($outletId, $data, $bahan, $fotoPath) {

            // Kurangi stok
            $bahan->decrement('stok', $data['jumlah']);

            // Buat record stock opname
            $opname = StockOpname::create([
                'id'              => Str::uuid(),
                'outlet_id'       => $outletId,
                'bahan_master_id' => $data['bahan_master_id'],
                'tipe'            => $data['tipe'], // busuk, rusak, ga_layak, hilang
                'jumlah'          => $data['jumlah'],
                'foto_bukti'      => $fotoPath,
                'keterangan'      => $data['keterangan'] ?? null,
            ]);

            // Catat stock movement (out)
            StockMovement::create([
                'id'              => Str::uuid(),
                'outlet_id'       => $outletId,
                'bahan_master_id' => $data['bahan_master_id'],
                'type'            => 'out',
                'quantity'        => $data['jumlah'],
                'reference_id'    => $opname->id,
                'note'            => "Stock opname [{$data['tipe']}]: {$bahan->bahanMaster->nama} -{$data['jumlah']} {$bahan->bahanMaster->satuan}",
            ]);

            // Hitung kerugian dari opname (jumlah × harga_default bahan)
            $nilaiKerugian = $data['jumlah'] * $bahan->bahanMaster->harga_default;

            \App\Models\Kerugian::create([
                'id'        => Str::uuid(),
                'outlet_id' => $outletId,
                'total_rugi'=> $nilaiKerugian,
                'tanggal'   => now()->toDateString(),
            ]);

            // Update laporan keuangan
            $this->laporanService->recalculate($outletId, now()->toDateString());

            ActivityLogService::log(
                'stock_opname',
                "Stock opname [{$data['tipe']}]: {$bahan->bahanMaster->nama} -{$data['jumlah']} {$bahan->bahanMaster->satuan} (Kerugian: Rp " . number_format($nilaiKerugian) . ")",
                ['opname_id' => $opname->id, 'tipe' => $data['tipe'], 'jumlah' => $data['jumlah']],
                null,
                $outletId,
            );

            return $opname->load('bahanMaster');
        });
    }

    /**
     * Alert system — stok menipis + mendekati expired
     */
    public function getAlerts(string $outletId): array
    {
        // Stok menipis (stok <= stok_minimum)
        $stokMenipis = BahanOutlet::where('outlet_id', $outletId)
            ->whereRaw('stok <= stok_minimum')
            ->with('bahanMaster:id,nama,satuan')
            ->get()
            ->map(fn($b) => [
                'tipe'         => 'stok_menipis',
                'bahan'        => $b->bahanMaster->nama,
                'satuan'       => $b->bahanMaster->satuan,
                'stok_saat_ini'=> (float) $b->stok,
                'stok_minimum' => (float) $b->stok_minimum,
                'pesan'        => "Stok {$b->bahanMaster->nama} menipis! Sisa {$b->stok} {$b->bahanMaster->satuan}",
            ]);

        // Mendekati expired (dalam 3 hari ke depan)
        $mendekatiExpired = BahanOutlet::where('outlet_id', $outletId)
            ->whereNotNull('tanggal_kadaluarsa')
            ->whereDate('tanggal_kadaluarsa', '<=', now()->addDays(3))
            ->whereDate('tanggal_kadaluarsa', '>=', now())
            ->with('bahanMaster:id,nama,satuan')
            ->get()
            ->map(fn($b) => [
                'tipe'               => 'mendekati_expired',
                'bahan'              => $b->bahanMaster->nama,
                'satuan'             => $b->bahanMaster->satuan,
                'stok_saat_ini'      => (float) $b->stok,
                'tanggal_kadaluarsa' => $b->tanggal_kadaluarsa->format('Y-m-d'),
                'sisa_hari'          => (int) now()->diffInDays($b->tanggal_kadaluarsa),
                'pesan'              => "Stok {$b->bahanMaster->nama} akan expired " . now()->diffInDays($b->tanggal_kadaluarsa) . " hari lagi!",
            ]);

        // Sudah expired
        $sudahExpired = BahanOutlet::where('outlet_id', $outletId)
            ->whereNotNull('tanggal_kadaluarsa')
            ->whereDate('tanggal_kadaluarsa', '<', now())
            ->where('stok', '>', 0)
            ->with('bahanMaster:id,nama,satuan')
            ->get()
            ->map(fn($b) => [
                'tipe'               => 'sudah_expired',
                'bahan'              => $b->bahanMaster->nama,
                'stok_saat_ini'      => (float) $b->stok,
                'tanggal_kadaluarsa' => $b->tanggal_kadaluarsa->format('Y-m-d'),
                'pesan'              => "⚠️ {$b->bahanMaster->nama} sudah EXPIRED sejak {$b->tanggal_kadaluarsa->format('d M Y')}!",
            ]);

        return [
            'total_alert'      => $stokMenipis->count() + $mendekatiExpired->count() + $sudahExpired->count(),
            'stok_menipis'     => $stokMenipis,
            'mendekati_expired'=> $mendekatiExpired,
            'sudah_expired'    => $sudahExpired,
        ];
    }
}