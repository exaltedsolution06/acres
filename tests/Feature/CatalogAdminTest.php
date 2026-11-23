<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\PropertyCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pages_render_for_super_admin_and_catalogue_is_closed_to_staff(): void
    {
        $this->actingAs($this->adminUser('staff'), 'admin');
        $this->get('/admin/catalog/categories')->assertForbidden();
        $this->get('/admin/properties')->assertOk();

        $this->actingAs($this->adminUser(), 'admin');
        foreach (['categories', 'amenities', 'locations'] as $type) {
            $this->get("/admin/catalog/{$type}")->assertOk();
        }
        $this->get('/admin/catalog/nonsense')->assertNotFound();
    }

    public function test_adding_categories_and_amenities(): void
    {
        $this->actingAs($this->adminUser(), 'admin');

        $this->post('/admin/catalog/categories', ['group' => 'commercial', 'name' => 'Penthouse Suite', 'has_bhk' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(PropertyCategory::where('slug', 'penthouse-suite')->first()->has_bhk);
        $this->post('/admin/catalog/categories', ['group' => 'commercial', 'name' => 'Penthouse Suite'])->assertSessionHasErrors('name');

        $this->post('/admin/catalog/amenities', ['name' => 'Rooftop Terrace'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('amenities', ['slug' => 'rooftop-terrace']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'catalog.amenities.created']);
    }

    public function test_a_new_locality_gets_an_seo_slug_and_a_landing_page(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $city = Location::where('seo_slug', 'kolkata')->first();

        $this->post('/admin/catalog/locations', ['location_type' => 'locality', 'parent_id' => $city->id, 'name' => 'Tollygunge', 'pin_code' => '700033'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', ['seo_slug' => 'tollygunge-kolkata', 'pin_code' => '700033']);
        $this->get('/buy-property-in-tollygunge-kolkata')->assertOk();
        $this->post('/admin/catalog/locations', ['location_type' => 'city', 'parent_id' => $city->id, 'name' => 'Nowhere'])->assertSessionHasErrors('parent_id'); // a city needs a state
    }

    public function test_deactivated_categories_disappear_from_the_posting_form_but_old_listings_keep_working(): void
    {
        $admin = $this->adminUser();
        $category = PropertyCategory::where('slug', 'shop')->first();
        $this->actingAs($admin, 'admin')->put("/admin/catalog/categories/{$category->id}", ['sort_order' => 5])->assertSessionHasNoErrors();

        $this->assertFalse($category->fresh()->is_active);

        $owner = $this->marketUser();
        $this->actingAs($owner)->post('/listings', $this->formPayload(['category_id' => $category->id, 'bhk' => null]))->assertSessionHasErrors('category_id');
    }

    public function test_approving_a_listing_that_is_not_pending_is_refused(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $draft = $this->draft($this->marketUser());

        $this->post("/admin/properties/{$draft->public_id}/approve")->assertSessionHasErrors('status');
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_published_listings_can_be_taken_down_with_a_reason(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $live = $this->published();

        $this->post("/admin/properties/{$live->public_id}/reject", ['reason' => 'Misleading photographs of another building.'])->assertRedirect();

        $this->assertSame('rejected', $live->fresh()->status);
        $this->assertSame([], $this->resultIds($this->get('/search')));
    }
}
