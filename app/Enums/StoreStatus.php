<?php

namespace App\Enums;

enum StoreStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Draft => in_array($status, [self::Active, self::Archived], true),
            self::Active => in_array($status, [self::Suspended, self::Archived], true),
            self::Suspended => in_array($status, [self::Active, self::Archived], true),
            self::Archived => false,
        };
    }
}
