<?php

use App\Http\Controllers\Api\Public\MenuPublicController;
use App\Http\Controllers\Api\Public\PesananPublicController;
use Illuminate\Support\Facades\Route;

// ============================================================
// PUBLIC ROUTES — tidak butuh auth apapun
// Diakses pelanggan setelah scan QR
// ============================================================

// Validasi QR saat scan
Route::get('validasi-meja', [MenuPublicController::class, 'validasiMeja']);

// List menu outlet
Route::get('menu',       [MenuPublicController::class, 'index']);
Route::get('menu/{id}',  [MenuPublicController::class, 'show']);

// Pesanan pelanggan
Route::post('pesanan',      [PesananPublicController::class, 'store']);
Route::get ('pesanan/{id}', [PesananPublicController::class, 'show']);
Route::post('/pesanan/{id}/cancel', [PesananPublicController::class, 'cancel']);