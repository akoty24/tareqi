<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Notifications\AnnouncementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/** Fan out an announcement to its audience in chunks (each delivery is queued). */
class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement)
    {
    }

    public function handle(): void
    {
        $notification = new AnnouncementNotification($this->announcement);
        $sent = 0;

        $this->announcement->recipientsQuery()->chunkById(200, function ($users) use ($notification, &$sent) {
            Notification::send($users, $notification);
            $sent += $users->count();
        });

        $this->announcement->forceFill(['recipients_count' => $sent, 'sent_at' => now()])->save();
    }
}
