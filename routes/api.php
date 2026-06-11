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

// Shared Authenticated Routes
Route::middleware('role')->prefix('shared')->group(function () {
    // Notifications
    Route::get  ('notifications',              [\App\Http\Controllers\Api\Shared\NotificationController::class, 'index']);
    Route::get  ('notifications/unread-count', [\App\Http\Controllers\Api\Shared\NotificationController::class, 'getUnreadCount']);
    Route::patch('notifications/read-all',     [\App\Http\Controllers\Api\Shared\NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{id}/read',    [\App\Http\Controllers\Api\Shared\NotificationController::class, 'markRead']);
});