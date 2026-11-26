<?php

namespace App\Notifications\Stores;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StoreEventNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }
}
