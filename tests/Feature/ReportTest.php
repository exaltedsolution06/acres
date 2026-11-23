<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyReport;
use App\Models\User;
use App\Notifications\EventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Notification::fake();
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    private function report(User $reporter, Property $property, string $reason = 'fake_property', ?string $details = null)
    {
        return $this->actingAs($reporter, 'web')->post(route('property.report', $property), ['reason' => $reason, 'details' => $details]);
    }

    // ---------- reporting ----------

    public function test_a_logged_in_user_can_report_a_live_listing(): void
    {
        $p = $this->published();
        $tenant = $this->marketUser(['tenant']);

        $this->report($tenant, $p, 'wrong_price')->assertSessionHasNoErrors();

        $r = PropertyReport::firstOrFail();
        $this->assertSame('open', $r->status);
        $this->assertSame($tenant->id, $r->reporter_id);
        $this->assertSame('wrong_price', $r->reason);
        Notification::assertNothingSent(); // the poster is never told
    }

    public function test_reporting_rules(): void
    {
        $owner = $this->marketUser(['owner']);
        $mine = $this->published(['user_id' => $owner->id]);
        $p = $this->published();
        $tenant = $this->marketUser(['tenant']);

        $this->report($owner, $mine)->assertSessionHasErrors('reason');                       // own listing
        $this->report($tenant, $p, 'not_a_reason')->assertSessionHasErrors('reason');
        $this->report($tenant, $p, 'other')->assertSessionHasErrors('details');               // "something else" needs details
        $this->report($tenant, $p, 'other', 'The photos show a different building entirely.')->assertSessionHasNoErrors();
        $this->report($tenant, $p, 'wrong_price')->assertSessionHasErrors('reason');          // already reported
        $this->report($tenant, $this->published(['status' => 'sold']), 'wrong_price')->assertSessionHasErrors('reason');

        $this->resetAuth();
        $this->post(route('property.report', $p), ['reason' => 'wrong_price'])->assertRedirect(route('login'));
        $this->assertSame(1, PropertyReport::count());
    }

    public function test_reports_per_user_are_capped_per_day(): void
    {
        config(['trust.reports_per_user_per_day' => 2]);
        $tenant = $this->marketUser(['tenant']);

        $this->report($tenant, $this->published())->assertSessionHasNoErrors();
        $this->report($tenant, $this->published())->assertSessionHasNoErrors();
        $this->report($tenant, $this->published())->assertSessionHasErrors('reason');
        $this->assertSame(2, PropertyReport::count());
    }

    public function test_the_report_form_shows_only_to_other_logged_in_users(): void
    {
        $owner = $this->marketUser(['owner']);
        $p = $this->published(['user_id' => $owner->id]);

        $this->actingAs($this->marketUser(['tenant']), 'web')->get(route('property.show', $p->slug))->assertSee('Report this listing');
        $this->actingAs($owner, 'web')->get(route('property.show', $p->slug))->assertDontSee('Report this listing');
        $this->resetAuth();
        $this->get(route('property.show', $p->slug))->assertDontSee('Report this listing');
    }

    // ---------- admin handling ----------

    public function test_dismissing_a_report_thanks_the_reporter_and_leaves_the_listing_alone(): void
    {
        $p = $this->published();
        $tenant = $this->marketUser(['tenant']);
        $this->report($tenant, $p);
        $report = PropertyReport::firstOrFail();

        $this->actingAs($this->adminUser('staff'), 'admin')->post(route('admin.reports.dismiss', $report), ['note' => 'Listing is genuine.'])->assertSessionHasNoErrors();

        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertSame('published', $p->fresh()->status);
        Notification::assertSentTo($tenant, EventNotification::class, fn ($n) => $n->event === 'report.update');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'report.dismissed']);
    }

    public function test_taking_action_removes_the_listing_closes_sibling_reports_and_can_suspend_the_poster(): void
    {
        $poster = User::factory()->create(['name' => 'Shady Poster']);
        $p = $this->published(['user_id' => $poster->id]);
        [$r1, $r2] = [$this->marketUser(['tenant'], ['name' => 'Reporter One']), $this->marketUser(['buyer'], ['name' => 'Reporter Two'])];
        $this->report($r1, $p, 'fake_property');
        $this->report($r2, $p, 'wrong_price');

        $this->actingAs($this->adminUser('staff'), 'admin')->post(route('admin.reports.action', PropertyReport::orderBy('id')->first()), ['take_down' => '1', 'suspend_poster' => '1', 'note' => 'Confirmed fake'])->assertSessionHasNoErrors();

        $this->assertSame(['actioned', 'actioned'], PropertyReport::orderBy('id')->pluck('status')->all());
        $this->assertSame('rejected', $p->fresh()->status);
        $this->assertStringContainsString('Removed after review of a report', $p->fresh()->rejection_reason);
        $this->assertSame('suspended', $poster->fresh()->status);

        Notification::assertSentTo($r1, EventNotification::class, fn ($n) => $n->event === 'report.update');
        Notification::assertSentTo($r2, EventNotification::class, fn ($n) => $n->event === 'report.update');
        Notification::assertSentTo($poster, EventNotification::class, fn ($n) => ! str_contains($n->body, 'Reporter') && ! str_contains($n->title, 'Reporter'));

        $this->resetAuth();
        $this->assertSame([], $this->resultIds($this->get('/search')));
    }

    public function test_action_without_takedown_only_records_the_outcome(): void
    {
        $poster = User::factory()->create();
        $p = $this->published(['user_id' => $poster->id]);
        $this->report($this->marketUser(['tenant']), $p);

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.reports.action', PropertyReport::firstOrFail()), ['note' => 'Warned the poster'])->assertSessionHasNoErrors();

        $this->assertSame('published', $p->fresh()->status);
        $this->assertSame('active', $poster->fresh()->status);
        $this->assertSame('actioned', PropertyReport::first()->status);
    }

    public function test_a_handled_report_cannot_be_handled_again(): void
    {
        $this->report($this->marketUser(['tenant']), $this->published());
        $report = PropertyReport::firstOrFail();
        $this->actingAs($this->adminUser(), 'admin');

        $this->post(route('admin.reports.dismiss', $report), ['note' => 'ok']);
        $this->post(route('admin.reports.dismiss', $report), ['note' => 'again'])->assertSessionHasErrors('report');
        $this->post(route('admin.reports.action', $report), ['take_down' => '1'])->assertSessionHasErrors('report');
    }

    public function test_the_fraud_dashboard_highlights_repeat_posters_and_renders(): void
    {
        $poster = User::factory()->create(['name' => 'Notorious Poster']);
        foreach (range(1, 3) as $i) {
            $this->report($this->marketUser(['tenant']), $this->published(['user_id' => $poster->id]), 'fake_property');
        }
        $report = PropertyReport::first();

        $this->actingAs($this->adminUser('staff'), 'admin');
        $this->get('/admin/reports')->assertOk()->assertSee('Posters with many reports')->assertSee('Notorious Poster (3)');
        $this->get('/admin/reports?reason=wrong_price')->assertOk()->assertSee('No reports.');
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee('Take action');
        $this->get('/admin')->assertOk()->assertSee('Open reports');
    }

    public function test_admin_pages_need_an_admin_login(): void
    {
        $this->resetAuth();
        $this->get('/admin/reports')->assertRedirect(route('admin.login'));
        $this->get('/admin/verifications')->assertRedirect(route('admin.login'));
    }
}
