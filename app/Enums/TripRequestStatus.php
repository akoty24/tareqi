<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TripRequestStatus: string
{
    use HasValues;

    case Active = 'active';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
