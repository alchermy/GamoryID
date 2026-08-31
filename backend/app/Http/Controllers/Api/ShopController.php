<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function show(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);

        return response()->json(['data' => $this->payload($shop)]);
    }

    public function update(Request $request, CurrentShop $currentShop, AuditLogger $audit)
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
        ]);
        $shop->update($data);
        $audit->record($request, $shop, 'shop.updated', $shop, ['fields' => array_keys($data)]);

        return response()->json(['data' => $this->payload($shop->fresh())]);
    }

    private function payload($shop): array
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
            'trial_ends_at' => $shop->trial_ends_at,
            'grace_ends_at' => $shop->grace_ends_at,
            'credit_balance' => $shop->credit_balance,
            'subscription' => $subscription,
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
