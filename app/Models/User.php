<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Enums\TripStatus;
use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Role, status and rating aggregates are never mass assignable. */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'notify_email',
        'notify_push',
    ];

    /** Same defaults as the columns, so fresh instances are complete. */
    protected $attributes = [
        'notify_email' => true,
        'notify_push' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'rating_average' => 'decimal:2',
            'ratings_count' => 'integer',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'owner_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    public function tripRequests(): HasMany
    {
        return $this->hasMany(TripRequest::class);
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    /** Staff member: any user with a role can open the admin panel. */
    public function isAdmin(): bool
    {
        return $this->role_id !== null;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->role?->is_super;
    }

    public function hasPermission(Permission|string $permission): bool
    {
        return (bool) $this->role?->hasPermission($permission);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->role?->effectivePermissions() ?? [];
    }

    /** FCM routing: every registered device of the user. */
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    /** Completed trips as owner and as passenger (calculated, not stored). */
    public function scopeWithCompletedTripCounts(Builder $query): Builder
    {
        return $query->withCount([
            'trips as completed_trips_as_owner_count' => fn ($q) => $q->where('status', TripStatus::Completed),
            'bookings as completed_trips_as_passenger_count' => fn ($q) => $q->where('status', BookingStatus::Completed),
        ]);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereNotNull('role_id');
    }

    public function scopeMembers(Builder $query): Builder
    {
        return $query->whereNull('role_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%"));
    }
}
