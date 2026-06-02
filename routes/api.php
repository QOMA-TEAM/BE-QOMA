<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Public\PesananPublicController;

// Auth
Route::prefix('auth')->group(base_path('routes/auth.php'));

// Public — pelanggan (TANPA AUTH)
Route::prefix('public')->group(base_path('routes/public.php'));

// Super Admin
Route::prefix('super-admin')->group(base_path('routes/superadmin.php'));

// Owner
Route::prefix('owner')->group(base_path('routes/owner.php'));

// Outlet
Route::prefix('outlet')->group(base_path('routes/outlet.php'));

// cancel oleh pelanggan
Route::post('pesanan/{id}/cancel', [PesananPublicController::class, 'cancel']);
