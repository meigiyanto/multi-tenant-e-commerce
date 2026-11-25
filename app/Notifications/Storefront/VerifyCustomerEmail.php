<?php

namespace App\Notifications\Storefront;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyCustomerEmail extends Notification
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Customer $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'storefront.account.verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'storeSubdomain' => $notifiable->store->slug,
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject(__('Verify your email address'))
            ->line(__('Please verify your email address to continue using your customer account.'))
            ->action(__('Verify email address'), $url)
            ->line(__('If you did not create this account, no further action is required.'));
    }
}
