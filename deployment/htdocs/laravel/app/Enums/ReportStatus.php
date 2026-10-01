<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ReportStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
