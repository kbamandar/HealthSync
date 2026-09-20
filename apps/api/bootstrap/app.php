<?php

use App\Http\Middleware\AuthenticateWithJwt;
use App\Http\Middleware\SanitizeInput;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => AuthenticateWithJwt::class,
        ]);

        // Per-user (falls back to per-IP for unauthenticated requests) API
        // rate limiting, backed by Redis (see config/database.php 'redis'
        // — already provisioned for Horizon). Limit itself is defined in
        // AppServiceProvider::boot() via RateLimiter::for('api', ...).
        // Skipped in the 'testing' environment: the feature suite fires
        // hundreds of requests from the same IP/user within milliseconds,
        // which any per-minute limit would trip — a dedicated test exercises
        // the limiter callback directly instead (RateLimiterTest).
        if (env('APP_ENV') !== 'testing') {
            $middleware->throttleApi('api', redis: true);
        }

        $middleware->api(prepend: [SanitizeInput::class], append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    'VALIDATION_ERROR',
                    'The given data was invalid.',
                    $e->errors(),
                    422,
                );
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('NOT_FOUND', 'The requested resource was not found.', status: 404);
            }
        });
    })->create();
