<?php

use Illuminate\Support\Facades\Route;

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