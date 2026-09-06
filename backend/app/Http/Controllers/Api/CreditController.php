<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\PaymentSubmission;
use App\Models\SlipVerification;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Services\CreditWallet;
use App\Services\CurrentShop;
use App\Services\SlipReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $subscriptionsQuery = $shop->subscriptions()->with('plan:id,name,code,price_monthly,price_yearly,monthly_days,yearly_days');
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
                    'billing_cycle' => $subscription->billing_cycle,
                    'price_paid' => $subscription->price_paid,
                    'plan' => $subscription->plan ? [
                        'name' => $subscription->plan->name,
                        'code' => $subscription->plan->code,
                        'price_monthly' => (int) $subscription->plan->price_monthly,
                        'price_yearly' => $subscription->plan->price_yearly,
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

    public function topUp(Request $request, CurrentShop $currentShop, AuditLogger $audit, SlipReview $slipReview)
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
        $review = $slipReview->evaluate(Storage::disk('private')->path($path), (float) $data['credits']);

        // A slip that the checker actively rejected never becomes a submission —
        // the merchant sees the reason immediately and can try again.
        if (in_array($review['outcome'], ['invalid', 'mismatch'], true)) {
            Storage::disk('private')->delete($path);
            throw ValidationException::withMessages(['slip' => $review['note']]);
        }

        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id,
            'submitted_by' => $request->user()->id,
            'status' => 'pending_review',
            'expected_amount' => $data['credits'],
            'credit_amount' => $data['credits'],
            'slip_disk' => 'private',
            'slip_path' => $path,
            'idempotency_key' => $idempotencyKey,
            'auto_slip_check' => $review['outcome'] === 'verified' ? 'passed' : 'unavailable',
            'provider_reference' => $review['verification']['transaction_reference'] ?? null,
            'review_note' => $review['note'],
        ]);
        if ($review['outcome'] === 'verified' && $review['verification']) {
            SlipVerification::create([
                'payment_submission_id' => $payment->id,
                'is_valid' => true,
                'amount' => $review['verification']['amount'],
                'receiver_account' => $review['verification']['receiver_account'],
                'transaction_reference' => $review['verification']['transaction_reference'],
                'transferred_at' => $review['verification']['transferred_at'],
                'response_summary' => $review['verification']['summary'],
            ]);
        }
        $audit->record($request, $shop, 'credit.top_up_submitted', $payment, [
            'credits' => $data['credits'],
            'auto_slip_check' => $payment->auto_slip_check,
        ]);

        return response()->json(['data' => $this->paymentPayload($payment)], 202);
    }

    public function purchase(Request $request, CurrentShop $currentShop, CreditWallet $wallet, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'auto_renew' => ['required', 'boolean'],
        ]);
        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']);
        try {
            $subscription = $wallet->purchase($shop, $plan, $data['billing_cycle'], (bool) $data['auto_renew'], $this->idempotencyKey($request));
        } catch (InsufficientCreditsException $exception) {
            throw ValidationException::withMessages(['credits' => $exception->getMessage()]);
        }
        $audit->record($request, $shop, 'subscription.purchased_with_credits', $subscription, [
            'plan' => $plan->code,
            'billing_cycle' => $data['billing_cycle'],
            'credits' => (int) $subscription->price_paid,
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
        $subscription = $shop->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'ยังไม่มีแพ็กเกจที่ใช้งานอยู่สำหรับตั้งค่าต่ออายุอัตโนมัติ',
            ], 422);
        }

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
