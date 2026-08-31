<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Jobs\VerifyPaymentSlip;
use App\Models\CreditTransaction;
use App\Models\PaymentSubmission;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Services\CreditWallet;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreditController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);

        return response()->json(['data' => [
            'balance' => $shop->credit_balance,
            'transactions' => CreditTransaction::where('shop_id', $shop->id)->with('plan:id,name,code')->latest()->limit(20)->get(),
        ]]);
    }

    public function history(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $subscriptionsQuery = $shop->subscriptions()->with('plan:id,name,code,price_thb,duration_days');
        $topUpsQuery = $shop->paymentSubmissions()->whereNotNull('credit_amount')->with('submittedBy:id,name');

        return response()->json(['data' => [
            'subscriptions' => [
                'items' => (clone $subscriptionsQuery)->latest()->limit(50)->get()->map(fn ($subscription) => [
                    'id' => $subscription->id,
                    'status' => $subscription->status->value,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                    'created_at' => $subscription->created_at,
                    'auto_renew' => $subscription->auto_renew,
                    'plan' => $subscription->plan ? [
                        'name' => $subscription->plan->name,
                        'code' => $subscription->plan->code,
                        'price_thb' => (int) $subscription->plan->price_thb,
                        'duration_days' => $subscription->plan->duration_days,
                    ] : null,
                ]),
                'total' => (clone $subscriptionsQuery)->count(),
            ],
            'top_ups' => [
                'items' => (clone $topUpsQuery)->latest()->limit(50)->get()->map(fn ($payment) => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'credits' => $payment->credit_amount,
                    'amount' => (float) $payment->expected_amount,
                    'created_at' => $payment->created_at,
                    'verified_at' => $payment->verified_at,
                    'review_note' => $payment->review_note,
                    'submitted_by' => $payment->submittedBy ? ['name' => $payment->submittedBy->name] : null,
                ]),
                'total' => (clone $topUpsQuery)->count(),
            ],
        ]]);
    }

    public function topUp(Request $request, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'credits' => ['required', 'integer', 'min:1', 'max:1000000'],
            'slip' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);
        $idempotencyKey = $this->idempotencyKey($request);
        $existing = PaymentSubmission::where('shop_id', $shop->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return response()->json(['data' => $this->paymentPayload($existing)], 202);
        }

        $path = $request->file('slip')->store("slips/{$shop->id}", 'private');
        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id,
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
            'expected_amount' => $data['credits'],
            'credit_amount' => $data['credits'],
            'slip_disk' => 'private',
            'slip_path' => $path,
            'idempotency_key' => $idempotencyKey,
        ]);
        VerifyPaymentSlip::dispatch($payment->id);
        $audit->record($request, $shop, 'credit.top_up_submitted', $payment, ['credits' => $data['credits']]);

        return response()->json(['data' => $this->paymentPayload($payment)], 202);
    }

    public function purchase(Request $request, CurrentShop $currentShop, CreditWallet $wallet, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'auto_renew' => ['required', 'boolean'],
        ]);
        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']);
        try {
            $subscription = $wallet->purchase($shop, $plan, (bool) $data['auto_renew'], $this->idempotencyKey($request));
        } catch (InsufficientCreditsException $exception) {
            throw ValidationException::withMessages(['credits' => $exception->getMessage()]);
        }
        $audit->record($request, $shop, 'subscription.purchased_with_credits', $subscription, [
            'plan' => $plan->code,
            'credits' => (int) $plan->price_thb,
            'auto_renew' => (bool) $data['auto_renew'],
        ]);

        return response()->json(['data' => [
            'subscription' => $subscription,
            'credit_balance' => $shop->fresh()->credit_balance,
        ]]);
    }

    public function updateAutoRenew(Request $request, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate(['auto_renew' => ['required', 'boolean']]);
        $subscription = $shop->subscriptions()->where('status', 'active')->latest()->firstOrFail();
        $subscription->update(['auto_renew' => (bool) $data['auto_renew']]);
        $audit->record($request, $shop, 'subscription.auto_renew_updated', $subscription, ['auto_renew' => (bool) $data['auto_renew']]);

        return response()->json(['data' => $subscription->fresh()->load('plan')]);
    }

    private function idempotencyKey(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'ต้องระบุ Idempotency-Key ที่ถูกต้อง']);
        }

        return $key;
    }

    private function paymentPayload(PaymentSubmission $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'credits' => $payment->credit_amount,
            'verified_at' => $payment->verified_at,
            'created_at' => $payment->created_at,
            'review_note' => $payment->review_note,
        ];
    }
}
