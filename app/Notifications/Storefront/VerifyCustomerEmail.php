<?php

namespace App\Notifications\Storefront;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;

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
        $rootDomain = trim((string) config('storefront.root_domain'));
        $primaryDomain = $notifiable->store?->primaryDomain?->domain;
        $suffix = '.'.$rootDomain;

        if ($rootDomain === '' || ! is_string($primaryDomain) || ! Str::endsWith($primaryDomain, $suffix)) {
            throw new LogicException('The store primary domain must be configured under the storefront root domain before sending verification emails.');
        }

        $storeSubdomain = Str::beforeLast($primaryDomain, $suffix);

        $url = URL::temporarySignedRoute(
            'storefront.account.verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'storeSubdomain' => $storeSubdomain,
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
