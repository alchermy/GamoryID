<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use App\Models\Shop;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use App\Services\PlanEntitlements;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopController extends Controller
{
    public function show(Request $request, CurrentShop $currentShop, PlanEntitlements $entitlements)
    {
        $shop = $currentShop->from($request);

        return response()->json(['data' => $this->payload($shop, $entitlements)]);
    }

    public function update(Request $request, CurrentShop $currentShop, AuditLogger $audit, PlanEntitlements $entitlements)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:80', Rule::unique('shops', 'slug')->ignore($shop->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'line_url' => ['nullable', 'url', 'max:500'],
            'phone' => ['nullable', 'string', 'max:32'],
            'inventory_copy_footer' => ['nullable', 'string', 'max:2000'],
            'storefront_enabled' => ['sometimes', 'boolean'],
        ]);

        if (($data['storefront_enabled'] ?? false) && ! $entitlements->can($shop, 'storefront')) {
            throw ValidationException::withMessages([
                'storefront_enabled' => 'หน้าร้านสาธารณะใช้ได้ตั้งแต่แพ็ก Starter ขึ้นไป อัปเกรดได้ที่หน้าแพ็กเกจ',
            ]);
        }

        $shop->update($data);
        $audit->record($request, $shop, 'shop.updated', $shop, ['fields' => array_keys($data)]);

        return response()->json(['data' => $this->payload($shop->fresh(), $entitlements)]);
    }

    private function payload(Shop $shop, PlanEntitlements $entitlements): array
    {
        $subscription = $shop->subscriptions()->latest()->with('plan')->first();
        $latestPayment = PaymentSubmission::query()->where('shop_id', $shop->id)->whereNotNull('credit_amount')->latest()->first();

        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'status' => $shop->status,
            'description' => $shop->description,
            'facebook_url' => $shop->facebook_url,
            'line_url' => $shop->line_url,
            'phone' => $shop->phone,
            'inventory_copy_footer' => $shop->inventory_copy_footer,
            'storefront_enabled' => $shop->storefront_enabled,
            'timezone' => $shop->timezone,
            'trial_ends_at' => $shop->trial_ends_at,
            'grace_ends_at' => $shop->grace_ends_at,
            'credit_balance' => $shop->credit_balance,
            'subscription' => $subscription,
            'entitlements' => $entitlements->summary($shop),
            'latest_top_up' => $latestPayment ? [
                'id' => $latestPayment->id,
                'status' => $latestPayment->status,
                'credits' => $latestPayment->credit_amount,
                'verified_at' => $latestPayment->verified_at,
                'created_at' => $latestPayment->created_at,
                'review_note' => $latestPayment->review_note,
            ] : null,
        ];
    }
}
