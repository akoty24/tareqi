<?php

namespace Database\Seeders;

use App\Enums\AnnouncementAudience;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Seeder;

/** A delivered announcement and a few audit log entries for the admin panel demo. */
class AdminActivitySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@mishwar.test')->firstOrFail();
        $blocked = User::where('email', 'blocked@mishwar.test')->firstOrFail();

        $announcement = new Announcement([
            'title' => 'أهلاً بيكم في طريقي',
            'message' => 'اعرض رحلتك أو اطلب مشوار، وخلّي جيرانك يشاركوك الطريق. خلي بالك من مواعيدك واحترم شركاء الرحلة.',
            'link' => '/search',
            'audience' => AnnouncementAudience::All,
            'send_email' => false,
        ]);
        $announcement->sender()->associate($admin);
        $recipients = $announcement->recipientsQuery()->get();
        $announcement->forceFill(['recipients_count' => $recipients->count(), 'sent_at' => now()->subDays(2)])->save();

        $recipients->each(fn (User $user) => $user->notifyNow(new AnnouncementNotification($announcement), ['database']));

        $log = function (string $action, $subject, array $properties, $when) use ($admin) {
            $entry = new ActivityLog(['action' => $action, 'properties' => $properties, 'ip_address' => '127.0.0.1']);
            $entry->causer()->associate($admin);
            $entry->subject()->associate($subject);
            $entry->created_at = $when;
            $entry->save();
        };

        $log('user.blocked', $blocked, ['name' => $blocked->name], now()->subDay());
        $log('announcement.sent', $announcement, ['title' => $announcement->title, 'count' => $announcement->recipients_count], now()->subDays(2));
    }
}
