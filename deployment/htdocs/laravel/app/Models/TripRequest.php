<?php

namespace App\Models;

use App\Enums\TripRequestStatus;
use App\Models\Concerns\HasNormalizedRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TripRequest extends Model
{
    use HasFactory, HasNormalizedRoute;

    protected $fillable = [
        'origin',
        'destination',
        'requested_date',
        'preferred_time_from',
        'preferred_time_to',
        'passengers_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date:Y-m-d',
            'passengers_count' => 'integer',
            'status' => TripRequestStatus::class,
        ];
    }

    protected function preferredTimeFrom(): Attribute
    {
        return Attribute::make(set: fn ($value) => $value ? Carbon::parse($value)->format('H:i:s') : null);
    }

    protected function preferredTimeTo(): Attribute
    {
        return Attribute::make(set: fn ($value) => $value ? Carbon::parse($value)->format('H:i:s') : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Trips this request was matched with (and notified about). */
    public function matchedTrips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_request_matches')
            ->withPivot('score')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TripRequestStatus::Active);
    }
}
