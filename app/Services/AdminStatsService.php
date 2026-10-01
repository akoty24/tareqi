<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ReportStatus;
use App\Enums\TripRequestStatus;
use App\Enums\TripStatus;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\Report;
use App\Models\Trip;
use App\Models\TripRequest;
use App\Models\User;

class AdminStatsService
{
    public function dashboard(): array
    {
        // One grouped query per table instead of one COUNT per metric.
        $users = User::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $trips = Trip::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $bookings = Booking::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $requests = TripRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $reports = Report::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $count = fn ($collection, $status) => (int) ($collection[$status->value] ?? 0);

        return [
            'users' => [
                'total' => (int) $users->sum(),
                'active' => $count($users, UserStatus::Active),
                'blocked' => $count($users, UserStatus::Blocked),
            ],
            'trips' => [
                'total' => (int) $trips->sum(),
                // "Active" = open or on the road.
                'active' => $count($trips, TripStatus::Published) + $count($trips, TripStatus::Full) + $count($trips, TripStatus::Started),
                'completed' => $count($trips, TripStatus::Completed),
                'cancelled' => $count($trips, TripStatus::Cancelled),
                'draft' => $count($trips, TripStatus::Draft),
            ],
            'bookings' => [
                'total' => (int) $bookings->sum(),
                'pending' => $count($bookings, BookingStatus::Pending),
                'confirmed' => $count($bookings, BookingStatus::Confirmed),
                'completed' => $count($bookings, BookingStatus::Completed),
            ],
            'trip_requests' => [
                'total' => (int) $requests->sum(),
                'active' => $count($requests, TripRequestStatus::Active),
            ],
            'reports' => [
                'total' => (int) $reports->sum(),
                'pending' => $count($reports, ReportStatus::Pending),
            ],
        ];
    }
}
