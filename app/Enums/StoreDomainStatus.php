<?php

namespace App\Enums;

enum StoreDomainStatus: string
{
    case Pending = 'pending';
    case Verifying = 'verifying';
    case Verified = 'verified';
    case Active = 'active';
    case Disabled = 'disabled';

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Pending => in_array($status, [self::Verifying, self::Disabled], true),
            self::Verifying => in_array($status, [self::Pending, self::Disabled], true),
            self::Verified => in_array($status, [self::Active, self::Disabled], true),
            self::Active => $status === self::Disabled,
            self::Disabled => $status === self::Pending,
        };
    }
}
