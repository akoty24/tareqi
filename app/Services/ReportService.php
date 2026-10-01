<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Report;
use App\Models\Trip;
use App\Models\User;

class ReportService
{
    /**
     * The reported user is taken from the request, or derived from the
     * booking (the other party) / trip (its owner).
     */
    public function create(User $reporter, array $data): Report
    {
        $booking = isset($data['booking_id']) ? Booking::with('trip')->findOrFail($data['booking_id']) : null;
        $trip = isset($data['trip_id']) ? Trip::findOrFail($data['trip_id']) : $booking?->trip;

        if ($booking && ! $booking->isPassenger($reporter) && $booking->trip->owner_id !== $reporter->id) {
            throw BusinessRuleException::make('not_booking_participant', 403);
        }

        $reportedUserId = $data['reported_user_id']
            ?? ($booking ? ($booking->isPassenger($reporter) ? $booking->trip->owner_id : $booking->passenger_id) : null)
            ?? $trip?->owner_id;

        if (! $reportedUserId) {
            throw BusinessRuleException::make('report_target_required');
        }
        if ($reportedUserId === $reporter->id) {
            throw BusinessRuleException::make('cannot_report_self');
        }

        $report = new Report(['reason' => $data['reason'], 'description' => $data['description'] ?? null]);
        $report->reporter_id = $reporter->id;
        $report->reported_user_id = $reportedUserId;
        $report->trip_id = $trip?->id;
        $report->booking_id = $booking?->id;
        $report->status = ReportStatus::Pending;
        $report->save();

        return $report;
    }

    public function review(Report $report, User $admin, ReportStatus $status, ?string $notes): Report
    {
        $report->status = $status;
        $report->admin_notes = $notes ?? $report->admin_notes;
        $report->reviewed_by = $admin->id;
        $report->reviewed_at = now();
        $report->save();

        return $report;
    }
}
