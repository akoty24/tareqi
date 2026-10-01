<?php

namespace App\Jobs;

use App\Enums\TripRequestStatus;
use App\Models\TripRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Active requests whose date has passed become expired. */
class ExpireTripRequests implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        TripRequest::query()
            ->active()
            ->whereDate('requested_date', '<', now()->toDateString())
            ->update(['status' => TripRequestStatus::Expired, 'updated_at' => now()]);
    }
}
