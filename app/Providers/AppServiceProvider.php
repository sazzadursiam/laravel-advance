<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Listeners\DispatchOrderProcessing;
use App\Models\Order;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(
            OrderCreated::class,
            DispatchOrderProcessing::class,
        );

        Gate::policy(Order::class, OrderPolicy::class);
    }
}
