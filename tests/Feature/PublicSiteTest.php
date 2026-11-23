<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\Property;
use App\Models\User;
use App\Services\ImageProcessor;
use App\Services\UsageTrackingService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('uploads');
    }

    // ---------- search ----------

    public function test_filters_narrow_the_results(): void
    {
        $newTown = $this->locality('new-town-kolkata');
        $a = $this->published(['title' => 'Alpha flat in Salt Lake', 'price' => 6500000, 'bhk' => 2, 'furnishing' => 'semi_furnished', 'carpet_area' => 900, 'property_age' => 2]);
        $b = $this->published(['title' => 'Bravo rental in Salt Lake', 'purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000, 'bhk' => 3, 'furnishing' => 'furnished', 'carpet_area' => 1300]);
        $c = $this->published(['title' => 'Charlie house in New Town', 'price' => 12000000, 'bhk' => 3, 'furnishing' => 'unfurnished', 'carpet_area' => 1800, 'locality_id' => $newTown->id, 'property_age' => 10]);
        $a->amenities()->attach(Amenity::where('slug', 'gym')->value('id'));

        $ids = fn (string $qs) => $this->resultIds($this->get('/search?'.$qs));

        $this->assertEqualsCanonicalizing([$a->public_id, $b->public_id, $c->public_id], $ids(''));
        $this->assertEqualsCanonicalizing([$a->public_id, $c->public_id], $ids('purpose=sell'));
        $this->assertSame([$b->public_id], $ids('purpose=rent'));
        $this->assertSame([$c->public_id], $ids('locality_id='.$newTown->id));
        $this->assertSame([$a->public_id], $ids('purpose=sell&price_max=8000000'));
        $this->assertSame([$c->public_id], $ids('purpose=sell&price_min=8000000'));
        $this->assertEqualsCanonicalizing([$b->public_id, $c->public_id], $ids('bhk[]=3'));
        $this->assertSame([$c->public_id], $ids('area_min=1500'));
        $this->assertSame([$b->public_id], $ids('furnishing[]=furnished'));
        $this->assertSame([$a->public_id], $ids('age_max=5&purpose=sell'));
        $this->assertSame([$a->public_id], $ids('amenities[]='.Amenity::where('slug', 'gym')->value('id')));
        $this->assertSame([$c->public_id], $ids('q=Charlie'));
        $this->assertEqualsCanonicalizing([$a->public_id, $b->public_id], $ids('pin_code=700091&q=Salt'));
    }

    public function test_only_live_listings_from_active_owners_are_searchable(): void
    {
        $live = $this->published(['title' => 'Visible listing']);
        foreach (['draft', 'pending_approval', 'rejected', 'paused', 'sold', 'rented', 'expired', 'archived'] as $status) {
            $this->published(['status' => $status]);
        }
        $this->published(['expires_at' => now()->subMinute()]);
        $this->published(['user_id' => User::factory()->create(['status' => 'suspended'])->id]);

        $this->assertSame([$live->public_id], $this->resultIds($this->get('/search')));
    }

    public function test_sorting_and_search_priority(): void
    {
        $cheap = $this->published(['price' => 3000000, 'published_at' => now()->subDays(3), 'views_count' => 5]);
        $mid = $this->published(['price' => 5000000, 'published_at' => now()->subDays(2), 'views_count' => 50]);
        $top = $this->published(['price' => 9000000, 'published_at' => now()->subDay(), 'views_count' => 1]);
        $boosted = $this->published(['price' => 7000000, 'published_at' => now()->subDays(10), 'priority' => 2]);

        $order = fn (string $sort) => $this->resultIds($this->get("/search?sort={$sort}"));

        $this->assertSame([$boosted->public_id, $top->public_id, $mid->public_id, $cheap->public_id], $order('recommended'));
        $this->assertSame([$top->public_id, $mid->public_id, $cheap->public_id, $boosted->public_id], $order('latest'));
        $this->assertSame([$cheap->public_id, $mid->public_id, $boosted->public_id, $top->public_id], $order('price_asc'));
        $this->assertSame([$top->public_id, $boosted->public_id, $mid->public_id, $cheap->public_id], $order('price_desc'));
        $this->assertSame($mid->public_id, $order('most_viewed')[0]);
    }

    // ---------- SEO pages ----------

    public function test_landing_pages_have_canonical_urls_and_thin_or_filtered_pages_are_noindex(): void
    {
        $this->published();

        $this->get('/buy-property-in-kolkata')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/buy-property-in-kolkata').'">', false)
            ->assertSee('Properties for Sale in Kolkata')
            ->assertDontSee('noindex');

        $this->get('/buy-property-in-salt-lake-kolkata')->assertOk()->assertSee('Salt Lake, Kolkata');
        $this->get('/buy-property-in-kolkata/apartment-flat')->assertOk()->assertSee('Apartment / Flat');

        $this->get('/buy-property-in-kolkata?bhk[]=2')->assertOk()
            ->assertSee('noindex,follow', false)
            ->assertSee('<link rel="canonical" href="'.url('/buy-property-in-kolkata').'">', false);

        $this->get('/rent-property-in-kolkata')->assertOk()->assertSee('noindex,follow', false); // no rentals: thin page
        $this->get('/search')->assertOk()->assertSee('noindex,follow', false);
    }

    public function test_unknown_landing_pages_return_404(): void
    {
        $this->get('/buy-property-in-atlantis')->assertNotFound();
        $this->get('/buy-property-in-kolkata/not-a-category')->assertNotFound();
        $this->get('/swap-property-in-kolkata')->assertNotFound();
    }

    public function test_property_page_is_server_rendered_with_seo_markup_and_no_private_data(): void
    {
        $owner = User::factory()->create(['mobile' => '9876501234']);
        $p = $this->published(['user_id' => $owner->id, 'title' => 'Sunny corner flat near the lake', 'address' => 'Flat 42B Hidden Lane', 'latitude' => 22.5812345, 'longitude' => 88.4123456]);
        $this->addPhoto($p);

        $html = $this->get(route('property.show', $p->slug))->assertOk()
            ->assertSee('Sunny corner flat near the lake')
            ->assertSee('<link rel="canonical" href="'.route('property.show', $p->slug).'">', false)
            ->assertSee('RealEstateListing', false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('"priceCurrency":"INR"', false)
            ->assertSee('og:title', false)
            ->assertDontSee('noindex')
            ->getContent();

        $this->assertStringNotContainsString('9876501234', $html);
        $this->assertStringNotContainsString('Hidden Lane', $html);
        $this->assertStringNotContainsString('22.58123', $html);
    }

    public function test_bad_slugs_and_unpublished_listings(): void
    {
        $live = $this->published();
        $owner = User::find($live->user_id);
        $draft = $this->draft($this->marketUser());
        $sold = $this->published(['status' => 'sold']);

        $this->get('/property/some-old-title-'.$live->public_id)->assertStatus(301)->assertRedirect(route('property.show', $live->slug));
        $this->get('/property/nothing-here-zzzzzzzz')->assertNotFound();
        $this->get(route('property.show', $draft->slug))->assertNotFound();
        $this->get(route('property.show', $sold->slug))->assertOk()->assertSee('no longer available')->assertSee('noindex,follow', false);

        $this->actingAs(User::find($draft->user_id))->get(route('property.show', $draft->slug))->assertOk()->assertSee('Preview');
    }

    public function test_sitemap_lists_live_pages_only_and_applies_thin_page_rules(): void
    {
        $live = $this->published();
        $draft = $this->draft($this->marketUser());

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();

        $this->assertStringContainsString(route('property.show', $live->slug), $xml);
        $this->assertStringNotContainsString($draft->public_id, $xml);
        $this->assertStringContainsString(url('/buy-property-in-kolkata'), $xml);
        $this->assertStringContainsString(url('/buy-property-in-salt-lake-kolkata'), $xml);
        $this->assertStringNotContainsString('/rent-property-in-kolkata', $xml);
        $this->assertStringNotContainsString('/buy-property-in-kolkata/apartment-flat', $xml); // fewer than 3 listings
    }

    public function test_category_landing_enters_the_sitemap_once_it_has_enough_listings(): void
    {
        $this->published();
        $this->published();
        $this->published();

        $this->assertStringContainsString('/buy-property-in-kolkata/apartment-flat', $this->get('/sitemap.xml')->getContent());
    }

    public function test_view_counter_counts_once_per_session(): void
    {
        $p = $this->published();

        $this->get(route('property.show', $p->slug));
        $this->get(route('property.show', $p->slug));

        $this->assertSame(1, $p->fresh()->views_count);
    }

    // ---------- daily view allowance ----------

    public function test_logged_in_users_consume_the_daily_view_allowance_and_guests_do_not(): void
    {
        $free = Package::where('slug', 'tenant-free')->first();
        PackageFeatureValue::where('package_id', $free->id)->where('feature_id', PackageFeature::where('key', 'daily_property_views')->value('id'))->update(['value' => '2']);

        $tenant = $this->marketUser(['tenant']);
        [$p1, $p2, $p3] = [$this->published(['description' => 'First secret description text for the listing.']), $this->published(['description' => 'Second secret description text for the listing.']), $this->published(['description' => 'Third secret description text for the listing.'])];

        $this->actingAs($tenant)->get(route('property.show', $p1->slug))->assertSee('First secret description');
        $this->get(route('property.show', $p2->slug))->assertSee('Second secret description');
        $this->get(route('property.show', $p3->slug))->assertSee('property view limit')->assertDontSee('Third secret description');
        $this->get(route('property.show', $p1->slug))->assertSee('First secret description'); // already viewed today: free
        $this->assertSame(2, app(UsageTrackingService::class)->usedToday($tenant, 'daily_property_views'));

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('property.show', $p3->slug))->assertSee('Third secret description');
    }

    public function test_guest_views_can_be_switched_off(): void
    {
        config(['property.guest_property_views' => false]);
        $p = $this->published();

        $this->get(route('property.show', $p->slug))->assertRedirect(route('login'));
    }

    // ---------- contact reveal ----------

    public function test_contact_numbers_follow_the_viewers_plan(): void
    {
        $owner = User::factory()->create(['mobile' => '9876501234']);
        $p = $this->published(['user_id' => $owner->id]);
        $tenant = $this->marketUser(['tenant']);

        $this->post(route('property.contact', $p))->assertRedirect(route('login')); // guests

        $this->actingAs($tenant)->post(route('property.contact', $p));
        $this->get(route('property.show', $p->slug))->assertSee('98XXXXXX34')->assertDontSee('9876501234'); // free plan: masked

        $this->subscribeTo($tenant, 'tenant-basic'); // on_click, 5 a day
        $this->post(route('property.contact', $p));
        $this->get(route('property.show', $p->slug))->assertSee('9876501234');
        $this->post(route('property.contact', $p));
        $this->get(route('property.show', $p->slug))->assertSee('9876501234');
        $this->assertSame(1, app(UsageTrackingService::class)->usedToday($tenant, 'daily_contact_views')); // same number: charged once
    }

    public function test_contact_reveal_stops_at_the_daily_limit(): void
    {
        $basic = Package::where('slug', 'tenant-basic')->first();
        PackageFeatureValue::where('package_id', $basic->id)->where('feature_id', PackageFeature::where('key', 'daily_contact_views')->value('id'))->update(['value' => '1']);

        $tenant = $this->marketUser(['tenant']);
        $this->subscribeTo($tenant, 'tenant-basic');
        $a = $this->published(['user_id' => User::factory()->create(['mobile' => '9000000011'])->id]);
        $b = $this->published(['user_id' => User::factory()->create(['mobile' => '9000000012'])->id]);

        $this->actingAs($tenant)->post(route('property.contact', $a));
        $this->get(route('property.show', $a->slug))->assertSee('9000000011');
        $this->post(route('property.contact', $b))->assertSessionHasErrors('contact');
    }

    // ---------- helpers ----------

    public function test_indian_price_formatting(): void
    {
        $this->assertSame('₹70 Lakh', Money::inr(7000000));
        $this->assertSame('₹1.25 Cr', Money::inr(12500000));
        $this->assertSame('₹45,000', Money::inr(45000));
        $this->assertSame('Price on request', Money::inr(null));
    }

    public function test_image_processor_resizes_strips_and_rejects_non_images(): void
    {
        $processor = new ImageProcessor;

        if (! $processor->available()) {
            $this->markTestSkipped('GD is not installed.');
        }

        $img = imagecreatetruecolor(3000, 2000);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $out = $processor->process($png);
        $this->assertSame(1600, getimagesizefromstring($out['main'])[0]);
        $this->assertSame(480, getimagesizefromstring($out['thumb'])[0]);

        $this->expectException(\RuntimeException::class);
        $processor->process("GIF89a<?php echo 'x'; ?>");
    }
}
