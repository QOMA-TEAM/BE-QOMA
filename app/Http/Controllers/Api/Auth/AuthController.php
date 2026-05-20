<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\{JWTException, TokenExpiredException};
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // POST /auth/login — ganti username → email
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Akun belum aktif atau telah dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message'      => 'Login berhasil',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user'         => new UserResource($user->load('role')),
        ]);
    }

    // POST /auth/logout
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Logout berhasil']);
    }

    // POST /auth/refresh
    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
            return response()->json([
                'message'      => 'Token berhasil direfresh',
                'access_token' => $newToken,
                'token_type'   => 'bearer',
                'expires_in'   => config('jwt.ttl') * 60,
            ]);
        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'Refresh token expired. Silakan login ulang.', 'code' => 'TOKEN_EXPIRED'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token tidak valid.', 'code' => 'TOKEN_INVALID'], 401);
        }
    }

    // GET /auth/me
    public function me()
    {
        $user = auth()->user()->load('role');
        return response()->json(new UserResource($user));
    }
}