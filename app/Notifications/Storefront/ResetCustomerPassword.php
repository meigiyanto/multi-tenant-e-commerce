<?php

namespace App\Notifications\Storefront;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ResetCustomerPassword extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::route('storefront.account.password.reset', [
            'storeSubdomain' => $notifiable->store->slug,
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        return (new MailMessage)
            ->subject(__('Reset your customer account password'))
            ->line(__('You are receiving this email because a password reset was requested for your store customer account.'))
            ->action(__('Reset password'), $url)
            ->line(__('This link expires in :count minutes.', ['count' => (int) config('auth.passwords.customers.expire', 60)]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
