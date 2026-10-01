<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CostType;
use App\Enums\TripStatus;
use App\Models\Concerns\HasNormalizedRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Trip extends Model
{
    use HasFactory, HasNormalizedRoute;

    protected $attributes = [
        'auto_confirm_bookings' => false,
    ];

    /** Matching score/breakdown attached by TripMatchingService (not persisted). */
    public ?array $match = null;

    /**
     * owner_id, parent_trip_id, status and available_seats are managed by
     * TripService / BookingService and are intentionally not fillable.
     */
    protected $fillable = [
        'vehicle_id',
        'origin',
        'destination',
        'departure_date',
        'departure_time',
        'total_seats',
        'cost_type',
        'price_per_seat',
        'estimated_cost_per_passenger',
        'notes',
        'auto_confirm_bookings',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date:Y-m-d',
            'total_seats' => 'integer',
            'available_seats' => 'integer',
            'cost_type' => CostType::class,
            'status' => TripStatus::class,
            'price_per_seat' => 'decimal:2',
            'estimated_cost_per_passenger' => 'decimal:2',
            'auto_confirm_bookings' => 'boolean',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /** Always store "H:i:s" so string comparisons work on every database. */
    protected function departureTime(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->format('H:i:s'));
    }

    // ---- Relationships -------------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /** The outbound trip this trip returns from. */
    public function parentTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'parent_trip_id');
    }

    /** The return trip of this outbound trip. */
    public function returnTrip(): HasOne
    {
        return $this->hasOne(Trip::class, 'parent_trip_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBookings(): HasMany
    {
        return $this->bookings()->whereIn('status', BookingStatus::active());
    }

    // ---- Scopes --------------------------------------------------------

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', TripStatus::visible());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        $now = now();

        return $query->where(fn ($q) => $q
            ->whereDate('departure_date', '>', $now->toDateString())
            ->orWhere(fn ($q) => $q
                ->whereDate('departure_date', $now->toDateString())
                ->where('departure_time', '>=', $now->format('H:i:s'))));
    }

    /** Published, in the future, with at least $seats free seats. */
    public function scopeBookable(Builder $query, int $seats = 1): Builder
    {
        return $query->where('status', TripStatus::Published)
            ->where('available_seats', '>=', max(1, $seats))
            ->upcoming();
    }

    public function scopeOnDate(Builder $query, ?string $date, int $flexDays = 0): Builder
    {
        if (blank($date)) {
            return $query;
        }
        $day = Carbon::parse($date);

        return $flexDays > 0
            ? $query->whereDate('departure_date', '>=', $day->copy()->subDays($flexDays)->toDateString())
                ->whereDate('departure_date', '<=', $day->copy()->addDays($flexDays)->toDateString())
            : $query->whereDate('departure_date', $day->toDateString());
    }

    public function scopeDepartingBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->where('departure_time', '>=', Carbon::parse($from)->format('H:i:s')))
            ->when($to, fn ($q) => $q->where('departure_time', '<=', Carbon::parse($to)->format('H:i:s')));
    }

    // ---- Domain helpers ------------------------------------------------

    public function departureAt(): Carbon
    {
        return Carbon::parse($this->departure_date->format('Y-m-d').' '.$this->departure_time);
    }

    public function hasDeparted(): bool
    {
        return $this->departureAt()->isPast();
    }

    public function isReturnTrip(): bool
    {
        return $this->parent_trip_id !== null;
    }

    public function bookedSeats(): int
    {
        return $this->total_seats - $this->available_seats;
    }

    /** Price a passenger pays per seat right now (used for booking snapshots). */
    public function currentSeatPrice(): string
    {
        return match ($this->cost_type) {
            CostType::Free => '0.00',
            CostType::CostSharing => (string) $this->estimated_cost_per_passenger,
            CostType::FixedPrice => (string) $this->price_per_seat,
        };
    }

    /**
     * Seat bookkeeping. Callers must hold a row lock on this trip
     * (see BookingService) so the arithmetic cannot race.
     */
    public function reserveSeats(int $seats): void
    {
        $this->available_seats -= $seats;

        if ($this->available_seats <= 0 && $this->status === TripStatus::Published) {
            $this->available_seats = 0;
            $this->status = TripStatus::Full;
        }
    }

    public function releaseSeats(int $seats): void
    {
        $this->available_seats = min($this->total_seats, $this->available_seats + $seats);

        if ($this->available_seats > 0 && $this->status === TripStatus::Full) {
            $this->status = TripStatus::Published;
        }
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->owner_id === $user->id;
    }
}
