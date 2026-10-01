<?php

namespace App\Models\Concerns;

use App\Support\PlaceName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Keeps origin_normalized / destination_normalized in sync with the
 * user-facing origin / destination and provides route search scopes.
 */
trait HasNormalizedRoute
{
    protected function origin(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => [
            'origin' => PlaceName::clean($value),
            'origin_normalized' => PlaceName::normalize($value),
        ]);
    }

    protected function destination(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => [
            'destination' => PlaceName::clean($value),
            'destination_normalized' => PlaceName::normalize($value),
        ]);
    }

    public function scopeFromPlace(Builder $query, ?string $place): Builder
    {
        return blank($place) ? $query
            : $query->where($this->qualifyColumn('origin_normalized'), 'like', '%'.PlaceName::normalize($place).'%');
    }

    public function scopeToPlace(Builder $query, ?string $place): Builder
    {
        return blank($place) ? $query
            : $query->where($this->qualifyColumn('destination_normalized'), 'like', '%'.PlaceName::normalize($place).'%');
    }
}
