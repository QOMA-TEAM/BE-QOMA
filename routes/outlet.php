<?php

use App\Http\Controllers\Api\Outlet\{
    ActivityLogController,
    BahanOutletController,
    MejaController,
    MenuOutletController,
    OutletDashboardController,
    PesananController,
    KeuanganOutletController,
    ApprovalHargaController,
    ApprovalHargaBahanController,
};
use Illuminate\Support\Facades\Route;


Route::middleware('role:outlet')->group(function () {

    // Dashboard & status toko
    Route::get  ('dashboard',      [OutletDashboardController::class, 'index']);
    Route::patch('toggle-status',  [OutletDashboardController::class, 'toggleStatus']);

    // Meja & QR
    Route::get   ('meja',      [MejaController::class, 'index']);
    Route::post  ('meja',      [MejaController::class, 'store']);
    Route::delete('meja/{id}', [MejaController::class, 'destroy']);

    // Pesanan
    Route::get ('pesanan',                                  [PesananController::class, 'index']);
    Route::get('pesanan/semua',                             [PesananController::class, 'semua']);
    Route::get ('pesanan/{id}',                             [PesananController::class, 'show']);
    Route::patch('pesanan/{id}/tipe',                       [PesananController::class, 'updateTipe']);
    Route::post('pesanan/{id}/tambah-item',                 [PesananController::class, 'tambahItem']);
    Route::patch('pesanan/{id}/item/{detail_id}/qty',       [PesananController::class, 'updateQty']);
    Route::delete('pesanan/{id}/item/{detail_id}',          [PesananController::class, 'hapusItem']);
    Route::post('pesanan/{id}/konfirmasi',                  [PesananController::class, 'konfirmasi']);
    Route::post('pesanan/{id}/bayar',                       [PesananController::class, 'bayar']);
    Route::post('pesanan/{id}/cancel',                      [PesananController::class, 'cancel']);

    // Bahan Baku Outlet
    Route::get  ('bahan-baku',                      [BahanOutletController::class, 'index']);
    Route::post ('bahan-baku',                      [BahanOutletController::class, 'store']);
    Route::patch('bahan-baku/{id}/konfigurasi',     [BahanOutletController::class, 'updateKonfigurasi']);
    Route::post ('stock-opname',                    [BahanOutletController::class, 'stockOpname']);
    Route::get  ('alerts',                          [BahanOutletController::class, 'alerts']);

    // Menu Outlet (edit harga)
    Route::get  ('menu',                    [MenuOutletController::class, 'index']);
    Route::patch('menu/{menu_id}/availability', [MenuOutletController::class, 'updateAvailability']);

    Route::get('keuangan', [KeuanganOutletController::class, 'index']);

    
    
    // Approval harga menu
    Route::get ('approval-harga',      [ApprovalHargaController::class, 'index']);
    Route::post ('approval-harga',     [ApprovalHargaController::class, 'store']);

    // Activity Log
    Route::get('activity-log', [ActivityLogController::class, 'index']);

    // Stock Opname — Draft System
    Route::get ('stock-opname',                    [BahanOutletController::class, 'opnameIndex']);  // list semua
    Route::get ('stock-opname/draft',              [BahanOutletController::class, 'draftIndex']);   // list draft
    Route::post('stock-opname/draft',              [BahanOutletController::class, 'draftStore']);   // buat/update draft
    Route::put ('stock-opname/draft/{id}',         [BahanOutletController::class, 'draftUpdate']);  // edit draft
    Route::delete('stock-opname/draft/{id}',       [BahanOutletController::class, 'draftDestroy']); // hapus draft
    Route::post('stock-opname/draft/{id}/final',   [BahanOutletController::class, 'draftFinalisasi']); // finalisasi

    // Approval Harga Bahan
    Route::get ('approval-harga-bahan',            [ApprovalHargaBahanController::class, 'index']);
    Route::post('approval-harga-bahan',            [ApprovalHargaBahanController::class, 'store']);
});