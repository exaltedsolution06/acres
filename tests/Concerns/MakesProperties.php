<?php

namespace Tests\Concerns;

use App\Models\Admin;
use App\Models\Location;
use App\Models\Package;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\User;
use App\Services\PropertyMedia;
use App\Services\RoleService;
use App\Services\SubscriptionService;
use Illuminate\Http\UploadedFile;

trait MakesProperties
{
    protected function marketUser(array $roles = ['owner'], array $attrs = []): User
    {
        $user = User::factory()->create($attrs + ['mobile' => '9'.random_int(100000000, 999999999)]);

        foreach ($roles as $role) {
            app(RoleService::class)->assign($user, $role);
        }

        return $user->fresh();
    }

    protected function adminUser(string $role = 'super_admin'): Admin
    {
        return Admin::create(['name' => 'Admin', 'email' => $role.'@example.com', 'password' => 'secret-pass-123', 'role' => $role, 'is_active' => true]);
    }

    protected function subscribeTo(User $user, string $packageSlug): void
    {
        app(SubscriptionService::class)->subscribe($user, Package::where('slug', $packageSlug)->firstOrFail(), null, 'admin');
    }

    protected function addPhoto(Property $property, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            app(PropertyMedia::class)->addImage($property->fresh(), UploadedFile::fake()->image('p.jpg', 400, 300));
        }
    }

    protected function draft(User $user, array $attrs = []): Property
    {
        return Property::factory()->state($attrs + ['user_id' => $user->id, 'status' => Property::DRAFT, 'published_at' => null, 'expires_at' => null])->create();
    }

    protected function published(array $attrs = []): Property
    {
        return Property::factory()->create($attrs);
    }

    protected function locality(string $seoSlug): Location
    {
        return Location::where('seo_slug', $seoSlug)->firstOrFail();
    }

    protected function formPayload(array $overrides = []): array
    {
        $locality = $this->locality('salt-lake-kolkata');

        return array_merge([
            'posted_by' => 'owner', 'purpose' => 'sell',
            'category_id' => PropertyCategory::where('slug', 'apartment-flat')->value('id'),
            'title' => 'Bright 2 BHK apartment in Salt Lake',
            'description' => 'A well maintained apartment close to the metro with good light and ventilation.',
            'availability' => 'ready_to_move', 'price' => 6500000, 'bhk' => 2, 'carpet_area' => 900,
            'floor_number' => 3, 'total_floors' => 8,
            'city_id' => $locality->parent_id, 'locality_id' => $locality->id,
            'pin_code' => '700091', 'address' => 'Plot 5, Sector 2, Salt Lake',
        ], $overrides);
    }

    /** @return string[] public ids from the paginated results of the last response */
    protected function resultIds($response): array
    {
        return $response->viewData('results')->pluck('public_id')->all();
    }
}
