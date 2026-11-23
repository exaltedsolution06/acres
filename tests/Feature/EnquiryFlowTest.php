<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\SiteVisit;
use App\Models\User;
use App\Notifications\EventNotification;
use App\Services\UsageTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class EnquiryFlowTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Notification::fake();
    }

    private function setFeature(string $packageSlug, string $key, string $value): void
    {
        PackageFeatureValue::updateOrCreate(
            ['package_id' => Package::where('slug', $packageSlug)->value('id'), 'feature_id' => PackageFeature::where('key', $key)->value('id')],
            ['value' => $value, 'is_unlimited' => false],
        );
    }

    private function lead(User $owner, User $seeker, array $attrs = []): Enquiry
    {
        $property = $this->published(['user_id' => $owner->id]);

        return Enquiry::create($attrs + [
            'property_id' => $property->id, 'from_user_id' => $seeker->id, 'to_user_id' => $owner->id,
            'interest_at' => now(), 'last_activity_at' => now(),
        ]);
    }

    private function tomorrow(): string
    {
        return now(config('marketplace.timezone'))->addDay()->format('Y-m-d\TH:i');
    }

    // ---------- interest and enquiries ----------

    public function test_showing_interest_creates_a_lead_notifies_the_poster_and_is_charged_once(): void
    {
        $owner = User::factory()->create();
        $p = $this->published(['user_id' => $owner->id]);
        $tenant = $this->marketUser(['tenant']);

        $this->actingAs($tenant)->post(route('property.interest', $p))->assertSessionHasNoErrors();

        $e = Enquiry::firstOrFail();
        $this->assertSame($owner->id, $e->to_user_id);
        $this->assertNotNull($e->interest_at);
        $this->assertSame('new', $e->status);
        Notification::assertSentTo($owner, EventNotification::class, fn ($n) => str_contains($n->body, $tenant->name));

        $this->post(route('property.interest', $p));
        $this->assertSame(1, Enquiry::count());
        $this->assertSame(1, app(UsageTrackingService::class)->usedToday($tenant, 'daily_interest'));
    }

    public function test_the_daily_interest_allowance_is_enforced(): void
    {
        $tenant = $this->marketUser(['tenant']); // free plan: 2 a day
        $props = [$this->published(), $this->published(), $this->published()];

        $this->actingAs($tenant)->post(route('property.interest', $props[0]));
        $this->post(route('property.interest', $props[1]));
        $this->post(route('property.interest', $props[2]))->assertSessionHasErrors('limit');

        $this->assertSame(2, Enquiry::count());
    }

    public function test_own_and_unavailable_listings_cannot_be_enquired_about(): void
    {
        $owner = $this->marketUser(['owner']);
        $mine = $this->published(['user_id' => $owner->id]);
        $sold = $this->published(['status' => 'sold']);

        $this->actingAs($owner)->post(route('property.interest', $mine))->assertSessionHasErrors('property');
        $this->post(route('property.interest', $sold))->assertSessionHasErrors('property');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_written_enquiry_uses_its_own_allowance_and_can_be_sent_once(): void
    {
        $p = $this->published();
        $tenant = $this->marketUser(['tenant']);
        $usage = app(UsageTrackingService::class);

        $this->actingAs($tenant)->post(route('property.enquire', $p), ['message' => 'short'])->assertSessionHasErrors('message');
        $this->post(route('property.enquire', $p), ['message' => 'Is the flat still available this month?'])->assertSessionHasNoErrors();
        $this->post(route('property.enquire', $p), ['message' => 'Sending the same thing again please.'])->assertSessionHasErrors('message');

        $this->assertSame(1, $usage->usedToday($tenant, 'daily_enquiries'));
        $this->assertSame(0, $usage->usedToday($tenant, 'daily_interest'));
        $this->assertNotNull(Enquiry::first()->enquired_at);
    }

    public function test_after_interest_contact_visibility_needs_an_interest_first(): void
    {
        $this->setFeature('tenant-free', 'contact_visibility', 'after_interest');
        $this->setFeature('tenant-free', 'daily_contact_views', '3');
        $owner = User::factory()->create(['mobile' => '9876501234']);
        $p = $this->published(['user_id' => $owner->id]);
        $tenant = $this->marketUser(['tenant']);

        $this->actingAs($tenant)->post(route('property.contact', $p))->assertSessionHasErrors('contact');

        $this->post(route('property.interest', $p));
        $this->post(route('property.contact', $p))->assertSessionHasNoErrors();
        $this->get(route('property.show', $p->slug))->assertSee('9876501234');
    }

    public function test_a_closed_lead_reopens_when_the_visitor_comes_back(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->marketUser(['tenant']);
        $e = $this->lead($owner, $tenant, ['interest_at' => null, 'status' => 'closed', 'closed_at' => now()]);

        $this->actingAs($tenant)->post(route('property.enquire', $e->property), ['message' => 'Is it still available please?']);

        $this->assertSame('new', $e->fresh()->status);
        $this->assertNull($e->fresh()->closed_at);
    }

    // ---------- the poster's leads ----------

    public function test_free_posters_see_leads_and_can_close_them_but_the_pipeline_needs_lead_management(): void
    {
        $owner = $this->marketUser(['owner']);
        $tenant = $this->marketUser(['tenant']);
        $e = $this->lead($owner, $tenant);

        $this->actingAs($owner)->get('/leads')->assertOk()->assertSee($tenant->name);
        $this->get(route('leads.show', $e))->assertOk();

        $this->post(route('leads.status', $e), ['status' => 'contacted'])->assertSessionHasErrors('feature');
        $this->post(route('leads.note', $e), ['body' => 'Called him'])->assertSessionHasErrors('feature');
        $this->post(route('leads.status', $e), ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $e->fresh()->status);
        $this->assertNotNull($e->fresh()->closed_at);
    }

    public function test_lead_management_unlocks_the_pipeline_notes_and_follow_ups(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->subscribeTo($owner, 'owner-basic');
        $e = $this->lead($owner, $this->marketUser(['tenant']));

        $this->actingAs($owner)->post(route('leads.status', $e), ['status' => 'negotiation'])->assertSessionHasNoErrors();
        $this->assertSame('negotiation', $e->fresh()->status);

        $this->post(route('leads.note', $e), ['body' => 'Wants a lower price'])->assertSessionHasNoErrors();
        $this->assertSame('Wants a lower price', $e->notes()->first()->body);

        $this->post(route('leads.follow-up', $e), ['follow_up_at' => $this->tomorrow(), 'follow_up_note' => 'Call back'])->assertSessionHasNoErrors();
        $this->assertNotNull($e->fresh()->follow_up_at);

        $past = now(config('marketplace.timezone'))->subDay()->format('Y-m-d\TH:i');
        $this->post(route('leads.follow-up', $e), ['follow_up_at' => $past])->assertSessionHasErrors('follow_up_at');

        $this->post(route('leads.status', $e), ['status' => 'converted']);
        $this->assertNotNull($e->fresh()->closed_at);
        $this->post(route('leads.status', $e), ['status' => 'nonsense'])->assertSessionHasErrors('status');
    }

    public function test_the_poster_can_reveal_the_visitors_number_under_their_own_plan(): void
    {
        $owner = $this->marketUser(['owner']); // free owner plan: on_click, 3 a day
        $tenant = $this->marketUser(['tenant'], ['mobile' => '9123400001']);
        $e = $this->lead($owner, $tenant);

        $this->actingAs($owner)->get(route('leads.show', $e))->assertDontSee('9123400001');
        $this->post(route('leads.contact', $e))->assertSessionHasNoErrors();
        $this->get(route('leads.show', $e))->assertSee('9123400001');
        $this->assertSame(1, app(UsageTrackingService::class)->usedToday($owner, 'daily_contact_views'));
    }

    public function test_only_the_right_people_can_touch_a_lead(): void
    {
        $owner = $this->marketUser(['owner']);
        $tenant = $this->marketUser(['tenant']);
        $stranger = $this->marketUser(['owner']);
        $e = $this->lead($owner, $tenant);

        $this->actingAs($stranger)->get(route('leads.show', $e))->assertForbidden();
        $this->post(route('leads.status', $e), ['status' => 'closed'])->assertForbidden();
        $this->post(route('enquiries.close', $e))->assertForbidden();
        $this->actingAs($owner)->post(route('enquiries.close', $e))->assertForbidden(); // the poster uses the lead screen
        $this->actingAs($tenant)->get(route('leads.show', $e))->assertForbidden();
    }

    public function test_the_visitor_tracks_a_simplified_status_and_can_close_their_enquiry(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->subscribeTo($owner, 'owner-basic');
        $tenant = $this->marketUser(['tenant']);
        $e = $this->lead($owner, $tenant);

        $this->actingAs($tenant)->get('/enquiries')->assertSee('Sent')->assertDontSee('Negotiation');
        $this->actingAs($owner)->post(route('leads.status', $e), ['status' => 'negotiation']);
        $this->actingAs($tenant)->get('/enquiries')->assertSee('In progress')->assertDontSee('Negotiation');

        $this->post(route('enquiries.close', $e));
        $this->assertSame('closed', $e->fresh()->status);
    }

    // ---------- site visits ----------

    public function test_visit_request_confirmation_and_cancellation(): void
    {
        $owner = $this->marketUser(['owner']);
        $tenant = $this->marketUser(['tenant']);
        $e = $this->lead($owner, $tenant);

        $this->actingAs($tenant)->post(route('enquiries.visit', $e), ['scheduled_at' => $this->tomorrow(), 'note' => 'After 5 pm'])->assertSessionHasNoErrors();
        $visit = SiteVisit::firstOrFail();
        $this->assertSame('requested', $visit->status);
        Notification::assertSentTo($owner, EventNotification::class, fn ($n) => $n->title === 'Site visit requested');

        $this->actingAs($owner)->post(route('visits.action', [$visit, 'confirm']));
        $this->assertSame('confirmed', $visit->fresh()->status);
        Notification::assertSentTo($tenant, EventNotification::class);

        $this->actingAs($tenant)->post(route('visits.action', [$visit, 'cancel']));
        $this->assertSame('cancelled', $visit->fresh()->status);
    }

    public function test_visit_rules(): void
    {
        $owner = $this->marketUser(['owner']);
        $tenant = $this->marketUser(['tenant']);
        $stranger = $this->marketUser(['tenant']);
        $e = $this->lead($owner, $tenant);

        $yesterday = now(config('marketplace.timezone'))->subDay()->format('Y-m-d\TH:i');
        $this->actingAs($tenant)->post(route('enquiries.visit', $e), ['scheduled_at' => $yesterday])->assertSessionHasErrors('scheduled_at');

        $this->post(route('enquiries.visit', $e), ['scheduled_at' => $this->tomorrow()]);
        $visit = SiteVisit::firstOrFail();

        $this->actingAs($stranger)->post(route('visits.action', [$visit, 'cancel']))->assertSessionHasErrors('feature');
        $this->actingAs($tenant)->post(route('visits.action', [$visit, 'confirm']))->assertSessionHasErrors('visit'); // only the poster confirms
        $this->assertSame('requested', $visit->fresh()->status);

        $this->actingAs($owner)->post(route('leads.visit', $e), ['scheduled_at' => $this->tomorrow()])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', SiteVisit::latest('id')->first()->status); // poster-proposed visits are confirmed

        $e->update(['status' => 'closed']);
        $this->actingAs($tenant)->post(route('enquiries.visit', $e), ['scheduled_at' => $this->tomorrow()])->assertSessionHasErrors('scheduled_at');
    }

    // ---------- reminders and broker CRM ----------

    public function test_due_follow_ups_send_one_reminder(): void
    {
        $owner = $this->marketUser(['owner']);
        $due = $this->lead($owner, $this->marketUser(['tenant']), ['follow_up_at' => now()->subMinute(), 'follow_up_note' => 'Call back']);
        $later = $this->lead($owner, $this->marketUser(['tenant']), ['follow_up_at' => now()->addDay()]);
        $closed = $this->lead($owner, $this->marketUser(['tenant']), ['follow_up_at' => now()->subMinute(), 'status' => 'closed']);

        Artisan::call('marketplace:send-followups');

        Notification::assertSentToTimes($owner, EventNotification::class, 1);
        $this->assertNull($due->fresh()->follow_up_at);
        $this->assertNotNull($later->fresh()->follow_up_at);

        Artisan::call('marketplace:send-followups');
        Notification::assertSentToTimes($owner, EventNotification::class, 1);
    }

    public function test_broker_performance_needs_the_broker_crm_feature(): void
    {
        $broker = $this->marketUser(['broker']);
        $this->lead($broker, $this->marketUser(['tenant']), ['status' => 'converted']);
        $this->lead($broker, $this->marketUser(['tenant']), ['status' => 'new']);

        $this->actingAs($broker)->get('/leads/performance')->assertSessionHasErrors('feature');

        $this->subscribeTo($broker, 'broker-professional');
        $this->get('/leads/performance')->assertOk()->assertSee('50%');
    }
}
