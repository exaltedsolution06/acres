<?php

namespace Database\Seeders;

use App\Models\NotificationSetting;
use Illuminate\Database\Seeder;

class NotificationSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(config('engagement.events')) as $event) {
            NotificationSetting::firstOrCreate(['event' => $event], ['in_app' => true, 'email' => true]);
        }
    }
}
