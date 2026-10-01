<?php

namespace App\Notifications\Concerns;

use App\Models\Trip;
use Illuminate\Support\Carbon;

trait DescribesTrip
{
    /**
     * Human, localized trip parameters for notification texts,
     * e.g. date "الثلاثاء 6 أكتوبر", time "7:30 ص".
     */
    protected function tripParams(Trip $trip): array
    {
        $locale = app()->getLocale();
        $time = Carbon::parse($trip->departure_time)->locale($locale);

        return [
            'origin' => $trip->origin,
            'destination' => $trip->destination,
            'date' => $trip->departure_date->copy()->locale($locale)->translatedFormat('l j F'),
            'time' => $time->translatedFormat('g:i A'),
        ];
    }

    /** "مقعد واحد" / "مقعدين" / "3 مقاعد" (grammatically correct counts). */
    protected function seatsLabel(int $seats): string
    {
        return trans_choice('notifications.seats', $seats, ['count' => $seats]);
    }
}
