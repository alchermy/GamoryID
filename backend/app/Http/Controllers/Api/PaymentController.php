<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\VerifyPaymentSlip;
use App\Models\PaymentSubmission;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function plans()
    {
        return response()->json(['data' => SubscriptionPlan::where('is_active', true)->orderBy('price_thb')->get()]);
    }

    public function store(Request $request, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id'], 'slip' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);
        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']);
        $path = $request->file('slip')->store("slips/{$shop->id}", 'private');
        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id,
            'subscription_plan_id' => $plan->id,
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
            'expected_amount' => $plan->price_thb,
            'slip_disk' => 'private',
            'slip_path' => $path,
        ]);
        VerifyPaymentSlip::dispatch($payment->id);
        $audit->record($request, $shop, 'payment.submitted', $payment, ['plan' => $plan->code]);

        return response()->json(['data' => $payment], 202);
    }

    public function show(Request $request, int $payment, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);

        return response()->json(['data' => PaymentSubmission::where('shop_id', $shop->id)->findOrFail($payment)]);
    }
}
