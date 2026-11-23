<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Notifications\EventNotification;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private_documents');
        Storage::fake('uploads');
        Notification::fake();
    }

    private function pdf(string $name = 'id.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    private function submitOwner(User $owner, ?array $documents = null)
    {
        return $this->actingAs($owner)->post('/verification', [
            'type' => 'owner',
            'documents' => $documents ?? ['id_proof' => $this->pdf('passport scan.pdf'), 'address_proof' => UploadedFile::fake()->image('bill.jpg')],
        ]);
    }

    /** Back to "nobody logged in, default guard web": admin requests leave the default guard on 'admin' within a test. */
    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    private function approve(VerificationRequest $r): void
    {
        $this->actingAs($this->adminUser('staff'), 'admin')->post(route('admin.verifications.approve', $r))->assertSessionHasNoErrors();
    }

    // ---------- submitting ----------

    public function test_documents_go_to_private_storage_under_random_names(): void
    {
        $owner = $this->marketUser(['owner']);

        $this->submitOwner($owner)->assertSessionHasNoErrors();

        $request = VerificationRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertCount(2, $request->documents);

        foreach ($request->documents as $doc) {
            Storage::disk('private_documents')->assertExists($doc->path);
            $this->assertStringStartsWith("verification/{$request->id}/", $doc->path);
            $this->assertStringNotContainsString('passport', $doc->path);
        }
        $this->assertSame([], Storage::disk('uploads')->allFiles()); // nothing under the public uploads disk
    }

    public function test_submission_rules(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->actingAs($owner);

        $this->post('/verification', ['type' => 'owner', 'documents' => ['address_proof' => $this->pdf('a.pdf')]])->assertSessionHasErrors('documents'); // photo ID is required
        $this->post('/verification', ['type' => 'owner', 'documents' => ['id_proof' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php')]])->assertSessionHasErrors('documents.id_proof');
        $this->post('/verification', ['type' => 'owner', 'documents' => ['id_proof' => $this->pdf('big.pdf', 6000)]])->assertSessionHasErrors('documents.id_proof');
        $this->post('/verification', ['type' => 'nonsense', 'documents' => ['id_proof' => $this->pdf()]])->assertSessionHasErrors('type');
        $this->assertSame(0, VerificationRequest::count());

        $this->actingAs($this->marketUser(['tenant']))->post('/verification', ['type' => 'owner', 'documents' => ['id_proof' => $this->pdf()]])->assertSessionHasErrors('type'); // needs the role
        $this->actingAs($owner)->post('/verification', ['type' => 'broker', 'documents' => ['id_proof' => $this->pdf(), 'business_registration' => $this->pdf('b.pdf')]])->assertSessionHasErrors('type');
    }

    public function test_one_pending_request_per_kind_and_resubmission_after_rejection(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->submitOwner($owner)->assertSessionHasNoErrors();
        $this->submitOwner($owner, ['id_proof' => $this->pdf()])->assertSessionHasErrors('type');

        $request = VerificationRequest::firstOrFail();
        $admin = $this->adminUser('staff');
        $this->actingAs($admin, 'admin')->post(route('admin.verifications.reject', $request), ['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->post(route('admin.verifications.reject', $request), ['reason' => 'The ID photo is unreadable.'])->assertSessionHasNoErrors();

        $this->resetAuth();
        $this->actingAs($owner, 'web')->get('/verification')->assertSee('The ID photo is unreadable.');
        $this->submitOwner($owner)->assertSessionHasNoErrors();
        $this->assertSame(2, VerificationRequest::count());
    }

    public function test_an_active_verification_can_be_renewed_only_near_expiry(): void
    {
        $owner = $this->marketUser(['owner']);
        $owner->update(['owner_verified_until' => now()->addDays(200)]);
        $this->submitOwner($owner)->assertSessionHasErrors('type');

        $owner->update(['owner_verified_until' => now()->addDays(20)]);
        $this->submitOwner($owner)->assertSessionHasNoErrors();
    }

    // ---------- review and badges ----------

    public function test_approval_sets_a_year_long_badge_that_is_specific_about_what_was_checked(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->submitOwner($owner);
        $this->approve(VerificationRequest::firstOrFail());

        $owner = $owner->fresh();
        $this->assertTrue($owner->owner_verified_until->between(now()->addDays(364), now()->addDays(366)));
        $this->assertSame('approved', VerificationRequest::first()->status);
        Notification::assertSentTo($owner, EventNotification::class, fn ($n) => $n->event === 'verification.reviewed');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'verification.approved']);

        $property = $this->published(['user_id' => $owner->id, 'posted_by' => 'owner']);
        $this->resetAuth();
        $this->get(route('property.show', $property->slug))
            ->assertSee('Verified owner')
            ->assertSee('Identity and address documents of this owner were reviewed');
    }

    public function test_verified_filter_and_card_badges(): void
    {
        $verified = $this->marketUser(['owner']);
        $verified->update(['owner_verified_until' => now()->addMonths(6)]);
        $a = $this->published(['user_id' => $verified->id, 'posted_by' => 'owner']);
        $b = $this->published();
        $c = $this->published(['user_id' => $verified->id, 'posted_by' => 'broker']); // verified as owner, but posted as broker
        $d = $this->published(['documents_reviewed_until' => now()->addMonths(6)]);

        $this->assertEqualsCanonicalizing([$a->public_id, $d->public_id], $this->resultIds($this->get('/search?verified=1')));
        $this->assertCount(4, $this->resultIds($this->get('/search')));
        $this->get('/search')->assertSee('Verified owner')->assertSee('Documents reviewed');
    }

    public function test_the_verified_badge_stops_when_the_period_ends(): void
    {
        $owner = $this->marketUser(['owner']);
        $owner->update(['owner_verified_until' => now()->subDay()]);
        $property = $this->published(['user_id' => $owner->id, 'posted_by' => 'owner']);

        $this->assertFalse($property->fresh()->isVerified());
        $this->assertSame([], $this->resultIds($this->get('/search?verified=1')));
    }

    public function test_revoking_removes_the_badge_and_needs_a_reason(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->submitOwner($owner);
        $request = VerificationRequest::firstOrFail();
        $this->approve($request);

        $admin = $this->adminUser('staff');
        $this->actingAs($admin, 'admin')->post(route('admin.verifications.revoke', $request), ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->post(route('admin.verifications.revoke', $request), ['reason' => 'Documents found to be forged.'])->assertSessionHasNoErrors();

        $this->assertSame('revoked', $request->fresh()->status);
        $this->assertNull($owner->fresh()->owner_verified_until);
    }

    public function test_only_pending_requests_can_be_approved_or_rejected(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->submitOwner($owner);
        $request = VerificationRequest::firstOrFail();
        $this->approve($request);

        $this->actingAs($this->adminUser('staff'), 'admin');
        $this->post(route('admin.verifications.approve', $request))->assertSessionHasErrors('status');
        $this->post(route('admin.verifications.reject', $request), ['reason' => 'Changed my mind later.'])->assertSessionHasErrors('status');
    }

    public function test_property_documents_review_marks_that_listing_only(): void
    {
        $owner = $this->marketUser(['owner']);
        $mine = $this->published(['user_id' => $owner->id]);
        $other = $this->published(['user_id' => $owner->id]);

        $this->actingAs($owner)->post('/verification', ['type' => 'property_documents', 'property' => $mine->public_id, 'documents' => ['ownership_document' => $this->pdf('deed.pdf')]])->assertSessionHasNoErrors();
        $this->approve(VerificationRequest::firstOrFail());
        $this->resetAuth();

        $this->assertTrue($mine->fresh()->documents_reviewed_until->isFuture());
        $this->assertNull($other->fresh()->documents_reviewed_until);
        $this->get(route('property.show', $mine->slug))->assertSee('Documents reviewed')->assertSee('This is not a legal title verification.');
        $this->get(route('property.show', $other->slug))->assertDontSee('This is not a legal title verification.');
    }

    public function test_property_documents_rules(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->actingAs($owner);
        $others = $this->published();
        $sold = $this->published(['user_id' => $owner->id, 'status' => 'sold']);
        $mine = $this->published(['user_id' => $owner->id]);

        $this->post('/verification', ['type' => 'property_documents', 'property' => $others->public_id, 'documents' => ['ownership_document' => $this->pdf()]])->assertSessionHasErrors('property');
        $this->post('/verification', ['type' => 'property_documents', 'property' => $sold->public_id, 'documents' => ['ownership_document' => $this->pdf()]])->assertSessionHasErrors('property');
        $this->post('/verification', ['type' => 'property_documents', 'property' => $mine->public_id, 'documents' => ['utility_bill' => $this->pdf()]])->assertSessionHasErrors('documents');
        $this->assertSame(0, VerificationRequest::count());
    }

    public function test_premium_advertiser_badge_needs_a_purchased_plan_and_says_what_it_means(): void
    {
        $paid = $this->marketUser(['owner']);
        app(SubscriptionService::class)->subscribe($paid, \App\Models\Package::where('slug', 'owner-basic')->first(), null, 'purchase');
        $granted = $this->marketUser(['owner']);
        $this->subscribeTo($granted, 'owner-basic'); // admin-granted: not a paying customer

        $a = $this->published(['user_id' => $paid->id]);
        $b = $this->published(['user_id' => $granted->id]);

        $this->get(route('property.show', $a->slug))->assertSee('Premium advertiser')->assertSee('does not mean the listing itself was checked', false);
        $this->get(route('property.show', $b->slug))->assertDontSee('Premium advertiser');
    }

    // ---------- downloads and retention ----------

    public function test_documents_can_be_downloaded_only_by_the_applicant_and_admins(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->submitOwner($owner);
        $doc = VerificationDocument::firstOrFail();

        $this->actingAs($owner, 'web')->get(route('verification.download', $doc))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->marketUser(['owner']), 'web')->get(route('verification.download', $doc))->assertForbidden();

        $this->actingAs($this->adminUser('staff'), 'admin')->get(route('admin.verifications.download', $doc))->assertOk();
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'verification.document_viewed']);
    }

    public function test_guests_cannot_reach_documents(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->submitOwner($owner);
        $doc = VerificationDocument::firstOrFail();
        $this->resetAuth();

        $this->get(route('verification.download', $doc))->assertRedirect(route('login'));
        $this->get(route('admin.verifications.download', $doc))->assertRedirect(route('admin.login'));
    }

    public function test_maintenance_expires_lapsed_verifications_and_purges_old_documents(): void
    {
        $owner = $this->marketUser(['owner']);
        $owner->update(['owner_verified_until' => now()->subDay()]);
        $lapsed = VerificationRequest::create(['user_id' => $owner->id, 'type' => 'owner', 'status' => 'approved', 'reviewed_at' => now()->subYear(), 'expires_at' => now()->subDay()]);

        $makeDoc = function (VerificationRequest $r, string $name) {
            Storage::disk('private_documents')->put("verification/{$r->id}/{$name}", 'x');

            return VerificationDocument::create(['verification_request_id' => $r->id, 'doc_type' => 'id_proof', 'path' => "verification/{$r->id}/{$name}", 'original_name' => $name, 'mime' => 'application/pdf', 'size_kb' => 1]);
        };
        $old = VerificationRequest::create(['user_id' => $owner->id, 'type' => 'owner', 'status' => 'rejected', 'reviewed_at' => now()->subDays(100)]);
        $recent = VerificationRequest::create(['user_id' => $owner->id, 'type' => 'broker', 'status' => 'rejected', 'reviewed_at' => now()->subDays(10)]);
        $pending = VerificationRequest::create(['user_id' => $owner->id, 'type' => 'owner', 'status' => 'pending']);
        $oldDoc = $makeDoc($old, 'old.pdf');
        $recentDoc = $makeDoc($recent, 'recent.pdf');
        $pendingDoc = $makeDoc($pending, 'pending.pdf');

        $this->artisan('marketplace:maintain-verifications')->assertSuccessful();

        $this->assertSame('expired', $lapsed->fresh()->status);
        $this->assertNull($owner->fresh()->owner_verified_until);
        Storage::disk('private_documents')->assertMissing($oldDoc->path);
        $this->assertNull(VerificationDocument::find($oldDoc->id));
        Storage::disk('private_documents')->assertExists($recentDoc->path);
        Storage::disk('private_documents')->assertExists($pendingDoc->path); // pending requests keep their files
    }

    public function test_pages_render(): void
    {
        $owner = $this->marketUser(['owner']);
        $this->submitOwner($owner);
        $request = VerificationRequest::firstOrFail();

        $this->actingAs($owner)->get('/verification')->assertOk()->assertSee('Verified owner');

        $this->actingAs($this->adminUser('staff'), 'admin');
        $this->get('/admin/verifications')->assertOk()->assertSee($owner->name);
        $this->get(route('admin.verifications.show', $request))->assertOk()->assertSee('not</strong> a legal title check', false);
        $this->get('/admin')->assertOk()->assertSee('Verifications waiting');
    }
}
