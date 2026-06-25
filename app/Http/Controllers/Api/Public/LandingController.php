<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Plan;

class LandingController extends Controller
{
    public function plans()
    {
        $plans = Plan::orderBy('harga', 'asc')->get();

        return response()->json([
            'message' => 'Daftar plan aktif',
            'data'    => $plans,
        ]);
    }
}
