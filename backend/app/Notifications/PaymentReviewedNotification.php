<?php

namespace App\Notifications;

use App\Models\PaymentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a shop's billing recipients after a Super Admin reviews a credit
 * top-up submission — approved (credits added) or rejected (needs a new slip).
 */
class PaymentReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const OUTCOME_APPROVED = 'approved';

    public const OUTCOME_REJECTED = 'rejected';

    public function __construct(
        private readonly PaymentSubmission $payment,
        private readonly string $outcome,
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
        $credits = number_format((int) $this->payment->credit_amount);
        $note = trim((string) $this->payment->review_note);

        if ($this->outcome === self::OUTCOME_APPROVED) {
            return (new MailMessage)
                ->subject('เติมเครดิต '.$credits.' เครดิตสำเร็จ · GamoryID')
                ->greeting('สวัสดี')
                ->line('ผู้ดูแลระบบตรวจสอบสลิปเรียบร้อยแล้ว เพิ่ม '.$credits.' เครดิตเข้าร้าน '.$this->payment->shop->name.' แล้ว')
                ->action('ไปซื้อหรือต่ออายุแพ็กเกจ', $billingUrl)
                ->line('ขอบคุณที่ใช้บริการ GamoryID');
        }

        $message = (new MailMessage)
            ->subject('การเติมเครดิตไม่ผ่านการตรวจสอบ · GamoryID')
            ->greeting('สวัสดี')
            ->line('ผู้ดูแลระบบไม่อนุมัติการเติมเครดิต '.$credits.' เครดิตของร้าน '.$this->payment->shop->name);

        if ($note !== '') {
            $message->line('หมายเหตุจากผู้ดูแลระบบ: '.$note);
        }

        return $message
            ->action('ส่งสลิปใหม่อีกครั้ง', $billingUrl)
            ->line('หากคิดว่าเป็นความผิดพลาด กรุณาติดต่อทีมงาน');
    }
}
