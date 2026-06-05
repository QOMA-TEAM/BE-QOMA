<?php

use App\Http\Controllers\Api\Owner\{
    ActivityLogController,
    BahanMasterController,
    KategoriMenuController,
    KeuanganController,
    MenuController,
    OutletController,
    OwnerDashboardController,
    OwnerSubscriptionController,
    UsahaController,
    AddonController,
    ApprovalHargaController,
};
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// Routes yang bisa diakses TANPA cek subscription aktif
// (dashboard, info usaha, subscription management, pilih outlet nonaktif)
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('role:owner')->group(function () {

    // Dashboard
    Route::get('dashboard',       [OwnerDashboardController::class, 'index']);
    Route::get('dashboard/graph', [OwnerDashboardController::class, 'graph']);

    // Subscription info & upgrade
    Route::get ('subscription',         [OwnerSubscriptionController::class, 'index']);
    Route::get ('subscription/plans',   [OwnerSubscriptionController::class, 'availablePlans']);
    Route::post('subscription/upgrade', [OwnerSubscriptionController::class, 'upgrade']);

    // Deactivation queue — owner pilih outlet yang dinonaktifkan setelah subscription expired
    // ← Wajib di luar check.subscription! Owner harus bisa akses ini meskipun subscription-nya sudah expired
    Route::get ('subscription/pilih-outlet-nonaktif', [OwnerSubscriptionController::class, 'deactivationQueueInfo']);
    Route::post('subscription/pilih-outlet-nonaktif', [OwnerSubscriptionController::class, 'pilihOutletNonaktif']);

    // Usaha
    Route::get('usaha',       [UsahaController::class, 'index']);
    Route::get('usaha/{id}',  [UsahaController::class, 'show']);
    Route::put('usaha/{id}',  [UsahaController::class, 'update']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Routes yang BUTUH subscription aktif (atau masih dalam grace period)
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['role:owner', 'check.subscription'])->group(function () {

    // Activity Log & Keuangan
    Route::get('activity-log',           [ActivityLogController::class, 'index']);
    Route::get('keuangan',               [KeuanganController::class, 'index']);
    Route::get('keuangan/list',          [KeuanganController::class, 'listTransaksi']);

    // Outlet
    Route::get   ('outlet',                                     [OutletController::class, 'myOutlets']);
    Route::get   ('usaha/{usaha_id}/outlet',                    [OutletController::class, 'index']);
    Route::post  ('usaha/{usaha_id}/outlet',                    [OutletController::class, 'store']);
    Route::get   ('usaha/{usaha_id}/outlet/{id}',               [OutletController::class, 'show']);
    Route::put   ('usaha/{usaha_id}/outlet/{id}',               [OutletController::class, 'update']);
    Route::patch ('usaha/{usaha_id}/outlet/{id}/toggle-status', [OutletController::class, 'toggleStatus']);
    Route::delete('usaha/{usaha_id}/outlet/{id}',               [OutletController::class, 'destroy']);

    // Bahan Baku
    Route::get   ('bahan-baku',      [BahanMasterController::class, 'index']);
    Route::post  ('bahan-baku',      [BahanMasterController::class, 'store']);
    Route::get   ('bahan-baku/{id}', [BahanMasterController::class, 'show']);
    Route::put   ('bahan-baku/{id}', [BahanMasterController::class, 'update']);
    Route::delete('bahan-baku/{id}', [BahanMasterController::class, 'destroy']);

    // Kategori Menu
    Route::get   ('kategori',      [KategoriMenuController::class, 'index']);
    Route::post  ('kategori',      [KategoriMenuController::class, 'store']);
    Route::get   ('kategori/{id}', [KategoriMenuController::class, 'show']);
    Route::put   ('kategori/{id}', [KategoriMenuController::class, 'update']);
    Route::delete('kategori/{id}', [KategoriMenuController::class, 'destroy']);

    // Menu
    Route::get   ('menu',      [MenuController::class, 'index']);
    Route::post  ('menu',      [MenuController::class, 'store']);
    Route::get   ('menu/{id}', [MenuController::class, 'show']);
    Route::post  ('menu/{id}', [MenuController::class, 'update']); // POST + _method=PUT untuk multipart
    Route::delete('menu/{id}', [MenuController::class, 'destroy']);

    // Addon
    Route::get   ('addon',      [AddonController::class, 'index']);
    Route::post  ('addon',      [AddonController::class, 'store']);
    Route::put   ('addon/{id}', [AddonController::class, 'update']);
    Route::delete('addon/{id}', [AddonController::class, 'destroy']);

    // Konfirmasi perubahan harga menu oleh outlet
    Route::get ('approval-harga',              [ApprovalHargaController::class, 'index']);
    Route::post('approval-harga/{id}/approve', [ApprovalHargaController::class, 'approve']);
    Route::post('approval-harga/{id}/reject',  [ApprovalHargaController::class, 'reject']);
});