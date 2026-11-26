<?php

namespace App\Notifications\Platform;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlatformEventNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $data) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array { return $this->data; }
}
