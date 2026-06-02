<?php

use App\Http\Controllers\Api\SuperAdmin\ActivityLogController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\NotificationController;
use App\Http\Controllers\Api\SuperAdmin\OwnerManagementController;
use App\Http\Controllers\Api\SuperAdmin\PlanController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionController;
use App\Http\Controllers\Api\SuperAdmin\UsahaManagementController;
use Illuminate\Support\Facades\Route;

// Ganti middleware(['auth:api', 'role:super_admin']) → cukup middleware('role:super_admin')
Route::middleware('role:super_admin')->group(function () {

    Route::get('dashboard',     [DashboardController::class, 'index']);
    Route::get('dashboard/mrr', [DashboardController::class, 'mrr']);

    Route::get('activity-logs', [ActivityLogController::class, 'index']);

    Route::get  ('notifications',           [NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('notifications/read-all',  [NotificationController::class, 'markAllRead']);

    Route::get ('usaha',                [UsahaManagementController::class, 'index']);
    Route::get ('usaha/pending',        [UsahaManagementController::class, 'pending']);
    Route::get ('usaha/{id}',           [UsahaManagementController::class, 'show']);
    Route::post('usaha/{id}/approve',   [UsahaManagementController::class, 'approve']);
    Route::post('usaha/{id}/reject',    [UsahaManagementController::class, 'reject']);
    Route::post('usaha/{id}/suspend',   [UsahaManagementController::class, 'suspend']);
    Route::post('usaha/{id}/unsuspend', [UsahaManagementController::class, 'unsuspend']);

    Route::get  ('owner',                     [OwnerManagementController::class, 'index']);
    Route::get  ('usaha/{usaha_id}/owner',    [OwnerManagementController::class, 'byUsaha']);
    Route::post ('owner/{id}/reset-password', [OwnerManagementController::class, 'resetPassword']);
    Route::patch('owner/{id}/toggle-status',  [OwnerManagementController::class, 'toggleStatus']);

    Route::get   ('plans',      [PlanController::class, 'index']);
    Route::post  ('plans',      [PlanController::class, 'store']);
    Route::get   ('plans/{id}', [PlanController::class, 'show']);
    Route::put   ('plans/{id}', [PlanController::class, 'update']);
    Route::delete('plans/{id}', [PlanController::class, 'destroy']);
    Route::patch('plans/{id}/status', [PlanController::class, 'toggleStatus']);

    Route::get ('subscriptions',                             [SubscriptionController::class, 'index']);
    Route::get ('subscriptions/{id}',                        [SubscriptionController::class, 'show']);
    Route::post('subscriptions/{id}/konfirmasi-pembayaran',  [SubscriptionController::class, 'konfirmasiPembayaran']);
    Route::post('subscriptions/{id}/cancel',                 [SubscriptionController::class, 'cancel']);
});