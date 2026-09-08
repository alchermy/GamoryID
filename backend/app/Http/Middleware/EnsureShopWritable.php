<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Services\CurrentShop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopWritable
{
    public function __construct(private readonly CurrentShop $currentShop) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shop = $this->currentShop->from($request);
        if (! $shop->isWritable()) {
            // An admin suspension needs "contact us"; a lapsed plan needs "renew".
            $suspended = in_array($shop->status, [
                SubscriptionStatus::Suspended->value,
                SubscriptionStatus::Cancelled->value,
            ], true);

            return response()->json([
                'message' => $suspended
                    ? 'ร้านนี้ถูกระงับการใช้งาน กรุณาติดต่อทีมงาน'
                    : 'แพ็กเกจปัจจุบันเป็นโหมดอ่านอย่างเดียว กรุณาต่ออายุเพื่อทำรายการ',
                'code' => $suspended ? 'SHOP_SUSPENDED' : 'SHOP_READ_ONLY',
            ], 423);
        }
        $request->attributes->set('current_shop', $shop);

        return $next($request);
    }
}
