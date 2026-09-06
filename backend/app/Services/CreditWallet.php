<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\PaymentSubmission;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditWallet
{
    public function approveTopUp(PaymentSubmission $submission): CreditTransaction
    {
        return DB::transaction(function () use ($submission) {
            $payment = PaymentSubmission::lockForUpdate()->findOrFail($submission->id);
            $existing = CreditTransaction::where('payment_submission_id', $payment->id)->first();
            if ($existing) {
                return $existing;
            }
            if (! $payment->credit_amount || $payment->credit_amount < 1) {
                throw ValidationException::withMessages(['credits' => 'รายการนี้ไม่ใช่การเติมเครดิต']);
            }

            $shop = Shop::lockForUpdate()->findOrFail($payment->shop_id);
            $balance = $shop->credit_balance + (int) $payment->credit_amount;
            $shop->update(['credit_balance' => $balance]);
            $payment->update(['status' => 'verified', 'verified_at' => now()]);

            return CreditTransaction::create([
                'shop_id' => $shop->id,
                'payment_submission_id' => $payment->id,
                'type' => 'credit_top_up',
                'credits' => (int) $payment->credit_amount,
                'balance_after' => $balance,
                'metadata' => ['source' => 'slip'],
            ]);
        });
    }

    /**
     * Undo a wrongly-approved top-up: claw the credited amount back out of the
     * shop's wallet. The balance is allowed to go negative when the shop has
     * already spent the credits — restoring it is on the shop (a real top-up).
     */
    public function reverseTopUp(PaymentSubmission $submission, string $reason): CreditTransaction
    {
        return DB::transaction(function () use ($submission, $reason) {
            $payment = PaymentSubmission::lockForUpdate()->findOrFail($submission->id);
            $reversalQuery = fn () => CreditTransaction::where('type', 'credit_top_up_reversed')
                ->where('metadata->reversed_payment_id', $payment->id);

            if ($payment->status === 'reversed') {
                return $reversalQuery()->firstOrFail(); // idempotent
            }
            $topUpTxn = CreditTransaction::where('payment_submission_id', $payment->id)
                ->where('type', 'credit_top_up')
                ->first();
            if ($payment->status !== 'verified' || ! $topUpTxn) {
                throw ValidationException::withMessages(['reason' => 'รายการนี้ไม่ได้อยู่ในสถานะอนุมัติแล้ว จึงยกเลิกไม่ได้']);
            }

            $shop = Shop::lockForUpdate()->findOrFail($payment->shop_id);
            $balance = $shop->credit_balance - (int) $payment->credit_amount;
            $shop->update(['credit_balance' => $balance]);
            $payment->update([
                'status' => 'reversed',
                'verified_at' => null,
                'review_note' => 'ยกเลิกการอนุมัติ: '.$reason,
            ]);

            // The unique index on credit_transactions.payment_submission_id is
            // already taken by the credit_top_up row, so the reversal links back
            // through metadata instead.
            return CreditTransaction::create([
                'shop_id' => $shop->id,
                'type' => 'credit_top_up_reversed',
                'credits' => -(int) $payment->credit_amount,
                'balance_after' => $balance,
                'metadata' => ['reason' => $reason, 'reversed_payment_id' => $payment->id, 'reversed_txn_id' => $topUpTxn->id],
            ]);
        });
    }

    public function purchase(Shop $shop, SubscriptionPlan $plan, string $cycle, bool $autoRenew, string $idempotencyKey, string $type = 'subscription_purchase'): Subscription
    {
        $cycle = $cycle === 'yearly' ? 'yearly' : 'monthly';

        return DB::transaction(function () use ($shop, $plan, $cycle, $autoRenew, $idempotencyKey, $type) {
            $existing = CreditTransaction::where('shop_id', $shop->id)->where('idempotency_key', $idempotencyKey)->with('subscription.plan')->first();
            if ($existing?->subscription) {
                return $existing->subscription;
            }

            $lockedShop = Shop::lockForUpdate()->findOrFail($shop->id);
            $lockedPlan = SubscriptionPlan::where('is_active', true)->lockForUpdate()->findOrFail($plan->id);

            if ($lockedPlan->isFree()) {
                throw ValidationException::withMessages(['plan_id' => 'แพ็กนี้ใช้ได้ฟรีอยู่แล้ว ไม่ต้องซื้อ']);
            }
            $cost = $lockedPlan->effectivePriceFor($cycle);
            if ($cost === null) {
                throw ValidationException::withMessages(['billing_cycle' => 'แพ็กเกจนี้ไม่เปิดขายรอบที่เลือก']);
            }
            if ($lockedShop->credit_balance < $cost) {
                throw new InsufficientCreditsException("เครดิตไม่เพียงพอ ต้องการ {$cost} เครดิต");
            }

            $balance = $lockedShop->credit_balance - $cost;
            $lockedShop->subscriptions()->where('status', SubscriptionStatus::Active->value)->update([
                'status' => SubscriptionStatus::Expired->value,
                'auto_renew' => false,
            ]);
            $startsAt = now();
            $endsAt = $startsAt->copy()->addDays($lockedPlan->daysFor($cycle));
            $subscription = Subscription::create([
                'shop_id' => $lockedShop->id,
                'subscription_plan_id' => $lockedPlan->id,
                'billing_cycle' => $cycle,
                'price_paid' => $cost,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $endsAt->copy()->addDays(14),
                'auto_renew' => $autoRenew,
            ]);
            $lockedShop->update([
                'credit_balance' => $balance,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => null,
                'grace_ends_at' => $endsAt->copy()->addDays(14),
            ]);
            CreditTransaction::create([
                'shop_id' => $lockedShop->id,
                'subscription_id' => $subscription->id,
                'subscription_plan_id' => $lockedPlan->id,
                'type' => $type,
                'credits' => -$cost,
                'balance_after' => $balance,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['plan_code' => $lockedPlan->code, 'billing_cycle' => $cycle, 'auto_renew' => $autoRenew],
            ]);

            return $subscription->load('plan');
        });
    }
}
