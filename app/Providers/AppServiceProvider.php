<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Listeners\DispatchOrderProcessing;
use App\Models\Order;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        DB::listen(function (QueryExecuted $query): void {
            if ($query->time > 200) {
                Log::warning('Slow database query detected.', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                ]);
            }
        });

        Event::listen(
            OrderCreated::class,
            DispatchOrderProcessing::class,
        );

        Gate::policy(Order::class, OrderPolicy::class);
    }
}
