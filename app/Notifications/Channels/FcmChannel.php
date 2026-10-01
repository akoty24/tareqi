<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Services\Push\FcmClient;
use Illuminate\Notifications\Notification;

/** Push notification to every device of the user; dead tokens are pruned. */
class FcmChannel
{
    public function __construct(private readonly FcmClient $client)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = (array) $notifiable->routeNotificationFor('fcm', $notification);
        if ($tokens === [] || ! method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);

        foreach ($tokens as $token) {
            if (! $this->client->send($token, $message)) {
                DeviceToken::query()->where('token', $token)->delete();
            }
        }
    }
}
