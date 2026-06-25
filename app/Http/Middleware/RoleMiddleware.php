<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json([
                'message' => 'Token sudah expired. Gunakan /api/auth/refresh untuk token baru.',
                'code'    => 'TOKEN_EXPIRED',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token tidak valid atau tidak ditemukan. Silakan login.',
                'code'    => 'TOKEN_INVALID',
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
                'code'    => 'USER_NOT_FOUND',
            ], 401);
        }

        // Kalau tidak ada roles yang dipass, berarti hanya butuh login (any role)
        if (!empty($roles)) {
            $userRole = $user->role->name ?? null;

            if (!in_array($userRole, $roles)) {
                return response()->json([
                    'message' => "Akses ditolak. Role kamu: '{$userRole}'. Dibutuhkan: " . implode(' atau ', $roles),
                    'code'    => 'FORBIDDEN',
                ], 403);
            }
        }

        return $next($request);
    }
}
