<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Services\Owner\OwnerDashboardService;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function __construct(private OwnerDashboardService $service) {}

    // GET /owner/dashboard
    public function index()
    {
        $usahaId = auth()->user()->usaha_id;

        if (!$usahaId) {
            return response()->json(['message' => 'Owner belum memiliki usaha'], 404);
        }

        return response()->json([
            'message' => 'Dashboard Owner',
            'data'    => $this->service->getDashboard($usahaId),
        ]);
    }

    // GET /owner/dashboard/graph?range=7days&outlet_id=xxx
    public function graph(Request $request)
    {
        $usahaId  = auth()->user()->usaha_id;
        $range    = $request->get('range', '7days');
        $outletId = $request->get('outlet_id');

        if (!in_array($range, ['1day', '7days', '30days'])) {
            return response()->json([
                'message' => 'Range tidak valid. Gunakan: 1day, 7days, 30days'
            ], 422);
        }

        return response()->json([
            'message' => 'Grafik pendapatan',
            'data'    => $this->service->getGrafik($usahaId, $range, $outletId),
        ]);
    }
}
