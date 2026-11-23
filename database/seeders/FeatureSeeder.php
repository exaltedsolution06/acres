<?php

namespace Database\Seeders;

use App\Models\PackageFeature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $visibility = ['hidden', 'masked', 'after_interest', 'on_click', 'credits', 'unlimited'];

        // key, name, type, default, is_daily, options
        $features = [
            ['daily_property_views', 'Daily property views', 'limit', '0', true],
            ['daily_contact_views', 'Daily contact views', 'limit', '0', true],
            ['daily_interest', 'Daily interests', 'limit', '0', true],
            ['daily_enquiries', 'Daily enquiries', 'limit', '0', true],
            ['active_listings', 'Active listings', 'limit', '0', false],
            ['photo_limit', 'Photos per listing', 'limit', '0', false],
            ['saved_properties', 'Saved properties', 'limit', '0', false],
            ['search_priority', 'Search ranking priority', 'limit', '0', false],
            ['video_upload', 'Video upload', 'boolean', '0', false],
            ['featured_listing', 'Featured listing', 'boolean', '0', false],
            ['map_search', 'Map search', 'boolean', '0', false],
            ['requirement_posting', 'Requirement posting', 'boolean', '0', false],
            ['lead_management', 'Lead management', 'boolean', '0', false],
            ['broker_crm', 'Broker CRM', 'boolean', '0', false],
            ['contact_unique_counting', 'Count each phone number once', 'boolean', '1', false],
            ['contact_visibility', 'Contact visibility mode', 'enum', 'hidden', false, $visibility],
        ];

        foreach ($features as $f) {
            PackageFeature::updateOrCreate(['key' => $f[0]], [
                'name' => $f[1], 'type' => $f[2], 'default_value' => $f[3],
                'is_daily' => $f[4], 'options' => $f[5] ?? null, 'is_active' => true,
            ]);
        }
    }
}
