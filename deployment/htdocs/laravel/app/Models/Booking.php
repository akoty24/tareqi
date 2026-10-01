<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    /** Everything is set by BookingService; nothing is mass assignable from requests. */
    protected $fillable = [
        'seats',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'status' => BookingStatus::class,
            'price_per_seat' => 'decimal:2',
            'total_price' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::active());
    }

    public function isPassenger(?User $user): bool
    {
        return $user !== null && $this->passenger_id === $user->id;
    }
}
