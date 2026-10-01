<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum VehicleType: string
{
    use HasValues;

    case Sedan = 'sedan';
    case Hatchback = 'hatchback';
    case Suv = 'suv';
    case Minivan = 'minivan';
    case Microbus = 'microbus';
    case Pickup = 'pickup';
}
