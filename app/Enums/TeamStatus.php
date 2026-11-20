<?php

namespace App\Enums;

enum TeamStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Pending => in_array($status, [self::Active, self::Cancelled], true),
            self::Active => in_array($status, [self::Suspended, self::Cancelled], true),
            self::Suspended => in_array($status, [self::Active, self::Cancelled], true),
            self::Cancelled => false,
        };
    }
}
