<?php

namespace App\Http\Middleware;

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
            return response()->json([
                'message' => 'แพ็กเกจปัจจุบันเป็นโหมดอ่านอย่างเดียว กรุณาต่ออายุเพื่อทำรายการ',
                'code' => 'SHOP_READ_ONLY',
            ], 423);
        }
        $request->attributes->set('current_shop', $shop);

        return $next($request);
    }
}
