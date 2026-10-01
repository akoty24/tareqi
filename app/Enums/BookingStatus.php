<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BookingStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** Statuses that hold seats on the trip. */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
