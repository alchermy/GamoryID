<?php

namespace App\Http\Middleware;

use App\Services\CurrentShop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopPermission
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $shop = $this->currentShop->from($request);
        if (! $request->user()->hasShopPermission($shop, $permission)) {
            abort(403, 'คุณไม่มีสิทธิ์ทำรายการนี้');
        }
        $request->attributes->set('current_shop', $shop);

        return $next($request);
    }
}
