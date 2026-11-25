<?php

namespace App\Notifications\Storefront;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundRequestResult extends Notification
{
    use Queueable;

    public function __construct(private readonly RefundRequest $refundRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $refunded = $this->refundRequest->status === 'refunded';
        $message = (new MailMessage)
            ->subject($refunded ? 'Your refund has been processed' : 'Your refund request was declined')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($refunded
                ? 'Your refund for order '.$this->refundRequest->order->order_number.' has been recorded as completed.'
                : 'Your refund request for order '.$this->refundRequest->order->order_number.' was declined.')
            ->line($this->refundRequest->result_notes ?? $this->refundRequest->admin_notes ?? 'Please contact customer support if you need more information.');

        if ($this->refundRequest->refund_reference !== null) {
            $message->line('Refund reference: '.$this->refundRequest->refund_reference);
        }

        return $message->line('Thank you.');
    }
}
