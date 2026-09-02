<?php

namespace App\Notifications;

use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Shop $shop,
        private readonly CarbonInterface $expiresAt,
        private readonly int $daysLeft,
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
        $expiresAtLabel = $this->expiresAt->timezone('Asia/Bangkok')->format('d/m/Y H:i');
        $daysLabel = $this->daysLeft <= 1 ? 'ภายในวันนี้พรุ่งนี้' : "อีก {$this->daysLeft} วัน";

        return (new MailMessage)
            ->subject('ร้าน '.$this->shop->name.' ใกล้หมดอายุการใช้งาน · GamoryID')
            ->greeting('สวัสดี')
            ->line('แพ็กเกจของร้าน '.$this->shop->name.' จะหมดอายุ '.$daysLabel.' ('.$expiresAtLabel.' น.)')
            ->line('หากไม่ต่ออายุ ระบบจะเข้าสู่โหมดอ่านอย่างเดียว 14 วัน ก่อนระงับการใช้งาน')
            ->action('ไปต่ออายุแพ็กเกจ', $billingUrl)
            ->line('หากตั้งค่าต่ออายุอัตโนมัติและมีเครดิตเพียงพอ ระบบจะต่ออายุให้อัตโนมัติ');
    }
}
