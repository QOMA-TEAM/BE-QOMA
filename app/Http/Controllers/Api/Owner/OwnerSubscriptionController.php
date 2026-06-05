<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Services\Owner\OwnerSubscriptionService;
use Illuminate\Http\Request;

class OwnerSubscriptionController extends Controller
{
    public function __construct(private OwnerSubscriptionService $service) {}

    // GET /owner/subscription
    public function index()
    {
        $usahaId = auth()->user()->usaha_id;

        return response()->json([
            'message' => 'Info subscription',
            'data'    => $this->service->getAktif($usahaId),
        ]);
    }

    // GET /owner/subscription/plans — tampilkan plan yang bisa di-upgrade
    public function availablePlans()
    {
        $usahaId = auth()->user()->usaha_id;
        $plans   = $this->service->getAvailablePlans($usahaId);

        if (empty($plans)) {
            return response()->json([
                'message' => 'Kamu sudah berada di plan tertinggi.',
                'data'    => [],
            ]);
        }

        return response()->json([
            'message' => 'Plan tersedia untuk upgrade',
            'data'    => $plans,
        ]);
    }

    // POST /owner/subscription/upgrade
    public function upgrade(Request $request)
    {
        $request->validate([
            'plan_id'           => 'required|exists:plans,id',
            'metode_pembayaran' => 'required|in:transfer,qris',
        ]);

        $usahaId = auth()->user()->usaha_id;

        try {
            $result = $this->service->upgrade($usahaId, $request->plan_id, $request->metode_pembayaran);
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /owner/subscription/pilih-outlet-nonaktif
    public function pilihOutletNonaktif(Request $request)
    {
        $request->validate([
            'outlet_ids'   => 'required|array|min:1',
            'outlet_ids.*' => 'required|string|exists:outlet,id',
        ]); 

        $usahaId = auth()->user()->usaha_id;

        try {
            $result = $this->service->pilihOutletNonaktif($usahaId, $request->outlet_ids);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}