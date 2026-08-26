<?php

use App\Http\Middleware\EnsureSensitiveAccess;
use App\Http\Middleware\EnsureShopPermission;
use App\Http\Middleware\EnsureShopWritable;
use App\Http\Middleware\EnsureSuperAdminSession;
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
            'sensitive' => EnsureSensitiveAccess::class,
            'admin.session' => EnsureSuperAdminSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
