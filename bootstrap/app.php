<?php

use App\Http\Controllers\DeployController;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Registered outside the `web` group on purpose. Its whole job is
            // to run the migrations that create the `sessions` and `cache`
            // tables — so it cannot itself depend on the session middleware
            // that needs those tables to exist. In the web group this route
            // would 500 on a fresh database, which is exactly when it is
            // needed. See docs/DEPLOYMENT.md.
            Route::post('deploy-hook', DeployController::class)
                ->middleware('throttle:5,1')
                ->name('deploy.hook');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // CI calls this with a bearer-style token and no session, so there is
        // no CSRF token to present. Its own token check is the gate.
        $middleware->validateCsrfTokens(except: ['deploy-hook']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
