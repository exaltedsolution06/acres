<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CreditPack;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PromotionPackage;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\EventNotification;
use App\Services\ContactCreditService;
use App\Services\PaymentService;
use App\Services\Payments\FakeGateway;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class RefundAndAdminBillingTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['payments.driver' => 'fake', 'payments.company.state' => 'West Bengal']);
        Package::where('slug', 'tenant-basic')->update(['refund_eligible' => true]);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        FakeGateway::$failRefunds = false;
        parent::tearDown();
    }

    private function buy(User $user, string $purpose, int $itemId, ?Property $property = null): Payment
    {
        $service = app(PaymentService::class);
        $payment = $service->checkout($user, $purpose, $itemId, ['name' => 'Customer', 'state' => 'West Bengal', 'gstin' => null], null, $property);
        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        return $service->verifyCheckout($payment, $payment->gateway_order_id, $id, $signature);
    }

    private function buyPlan(User $user, string $slug = 'tenant-basic'): Payment
    {
        return $this->buy($user, 'subscription', Package::where('slug', $slug)->value('id'));
    }

    // ---------- customer refund requests ----------

    public function test_eligible_purchases_can_be_refunded_after_admin_approval(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->buyPlan($tenant);
        $this->assertSame(10, app(PermissionService::class)->limit($tenant, 'daily_interest'));

        $this->actingAs($tenant)->get('/billing')->assertSee('Request a refund');
        $this->post(route('billing.refund', $payment), ['reason' => 'I bought the wrong plan by mistake.'])->assertSessionHasNoErrors();
        $refund = Refund::firstOrFail();
        $this->assertSame('requested', $refund->status);
        $this->assertSame('paid', $payment->fresh()->status); // nothing happens until an admin approves

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.refunds.approve', $refund))->assertSessionHasNoErrors();

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('processed', $refund->fresh()->status);
        $this->assertNotNull($refund->fresh()->gateway_refund_id);
        $this->assertSame('cancelled', Subscription::firstOrFail()->status);
        $this->assertSame(2, app(PermissionService::class)->limit($tenant, 'daily_interest')); // back on the free plan
        $note = $payment->fresh()->creditNote;
        $this->assertMatchesRegularExpression('#^CN/\d{4}-\d{2}/000001$#', $note->number);
        $this->assertSame($payment->invoice->id, $note->related_invoice_id);
        $this->assertSame((int) $payment->total_paise, $note->total_paise);
        Notification::assertSentTo($tenant, EventNotification::class, fn ($n) => $n->event === 'refund.update');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'refund.approved']);
    }

    public function test_refund_requests_have_eligibility_rules(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->buyPlan($tenant);
        $reason = ['reason' => 'Please refund this purchase.'];

        $this->actingAs($this->marketUser(['tenant']))->post(route('billing.refund', $payment), $reason)->assertForbidden();

        $this->actingAs($tenant)->post(route('billing.refund', $payment), ['reason' => 'short'])->assertSessionHasErrors('reason');

        $this->post(route('billing.refund', $payment), $reason)->assertSessionHasNoErrors();
        $this->post(route('billing.refund', $payment), $reason)->assertSessionHasErrors('refund'); // already requested

        $old = $this->buyPlan($this->marketUser(['tenant']));
        $old->update(['paid_at' => now()->subDays(10)]);
        $this->actingAs(User::find($old->user_id))->post(route('billing.refund', $old), $reason)->assertSessionHasErrors('refund');

        Package::where('slug', 'tenant-premium')->update(['refund_eligible' => false]);
        $premium = $this->buyPlan($tenant, 'tenant-premium');
        $this->actingAs($tenant)->post(route('billing.refund', $premium), $reason)->assertSessionHasErrors('refund');
    }

    public function test_declined_requests_change_nothing(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->buyPlan($tenant);
        $this->actingAs($tenant)->post(route('billing.refund', $payment), ['reason' => 'Changed my mind about this.']);
        $refund = Refund::firstOrFail();

        $admin = $this->adminUser();
        $this->actingAs($admin, 'admin')->post(route('admin.refunds.reject', $refund), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('admin.refunds.reject', $refund), ['note' => 'Plan was already used heavily.'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $refund->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('active', Subscription::firstOrFail()->status);
        $this->actingAs($tenant)->get('/billing')->assertSee('Plan was already used heavily.');
    }

    // ---------- admin refunds and reversals ----------

    public function test_a_gateway_failure_leaves_everything_untouched(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->buyPlan($tenant);
        FakeGateway::$failRefunds = true;

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.payments.refund', $payment), ['reason' => 'Goodwill refund requested'])->assertSessionHasErrors('refund');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('active', Subscription::firstOrFail()->status);
        $this->assertNull($payment->fresh()->creditNote);
        $this->assertSame('failed', Refund::firstOrFail()->status);
    }

    public function test_refunding_a_renewal_takes_back_only_the_extra_days(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $this->buyPlan($tenant);
        $first = Subscription::firstOrFail();
        $afterFirst = $first->ends_at->copy();
        $renewal = $this->buyPlan($tenant);
        $this->assertTrue($first->fresh()->ends_at->equalTo($afterFirst->copy()->addDays(30)));

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.payments.refund', $renewal), ['reason' => 'Duplicate purchase refund'])->assertSessionHasNoErrors();

        $this->assertSame('active', $first->fresh()->status);
        $this->assertTrue($first->fresh()->ends_at->equalTo($afterFirst));
    }

    public function test_unspent_credit_packs_can_be_refunded_but_spent_ones_cannot(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $credits = app(ContactCreditService::class);
        $pack = CreditPack::where('name', '10 contacts')->first();
        $admin = $this->adminUser();

        $spent = $this->buy($tenant, 'credit_pack', $pack->id);
        $credits->spend($tenant);
        $this->actingAs($admin, 'admin')->post(route('admin.payments.refund', $spent), ['reason' => 'Customer asked for refund'])->assertSessionHasErrors('refund');
        $this->assertSame('paid', $spent->fresh()->status);
        $this->assertSame(9, $credits->balance($tenant));

        $clean = $this->buy($this->marketUser(['tenant']), 'credit_pack', $pack->id);
        $this->post(route('admin.payments.refund', $clean), ['reason' => 'Customer asked for refund'])->assertSessionHasNoErrors();
        $this->assertSame(0, $credits->balance(User::find($clean->user_id)));
        $this->assertDatabaseHas('contact_credit_transactions', ['type' => 'refund', 'amount' => -10]);
    }

    public function test_refunding_a_promotion_ends_the_featuring(): void
    {
        $owner = $this->marketUser(['owner']);
        $property = $this->published(['user_id' => $owner->id]);
        $payment = $this->buy($owner, 'promotion', PromotionPackage::first()->id, $property);
        $this->assertTrue($property->fresh()->isFeatured());

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.payments.refund', $payment), ['reason' => 'Listing was removed early'])->assertSessionHasNoErrors();

        $this->assertFalse($property->fresh()->isFeatured());
        $this->assertNull($property->fresh()->featured_until);
    }

    public function test_a_refunded_payment_cannot_be_refunded_again(): void
    {
        $payment = $this->buyPlan($this->marketUser(['tenant']));
        $admin = $this->adminUser();

        $this->actingAs($admin, 'admin')->post(route('admin.payments.refund', $payment), ['reason' => 'First refund, fine'])->assertSessionHasNoErrors();
        $this->post(route('admin.payments.refund', $payment), ['reason' => 'Second refund, not fine'])->assertSessionHasErrors('refund');
        $this->assertSame(1, Refund::where('status', 'processed')->count());
    }

    // ---------- admin screens ----------

    public function test_finance_screens_render_and_summarise_paid_payments(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $kept = $this->buyPlan($tenant);
        $refunded = $this->buyPlan($this->marketUser(['tenant']));
        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.payments.refund', $refunded), ['reason' => 'Refunded for the report']);

        $this->get(route('admin.payments.index'))->assertOk()->assertSee($kept->txn_id)->assertSee($refunded->txn_id);
        $this->get(route('admin.payments.index', ['status' => 'refunded']))->assertSee($refunded->txn_id)->assertDontSee($kept->txn_id);
        $this->get(route('admin.payments.show', $kept))->assertOk()->assertSee('payment.fetched');
        $this->get(route('admin.refunds.index', ['status' => 'all']))->assertOk();

        $this->get(route('admin.finance'))->assertOk()
            ->assertSee('₹234.82')                      // net revenue: only the payment that was not refunded
            ->assertSee('₹35.82');                      // GST on it
        $this->get('/admin')->assertOk()->assertSee('₹234.82');
    }

    public function test_csv_export_contains_gst_columns_and_is_safe_for_spreadsheets(): void
    {
        $payment = $this->buyPlan($this->marketUser(['tenant']));
        $payment->update(['description' => '=HYPERLINK("http://evil.example")']);

        $csv = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.payments.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString($payment->txn_id, $csv);
        $this->assertStringContainsString($payment->invoice->number, $csv);
        $this->assertStringContainsString('17.91', $csv); // CGST
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_billing_catalogue_management(): void
    {
        $this->actingAs($this->adminUser(), 'admin');

        foreach (['coupons', 'credit-packs', 'promotion-packages'] as $type) {
            $this->get(route('admin.billing.index', $type))->assertOk();
        }
        $this->get(route('admin.billing.index', 'nonsense'))->assertNotFound();

        $this->post(route('admin.billing.store', 'coupons'), ['code' => 'welcome20', 'type' => 'percent', 'value' => 20, 'applies_to' => 'all', 'per_user_limit' => 1])->assertSessionHasNoErrors();
        $this->assertSame('WELCOME20', Coupon::firstOrFail()->code);
        $this->post(route('admin.billing.store', 'coupons'), ['code' => 'WELCOME20', 'type' => 'percent', 'value' => 20, 'applies_to' => 'all', 'per_user_limit' => 1])->assertSessionHasErrors('code');
        $this->post(route('admin.billing.store', 'coupons'), ['code' => 'TOOMUCH', 'type' => 'percent', 'value' => 150, 'applies_to' => 'all', 'per_user_limit' => 1])->assertSessionHasErrors('value');

        $this->post(route('admin.billing.store', 'credit-packs'), ['name' => '100 contacts', 'credits' => 100, 'price' => 599, 'gst_percent' => 18])->assertSessionHasNoErrors();
        $pack = CreditPack::where('name', '100 contacts')->firstOrFail();
        $this->put(route('admin.billing.update', ['credit-packs', $pack->id]), ['price' => 549])->assertSessionHasNoErrors();
        $this->assertSame(549, $pack->fresh()->price);
        $this->assertFalse($pack->fresh()->is_active); // the unchecked box switches it off

        $this->post(route('admin.billing.store', 'promotion-packages'), ['name' => 'Spotlight', 'duration_days' => 30, 'price' => 2499, 'gst_percent' => 18, 'show_on_homepage' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(PromotionPackage::where('name', 'Spotlight')->first()->show_on_homepage);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'billing.coupons.created']);
    }

    public function test_billing_admin_is_super_admin_only(): void
    {
        $this->actingAs($this->adminUser('staff'), 'admin');

        foreach (['admin.payments.index', 'admin.finance', 'admin.refunds.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('admin.billing.index', 'coupons'))->assertForbidden();
    }

    public function test_customers_can_open_only_their_own_invoices(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->buyPlan($tenant);

        $this->actingAs($tenant)->get(route('billing.invoice', $payment))->assertOk()->assertSee($payment->invoice->number)->assertSee('CGST')->assertSee('SGST');
        $this->actingAs($this->marketUser(['tenant']))->get(route('billing.invoice', $payment))->assertForbidden();
        $this->actingAs($tenant)->get(route('billing.credit-note', $payment))->assertNotFound(); // no credit note until a refund
    }

    public function test_promote_page_is_for_the_owner_of_a_live_listing(): void
    {
        $owner = $this->marketUser(['owner']);
        $property = $this->published(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('promote', $property))->assertOk()->assertSee('Featured, 7 days');
        $this->actingAs($this->marketUser(['owner']))->get(route('promote', $property))->assertNotFound();
        $this->actingAs($owner)->get('/listings')->assertSee('Promote');
    }
}
