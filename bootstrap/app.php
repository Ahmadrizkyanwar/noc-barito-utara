<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Properti bersama Inertia (auth, flash, appName) untuk SEMUA halaman web.
        $middleware->web(append: HandleInertiaRequests::class);

        // Alias peran: ->middleware('role:admin')
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Dipercaya sebagai reverse proxy (nginx-proxy-manager) → X-Forwarded-* valid.
        $middleware->trustProxies(at: array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUST_PROXIES', ''))
        )));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
        );
    })->create();
