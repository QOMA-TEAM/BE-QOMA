<?php
namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Services\Outlet\BahanOutletService;
use App\Services\LaporanKeuanganService;
use App\Traits\OutletAccess;
use Illuminate\Http\Request;

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
                    'gambar_icon'   => $outlet->gambar_icon ? asset('storage/' . $outlet->gambar_icon) : null,
                    'gambar_header' => $outlet->gambar_header ? asset('storage/' . $outlet->gambar_header) : null,

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

    // PATCH /outlet/gambar
    public function updateGambar(Request $request)
    {
        $outlet = $this->getOutlet();

        $request->validate([
            'gambar_icon'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'gambar_header' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $imageService = app(\App\Services\ImageService::class);

        if ($request->hasFile('gambar_icon')) {
            $iconPath = $imageService->replace(
                $request->file('gambar_icon'),
                $outlet->gambar_icon,
                "outlet/{$outlet->usaha_id}/icon"
            );
            $outlet->update(['gambar_icon' => $iconPath]);
        }

        if ($request->hasFile('gambar_header')) {
            $headerPath = $imageService->replace(
                $request->file('gambar_header'),
                $outlet->gambar_header,
                "outlet/{$outlet->usaha_id}/header"
            );
            $outlet->update(['gambar_header' => $headerPath]);
        }

        \App\Services\ActivityLogService::log(
            'update_gambar_outlet',
            "Gambar outlet diupdate",
            [],
            null,
            $outlet->id,
        );

        return response()->json([
            'message'       => 'Gambar outlet berhasil diupdate',
            'gambar_icon'   => $outlet->fresh()->gambar_icon
                                ? asset('storage/' . $outlet->fresh()->gambar_icon)
                                : null,
            'gambar_header' => $outlet->fresh()->gambar_header
                                ? asset('storage/' . $outlet->fresh()->gambar_header)
                                : null,
        ]);
    }
}