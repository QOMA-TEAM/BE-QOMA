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

Route::middleware('role:owner')->group(function () {

    // Dashboard
    Route::get('dashboard',       [OwnerDashboardController::class, 'index']);
    Route::get('dashboard/graph', [OwnerDashboardController::class, 'graph']);

    // Subscription
    Route::get ('subscription',         [OwnerSubscriptionController::class, 'index']);
    Route::get ('subscription/plans',   [OwnerSubscriptionController::class, 'availablePlans']);
    Route::post('subscription/upgrade', [OwnerSubscriptionController::class, 'upgrade']);

    // Keuangan
    Route::get('keuangan',      [KeuanganController::class, 'index']);
    Route::get('keuangan/list', [KeuanganController::class, 'listTransaksi']); // ← BARU

    // Activity Log
    Route::get('activity-log', [ActivityLogController::class, 'index']);

    // Usaha
    Route::get('usaha',       [UsahaController::class, 'index']);
    Route::get('usaha/{id}',  [UsahaController::class, 'show']);
    Route::put('usaha/{id}',  [UsahaController::class, 'update']);

    // Outlet (nested di bawah usaha karena butuh usaha_id untuk isolasi)
    Route::get   ('usaha/{usaha_id}/outlet',                    [OutletController::class, 'index']);
    Route::post  ('usaha/{usaha_id}/outlet',                    [OutletController::class, 'store']);
    Route::get   ('usaha/{usaha_id}/outlet/{id}',               [OutletController::class, 'show']);
    Route::put   ('usaha/{usaha_id}/outlet/{id}',               [OutletController::class, 'update']);
    Route::patch ('usaha/{usaha_id}/outlet/{id}/toggle-status', [OutletController::class, 'toggleStatus']);
    Route::delete('usaha/{usaha_id}/outlet/{id}',               [OutletController::class, 'destroy']);

    // Bahan Baku ← URL disederhanakan
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

    // Menu ← URL disederhanakan
    Route::get   ('menu',      [MenuController::class, 'index']);
    Route::post  ('menu',      [MenuController::class, 'store']);
    Route::get   ('menu/{id}', [MenuController::class, 'show']);
    Route::post  ('menu/{id}', [MenuController::class, 'update']); // POST + _method=PUT untuk multipart
    Route::delete('menu/{id}', [MenuController::class, 'destroy']);

    // Shortcut — owner lihat semua outletnya tanpa perlu tahu usaha_id
    Route::get('outlet', [OutletController::class, 'myOutlets']);

    Route::get   ('addon',      [AddonController::class, 'index']);
    Route::post  ('addon',      [AddonController::class, 'store']);
    Route::put   ('addon/{id}', [AddonController::class, 'update']);
    Route::delete('addon/{id}', [AddonController::class, 'destroy']);
    
});

// routes/owner.php

// Bisa diakses meski subscription expired
Route::middleware('role:owner')->group(function () {
    Route::get('dashboard',              [OwnerDashboardController::class, 'index']);
    Route::get('dashboard/graph',        [OwnerDashboardController::class, 'graph']);
    Route::get('subscription',           [OwnerSubscriptionController::class, 'index']);
    Route::get('subscription/plans',     [OwnerSubscriptionController::class, 'availablePlans']);
    Route::post('subscription/upgrade',  [OwnerSubscriptionController::class, 'upgrade']);
    Route::get('usaha',                  [UsahaController::class, 'index']);
    Route::get('usaha/{id}',             [UsahaController::class, 'show']);
    Route::put('usaha/{id}',             [UsahaController::class, 'update']);
});

// Butuh subscription aktif
Route::middleware(['role:owner', 'check.subscription'])->group(function () {
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

    // Kategori
    Route::get   ('kategori',      [KategoriMenuController::class, 'index']);
    Route::post  ('kategori',      [KategoriMenuController::class, 'store']);
    Route::get   ('kategori/{id}', [KategoriMenuController::class, 'show']);
    Route::put   ('kategori/{id}', [KategoriMenuController::class, 'update']);
    Route::delete('kategori/{id}', [KategoriMenuController::class, 'destroy']);

    // Menu
    Route::get   ('menu',      [MenuController::class, 'index']);
    Route::post  ('menu',      [MenuController::class, 'store']);
    Route::get   ('menu/{id}', [MenuController::class, 'show']);
    Route::put  ('menu/{id}', [MenuController::class, 'update']);
    Route::delete('menu/{id}', [MenuController::class, 'destroy']);

    // Addon
    Route::get   ('addon',      [AddonController::class, 'index']);
    Route::post  ('addon',      [AddonController::class, 'store']);
    Route::put   ('addon/{id}', [AddonController::class, 'update']);
    Route::delete('addon/{id}', [AddonController::class, 'destroy']);

    // konfirmasi perubahan harga menu oleh outlet
    Route::get ('approval-harga',             [ApprovalHargaController::class, 'index']);
    Route::post('approval-harga/{id}/approve',[ApprovalHargaController::class, 'approve']);
    Route::post('approval-harga/{id}/reject', [ApprovalHargaController::class, 'reject']);

    // Tambahkan di dalam Route::middleware(['role:owner', 'check.subscription'])
    Route::post('subscription/pilih-outlet-nonaktif', [OwnerSubscriptionController::class, 'pilihOutletNonaktif']);
});