<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['secure.headers'])->prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/tokens', [AuthController::class, 'tokens']);
        Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken']);


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

        Route::post('/reports/orders', [ReportController::class, 'generateOrderReport'])
            ->middleware('throttle:order-create');

        Route::get('/reports/orders/batches/{batchId}', [ReportController::class, 'showBatch']);

        if (app()->isLocal()) {
            Route::get('/debug/orders-query-benchmark', function () {
                $start = microtime(true);

                $orders = \App\Models\Order::query()
                    ->with(['items.product'])
                    ->latest()
                    ->limit(100)
                    ->get();

                return response()->json([
                    'total_loaded' => $orders->count(),
                    'time_ms' => round((microtime(true) - $start) * 1000, 2),
                    'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ]);
            });
        }



    });
});
