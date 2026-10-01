<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Notifications\Concerns\DescribesTrip;

class TripCancelledNotification extends AppNotification
{
    use DescribesTrip;

    protected bool $mail = true;

    public function __construct(public Trip $trip, public bool $byAdmin = false)
    {
    }

    public function type(): string
    {
        return $this->byAdmin ? 'trip_cancelled_by_admin' : 'trip_cancelled';
    }

    public function title(): string
    {
        return __("notifications.{$this->type()}.title");
    }

    public function message(): string
    {
        return __("notifications.{$this->type()}.message", $this->tripParams($this->trip));
    }

    public function data(): array
    {
        return ['trip_id' => $this->trip->id];
    }

    public function link(): ?string
    {
        return '/trips/'.$this->trip->id;
    }
}
