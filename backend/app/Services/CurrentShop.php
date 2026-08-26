<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CurrentShop
{
    public function from(Request $request): Shop
    {
        $user = $request->user();
        $shopId = $request->header('X-Shop-Id') ?: $user?->current_shop_id;

        if (! $user || ! $shopId) {
            throw new AccessDeniedHttpException('กรุณาเลือกร้านก่อนใช้งาน');
        }

        if ($user->is_super_admin) {
            return Shop::findOrFail($shopId);
        }

        return Shop::query()
            ->whereKey($shopId)
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->firstOrFail();
    }
}
