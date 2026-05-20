<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ChangePasswordController;
use Illuminate\Support\Facades\Route;

// PUBLIC — tidak butuh middleware apapun
Route::get ('plans',    [RegisterController::class, 'plans']);
Route::post('register', [RegisterController::class, 'register']);
Route::post('login',    [AuthController::class, 'login']);
Route::post('refresh',  [AuthController::class, 'refresh']);

// PROTECTED — pakai role middleware tanpa role spesifik (any authenticated user)
Route::middleware('role')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get ('me',     [AuthController::class, 'me']);
    Route::post('change-password', ChangePasswordController::class);
});