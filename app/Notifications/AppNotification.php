<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for all platform notifications.
 *
 * Subclasses only describe the content (type, title, message, data, link).
 * Delivery channels are decided here, so adding push / WhatsApp later means
 * adding a channel + a `toX()` method here, without touching business logic.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Whether this notification is also worth an email. */
    protected bool $mail = false;

    /** Stable machine-readable type used by the frontend for icons/translation. */
    abstract public function type(): string;

    abstract public function title(): string;

    abstract public function message(): string;

    /** Extra identifiers (trip_id, booking_id, ...) for deep links. */
    public function data(): array
    {
        return [];
    }

    /** Frontend path to open when the notification is clicked. */
    public function link(): ?string
    {
        return null;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->mail && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'message' => $this->message(),
            'link' => $this->link(),
            'data' => $this->data(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line($this->message());

        if ($this->link()) {
            $mail->action(__('notifications.open'), rtrim(config('app.frontend_url'), '/').$this->link());
        }

        return $mail;
    }
}
