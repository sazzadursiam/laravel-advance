<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/orders', [OrderController::class, 'index']);

        Route::post('/orders', [OrderController::class, 'store'])
            ->middleware(['throttle:order-create', 'idempotency']);

        Route::get('/orders/{order}', [OrderController::class, 'show']);

        Route::put('/orders/{order}', [OrderController::class, 'update'])
            ->middleware('idempotency');

        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])
            ->middleware('idempotency');

        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::get('/reports/orders/summary', [ReportController::class, 'orderSummary']);
        Route::post('/reports/orders', [ReportController::class, 'generateOrderReport']);

    });
});
