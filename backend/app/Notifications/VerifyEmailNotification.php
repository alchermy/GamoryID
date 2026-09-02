<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent synchronously (not queued): the user is actively waiting on the
 * verify-email screen, so delivery must not depend on a running queue worker.
 * Mirrors the ShopInvitationNotification convention.
 */
class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('ยืนยันอีเมลของคุณ · GamoryID')
            ->greeting('สวัสดี')
            ->line('กดยืนยันอีเมลเพื่อเริ่มใช้งาน GamoryID')
            ->action('ยืนยันอีเมล', $url)
            ->line('หากคุณไม่ได้สมัครใช้งาน GamoryID สามารถละเว้นอีเมลฉบับนี้ได้');
    }
}
