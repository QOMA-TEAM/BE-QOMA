<?php

namespace App\Services\Owner;

use App\Models\{Outlet, Subscription};
use App\Services\LaporanKeuanganService;
use Illuminate\Support\Facades\DB;

class OwnerDashboardService
{
    public function __construct(
        private LaporanKeuanganService $laporanService
    ) {}

    /**
     * Data utama dashboard owner
     */
    public function getDashboard(string $usahaId): array
    {
        $totalOutlet = Outlet::where('usaha_id', $usahaId)->count();

        // Subscription aktif
        $sub = Subscription::where('usaha_id', $usahaId)
            ->where('status', 'active')
            ->with('plan:id,nama_plan,batas_outlet')
            ->latest()
            ->first();

        // Laporan keuangan 30 hari terakhir
        $laporan = $this->laporanService->getLaporanByUsaha($usahaId, '30days');

        return [
            'ringkasan' => [
                'total_outlet'      => $totalOutlet,
                'total_pendapatan'  => $laporan['total_pendapatan'],
                'total_pengeluaran' => $laporan['total_pengeluaran'],
                'total_kerugian'    => $laporan['total_kerugian'],
                'total_keuntungan'  => $laporan['total_keuntungan'],
                'status_keuangan'   => $laporan['status'],
            ],
            'subscription' => $sub ? [
                'plan'         => $sub->plan->nama_plan,
                'batas_outlet' => $sub->plan->batas_outlet === -1 ? 'Unlimited' : $sub->plan->batas_outlet,
                'sisa_outlet'  => $sub->plan->batas_outlet === -1
                                    ? 'Unlimited'
                                    : max(0, $sub->plan->batas_outlet - $totalOutlet),
                'end_date'     => $sub->end_date,
            ] : null,
            'outlet_list' => Outlet::where('usaha_id', $usahaId)
                ->select('id', 'nama_outlet', 'alamat', 'status_buka')
                ->get(),
        ];
    }

    /**
     * Grafik pendapatan per outlet
     * Untuk dropdown pilih cabang di dashboard
     */
    public function getGrafik(string $usahaId, string $range = '7days', ?string $outletId = null): array
    {
        // Kalau outlet_id diisi, ambil grafik outlet itu saja
        if ($outletId) {
            $laporan = $this->laporanService->getLaporan($outletId, $range);

            return [
                'outlet_id' => $outletId,
                'range'     => $range,
                'summary'   => $laporan['summary'],
                'grafik'    => $laporan['detail']->map(fn($r) => [
                    'tanggal'          => $r->periode,
                    'total_pendapatan' => (float) $r->total_pendapatan,
                    'total_pengeluaran'=> (float) $r->total_pengeluaran,
                    'total_kerugian'   => (float) $r->total_kerugian,
                    'total_keuntungan' => (float) $r->total_keuntungan,
                ]),
            ];
        }

        // Tanpa outlet_id → ambil semua outlet (default)
        return $this->laporanService->getLaporanByUsaha($usahaId, $range);
    }
}
