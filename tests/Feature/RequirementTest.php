<?php

namespace Tests\Feature;

use App\Models\Requirement;
use App\Models\RequirementMatch;
use App\Models\RequirementResponse;
use App\Models\User;
use App\Notifications\EventNotification;
use App\Services\UsageTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class RequirementTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Notification::fake();
    }

    private function payload(array $overrides = []): array
    {
        $locality = $this->locality('salt-lake-kolkata');

        return array_merge([
            'role' => 'tenant', 'purpose' => 'rent', 'title' => '2 BHK in Salt Lake within 20,000 rent',
            'city_id' => $locality->parent_id, 'locality_id' => $locality->id, 'budget_max' => 20000, 'bhk' => 2,
        ], $overrides);
    }

    private function tenantWithPlan(string $plan = 'tenant-basic'): User
    {
        $user = $this->marketUser(['tenant']);
        $this->subscribeTo($user, $plan);

        return $user;
    }

    private function requirement(User $user, array $attrs = []): Requirement
    {
        return Requirement::create($attrs + [
            'user_id' => $user->id, 'role' => 'tenant', 'purpose' => 'rent', 'title' => 'Flat wanted in Salt Lake',
            'city_id' => $this->locality('salt-lake-kolkata')->parent_id, 'status' => 'active', 'expires_at' => now()->addDays(30),
        ]);
    }

    // ---------- posting ----------

    public function test_posting_needs_a_plan_with_requirement_posting(): void
    {
        $free = $this->marketUser(['tenant']);
        $this->actingAs($free)->post('/requirements', $this->payload())->assertSessionHasErrors('feature');
        $this->assertSame(0, Requirement::count());

        $paid = $this->tenantWithPlan();
        $this->actingAs($paid)->post('/requirements', $this->payload())->assertSessionHasNoErrors();
        $r = Requirement::firstOrFail();
        $this->assertSame('active', $r->status);
        $this->assertTrue($r->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_purpose_must_fit_the_role_and_roles_must_be_held(): void
    {
        $buyer = $this->marketUser(['buyer']);
        $this->subscribeTo($buyer, 'buyer-basic');

        $this->actingAs($buyer)->post('/requirements', $this->payload(['role' => 'buyer', 'purpose' => 'rent']))->assertSessionHasErrors('purpose');
        $this->post('/requirements', $this->payload(['role' => 'buyer', 'purpose' => 'sell', 'budget_max' => 7000000]))->assertSessionHasNoErrors();
        $this->post('/requirements', $this->payload(['role' => 'tenant']))->assertSessionHasErrors('role');
    }

    public function test_active_requirements_are_capped(): void
    {
        config(['engagement.max_active_requirements' => 2]);
        $tenant = $this->tenantWithPlan();

        $this->actingAs($tenant)->post('/requirements', $this->payload());
        $this->post('/requirements', $this->payload(['title' => 'Second requirement for a flat']));
        $this->post('/requirements', $this->payload(['title' => 'Third requirement for a flat']))->assertSessionHasErrors('title');

        $this->assertSame(2, Requirement::count());
    }

    public function test_budget_max_cannot_be_below_budget_min(): void
    {
        $this->actingAs($this->tenantWithPlan())->post('/requirements', $this->payload(['budget_min' => 30000, 'budget_max' => 20000]))->assertSessionHasErrors('budget_max');
    }

    // ---------- matching ----------

    public function test_a_new_requirement_matches_existing_listings(): void
    {
        $fit = $this->published(['purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000, 'bhk' => 2]);
        $this->published(['purpose' => 'rent', 'price' => null, 'monthly_rent' => 25000, 'bhk' => 2]);      // over budget
        $this->published(['purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000, 'bhk' => 3]);      // wrong BHK
        $this->published(['price' => 6000000]);                                                              // for sale
        $tenant = $this->tenantWithPlan();

        $this->actingAs($tenant)->post('/requirements', $this->payload());

        $r = Requirement::firstOrFail();
        $this->assertSame([$fit->id], RequirementMatch::where('requirement_id', $r->id)->pluck('property_id')->all());
        Notification::assertSentTo($tenant, EventNotification::class, fn ($n) => $n->event === 'requirement.match');
    }

    public function test_a_listing_that_goes_live_matches_waiting_requirements(): void
    {
        $tenant = $this->tenantWithPlan();
        $r = $this->requirement($tenant, ['budget_max' => 20000, 'bhk' => 2]);
        $other = $this->requirement($this->tenantWithPlan(), ['budget_max' => 10000]); // too low

        $pending = $this->published(['status' => 'pending_approval', 'published_at' => null, 'expires_at' => null, 'purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000, 'bhk' => 2]);

        $this->actingAs($this->adminUser(), 'admin')->post("/admin/properties/{$pending->public_id}/approve")->assertRedirect();

        $this->assertTrue(RequirementMatch::where('requirement_id', $r->id)->where('property_id', $pending->id)->exists());
        $this->assertFalse(RequirementMatch::where('requirement_id', $other->id)->exists());
        Notification::assertSentTo($tenant, EventNotification::class, fn ($n) => $n->title === 'A new listing matches your requirement');
    }

    public function test_listings_by_the_requirement_owner_never_match(): void
    {
        $user = $this->tenantWithPlan();
        $this->published(['user_id' => $user->id, 'purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000, 'bhk' => 2]);

        $this->actingAs($user)->post('/requirements', $this->payload());

        $this->assertSame(0, RequirementMatch::count());
    }

    // ---------- browsing and responding ----------

    public function test_only_posters_with_the_feature_can_browse_requirements(): void
    {
        $this->requirement($this->tenantWithPlan(), ['title' => 'Flat wanted near the lake']);
        $free = $this->marketUser(['owner']);
        $paid = $this->marketUser(['owner']);
        $this->subscribeTo($paid, 'owner-premium');

        $this->actingAs($free)->get('/requirements/browse')->assertSessionHasErrors('feature');
        $this->actingAs($paid)->get('/requirements/browse')->assertOk()->assertSee('Flat wanted near the lake');
        $this->actingAs($this->marketUser(['tenant']))->get('/requirements/browse')->assertForbidden();
    }

    public function test_expired_and_closed_requirements_are_not_browsable(): void
    {
        $tenant = $this->tenantWithPlan();
        $this->requirement($tenant, ['title' => 'Expired wish', 'expires_at' => now()->subDay()]);
        $this->requirement($tenant, ['title' => 'Closed wish', 'status' => 'closed']);
        $paid = $this->marketUser(['owner']);
        $this->subscribeTo($paid, 'owner-premium');

        $this->actingAs($paid)->get('/requirements/browse')->assertDontSee('Expired wish')->assertDontSee('Closed wish');
    }

    public function test_responding_costs_an_enquiry_and_notifies_the_requirement_owner(): void
    {
        $tenant = $this->tenantWithPlan();
        $r = $this->requirement($tenant);
        $poster = $this->marketUser(['owner']);
        $this->subscribeTo($poster, 'owner-premium');
        $mine = $this->published(['user_id' => $poster->id, 'purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000]);

        $this->actingAs($poster)->post(route('requirements.respond', $r), ['message' => 'I have a flat that fits.', 'property_id' => $mine->id])->assertSessionHasNoErrors();

        $this->assertSame(1, RequirementResponse::count());
        $this->assertSame(1, app(UsageTrackingService::class)->usedToday($poster, 'daily_enquiries'));
        Notification::assertSentTo($tenant, EventNotification::class, fn ($n) => $n->event === 'requirement.response');

        $this->post(route('requirements.respond', $r), ['message' => 'Responding a second time now.'])->assertSessionHasErrors('message');
    }

    public function test_response_rules(): void
    {
        $tenant = $this->tenantWithPlan();
        $r = $this->requirement($tenant);
        $poster = $this->marketUser(['owner']);
        $this->subscribeTo($poster, 'owner-premium');
        $notMine = $this->published(['purpose' => 'rent', 'price' => null, 'monthly_rent' => 15000]);
        $wrongPurpose = $this->published(['user_id' => $poster->id, 'price' => 5000000]);

        $this->actingAs($poster)->post(route('requirements.respond', $r), ['message' => 'Here is another listing.', 'property_id' => $notMine->id])->assertSessionHasErrors('property_id');
        $this->post(route('requirements.respond', $r), ['message' => 'This one is for sale though.', 'property_id' => $wrongPurpose->id])->assertSessionHasErrors('property_id');

        $r->update(['status' => 'closed']);
        $this->post(route('requirements.respond', $r), ['message' => 'Can I still respond?'])->assertSessionHasErrors('message');
        $this->assertSame(0, RequirementResponse::count());

        $free = $this->marketUser(['owner']); // no requirement_posting on the free plan
        $open = $this->requirement($tenant, ['title' => 'Another open wish']);
        $this->actingAs($free)->post(route('requirements.respond', $open), ['message' => 'I would like to respond.'])->assertSessionHasErrors('feature');
    }

    public function test_the_owner_sees_responses_and_matches_and_others_see_the_respond_form(): void
    {
        $tenant = $this->tenantWithPlan();
        $r = $this->requirement($tenant, ['title' => 'Wanted: quiet flat for family']);
        $poster = $this->marketUser(['owner']);
        $this->subscribeTo($poster, 'owner-premium');
        $this->actingAs($poster)->post(route('requirements.respond', $r), ['message' => 'I can offer a quiet flat.']);

        $this->actingAs($tenant)->get(route('requirements.show', $r))->assertOk()->assertSee('I can offer a quiet flat.')->assertSee('View contact number');
        $this->actingAs($this->marketUser(['tenant']))->get(route('requirements.show', $r))->assertForbidden();

        $second = $this->marketUser(['owner']);
        $this->subscribeTo($second, 'owner-premium');
        $this->actingAs($second)->get(route('requirements.show', $r))->assertOk()->assertSee('Send response');
    }

    public function test_contact_numbers_between_requirement_owner_and_responder(): void
    {
        $tenant = $this->tenantWithPlan(); // on_click, 5 a day
        $tenant->update(['mobile' => '9300000001']);
        $r = $this->requirement($tenant);
        $poster = $this->marketUser(['owner'], ['mobile' => '9300000002']);
        $this->subscribeTo($poster, 'owner-premium');

        $this->actingAs($poster)->post(route('requirements.respond', $r), ['message' => 'I can offer a quiet flat.']);
        $response = RequirementResponse::firstOrFail();

        $this->post(route('requirements.contact', $r));
        $this->get(route('requirements.show', $r))->assertSee('9300000001');

        $this->actingAs($tenant)->post(route('requirements.responses.contact', [$r, $response]))->assertSessionHasNoErrors();
        $this->get(route('requirements.show', $r))->assertSee('9300000002');

        $this->actingAs($poster)->post(route('requirements.responses.contact', [$r, $response]))->assertForbidden(); // only the requirement owner
    }

    public function test_closing_and_listing_my_requirements(): void
    {
        $tenant = $this->tenantWithPlan();
        $r = $this->requirement($tenant, ['title' => 'Wanted: flat near the metro']);

        $this->actingAs($tenant)->get('/requirements')->assertOk()->assertSee('Wanted: flat near the metro');
        $this->actingAs($this->marketUser(['tenant']))->post(route('requirements.close', $r))->assertForbidden();
        $this->actingAs($tenant)->post(route('requirements.close', $r));
        $this->assertSame('closed', $r->fresh()->status);
    }
}
