<?php

use App\Http\Middleware\EnsureAdmin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

$application = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => EnsureAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception): bool {
            Log::error('Database request failed', [
                'sqlstate' => $exception->errorInfo[0] ?? 'unknown',
                // PostgreSQL DETAIL may contain row values; do not record it or SQL bindings.
                'driver_summary' => strtok($exception->errorInfo[2] ?? 'unavailable', "\n"),
            ]);

            return false;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

$application->booted(function (): void {
    RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip().'|'.strtolower((string) $request->input('email'))));
    RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)->by('order-user:'.(string) $request->user()?->getAuthIdentifier()));
});

return $application;
