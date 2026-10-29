<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\NoStoreOnReadOnlyPages::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
