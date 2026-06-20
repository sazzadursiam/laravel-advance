<?php

use App\Http\Middleware\EnsureIdempotencyKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            RateLimiter::for('api', function (Request $request) {
                $user = $request->user();

                if ($user?->isAdmin()) {
                    return Limit::perMinute(300)
                        ->by('admin:'.$user->id);
                }

                if ($user) {
                    return Limit::perMinute(120)
                        ->by('user:'.$user->id);
                }

                return Limit::perMinute(60)
                    ->by('guest:'.$request->ip());
            });

            RateLimiter::for('login', function (Request $request) {
                return [
                    Limit::perMinute(5)
                        ->by('login:'.$request->ip()),

                    Limit::perMinute(3)
                        ->by('login:'.$request->input('email')),
                ];
            });

            RateLimiter::for('order-create', function (Request $request) {
                $user = $request->user();

                if ($user?->isAdmin()) {
                    return Limit::perMinute(60)
                        ->by('order-create:admin:'.$user->id);
                }

                return Limit::perMinute(10)
                    ->by('order-create:user:'.($user?->id ?? $request->ip()));
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => EnsureIdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
            ], 429);
        });
    })->create();
