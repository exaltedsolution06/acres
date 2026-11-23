<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class NotificationSettingController extends Controller
{
    public function index()
    {
        $settings = NotificationSetting::all()->keyBy('event');

        return view('admin.notifications.settings', ['events' => config('engagement.events'), 'settings' => $settings]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $before = NotificationSetting::pluck('in_app', 'event')->all() + [];

        foreach (array_keys(config('engagement.events')) as $event) {
            NotificationSetting::updateOrCreate(['event' => $event], [
                'in_app' => $request->boolean("in_app.{$event}"),
                'email' => $request->boolean("email.{$event}"),
            ]);
        }

        $audit->log('notifications.settings_updated', null, $before, NotificationSetting::all()->mapWithKeys(fn ($s) => [$s->event => ['in_app' => $s->in_app, 'email' => $s->email]])->all());

        return back()->with('success', 'Notification settings saved.');
    }
}
