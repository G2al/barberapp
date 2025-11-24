<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lasciamo vuoto per usare gli stack DI DEFAULT di Laravel
        // (web, api, ecc. li gestisce lui – noi qui non tocchiamo nulla)
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
