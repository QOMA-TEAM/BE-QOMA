<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class ChangePasswordController extends Controller
{
    /**
     * POST /auth/change-password
     * Dipakai semua role: super_admin, owner, outlet
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:6|confirmed', // butuh password_baru_confirmation
        ]);

        $user = JWTAuth::parseToken()->authenticate();

        // Verifikasi password lama
        if (!Hash::check($request->password_lama, $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai.',
                'code'    => 'WRONG_OLD_PASSWORD',
            ], 422);
        }

        // Cek password baru tidak sama dengan yang lama
        if (Hash::check($request->password_baru, $user->password)) {
            return response()->json([
                'message' => 'Password baru tidak boleh sama dengan password lama.',
                'code'    => 'SAME_PASSWORD',
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password_baru),
        ]);

        // Catat activity log
        ActivityLogService::log(
            'change_password',
            "User '{$user->username}' mengubah password",
            [],
            $user->usaha_id,
            $user->outlet_id,
        );

        // Invalidate token lama — user harus login ulang
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login kembali dengan password baru.',
        ]);
    }
}