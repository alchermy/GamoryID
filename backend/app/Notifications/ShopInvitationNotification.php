<?php

namespace App\Notifications;

use App\Models\ShopInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ShopInvitation $invitation, private readonly string $inviteUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('คำเชิญเข้าร่วมร้าน '.$this->invitation->shop->name.' · GamoryID')
            ->greeting('สวัสดี '.$this->invitation->name)
            ->line('คุณได้รับคำเชิญให้เข้าร่วมร้าน '.$this->invitation->shop->name.' บน GamoryID')
            ->action('เข้าร่วมร้าน', $this->inviteUrl)
            ->line('ลิงก์นี้ใช้ได้ถึง '.$this->invitation->expires_at->timezone('Asia/Bangkok')->format('d/m/Y H:i').' น. และใช้ได้เพียงครั้งเดียว');
    }
}
