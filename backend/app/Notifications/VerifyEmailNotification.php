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
            ->subject('ยืนยันอีเมลเพื่อเปิดใช้งานบัญชี · GamoryID')
            ->greeting('ยินดีต้อนรับสู่ GamoryID')
            ->line('ขอบคุณที่สมัครใช้งาน กรุณายืนยันอีเมลนี้เพื่อเริ่มเปิดร้านและใช้งานระบบทั้งหมด')
            ->action('ยืนยันอีเมล', $url)
            ->line('ลิงก์ยืนยันจะหมดอายุภายใน 60 นาที หากหมดอายุแล้ว สามารถขอลิงก์ใหม่ได้จากหน้าเข้าสู่ระบบ')
            ->line('หากคุณไม่ได้เป็นผู้สมัครใช้งาน โปรดละเว้นอีเมลฉบับนี้ ระบบจะไม่ดำเนินการใด ๆ กับบัญชีของคุณ')
            ->salutation('ด้วยความเคารพ, ทีมงาน GamoryID');
    }
}
