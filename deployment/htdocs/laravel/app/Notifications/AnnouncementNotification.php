<?php

namespace App\Notifications;

use App\Models\Announcement;

/** Message broadcast by the admin team. */
class AnnouncementNotification extends AppNotification
{
    public function __construct(public Announcement $announcement)
    {
        $this->mail = $announcement->send_email;
    }

    public function type(): string
    {
        return 'announcement';
    }

    public function title(): string
    {
        return $this->announcement->title;
    }

    public function message(): string
    {
        return $this->announcement->message;
    }

    public function link(): ?string
    {
        return $this->announcement->link;
    }

    public function data(): array
    {
        return ['announcement_id' => $this->announcement->id];
    }
}
