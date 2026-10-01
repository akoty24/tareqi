<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ActivityLog */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => __('activity.'.str_replace('.', '_', $this->action), $this->replacements()),
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'properties' => $this->properties ?? [],
            'causer' => new PublicUserResource($this->whenLoaded('causer')),
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Scalar properties are available as :placeholders in the translated description. */
    private function replacements(): array
    {
        return collect($this->properties ?? [])
            ->filter(fn ($value) => is_scalar($value))
            ->put('id', $this->subject_id)
            ->all();
    }
}
