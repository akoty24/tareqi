<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Models\TripRequest;
use App\Notifications\Concerns\DescribesTrip;

class TripRequestMatchedNotification extends AppNotification
{
    use DescribesTrip;

    protected bool $mail = true;

    public function __construct(public TripRequest $tripRequest, public Trip $trip, public int $score)
    {
    }

    public function type(): string
    {
        return 'trip_request_matched';
    }

    public function title(): string
    {
        return __('notifications.trip_request_matched.title');
    }

    public function message(): string
    {
        return __('notifications.trip_request_matched.message', $this->tripParams($this->trip));
    }

    public function data(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'trip_request_id' => $this->tripRequest->id,
            'score' => $this->score,
        ];
    }

    public function link(): ?string
    {
        return '/trips/'.$this->trip->id;
    }
}
