<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Illustrative packages only. Prices and limits are sample data that the admin
 * panel (Milestone 2) will edit; nothing in application code depends on them.
 * null = unlimited.
 */
class PackageSeeder extends Seeder
{
    private array $base = [
        'daily_property_views' => 20, 'daily_contact_views' => 0, 'daily_interest' => 2, 'daily_enquiries' => 2,
        'active_listings' => 0, 'photo_limit' => 5, 'saved_properties' => 10, 'search_priority' => 0,
        'video_upload' => false, 'featured_listing' => false, 'map_search' => false,
        'requirement_posting' => false, 'lead_management' => false, 'broker_crm' => false,
        'contact_unique_counting' => true, 'contact_visibility' => 'masked',
    ];

    public function run(): void
    {
        foreach ($this->definitions() as $role => $packages) {
            foreach ($packages as $i => [$name, $price, $days, $overrides]) {
                $free = $price === 0;

                $package = Package::updateOrCreate(['slug' => "{$role}-".strtolower($name)], [
                    'role_id' => Role::where('slug', $role)->value('id'),
                    'name' => $name, 'price' => $price, 'gst_percent' => 18,
                    'validity_days' => $days, 'grace_days' => $free ? 0 : 3,
                    'is_free' => $free, 'is_default' => $free,
                    'is_recommended' => in_array($name, ['Premium', 'Professional'], true),
                    'display_order' => $i + 1, 'status' => 'active',
                ]);

                foreach (array_merge($this->base, $overrides) as $key => $value) {
                    PackageFeatureValue::updateOrCreate(
                        ['package_id' => $package->id, 'feature_id' => PackageFeature::where('key', $key)->value('id')],
                        [
                            'value' => $value === null ? null : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
                            'is_unlimited' => $value === null,
                        ],
                    );
                }
            }
        }
    }

    private function definitions(): array
    {
        $seeker = fn (int $views, int $interest, int $contacts, int $saved, array $extra = []) => array_merge([
            'daily_property_views' => $views, 'daily_interest' => $interest, 'daily_enquiries' => $interest,
            'daily_contact_views' => $contacts, 'contact_visibility' => 'on_click', 'saved_properties' => $saved,
            'requirement_posting' => true, 'map_search' => true,
        ], $extra);

        return [
            'owner' => [
                ['Free', 0, null, ['active_listings' => 1, 'daily_contact_views' => 3, 'contact_visibility' => 'on_click']],
                ['Basic', 499, 30, ['active_listings' => 5, 'photo_limit' => 15, 'video_upload' => true, 'daily_property_views' => 100, 'daily_contact_views' => 15, 'contact_visibility' => 'on_click', 'lead_management' => true]],
                ['Premium', 1499, 90, ['active_listings' => 15, 'photo_limit' => 30, 'video_upload' => true, 'featured_listing' => true, 'daily_property_views' => 200, 'daily_contact_views' => 40, 'contact_visibility' => 'on_click', 'lead_management' => true, 'requirement_posting' => true, 'map_search' => true, 'search_priority' => 1]],
                ['Business', 3999, 365, ['active_listings' => 50, 'photo_limit' => 40, 'video_upload' => true, 'featured_listing' => true, 'daily_property_views' => null, 'daily_contact_views' => null, 'contact_visibility' => 'unlimited', 'lead_management' => true, 'requirement_posting' => true, 'map_search' => true, 'search_priority' => 2]],
            ],
            'tenant' => [
                ['Free', 0, null, ['contact_visibility' => 'masked']],
                ['Basic', 199, 30, $seeker(60, 10, 5, 50)],
                ['Premium', 499, 90, $seeker(150, 25, 15, 200)],
            ],
            'buyer' => [
                ['Free', 0, null, ['contact_visibility' => 'masked']],
                ['Basic', 299, 30, $seeker(60, 10, 5, 50)],
                ['Premium', 799, 90, $seeker(150, 25, 15, 200)],
            ],
            'broker' => [
                ['Free', 0, null, ['active_listings' => 2, 'daily_contact_views' => 2, 'contact_visibility' => 'on_click']],
                ['Basic', 999, 30, ['active_listings' => 10, 'photo_limit' => 15, 'daily_property_views' => 100, 'daily_interest' => 20, 'daily_enquiries' => 20, 'daily_contact_views' => 20, 'contact_visibility' => 'on_click', 'lead_management' => true, 'saved_properties' => 100]],
                ['Professional', 2499, 90, ['active_listings' => 40, 'photo_limit' => 30, 'video_upload' => true, 'featured_listing' => true, 'daily_property_views' => 300, 'daily_interest' => 60, 'daily_enquiries' => 60, 'daily_contact_views' => 60, 'contact_visibility' => 'on_click', 'lead_management' => true, 'broker_crm' => true, 'map_search' => true, 'requirement_posting' => true, 'saved_properties' => 300, 'search_priority' => 1]],
                ['Agency', 5999, 365, ['active_listings' => null, 'photo_limit' => 40, 'video_upload' => true, 'featured_listing' => true, 'daily_property_views' => null, 'daily_interest' => null, 'daily_enquiries' => null, 'daily_contact_views' => 150, 'contact_visibility' => 'on_click', 'lead_management' => true, 'broker_crm' => true, 'map_search' => true, 'requirement_posting' => true, 'saved_properties' => null, 'search_priority' => 3]],
            ],
        ];
    }
}
