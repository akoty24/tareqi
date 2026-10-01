<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CostType: string
{
    use HasValues;

    case Free = 'free';
    case CostSharing = 'cost_sharing';
    case FixedPrice = 'fixed_price';
}
