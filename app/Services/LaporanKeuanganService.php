<?php

namespace App\Services;

use App\Models\{ LaporanKeuangan, Outlet};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LaporanKeuanganService
{
    /**
     * Ambil atau buat record laporan untuk outlet + periode tertentu.
     * Ini adalah helper utama — semua method lain pakai ini.
     */
    private function getOrCreate(string $outletId, string $periode, string $tipe = 'daily'): LaporanKeuangan
    {
        return LaporanKeuangan::firstOrCreate(
            [
                'outlet_id'    => $outletId,
                'periode'      => $periode,
                'tipe_periode' => $tipe,
            ],
            [
                'id'                => Str::uuid(),
                'total_pendapatan'  => 0,
                'total_pengeluaran' => 0,
                'total_kerugian'    => 0,
                'total_keuntungan'  => 0,
            ]
        );
    }

    /**
     * Recalculate dan simpan semua komponen laporan untuk 1 outlet 1 hari.
     * Selalu hitung ulang dari scratch supaya tidak ada data stale.
     *
     * Dipanggil setiap kali ada event (pesanan paid, pengeluaran, stock opname).
     */
    public function recalculate(string $outletId, string $tanggal): LaporanKeuangan
    {
        return DB::transaction(function () use ($outletId, $tanggal) {

            // 1. Total pendapatan dari pesanan paid di hari ini
            $totalPendapatan = DB::table('pesanan')
                ->where('outlet_id', $outletId)
                ->where('status', 'paid')
                ->whereDate('updated_at', $tanggal)
                ->sum('total_harga');

            // 2. Total pengeluaran bahan baku di hari ini
            $totalPengeluaran = DB::table('pengeluaran')
                ->where('outlet_id', $outletId)
                ->where('tanggal', $tanggal)
                ->sum('total');

            // 3. Total kerugian dari 2 sumber:

            // 3a. Kerugian manual (input outlet)
            $kerugianManual = DB::table('kerugian')
                ->where('outlet_id', $outletId)
                ->where('tanggal', $tanggal)
                ->sum('total_rugi');

            // 3b. Kerugian dari stock opname (bahan rusak/busuk/hilang)
            //     Hitung: jumlah × harga_default bahan_master
            $kerugianStockOpname = DB::table('stock_opname')
                ->join('bahan_master', 'stock_opname.bahan_master_id', '=', 'bahan_master.id')
                ->where('stock_opname.outlet_id', $outletId)
                ->where('stock_opname.tipe', 'keluar') // keluar = rusak/busuk/hilang
                ->whereDate('stock_opname.created_at', $tanggal)
                ->sum(DB::raw('stock_opname.jumlah * bahan_master.harga_default'));

            $totalKerugian = $kerugianManual + $kerugianStockOpname;

            // 4. Keuntungan = pendapatan - pengeluaran - kerugian
            $totalKeuntungan = $totalPendapatan - $totalPengeluaran - $totalKerugian;

            // 5. Simpan / update laporan harian
            $laporan = $this->getOrCreate($outletId, $tanggal, 'daily');
            $laporan->update([
                'total_pendapatan'  => $totalPendapatan,
                'total_pengeluaran' => $totalPengeluaran,
                'total_kerugian'    => $totalKerugian,
                'total_keuntungan'  => $totalKeuntungan,
            ]);

            // 6. Update juga laporan bulanan (format: "2026-05")
            $bulan = substr($tanggal, 0, 7);
            $this->recalculateBulanan($outletId, $bulan);

            return $laporan->fresh();
        });
    }

    /**
     * Recalculate laporan bulanan dari aggregate laporan harian.
     */
    public function recalculateBulanan(string $outletId, string $bulan): LaporanKeuangan
    {
        $aggregate = LaporanKeuangan::where('outlet_id', $outletId)
            ->where('tipe_periode', 'daily')
            ->where('periode', 'like', "{$bulan}%")
            ->selectRaw('
                SUM(total_pendapatan)  as pendapatan,
                SUM(total_pengeluaran) as pengeluaran,
                SUM(total_kerugian)    as kerugian,
                SUM(total_keuntungan)  as keuntungan
            ')
            ->first();

        $laporan = $this->getOrCreate($outletId, $bulan, 'monthly');
        $laporan->update([
            'total_pendapatan'  => $aggregate->pendapatan  ?? 0,
            'total_pengeluaran' => $aggregate->pengeluaran ?? 0,
            'total_kerugian'    => $aggregate->kerugian    ?? 0,
            'total_keuntungan'  => $aggregate->keuntungan  ?? 0,
        ]);

        return $laporan->fresh();
    }

    /**
     * Ambil laporan untuk range tertentu (untuk dashboard & grafik).
     *
     * @param string $outletId
     * @param string $range    '1day' | '7days' | '30days'
     */
    public function getLaporan(string $outletId, string $range = '7days'): array
    {
        [$dari, $sampai] = match($range) {
            '1day'  => [now()->toDateString(), now()->toDateString()],
            '7days' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            default => [now()->subDays(29)->toDateString(), now()->toDateString()],
        };

        $data = LaporanKeuangan::select('id', 'outlet_id', 'total_pendapatan', 'total_pengeluaran', 'total_kerugian', 'total_keuntungan', 'periode')
                            ->where('outlet_id', $outletId)
                            ->where('tipe_periode', 'daily')
                            ->whereBetween('periode', [$dari, $sampai])
                            ->orderBy('periode')
                            ->get();

        $summary = [
            'total_pendapatan'  => (float) $data->sum('total_pendapatan'),
            'total_pengeluaran' => (float) $data->sum('total_pengeluaran'),
            'total_kerugian'    => (float) $data->sum('total_kerugian'),
            'total_keuntungan'  => (float) $data->sum('total_keuntungan'),
            'status'            => $data->sum('total_keuntungan') >= 0 ? 'untung' : 'rugi',
        ];

        return ['range' => $range, 'summary' => $summary, 'detail' => $data];
    }

    public function getLaporanByUsaha(string $usahaId, string $range = '30days'): array
    {
        [$dari, $sampai] = match($range) {
            '1day'  => [now()->toDateString(), now()->toDateString()],
            '7days' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            default => [now()->subDays(29)->toDateString(), now()->toDateString()],
        };

        // 1 query untuk ambil semua outlet_id
        $outletIds = Outlet::where('usaha_id', $usahaId)->pluck('id');

        if ($outletIds->isEmpty()) {
            return [
                'range' => $range, 'dari' => $dari, 'sampai' => $sampai,
                'total_pendapatan' => 0, 'total_pengeluaran' => 0,
                'total_kerugian' => 0, 'total_keuntungan' => 0,
                'status' => 'untung', 'per_outlet' => [],
            ];
        }

        // 1 query untuk semua laporan semua outlet
        $semuaLaporan = LaporanKeuangan::select('id', 'outlet_id', 'total_pendapatan', 'total_pengeluaran', 'total_kerugian', 'total_keuntungan', 'periode')
                                    ->whereIn('outlet_id', $outletIds)
                                    ->where('tipe_periode', 'daily')
                                    ->whereBetween('periode', [$dari, $sampai])
                                    ->with('outlet:id,nama_outlet')
                                    ->orderBy('periode')
                                    ->get();

        $global = [
            'total_pendapatan'  => (float) $semuaLaporan->sum('total_pendapatan'),
            'total_pengeluaran' => (float) $semuaLaporan->sum('total_pengeluaran'),
            'total_kerugian'    => (float) $semuaLaporan->sum('total_kerugian'),
            'total_keuntungan'  => (float) $semuaLaporan->sum('total_keuntungan'),
        ];

        // Group by outlet_id — tidak ada query tambahan
        $perOutlet = $semuaLaporan->groupBy('outlet_id')->map(fn($rows) => [
            'outlet_id'         => $rows->first()->outlet_id,
            'nama_outlet'       => $rows->first()->outlet->nama_outlet ?? '-',
            'total_pendapatan'  => (float) $rows->sum('total_pendapatan'),
            'total_pengeluaran' => (float) $rows->sum('total_pengeluaran'),
            'total_kerugian'    => (float) $rows->sum('total_kerugian'),
            'total_keuntungan'  => (float) $rows->sum('total_keuntungan'),
            'grafik'            => $rows->map(fn($r) => [
                'tanggal'          => $r->periode,
                'total_pendapatan' => (float) $r->total_pendapatan,
                'total_keuntungan' => (float) $r->total_keuntungan,
            ]),
        ])->values();

        return array_merge($global, [
            'range'  => $range,
            'dari'   => $dari,
            'sampai' => $sampai,
            'status' => $global['total_keuntungan'] >= 0 ? 'untung' : 'rugi',
            'per_outlet' => $perOutlet,
        ]);
    }
}