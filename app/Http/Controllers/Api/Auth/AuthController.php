<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Resources\Auth\UserResource;

class AuthController extends Controller
{
    // POST /auth/login
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Username atau password salah'], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Akun Anda belum aktif atau telah dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message'      => 'Login berhasil',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user' => new UserResource($user),
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
            return response()->json([
                'message' => 'Refresh token sudah expired. Silakan login ulang.',
                'code'    => 'TOKEN_EXPIRED',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token tidak valid.',
                'code'    => 'TOKEN_INVALID',
            ], 401);
        }
    }

    // GET /auth/me
    public function me()
    {
        $user = auth()->user()->load('role');

        return response()->json([
            'id'           => $user->id,
            'username'     => $user->username,
            'nama_lengkap' => $user->nama_lengkap,
            'email'        => $user->email,
            'role'         => $user->role->name,
        ]);
    }
}