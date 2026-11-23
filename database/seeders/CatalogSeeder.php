<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Location;
use App\Models\PropertyCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Starter catalogue. Admins manage all of this from the admin panel afterwards. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['residential', 'Apartment / Flat', true], ['residential', 'Independent House', true], ['residential', 'Villa', true],
            ['residential', 'Duplex', true], ['residential', 'Builder Floor', true], ['residential', 'Studio Apartment', false],
            ['residential', 'Residential Plot', false], ['residential', 'Farmhouse', false],
            ['commercial', 'Office Space', false], ['commercial', 'Shop', false], ['commercial', 'Showroom', false],
            ['commercial', 'Warehouse', false], ['commercial', 'Industrial Property', false],
            ['commercial', 'Commercial Land', false], ['commercial', 'Co-working Space', false],
        ];

        foreach ($categories as $i => [$group, $name, $bhk]) {
            PropertyCategory::updateOrCreate(['slug' => Str::slug($name)], [
                'group' => $group, 'name' => $name, 'has_bhk' => $bhk, 'is_active' => true, 'sort_order' => $i,
            ]);
        }

        foreach (['Lift', 'Security', 'Swimming Pool', 'Gym', 'Clubhouse', 'Garden', 'Power Backup', 'Parking', 'CCTV', "Children's Play Area"] as $name) {
            Amenity::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
        }

        $tree = [
            'West Bengal' => ['Kolkata' => ['Salt Lake' => '700091', 'New Town' => '700156', 'Ballygunge' => '700019', 'Behala' => '700034', 'Garia' => '700084']],
            'Maharashtra' => ['Mumbai' => ['Andheri' => '400053', 'Powai' => '400076', 'Bandra' => '400050']],
            'Karnataka' => ['Bengaluru' => ['Whitefield' => '560066', 'Koramangala' => '560034', 'Indiranagar' => '560038']],
        ];

        foreach ($tree as $state => $cities) {
            $stateRow = Location::firstOrCreate(['type' => 'state', 'name' => $state], ['is_active' => true]);

            foreach ($cities as $city => $localities) {
                $cityRow = Location::firstOrCreate(['type' => 'city', 'name' => $city, 'parent_id' => $stateRow->id], ['is_active' => true]);

                foreach ($localities as $locality => $pin) {
                    Location::firstOrCreate(
                        ['type' => 'locality', 'name' => $locality, 'parent_id' => $cityRow->id],
                        ['pin_code' => $pin, 'is_active' => true],
                    );
                }
            }
        }
    }
}
