<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class ListingLifecycleTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('uploads');
        config(['property.min_photos' => 1]);
    }

    // ---------- creating ----------

    public function test_users_need_the_owner_or_broker_role_to_post_and_can_add_it_themselves(): void
    {
        $tenant = $this->marketUser(['tenant']);

        $this->actingAs($tenant)->post('/listings', $this->formPayload())->assertForbidden();

        $this->post('/role/add', ['role' => 'owner'])->assertSessionHasNoErrors();
        $this->post('/listings', $this->formPayload())->assertSessionHasNoErrors();
        $this->assertSame(1, Property::count());
    }

    public function test_a_draft_gets_a_public_id_slug_and_list_price(): void
    {
        $owner = $this->marketUser();

        $this->actingAs($owner)->post('/listings', $this->formPayload())->assertRedirect();

        $p = Property::firstOrFail();
        $this->assertSame('draft', $p->status);
        $this->assertSame(8, strlen($p->public_id));
        $this->assertStringEndsWith('-'.$p->public_id, $p->slug);
        $this->assertSame(6500000, $p->list_price);
        $this->assertSame(7222, $p->price_per_sqft);
        $this->assertNotNull($p->fingerprint);
    }

    public function test_pricing_rules_follow_the_purpose(): void
    {
        $this->actingAs($this->marketUser());

        $this->post('/listings', $this->formPayload(['price' => null]))->assertSessionHasErrors('price');
        $this->post('/listings', $this->formPayload(['purpose' => 'rent', 'price' => null, 'monthly_rent' => null]))->assertSessionHasErrors('monthly_rent');

        $this->post('/listings', $this->formPayload(['purpose' => 'rent', 'price' => 999, 'monthly_rent' => 18000]))->assertSessionHasNoErrors();
        $rent = Property::firstOrFail();
        $this->assertNull($rent->price);
        $this->assertSame(18000, $rent->list_price);
    }

    public function test_bhk_is_required_only_for_categories_that_use_it_and_the_locality_must_match_the_city(): void
    {
        $this->actingAs($this->marketUser());
        $plot = \App\Models\PropertyCategory::where('slug', 'residential-plot')->value('id');
        $andheri = $this->locality('andheri-mumbai');

        $this->post('/listings', $this->formPayload(['bhk' => null]))->assertSessionHasErrors('bhk');
        $this->post('/listings', $this->formPayload(['category_id' => $plot, 'bhk' => null]))->assertSessionHasNoErrors();
        $this->post('/listings', $this->formPayload(['locality_id' => $andheri->id]))->assertSessionHasErrors('locality_id');
    }

    public function test_only_the_owner_account_can_manage_a_listing(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner);
        $other = $this->marketUser(['owner']);

        $this->actingAs($other)->get("/listings/{$draft->public_id}/edit")->assertForbidden();
        $this->put("/listings/{$draft->public_id}", $this->formPayload())->assertForbidden();
        $this->post("/listings/{$draft->public_id}/submit")->assertForbidden();
        $this->actingAs($owner)->get("/listings/{$draft->public_id}/edit")->assertOk();
    }

    // ---------- media ----------

    public function test_photos_are_resized_given_random_names_and_limited_by_the_plan(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner);
        $files = fn (int $n) => array_map(fn ($i) => UploadedFile::fake()->image("holiday photo {$i}.jpg", 800, 600), range(1, $n));

        $this->actingAs($owner)->post("/listings/{$draft->public_id}/images", ['type' => 'photo', 'images' => $files(5)])->assertSessionHasNoErrors();

        $images = PropertyImage::where('property_id', $draft->id)->get();
        $this->assertCount(5, $images);
        $this->assertSame(1, $images->where('is_cover', true)->count());
        $this->assertStringNotContainsString('holiday', $images->first()->path);
        Storage::disk('uploads')->assertExists($images->first()->path);
        if (function_exists('imagejpeg')) {
            Storage::disk('uploads')->assertExists($images->first()->thumb_path);
        }

        // Free owner plan allows 5 photos.
        $this->post("/listings/{$draft->public_id}/images", ['type' => 'photo', 'images' => $files(1)])->assertSessionHasErrors('limit');
        $this->assertCount(5, PropertyImage::where('property_id', $draft->id)->get());
    }

    public function test_non_images_are_rejected(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner);

        $this->actingAs($owner)->post("/listings/{$draft->public_id}/images", [
            'type' => 'photo', 'images' => [UploadedFile::fake()->create('shell.php', 5, 'application/x-php')],
        ])->assertSessionHasErrors('images.0');
    }

    public function test_removing_the_cover_promotes_another_photo(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner);
        $this->addPhoto($draft, 2);

        $cover = PropertyImage::where('property_id', $draft->id)->where('is_cover', true)->firstOrFail();
        $this->actingAs($owner)->delete("/listings/{$draft->public_id}/images/{$cover->id}")->assertRedirect();

        $this->assertSame(1, PropertyImage::where('property_id', $draft->id)->count());
        $this->assertTrue(PropertyImage::where('property_id', $draft->id)->first()->is_cover);
    }

    public function test_video_upload_needs_a_plan_that_includes_it(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner);
        $video = fn () => UploadedFile::fake()->create('tour.mp4', 500, 'video/mp4');

        $this->actingAs($owner)->post("/listings/{$draft->public_id}/video", ['video' => $video()])->assertSessionHasErrors('feature');

        $this->subscribeTo($owner, 'owner-basic');
        $this->post("/listings/{$draft->public_id}/video", ['video' => $video()])->assertSessionHasNoErrors();
        $this->assertNotNull($draft->fresh()->video);
    }

    // ---------- submit, moderate, publish ----------

    public function test_incomplete_listings_cannot_be_submitted(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner, ['address' => null]);

        $this->actingAs($owner)->post("/listings/{$draft->public_id}/submit")->assertSessionHasErrors('submit');
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_submit_then_admin_approval_publishes_with_validity_and_search_priority(): void
    {
        $owner = $this->marketUser();
        $this->subscribeTo($owner, 'owner-premium'); // search_priority = 1
        $draft = $this->draft($owner);
        $this->addPhoto($draft);

        $this->actingAs($owner)->post("/listings/{$draft->public_id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $draft->fresh()->status);

        $this->actingAs($this->adminUser(), 'admin')->post("/admin/properties/{$draft->public_id}/approve")->assertRedirect();

        $p = $draft->fresh();
        $this->assertSame('published', $p->status);
        $this->assertSame(1, $p->priority);
        $this->assertTrue($p->expires_at->between(now()->addDays(59), now()->addDays(61)));
        $this->assertDatabaseHas('property_status_histories', ['property_id' => $p->id, 'to_status' => 'published', 'actor_type' => 'admin']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'property.approved', 'subject_id' => $p->id]);
    }

    public function test_rejection_needs_a_reason_which_the_owner_sees_and_can_fix_and_resubmit(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner);
        $this->addPhoto($draft);
        $this->actingAs($owner)->post("/listings/{$draft->public_id}/submit");

        $admin = $this->adminUser('staff');
        $this->actingAs($admin, 'admin')->post("/admin/properties/{$draft->public_id}/reject", ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->post("/admin/properties/{$draft->public_id}/reject", ['reason' => 'Photos do not show the property.'])->assertRedirect();

        $this->actingAs($owner)->get('/listings')->assertSee('Photos do not show the property.');
        $this->post("/listings/{$draft->public_id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $draft->fresh()->status);
    }

    public function test_the_plan_limits_how_many_listings_can_be_live_at_once(): void
    {
        $owner = $this->marketUser(); // free plan: 1 active listing
        $first = $this->draft($owner);
        $second = $this->draft($owner);
        $this->addPhoto($first);
        $this->addPhoto($second);

        $this->actingAs($owner)->post("/listings/{$first->public_id}/submit")->assertSessionHasNoErrors();
        $this->post("/listings/{$second->public_id}/submit")->assertSessionHasErrors('limit');
        $this->assertSame('draft', $second->fresh()->status);

        $this->post("/listings/{$first->public_id}/archive");
        $this->post("/listings/{$second->public_id}/submit")->assertSessionHasNoErrors();
    }

    public function test_duplicates_are_blocked_for_the_same_user_and_flagged_across_users(): void
    {
        $owner = $this->marketUser();
        $this->subscribeTo($owner, 'owner-business');
        $first = $this->draft($owner, ['address' => '12 Park Lane, Salt Lake']);
        $same = $this->draft($owner, ['address' => '12 Park Lane, Salt Lake']);
        $this->addPhoto($first);
        $this->addPhoto($same);

        $this->actingAs($owner)->post("/listings/{$first->public_id}/submit")->assertSessionHasNoErrors();
        $this->post("/listings/{$same->public_id}/submit")->assertSessionHasErrors('submit');

        $broker = $this->marketUser(['owner']);
        $this->subscribeTo($broker, 'owner-business');
        $clone = $this->draft($broker, ['address' => '12 PARK LANE salt-lake']);
        $this->addPhoto($clone);

        $this->actingAs($broker)->post("/listings/{$clone->public_id}/submit")->assertSessionHasNoErrors();
        $this->assertSame($first->id, $clone->fresh()->duplicate_of_id);

        $this->actingAs($this->adminUser(), 'admin')->get('/admin/properties?duplicates=1')->assertSee($clone->title);
    }

    public function test_reapproval_after_edit_is_configurable(): void
    {
        $owner = $this->marketUser();
        $live = $this->published(['user_id' => $owner->id]);

        $this->actingAs($owner)->put("/listings/{$live->public_id}", $this->formPayload(['title' => 'Updated listing title for the flat']))->assertSessionHasNoErrors();
        $this->assertSame('published', $live->fresh()->status);

        config(['property.reapprove_on_edit' => true]);
        $this->put("/listings/{$live->public_id}", $this->formPayload(['title' => 'Another updated title for the flat']))->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $live->fresh()->status);
    }

    public function test_changing_the_title_changes_the_slug_and_the_old_url_redirects(): void
    {
        $owner = $this->marketUser();
        $live = $this->published(['user_id' => $owner->id]);
        $oldSlug = $live->slug;

        $this->actingAs($owner)->put("/listings/{$live->public_id}", $this->formPayload(['title' => 'Brand new title for this lovely flat']))->assertSessionHasNoErrors();

        $new = $live->fresh()->slug;
        $this->assertNotSame($oldSlug, $new);
        $this->assertStringContainsString('brand-new-title', $new);
        $this->get('/property/'.$oldSlug)->assertStatus(301)->assertRedirect(route('property.show', $new));
    }

    // ---------- status changes ----------

    public function test_owner_status_actions_follow_the_allowed_transitions(): void
    {
        $owner = $this->marketUser();
        $sale = $this->published(['user_id' => $owner->id]);
        $rental = $this->published(['user_id' => $owner->id, 'purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000]);

        $this->actingAs($owner);
        $this->post("/listings/{$sale->public_id}/pause");
        $this->assertSame('paused', $sale->fresh()->status);
        $this->post("/listings/{$sale->public_id}/resume");
        $this->assertSame('published', $sale->fresh()->status);

        $this->post("/listings/{$sale->public_id}/rented")->assertSessionHasErrors('status');
        $this->post("/listings/{$rental->public_id}/sold")->assertSessionHasErrors('status');

        $this->post("/listings/{$sale->public_id}/sold");
        $this->assertSame('sold', $sale->fresh()->status);
        $this->post("/listings/{$sale->public_id}/pause")->assertSessionHasErrors('status'); // sold is final
        $this->post("/listings/{$rental->public_id}/rented");
        $this->assertSame('rented', $rental->fresh()->status);
    }

    public function test_only_drafts_can_be_deleted(): void
    {
        $owner = $this->marketUser();
        $draft = $this->draft($owner);
        $live = $this->published(['user_id' => $owner->id]);

        $this->actingAs($owner)->delete("/listings/{$live->public_id}")->assertSessionHasErrors('status');
        $this->delete("/listings/{$draft->public_id}")->assertRedirect();
        $this->assertSoftDeleted($draft);
    }

    public function test_renewal_window_and_renewing_after_expiry(): void
    {
        $owner = $this->marketUser();
        $live = $this->published(['user_id' => $owner->id, 'expires_at' => now()->addDays(30)]);

        $this->actingAs($owner)->post("/listings/{$live->public_id}/renew")->assertSessionHasErrors('status');

        $live->update(['expires_at' => now()->addDays(10)]);
        $this->post("/listings/{$live->public_id}/renew")->assertSessionHasNoErrors();
        $this->assertTrue($live->fresh()->expires_at->between(now()->addDays(69), now()->addDays(71)));

        $old = $this->published(['user_id' => $owner->id, 'status' => 'expired', 'expires_at' => now()->subDays(3)]);
        $live->update(['status' => 'archived']); // free the single slot of the free plan
        $this->post("/listings/{$old->public_id}/renew")->assertSessionHasNoErrors();
        $this->assertSame('published', $old->fresh()->status);
        $this->assertTrue($old->fresh()->expires_at->between(now()->addDays(59), now()->addDays(61)));
    }

    public function test_the_scheduler_command_expires_listings_past_their_date(): void
    {
        $due = $this->published(['expires_at' => now()->subHour()]);
        $fine = $this->published(['expires_at' => now()->addDays(5)]);

        Artisan::call('marketplace:expire-listings');

        $this->assertSame('expired', $due->fresh()->status);
        $this->assertSame('published', $fine->fresh()->status);
        $this->assertDatabaseHas('property_status_histories', ['property_id' => $due->id, 'to_status' => 'expired', 'actor_type' => 'system']);
    }

    public function test_a_package_edited_in_the_admin_changes_listing_limits(): void
    {
        $owner = $this->marketUser();
        $first = $this->draft($owner);
        $second = $this->draft($owner);
        $this->addPhoto($first);
        $this->addPhoto($second);

        $free = Package::where('slug', 'owner-free')->first();
        PackageFeatureValue::where('package_id', $free->id)->where('feature_id', PackageFeature::where('key', 'active_listings')->value('id'))->update(['value' => '2']);

        $this->actingAs($owner)->post("/listings/{$first->public_id}/submit")->assertSessionHasNoErrors();
        $this->post("/listings/{$second->public_id}/submit")->assertSessionHasNoErrors();
    }
}
