<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One generic notification: the event decides who gets it and which channels are on (see Notifier). */
class EventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $channels,
        public string $title,
        public string $body,
        public string $url,
        public string $event = '',
    ) {
    }

    public function via($notifiable): array
    {
        return $this->channels;
    }

    public function toArray($notifiable): array
    {
        return ['event' => $this->event, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->line($this->body)->action('Open', $this->url);
    }
}
