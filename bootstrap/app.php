<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->prefix('seller')
                ->name('seller.')
                ->group(base_path('routes/seller.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->append(\App\Http\Middleware\HandleRedirects::class);

        $middleware->validateCsrfTokens(except: [
            'payment/callback',
            'webhook/payment/*',
            'api/webhook/payment/*',
            'api/v1/cargo/webhook',
            'api/v1/integrations/*',
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('admin*')) {
                return route('admin.login');
            }
            if ($request->is('seller*')) {
                return route('seller.login');
            }
            return route('login');
        });

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'seller.status' => \App\Http\Middleware\EnsureSellerStatus::class,
            'integration.auth' => \App\Http\Middleware\VerifyCustomApiCredentials::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
