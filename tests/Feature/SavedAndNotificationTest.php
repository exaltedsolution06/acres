<?php

namespace Tests\Feature;

use App\Models\NotificationSetting;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\EventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class SavedAndNotificationTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setFeature(string $packageSlug, string $key, string $value): void
    {
        PackageFeatureValue::updateOrCreate(
            ['package_id' => Package::where('slug', $packageSlug)->value('id'), 'feature_id' => PackageFeature::where('key', $key)->value('id')],
            ['value' => $value, 'is_unlimited' => false],
        );
    }

    // ---------- saved properties ----------

    public function test_saving_and_removing_properties(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $p = $this->published();

        $this->actingAs($tenant)->post(route('property.save', $p))->assertSessionHasNoErrors();
        $this->post(route('property.save', $p));
        $this->assertSame(1, SavedProperty::count());

        $this->get('/saved')->assertOk()->assertSee($p->title);
        $this->get(route('property.show', $p->slug))->assertSee('Remove');

        $this->delete(route('property.unsave', $p));
        $this->assertSame(0, SavedProperty::count());
    }

    public function test_the_saved_properties_limit_comes_from_the_plan(): void
    {
        $tenant = $this->marketUser(['tenant']);
        [$a, $b] = [$this->published(), $this->published()];

        $this->setFeature('tenant-free', 'saved_properties', '1');
        $this->actingAs($tenant)->post(route('property.save', $a));
        $this->post(route('property.save', $b))->assertSessionHasErrors('limit');

        $this->setFeature('tenant-free', 'saved_properties', '0');
        $this->post(route('property.save', $b))->assertSessionHasErrors('feature');
        $this->assertSame(1, SavedProperty::count());
    }

    // ---------- saved searches and alerts ----------

    public function test_saved_searches_keep_only_known_filters_and_are_capped_and_private(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $filters = json_encode(['purpose' => 'sell', 'bhk' => [2], 'junk' => 'x', 'q' => '']);

        $this->actingAs($tenant)->post('/saved-searches', ['name' => '2 BHK for sale', 'filters' => $filters])->assertSessionHasNoErrors();
        $saved = SavedSearch::firstOrFail();
        $this->assertSame(['purpose' => 'sell', 'bhk' => [2]], $saved->filters);

        config(['engagement.max_saved_searches' => 1]);
        $this->post('/saved-searches', ['name' => 'Another', 'filters' => $filters])->assertSessionHasErrors('name');

        $this->get('/saved-searches')->assertOk()->assertSee('2 BHK for sale');
        $this->actingAs($this->marketUser(['tenant']))->delete(route('saved.searches.destroy', $saved))->assertForbidden();
        $this->actingAs($tenant)->delete(route('saved.searches.destroy', $saved));
        $this->assertSame(0, SavedSearch::count());
    }

    public function test_alerts_cover_listings_published_since_the_last_check_and_only_once(): void
    {
        Notification::fake();
        $tenant = $this->marketUser(['tenant']);
        Carbon::setTestNow('2026-05-01 10:00:00');
        $this->actingAs($tenant)->post('/saved-searches', ['name' => 'Flats for sale', 'filters' => json_encode(['purpose' => 'sell'])]);

        Carbon::setTestNow('2026-05-01 12:00:00');
        $this->published(['purpose' => 'sell', 'published_at' => now()]);                                                  // matches
        $this->published(['purpose' => 'rent', 'price' => null, 'monthly_rent' => 9000, 'published_at' => now()]);        // other purpose
        $this->published(['purpose' => 'sell', 'published_at' => now()->subDay()]);                                        // published before the search

        Artisan::call('marketplace:send-search-alerts');
        Notification::assertSentToTimes($tenant, EventNotification::class, 1);
        Notification::assertSentTo($tenant, EventNotification::class, fn ($n) => str_contains($n->title, '1 new listing'));

        Artisan::call('marketplace:send-search-alerts');
        Notification::assertSentToTimes($tenant, EventNotification::class, 1);
    }

    // ---------- in-app notifications ----------

    public function test_notifications_page_opens_links_and_marks_them_read(): void
    {
        $user = $this->marketUser(['tenant']);
        $user->notify(new EventNotification(['database'], 'Hello', 'Body text', route('enquiries.index'), 'x'));
        $user->notify(new EventNotification(['database'], 'Sneaky', 'Body', 'https://evil.example/phish', 'x'));
        $this->assertSame(2, $user->unreadNotifications()->count());

        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Hello')->assertSee('Sneaky');

        $good = $user->notifications()->where('data', 'like', '%Hello%')->first();
        $this->get(route('notifications.open', $good->id))->assertRedirect(route('enquiries.index'));
        $this->assertNotNull($good->fresh()->read_at);

        $bad = $user->notifications()->where('data', 'like', '%Sneaky%')->first();
        $this->get(route('notifications.open', $bad->id))->assertRedirect(route('notifications.index')); // off-site links are never followed

        $this->post('/notifications/read-all');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_users_cannot_open_each_others_notifications(): void
    {
        $owner = $this->marketUser(['tenant']);
        $owner->notify(new EventNotification(['database'], 'Private', 'Body', route('enquiries.index'), 'x'));

        $this->actingAs($this->marketUser(['tenant']))->get(route('notifications.open', $owner->notifications()->first()->id))->assertNotFound();
    }

    public function test_a_real_enquiry_lands_in_the_posters_inbox_and_the_unread_badge_shows(): void
    {
        $owner = User::factory()->create();
        $p = $this->published(['user_id' => $owner->id]);
        $tenant = $this->marketUser(['tenant']);

        $this->actingAs($tenant)->post(route('property.interest', $p));

        $this->assertSame(1, $owner->unreadNotifications()->count());
        $this->actingAs($owner)->get('/notifications')->assertSee('New enquiry on your listing');
    }

    // ---------- admin switches ----------

    public function test_admin_can_switch_events_and_channels_off(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $p = $this->published(['user_id' => $owner->id]);
        [$t1, $t2, $t3] = [$this->marketUser(['tenant']), $this->marketUser(['tenant']), $this->marketUser(['tenant'])];

        $this->actingAs($t1)->post(route('property.interest', $p));
        Notification::assertSentTo($owner, EventNotification::class, fn ($n, $channels) => $channels === ['database', 'mail']);

        NotificationSetting::where('event', 'enquiry.received')->update(['email' => false]);
        $this->actingAs($t2)->post(route('property.interest', $p));
        Notification::assertSentTo($owner, EventNotification::class, fn ($n, $channels) => $channels === ['database']);

        NotificationSetting::where('event', 'enquiry.received')->update(['in_app' => false]);
        $this->actingAs($t3)->post(route('property.interest', $p));
        Notification::assertSentToTimes($owner, EventNotification::class, 2); // the third sent nothing
    }

    public function test_notification_settings_screen(): void
    {
        $this->actingAs($this->adminUser('staff'), 'admin')->get('/admin/notification-settings')->assertForbidden();

        $this->actingAs($this->adminUser(), 'admin')->get('/admin/notification-settings')->assertOk()->assertSee('enquiry.received');
        $this->put('/admin/notification-settings', ['in_app' => ['enquiry.received' => '1']])->assertSessionHasNoErrors();

        $s = NotificationSetting::where('event', 'enquiry.received')->first();
        $this->assertTrue($s->in_app);
        $this->assertFalse($s->email);
        $this->assertFalse(NotificationSetting::where('event', 'search.alert')->first()->in_app);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'notifications.settings_updated']);
    }

    public function test_listing_review_outcomes_notify_the_poster(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $one = $this->published(['user_id' => $owner->id, 'status' => 'pending_approval', 'published_at' => null, 'expires_at' => null]);
        $two = $this->published(['user_id' => $owner->id, 'status' => 'pending_approval', 'published_at' => null, 'expires_at' => null]);

        $this->actingAs($this->adminUser(), 'admin');
        $this->post("/admin/properties/{$one->public_id}/approve");
        $this->post("/admin/properties/{$two->public_id}/reject", ['reason' => 'The photos are of another building.']);

        Notification::assertSentTo($owner, EventNotification::class, fn ($n) => $n->title === 'Your listing is live');
        Notification::assertSentTo($owner, EventNotification::class, fn ($n) => $n->title === 'Your listing needs changes' && str_contains($n->body, 'another building'));
    }
}
