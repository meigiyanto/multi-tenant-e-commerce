<?php

namespace App\Notifications\Merchant;

use App\Models\MerchantApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchantApplicationResult extends Notification
{
    use Queueable;

    public function __construct(private readonly MerchantApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $approved = $this->application->status === 'approved';

        return [
            'event' => $approved ? 'merchant_application_approved' : 'merchant_application_rejected',
            'title' => $approved ? 'Merchant application approved' : 'Merchant application rejected',
            'message' => $approved
                ? 'Your application for '.$this->application->business_name.' has been approved.'
                : 'Your application for '.$this->application->business_name.' was rejected.',
            'url' => route('merchant.application.show'),
            'subject_id' => $this->application->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->application->status === 'approved';

        return (new MailMessage)
            ->subject($approved ? 'Your merchant application was approved' : 'Your merchant application was rejected')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($approved
                ? 'Your merchant application for '.$this->application->business_name.' has been approved.'
                : 'Your merchant application for '.$this->application->business_name.' was rejected.')
            ->line($this->application->review_notes ?: ($approved
                ? 'You can now continue setting up your merchant workspace.'
                : 'You may review the notes and submit an updated application if eligible.'));
    }
}
