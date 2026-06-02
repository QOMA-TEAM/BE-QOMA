<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;          // ← tambahkan ini

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ─── CORS: izinkan request dari FE (localhost:3000) ───────────────────
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // Jangan validasi CSRF untuk API routes
        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'check.subscription'   => \App\Http\Middleware\CheckSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ← TAMBAHKAN BLOK INI
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
                    'code'    => 'UNAUTHENTICATED',
                ], 401);
            }
        });
        

    })->create();