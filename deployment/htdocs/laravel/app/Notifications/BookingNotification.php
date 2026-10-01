<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\DescribesTrip;

/**
 * Shared shape for notifications about a single booking. The concrete
 * classes only choose the translation key and link.
 */
abstract class BookingNotification extends AppNotification
{
    use DescribesTrip;

    public function __construct(public Booking $booking)
    {
    }

    protected function params(): array
    {
        $this->booking->loadMissing(['trip', 'passenger']);

        return $this->tripParams($this->booking->trip) + [
            'seats' => $this->seatsLabel($this->booking->seats),
            'passenger' => $this->booking->passenger->name,
        ];
    }

    public function title(): string
    {
        return __("notifications.{$this->type()}.title");
    }

    public function message(): string
    {
        return __("notifications.{$this->type()}.message", $this->params());
    }

    public function data(): array
    {
        return ['booking_id' => $this->booking->id, 'trip_id' => $this->booking->trip_id];
    }

    public function link(): ?string
    {
        return '/trips/'.$this->booking->trip_id;
    }
}
