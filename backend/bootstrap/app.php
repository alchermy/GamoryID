<?php

use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureSensitiveAccess;
use App\Http\Middleware\EnsureShopPermission;
use App\Http\Middleware\EnsureShopWritable;
use App\Http\Middleware\EnsureSuperAdminSession;
use App\Http\Middleware\EnsureTermsAccepted;
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
        $middleware->statefulApi();
        $middleware->alias([
            'shop.permission' => EnsureShopPermission::class,
            'shop.writable' => EnsureShopWritable::class,
            'plan.feature' => EnsurePlanFeature::class,
            'sensitive' => EnsureSensitiveAccess::class,
            'admin.session' => EnsureSuperAdminSession::class,
            'terms.current' => EnsureTermsAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Attach request/actor context to every reported exception so the log
        // line points at exactly which request, user and shop hit the failure.
        $exceptions->context(function (): array {
            $request = request();

            return array_filter([
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
                'route' => $request?->route()?->getName() ?: optional($request?->route())->uri(),
                'ip' => $request?->ip(),
                'user_id' => optional($request?->user())->id,
                'shop_id' => $request?->header('X-Shop-Id'),
            ], fn ($value) => $value !== null && $value !== '');
        });
    })->create();
