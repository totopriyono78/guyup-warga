<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Nonaktifkan akun yang dinonaktifkan pengurus walau sesinya masih ada
        $middleware->web(append: [
            \App\Http\Middleware\PastikanAkunAktif::class,
        ]);

        // Callback AINO (Finish Notify) dikirim server-ke-server, tanpa token CSRF
        $middleware->validateCsrfTokens(except: [
            'aino/notify',
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
