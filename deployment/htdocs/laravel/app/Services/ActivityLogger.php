<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /** Record a staff action, e.g. log('user.blocked', $user, ['reason' => ...]). */
    public function log(string $action, ?Model $subject = null, array $properties = []): ActivityLog
    {
        $log = new ActivityLog([
            'action' => $action,
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
        $log->causer()->associate(auth()->user());
        if ($subject) {
            $log->subject()->associate($subject);
        }
        $log->save();

        return $log;
    }
}
