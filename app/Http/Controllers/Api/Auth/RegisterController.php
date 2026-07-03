<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Auth\RegisterService;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function __construct(private RegisterService $service) {}

    public function plans()
    {
        $plans = Plan::select('id', 'nama_plan', 'harga', 'batas_outlet', 'durasi_hari', 'is_lifetime', 'deskripsi')
                     ->get()
                     ->map(fn($plan) => [
                         'id'           => $plan->id,
                         'nama_plan'    => $plan->nama_plan,
                         'harga'        => (float) $plan->harga,
                         'batas_outlet' => $plan->batas_outlet === -1 ? 'Unlimited' : $plan->batas_outlet,
                         'durasi'       => $plan->is_lifetime ? 'Selamanya' : "{$plan->durasi_hari} hari",
                         'deskripsi'    => $plan->deskripsi,
                         'is_free'      => $plan->harga == 0,
                     ]);

        return response()->json(['message' => 'Daftar plan', 'data' => $plans]);
    }

    public function register(Request $request)
    {
        $request->validate([
            // Data Owner
            'nama_owner'          => 'required|string|max:100',  
            'username'            => 'required|string|min:4|unique:users,username',
            'email'               => 'required|email|unique:users,email|unique:usaha,email',
            'no_telp'             => 'required|string|max:20',  
            'password'            => 'required|string|min:6|confirmed',

            // Data Usaha
            'nama_usaha'          => 'required|string|max:100',
            'telp_usaha'          => 'required|string|max:20',   
            'alamat'              => 'nullable|string|max:255',
            'deskripsi_usaha'     => 'nullable|string|max:1000', 

            // Plan & Pembayaran
            'plan_id'             => 'required|exists:plans,id',
            'metode_pembayaran'   => 'nullable|in:transfer,qris',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        if ($plan->harga > 0) {
            $request->validate([
                'metode_pembayaran' => 'required|in:transfer,qris',
            ]);
        }

        try {
            $result = $this->service->register($request->all());
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
