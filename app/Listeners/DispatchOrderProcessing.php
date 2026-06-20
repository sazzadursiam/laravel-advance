<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\ProcessOrderJob;

class DispatchOrderProcessing
{
    public function handle(OrderCreated $event): void
    {
        ProcessOrderJob::dispatch($event->order->id)
            ->onQueue('orders');
    }
}
