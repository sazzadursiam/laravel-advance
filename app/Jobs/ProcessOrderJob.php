<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderProcessedNotification;
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

    public function handle(): void
    {
        $lock = Cache::lock("order-processing:{$this->orderId}", 60);

        $lock->block(10, function () {
            $order = Order::query()
                ->with('user')
                ->findOrFail($this->orderId);

            if ($order->status === Order::STATUS_COMPLETED) {
                return;
            }

            $order->update([
                'status' => Order::STATUS_PROCESSING,
            ]);

            // Simulate external payment/shipping/inventory sync.
            sleep(2);

            $order->update([
                'status' => Order::STATUS_COMPLETED,
                'processed_at' => now(),
            ]);

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
