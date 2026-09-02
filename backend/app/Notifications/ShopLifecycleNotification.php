<?php

namespace App\Notifications;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a shop's billing recipients when SubscriptionLifecycle moves the shop
 * between states: into the read-only grace period, into suspension, or back to
 * active after a successful automatic renewal.
 */
class ShopLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const STAGE_GRACE = 'grace';

    public const STAGE_SUSPENDED = 'suspended';

    public const STAGE_RENEWED = 'renewed';

    public function __construct(
        private readonly Shop $shop,
        private readonly string $stage,
        private readonly ?string $graceEndsAtLabel = null,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $billingUrl = rtrim(config('app.frontend_url'), '/').'/billing';
        $name = $this->shop->name;

        return match ($this->stage) {
            self::STAGE_GRACE => (new MailMessage)
                ->subject('ร้าน '.$name.' เข้าสู่โหมดอ่านอย่างเดียว · GamoryID')
                ->greeting('สวัสดี')
                ->line('แพ็กเกจของร้าน '.$name.' หมดอายุแล้ว ระบบจึงเข้าสู่โหมดอ่านอย่างเดียว')
                ->line($this->graceEndsAtLabel
                    ? 'คุณยังเข้าดูและส่งออกข้อมูลได้จนถึง '.$this->graceEndsAtLabel.' น. หลังจากนั้นร้านจะถูกระงับ'
                    : 'คุณยังเข้าดูและส่งออกข้อมูลได้อีก 14 วัน หลังจากนั้นร้านจะถูกระงับ')
                ->action('ต่ออายุแพ็กเกจ', $billingUrl)
                ->line('ต่ออายุเมื่อไรก็กลับมาใช้งานได้เต็มรูปแบบทันที'),

            self::STAGE_SUSPENDED => (new MailMessage)
                ->subject('ร้าน '.$name.' ถูกระงับการใช้งาน · GamoryID')
                ->greeting('สวัสดี')
                ->line('ร้าน '.$name.' ถูกระงับการใช้งานเนื่องจากไม่ได้ต่ออายุแพ็กเกจ')
                ->line('ตอนนี้เหลือเพียงการชำระเงินและส่งออกข้อมูลเท่านั้น ข้อมูลทั้งหมดยังอยู่ครบ')
                ->action('ต่ออายุเพื่อเปิดร้านอีกครั้ง', $billingUrl),

            default => (new MailMessage)
                ->subject('ต่ออายุแพ็กเกจร้าน '.$name.' อัตโนมัติแล้ว · GamoryID')
                ->greeting('สวัสดี')
                ->line('ระบบต่ออายุแพ็กเกจของร้าน '.$name.' อัตโนมัติด้วยเครดิตเรียบร้อยแล้ว')
                ->line('ร้านของคุณใช้งานได้ต่อเนื่องตามปกติ')
                ->action('ดูรายละเอียดแพ็กเกจ', $billingUrl),
        };
    }
}
