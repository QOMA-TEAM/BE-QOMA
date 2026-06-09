<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Services\Outlet\BahanOutletService;
use App\Services\LaporanKeuanganService;
use App\Traits\OutletAccess;

class OutletDashboardController extends Controller
{
    use OutletAccess;

    public function __construct(
        private BahanOutletService $bahanService,
        private LaporanKeuanganService $laporanService,
    ) {}

    // GET /outlet/dashboard
    public function index()
    {
        $outlet  = $this->getOutlet();
        $laporan = $this->laporanService->getLaporan($outlet->id, '7days');
        $alerts  = $this->bahanService->getAlerts($outlet->id);

        return response()->json([
            'message' => 'Dashboard Outlet',
            'data'    => [
                'outlet'  => [
                    'id'          => $outlet->id,
                    'nama_outlet' => $outlet->nama_outlet,
                    'status_buka' => $outlet->status_buka,
                ],
                'keuangan_7_hari' => $laporan['summary'],
                'grafik_pendapatan' => $laporan['detail'],
                'alert_summary'   => [
                    'total'            => $alerts['total_alert'],
                    'stok_menipis'     => count($alerts['stok_menipis']),
                    'mendekati_expired'=> count($alerts['mendekati_expired']),
                    'sudah_expired'    => count($alerts['sudah_expired']),
                ],
                'alerts' => [
                    'stok_menipis'      => $alerts['stok_menipis'],
                    'mendekati_expired' => $alerts['mendekati_expired'],
                    'sudah_expired'     => $alerts['sudah_expired'],
                ],
            ],
        ]);
    }

    // PATCH /outlet/toggle-status — buka/tutup toko
    public function toggleStatus()
    {
        $outlet = $this->getOutlet();
        $outlet->update(['status_buka' => !$outlet->status_buka]);

        $status = $outlet->status_buka ? 'dibuka' : 'ditutup';

        \App\Services\ActivityLogService::log(
            'toggle_outlet_status',
            "Outlet {$status}",
            ['status_buka' => $outlet->status_buka],
            null,
            $outlet->id,
        );

        return response()->json([
            'message'     => "Outlet berhasil {$status}",
            'status_buka' => $outlet->fresh()->status_buka,
        ]);
    }
}