<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Listeners\DispatchOrderProcessing;
use Illuminate\Support\Facades\Event;
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
    }
}
