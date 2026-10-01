<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ReportReason: string
{
    use HasValues;

    case UnsafeDriving = 'unsafe_driving';
    case Harassment = 'harassment';
    case NoShow = 'no_show';
    case Fraud = 'fraud';
    case InappropriateContent = 'inappropriate_content';
    case Other = 'other';
}
