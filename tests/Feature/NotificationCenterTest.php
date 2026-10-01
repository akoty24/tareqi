<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AnnouncementNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_deletes_a_notification_and_clears_read_ones(): void
    {
        $user = $this->actingAsUser();
        $booking = Booking::factory()->create();
        foreach (range(1, 3) as $_) {
            $user->notifyNow(new BookingConfirmedNotification($booking), ['database']);
        }
        [$first, $second] = $user->notifications()->pluck('id');
        $user->notifications()->whereKey($second)->update(['read_at' => now()]);

        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 2);

        $this->deleteJson("/api/notifications/{$first}")->assertOk();
        $this->deleteJson('/api/notifications/read')->assertOk();

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(1, $user->unreadNotifications()->count());

        // Someone else's notification is not found.
        $this->actingAsUser();
        $remaining = $user->notifications()->value('id');
        $this->deleteJson("/api/notifications/{$remaining}")->assertNotFound();
    }

    public function test_notification_settings_control_email_delivery(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/profile/notification-settings', ['email' => false])
            ->assertOk()
            ->assertJsonPath('data.notification_settings', ['email' => false, 'push' => true]);

        $booking = Booking::factory()->for($user, 'passenger')->create();
        $channels = (new BookingConfirmedNotification($booking))->via($user->fresh());
        $this->assertSame(['database'], $channels);
    }

    public function test_device_tokens_are_registered_moved_and_removed(): void
    {
        $first = $this->actingAsUser();
        $this->postJson('/api/devices', ['token' => 'device-abc', 'platform' => 'android'])->assertOk();
        $this->assertSame($first->id, DeviceToken::where('token', 'device-abc')->value('user_id'));

        // Same phone, another account logs in: the token moves.
        $second = $this->actingAsUser();
        $this->postJson('/api/devices', ['token' => 'device-abc'])->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->assertSame($second->id, DeviceToken::value('user_id'));

        $this->deleteJson('/api/devices', ['token' => 'device-abc'])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_push_is_sent_through_fcm_and_dead_tokens_are_pruned(): void
    {
        $this->configureFakeFirebase();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'projects/mishwar-test/messages/1'])
                ->push(['error' => ['status' => 'NOT_FOUND']], 404),
        ]);
        $user = User::factory()->create();
        $user->deviceTokens()->createMany([['token' => 'alive'], ['token' => 'dead']]);
        $booking = Booking::factory()->for($user, 'passenger')->create();

        $notification = new BookingConfirmedNotification($booking);
        $this->assertContains(FcmChannel::class, $notification->via($user));
        $user->notifyNow($notification, [FcmChannel::class]);

        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/mishwar-test/messages:send'
            && $request['message']['token'] === 'alive'
            && $request['message']['data']['link'] === "/trips/{$booking->trip_id}"
            && $request->hasHeader('Authorization', 'Bearer ya29.test'));
        $this->assertSame(['alive'], $user->deviceTokens()->pluck('token')->all());
    }

    public function test_push_channel_is_skipped_when_firebase_is_not_configured_or_user_opted_out(): void
    {
        config(['services.fcm.credentials' => storage_path('app/missing.json')]);
        $user = User::factory()->create();
        $booking = Booking::factory()->for($user, 'passenger')->create();
        $this->assertNotContains(FcmChannel::class, (new BookingConfirmedNotification($booking))->via($user));

        $this->configureFakeFirebase();
        $user->forceFill(['notify_push' => false])->save();
        $this->assertNotContains(FcmChannel::class, (new BookingConfirmedNotification($booking))->via($user));
    }

    public function test_admin_broadcasts_an_announcement_to_an_audience(): void
    {
        Notification::fake();
        $admin = $this->actingAsAdmin();
        $driver = User::factory()->create();
        Vehicle::factory()->for($driver)->create();
        $passenger = User::factory()->create();
        $blocked = User::factory()->blocked()->create();
        Vehicle::factory()->for($blocked)->create();

        $this->postJson('/api/admin/announcements', [
            'title' => 'تحديث مهم',
            'message' => 'تم إضافة رحلات العودة.',
            'link' => '/search',
            'audience' => 'drivers',
        ])->assertCreated()
            ->assertJsonPath('data.recipients_count', 1)
            ->assertJsonPath('data.sender.id', $admin->id);

        Notification::assertSentTo($driver, AnnouncementNotification::class, fn ($n) => $n->title() === 'تحديث مهم');
        Notification::assertNotSentTo([$passenger, $blocked, $admin], AnnouncementNotification::class);
        $this->assertNotNull(Announcement::first()->sent_at);

        $this->postJson('/api/admin/announcements', [
            'title' => 'لك أنت',
            'message' => 'رسالة خاصة.',
            'audience' => 'selected',
            'user_ids' => [$passenger->id],
            'send_email' => true,
        ])->assertCreated();
        Notification::assertSentTo($passenger, AnnouncementNotification::class, fn ($n, $channels) => in_array('mail', $channels, true));

        $this->getJson('/api/admin/announcements')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_announcement_validation_and_permission(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/admin/announcements', [
            'title' => 'رابط خارجي',
            'message' => 'رسالة',
            'link' => 'https://evil.example.com',
            'audience' => 'selected',
        ])->assertUnprocessable()->assertJsonValidationErrors(['link', 'user_ids']);

        $role = Role::factory()->withPermissions([Permission::UsersView])->create();
        $this->actingAsUser(User::factory()->withRole($role)->create());
        $this->postJson('/api/admin/announcements', ['title' => 'x', 'message' => 'y', 'audience' => 'all'])->assertForbidden();
    }

    public function test_announcement_appears_in_the_notification_center(): void
    {
        $this->actingAsAdmin();
        $member = User::factory()->create();

        $this->postJson('/api/admin/announcements', ['title' => 'أهلاً', 'message' => 'نورتونا', 'audience' => 'all'])->assertCreated();

        $this->actingAsUser($member);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'announcement')
            ->assertJsonPath('data.0.title', 'أهلاً')
            ->assertJsonPath('meta.unread_count', 1);
    }

    /** Writes a throwaway service account signed with a test-only RSA key. */
    private function configureFakeFirebase(): void
    {
        $pem = file_get_contents(base_path('tests/Fixtures/fcm-test-key.pem'));
        $path = storage_path('framework/testing/firebase-test.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'mishwar-test',
            'client_email' => 'push@mishwar-test.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $path, 'services.fcm.project_id' => null]);
        cache()->forget('fcm.access_token');
    }
}
