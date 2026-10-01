<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Published = 'published';
    case Full = 'full';
    case Started = 'started';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Only published trips accept new bookings. */
    public function acceptsBookings(): bool
    {
        return $this === self::Published;
    }

    /** Owner may still edit details / cancel. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Published, self::Full], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Statuses visible to other users (search, details). */
    public static function visible(): array
    {
        return [self::Published, self::Full, self::Started, self::Completed];
    }
}
