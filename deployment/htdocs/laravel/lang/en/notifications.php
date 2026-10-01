<?php

return [
    'greeting' => 'Hello :name,',
    'open' => 'Open Tareeqi',
    'seats' => '{1} 1 seat|[2,*] :count seats',

    'booking_created_pending' => [
        'title' => 'Booking request sent',
        'message' => 'Your request for :seats on :origin → :destination on :date at :time is awaiting the owner\'s approval.',
    ],
    'booking_created_confirmed' => [
        'title' => 'Booking confirmed',
        'message' => 'Your :seats on :origin → :destination on :date at :time are confirmed.',
    ],
    'new_booking_request' => [
        'title' => 'New booking request',
        'message' => ':passenger requested :seats on your trip :origin → :destination on :date. Please approve or reject.',
    ],
    'new_booking_confirmed' => [
        'title' => 'New booking on your trip',
        'message' => ':passenger booked :seats on your trip :origin → :destination on :date.',
    ],
    'booking_confirmed' => [
        'title' => 'Booking approved',
        'message' => 'The owner approved your booking on :origin → :destination on :date at :time.',
    ],
    'booking_rejected' => [
        'title' => 'Booking rejected',
        'message' => 'Your booking on :origin → :destination on :date was rejected. Try another trip.',
    ],
    'booking_cancelled_by_passenger' => [
        'title' => 'Booking cancelled',
        'message' => ':passenger cancelled :seats on your trip :origin → :destination on :date.',
    ],
    'booking_cancelled_by_owner' => [
        'title' => 'Your booking was cancelled',
        'message' => 'The owner cancelled your booking on :origin → :destination on :date.',
    ],
    'booking_cancelled_by_admin' => [
        'title' => 'A booking was cancelled by the platform',
        'message' => 'The platform team cancelled the booking of :passenger (:seats) on :origin → :destination on :date.',
    ],
    'trip_cancelled' => [
        'title' => 'Trip cancelled',
        'message' => 'The trip :origin → :destination on :date at :time was cancelled.',
    ],
    'trip_cancelled_by_admin' => [
        'title' => 'Trip cancelled by admin',
        'message' => 'The platform administration cancelled the trip :origin → :destination on :date.',
    ],
    'trip_approaching' => [
        'title' => 'Your trip is coming up',
        'message' => 'The trip :origin → :destination departs on :date at :time.',
    ],
    'return_trip_approaching' => [
        'title' => 'Your return trip is coming up',
        'message' => 'The return trip :origin → :destination departs on :date at :time.',
    ],
    'trip_request_matched' => [
        'title' => 'We found a trip for your request',
        'message' => 'A trip :origin → :destination on :date at :time matches your request. Book before seats run out.',
    ],
];
