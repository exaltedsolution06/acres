<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $locality = Location::where('seo_slug', 'salt-lake-kolkata')->first();

        return [
            'user_id' => User::factory(),
            'posted_by' => 'owner',
            'category_id' => PropertyCategory::where('slug', 'apartment-flat')->value('id'),
            'purpose' => 'sell',
            'title' => 'Spacious 2 BHK apartment near the metro '.$this->faker->unique()->numberBetween(1000, 99999),
            'description' => 'A bright, well ventilated apartment with good connectivity and a quiet neighbourhood.',
            'availability' => 'ready_to_move',
            'price' => 6500000,
            'bhk' => 2, 'bedrooms' => 2, 'bathrooms' => 2,
            'carpet_area' => 900,
            'floor_number' => 3, 'total_floors' => 8,
            'furnishing' => 'semi_furnished',
            'city_id' => $locality?->parent_id,
            'locality_id' => $locality?->id,
            'pin_code' => '700091',
            'address' => $this->faker->unique()->streetAddress().' Salt Lake',
            'status' => Property::PUBLISHED,
            'published_at' => now(),
            'expires_at' => now()->addDays(60),
        ];
    }

    public function rent(int $rent = 15000): static
    {
        return $this->state(fn () => ['purpose' => 'rent', 'price' => null, 'monthly_rent' => $rent]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
