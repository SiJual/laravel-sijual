<?php

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
        $middleware->alias([
            'profile.complete' => \App\Http\Middleware\EnsureProfileComplete::class,
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        
        $middleware->web(append: [
            \App\Http\Middleware\StripTagsMiddleware::class,
        ]);

        $middleware->encryptCookies(except: [
            'sijual_token',
        ]);

        $middleware->validateCsrfTokens(except: [
            'sikas/qris-sync',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }
        
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson(),
        );
    })->create();
