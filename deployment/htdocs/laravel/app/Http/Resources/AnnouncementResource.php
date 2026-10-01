<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Announcement */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'audience' => $this->audience->value,
            'user_ids' => $this->user_ids ?? [],
            'send_email' => $this->send_email,
            'recipients_count' => $this->recipients_count,
            'sender' => new PublicUserResource($this->whenLoaded('sender')),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
