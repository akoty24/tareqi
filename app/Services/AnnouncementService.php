<?php

namespace App\Services;

use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementService
{
    public function __construct(private readonly ActivityLogger $activity)
    {
    }

    public function send(User $sender, array $data): Announcement
    {
        $announcement = new Announcement($data);
        $announcement->sender()->associate($sender);
        // Estimated now; the job stores the exact number once delivered.
        $announcement->recipients_count = $announcement->recipientsQuery()->count();
        $announcement->save();

        SendAnnouncement::dispatch($announcement);

        $this->activity->log('announcement.sent', $announcement, [
            'title' => $announcement->title,
            'count' => $announcement->recipients_count,
        ]);

        return $announcement;
    }
}
