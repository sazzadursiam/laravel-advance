<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderProcessedNotification;
use App\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly int $orderId
    ) {
        $this->onQueue('orders');
    }

    public function handle(AuditLogService $auditLogService): void
    {
        $lock = Cache::lock("order-processing:{$this->orderId}", 60);

        $lock->block(10, function () use ($auditLogService) {
            $order = Order::query()
                ->with('user')
                ->findOrFail($this->orderId);

            if ($order->status === Order::STATUS_COMPLETED) {
                return;
            }

            $oldValues = $order->only([
                'status',
                'processed_at',
            ]);

            $order->update([
                'status' => Order::STATUS_PROCESSING,
            ]);

            sleep(2);

            $order->update([
                'status' => Order::STATUS_COMPLETED,
                'processed_at' => now(),
            ]);

            $order->refresh();

            $auditLogService->log(
                action: 'order.processed',
                model: $order,
                oldValues: $oldValues,
                newValues: $order->only([
                    'status',
                    'processed_at',
                ]),
            );

            $order->user->notify(new OrderProcessedNotification($order));
        });
    }

    public function failed(Throwable $exception): void
    {
        Order::query()
            ->where('id', $this->orderId)
            ->update([
                'status' => Order::STATUS_FAILED,
            ]);
    }
}
