<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without_all:trip_id,booking_id'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
