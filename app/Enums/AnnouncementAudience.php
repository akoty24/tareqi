<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AnnouncementAudience: string
{
    use HasValues;

    /** Every active user. */
    case All = 'all';
    /** Active users who own at least one vehicle. */
    case Drivers = 'drivers';
    /** Active staff members (users with a role). */
    case Staff = 'staff';
    /** An explicit list of user IDs. */
    case Selected = 'selected';
}
