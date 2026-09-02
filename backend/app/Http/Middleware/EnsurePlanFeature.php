<?php

namespace App\Http\Middleware;

use App\Services\CurrentShop;
use App\Services\PlanEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function __construct(
        private readonly CurrentShop $currentShop,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $shop = $this->currentShop->from($request);
        $this->entitlements->ensureFeature($shop, $feature);
        $request->attributes->set('current_shop', $shop);

        return $next($request);
    }
}
