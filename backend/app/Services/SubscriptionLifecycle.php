<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Jobs\SendDiscordShopNotification;
use App\Models\Shop;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class SubscriptionLifecycle
{
    public function __construct(private readonly CreditWallet $wallet) {}

    public function run(): void
    {
        $log = Log::channel('scheduler');
        $log->info('เริ่มรอบตรวจสอบวงจรแพ็กเกจ (subscriptions.lifecycle)');

        $this->sendExpiryReminders($log);

        Subscription::where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->with(['shop', 'plan'])->orderBy('id')->each(function (Subscription $subscription) use ($log) {
                if ($subscription->auto_renew && $subscription->shop && $subscription->plan) {
                    try {
                        $this->wallet->purchase($subscription->shop, $subscription->plan, true, (string) Str::uuid(), 'subscription_renewal');
                        $log->info('ต่ออายุแพ็กเกจอัตโนมัติด้วยเครดิตสำเร็จ', [
                            'shop_id' => $subscription->shop_id,
                            'plan_code' => $subscription->plan->code,
                        ]);

                        return;
                    } catch (InsufficientCreditsException) {
                        $log->warning('ต่ออายุอัตโนมัติไม่สำเร็จ: เครดิตไม่พอ เข้าสู่โหมดอ่านอย่างเดียว', [
                            'shop_id' => $subscription->shop_id,
                            'plan_code' => $subscription->plan->code,
                        ]);
                    }
                }

                $graceEndsAt = $subscription->ends_at->copy()->addDays(14);
                $subscription->update(['status' => SubscriptionStatus::GraceReadOnly, 'grace_ends_at' => $graceEndsAt]);
                Shop::whereKey($subscription->shop_id)->update([
                    'status' => SubscriptionStatus::GraceReadOnly,
                    'grace_ends_at' => $graceEndsAt,
                ]);
                $log->info('แพ็กเกจหมดอายุ: เปลี่ยนสถานะร้านเป็นโหมดอ่านอย่างเดียว 14 วัน', [
                    'shop_id' => $subscription->shop_id,
                    'grace_ends_at' => $graceEndsAt->toDateTimeString(),
                ]);
            });

        $trialExpired = Shop::whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
            ->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::GraceReadOnly->value]);
        if ($trialExpired > 0) {
            $log->info('ทดลองใช้หมดอายุ: เปลี่ยนสถานะร้านเป็นโหมดอ่านอย่างเดียว', ['shops' => $trialExpired]);
        }

        $suspended = Shop::where('status', SubscriptionStatus::GraceReadOnly->value)
            ->whereNotNull('grace_ends_at')->where('grace_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Suspended->value]);
        if ($suspended > 0) {
            $log->info('พ้นช่วงผ่อนผัน: ระงับการใช้งานร้าน', ['shops' => $suspended]);
        }
    }

    private function sendExpiryReminders(LoggerInterface $log): void
    {
        Subscription::whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
            ->with('shop')
            ->orderBy('id')
            ->each(function (Subscription $subscription) use ($log) {
                $shop = $subscription->shop;
                if (! $shop) {
                    return;
                }

                $expiresAt = $subscription->status === SubscriptionStatus::Trialing
                    ? $shop->trial_ends_at
                    : $subscription->ends_at;
                if (! $expiresAt || $expiresAt->isPast()) {
                    return;
                }

                $daysLeft = (int) ceil(now()->floatDiffInDays($expiresAt, absolute: true));
                $targetStage = match (true) {
                    $daysLeft <= 1 => 3,
                    $daysLeft <= 3 => 2,
                    $daysLeft <= 7 => 1,
                    default => 0,
                };

                if ($targetStage <= $subscription->expiry_reminder_stage) {
                    return;
                }

                $recipients = $shop->billingRecipients();
                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new SubscriptionExpiringNotification($shop, $expiresAt, $daysLeft));
                }

                $expiresAtLabel = $expiresAt->timezone('Asia/Bangkok')->format('d/m/Y H:i');
                SendDiscordShopNotification::dispatch(
                    $shop->id,
                    'system',
                    'แพ็กเกจใกล้หมดอายุ',
                    "ร้าน **{$shop->name}** จะหมดอายุวันที่ {$expiresAtLabel} น. (เหลืออีก {$daysLeft} วัน)\nต่ออายุได้ที่หน้าจัดการแพ็กเกจในระบบ Merchant",
                );

                $subscription->update(['expiry_reminder_stage' => $targetStage]);
                $log->info('ส่งแจ้งเตือนแพ็กเกจใกล้หมดอายุ', [
                    'shop_id' => $shop->id,
                    'days_left' => $daysLeft,
                    'stage' => $targetStage,
                    'email_recipients' => $recipients->count(),
                ]);
            });
    }
}
