<?php

namespace App\Listeners;

use App\Events\TripPublished;
use App\Services\Matching\TripRequestMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Queued: matching can scan many requests and must not slow down publishing. */
class MatchTripRequests implements ShouldQueue
{
    public function __construct(private readonly TripRequestMatchingService $matching)
    {
    }

    public function handle(TripPublished $event): void
    {
        $this->matching->matchTrip($event->trip);
    }
}
