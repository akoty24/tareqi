<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Report */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->hasPermission(Permission::ReportsView);

        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'description' => $this->description,
            'status' => $this->status->value,
            'trip_id' => $this->trip_id,
            'booking_id' => $this->booking_id,
            'reporter' => $this->when($isAdmin, fn () => new UserResource($this->whenLoaded('reporter'))),
            'reported_user' => $this->when($isAdmin, fn () => new UserResource($this->whenLoaded('reportedUser'))),
            'trip' => $this->when($isAdmin, fn () => new TripResource($this->whenLoaded('trip'))),
            'admin_notes' => $this->when($isAdmin, $this->admin_notes),
            'reviewer' => $this->when($isAdmin, fn () => new PublicUserResource($this->whenLoaded('reviewer'))),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
