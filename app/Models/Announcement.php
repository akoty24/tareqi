<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'message',
        'link',
        'audience',
        'user_ids',
        'send_email',
    ];

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'user_ids' => 'array',
            'send_email' => 'boolean',
            'recipients_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Active users targeted by this announcement. */
    public function recipientsQuery(): Builder
    {
        $query = User::query()->where('status', UserStatus::Active);

        return match ($this->audience) {
            AnnouncementAudience::All => $query,
            AnnouncementAudience::Drivers => $query->whereHas('vehicles'),
            AnnouncementAudience::Staff => $query->whereNotNull('role_id'),
            AnnouncementAudience::Selected => $query->whereIn('id', $this->user_ids ?? []),
        };
    }
}
