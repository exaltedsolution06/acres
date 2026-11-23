<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\EventNotification;

/** Sends in-app and email notifications, honouring the admin's per-event switches. */
class Notifier
{
    public function send(User $user, string $event, string $title, string $body, string $url): void
    {
        $setting = NotificationSetting::where('event', $event)->first(); // no row = enabled

        $channels = [];

        if (! $setting || $setting->in_app) {
            $channels[] = 'database';
        }

        if ((! $setting || $setting->email) && $user->email) {
            $channels[] = 'mail';
        }

        if ($channels) {
            $user->notify(new EventNotification($channels, $title, $body, $url, $event));
        }
    }
}
