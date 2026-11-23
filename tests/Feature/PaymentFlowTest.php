<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CreditPack;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentGatewayLog;
use App\Models\Property;
use App\Models\PromotionPackage;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\EventNotification;
use App\Services\ContactCreditService;
use App\Services\PaymentService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PermissionService;
use App\Services\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\MakesProperties;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use MakesProperties, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['payments.driver' => 'fake', 'payments.company.state' => 'West Bengal', 'payments.company.name' => 'Test Co']);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        FakeGateway::$failRefunds = false;
        parent::tearDown();
    }

    private function packageId(string $slug): int
    {
        return Package::where('slug', $slug)->value('id');
    }

    private function coupon(array $attrs = []): Coupon
    {
        return Coupon::create($attrs + ['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'applies_to' => 'all', 'per_user_limit' => 1, 'is_active' => true]);
    }

    private function billing(string $state = 'West Bengal'): array
    {
        return ['name' => 'Test Customer', 'state' => $state, 'gstin' => null];
    }

    /** Checkout, then pay at the (fake) gateway and verify, the way a real customer would. */
    private function buy(User $user, string $purpose, int $itemId, ?string $coupon = null, ?Property $property = null, string $state = 'West Bengal'): Payment
    {
        $service = app(PaymentService::class);
        $payment = $service->checkout($user, $purpose, $itemId, $this->billing($state), $coupon, $property);

        if ($payment->isPaid()) {
            return $payment;
        }

        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        return $service->verifyCheckout($payment, $payment->gateway_order_id, $id, $signature);
    }

    private function openPayment(User $user, string $slug = 'tenant-basic'): Payment
    {
        return app(PaymentService::class)->checkout($user, 'subscription', $this->packageId($slug), $this->billing());
    }

    // ---------- pricing ----------

    public function test_prices_exclude_gst_and_are_computed_on_the_server(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $quote = app(PriceCalculator::class)->quote($tenant, 'subscription', $this->packageId('tenant-basic')); // ₹199 + 18%

        $this->assertSame(19900, $quote['subtotal']);
        $this->assertSame(3582, $quote['tax']);
        $this->assertSame(23482, $quote['total']);
    }

    public function test_coupons_reduce_the_taxable_amount(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $this->coupon();
        $this->coupon(['code' => 'FLAT50', 'type' => 'fixed', 'value' => 50]);
        $prices = app(PriceCalculator::class);

        $q = $prices->quote($tenant, 'subscription', $this->packageId('tenant-basic'), 'save10');
        $this->assertSame(1990, $q['discount']);
        $this->assertSame(17910 + 3224, $q['total']);

        $this->assertSame(5000, $prices->quote($tenant, 'subscription', $this->packageId('tenant-basic'), 'FLAT50')['discount']);
    }

    public function test_coupon_rules(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $id = $this->packageId('tenant-basic');
        $prices = app(PriceCalculator::class);

        $cases = [
            'inactive' => ['code' => 'OFF', 'is_active' => false],
            'expired' => ['code' => 'OLD', 'ends_at' => now()->subDay()],
            'future' => ['code' => 'SOON', 'starts_at' => now()->addDay()],
            'other purchase' => ['code' => 'PACKS', 'applies_to' => 'credit_pack'],
            'minimum' => ['code' => 'BIG', 'min_amount' => 5000],
        ];

        foreach ($cases as $label => $attrs) {
            $this->coupon($attrs);

            try {
                $prices->quote($tenant, 'subscription', $id, $attrs['code']);
                $this->fail("Coupon [{$label}] should have been refused.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('coupon', $e->errors(), $label);
            }
        }

        $this->expectException(ValidationException::class);
        $prices->quote($tenant, 'subscription', $id, 'DOESNOTEXIST');
    }

    public function test_coupon_use_limits(): void
    {
        $this->coupon(['code' => 'ONCE', 'usage_limit' => 1]);
        [$a, $b] = [$this->marketUser(['tenant']), $this->marketUser(['tenant'])];

        $this->buy($a, 'subscription', $this->packageId('tenant-basic'), 'ONCE');
        $this->assertSame(1, Coupon::first()->redemptions()->count());

        try {
            app(PriceCalculator::class)->quote($b, 'subscription', $this->packageId('tenant-basic'), 'ONCE');
            $this->fail('The total usage limit should apply.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('fully used', $e->errors()['coupon'][0]);
        }

        $this->coupon(['code' => 'PERUSER', 'per_user_limit' => 1]);
        $this->buy($b, 'subscription', $this->packageId('tenant-premium'), 'PERUSER');
        $this->expectException(ValidationException::class);
        app(PriceCalculator::class)->quote($b, 'subscription', $this->packageId('tenant-basic'), 'PERUSER');
    }

    public function test_only_plans_for_your_roles_can_be_bought(): void
    {
        $tenant = $this->marketUser(['tenant']);

        $this->expectException(ValidationException::class);
        app(PriceCalculator::class)->quote($tenant, 'subscription', $this->packageId('broker-basic'));
    }

    // ---------- checkout over HTTP ----------

    public function test_checkout_form_creates_a_payment_the_browser_cannot_price(): void
    {
        $tenant = $this->marketUser(['tenant']);

        $this->actingAs($tenant)->get(route('checkout.show', ['subscription', $this->packageId('tenant-basic')]))->assertOk()->assertSee('₹234.82');

        $this->post('/checkout', [
            'purpose' => 'subscription', 'item_id' => $this->packageId('tenant-basic'), 'billing_name' => 'Asha Roy', 'billing_state' => 'West Bengal',
            'total_paise' => 1, 'amount' => 1, 'price' => 1, // ignored: the server prices the order
        ])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertSame(23482, (int) $payment->total_paise);
        $this->assertSame('created', $payment->status);
        $this->assertNotNull($payment->gateway_order_id);
        $this->get(route('checkout.pay', $payment))->assertOk()->assertSee('Simulate successful payment');
        $this->assertSame(0, Subscription::count());
    }

    public function test_checkout_validates_billing_details(): void
    {
        $this->actingAs($this->marketUser(['tenant']));
        $base = ['purpose' => 'subscription', 'item_id' => $this->packageId('tenant-basic'), 'billing_name' => 'Asha'];

        $this->post('/checkout', $base + ['billing_state' => 'Atlantis'])->assertSessionHasErrors('billing_state');
        $this->post('/checkout', $base + ['billing_state' => 'Kerala', 'gstin' => 'NOTAGSTIN'])->assertSessionHasErrors('gstin');
        $this->post('/checkout', $base + ['billing_state' => 'Kerala', 'gstin' => '32AAAAA0000A1Z5'])->assertSessionHasNoErrors();
        $this->post('/checkout', ['purpose' => 'subscription', 'item_id' => $this->packageId('broker-basic'), 'billing_name' => 'A', 'billing_state' => 'Kerala'])->assertSessionHasErrors('item');
    }

    public function test_the_payment_page_belongs_to_its_owner(): void
    {
        $payment = $this->openPayment($this->marketUser(['tenant']));

        $this->actingAs($this->marketUser(['tenant']))->get(route('checkout.pay', $payment))->assertForbidden();
    }

    // ---------- verification ----------

    public function test_a_verified_payment_activates_the_plan_and_issues_a_numbered_gst_invoice(): void
    {
        Carbon::setTestNow('2026-08-10 10:00:00');
        $tenant = $this->marketUser(['tenant']);

        $payment = $this->buy($tenant, 'subscription', $this->packageId('tenant-basic'));

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->fulfilled_at);
        $this->assertSame(10, app(PermissionService::class)->limit($tenant, 'daily_interest')); // Basic plan
        $sub = Subscription::firstOrFail();
        $this->assertSame($payment->id, $sub->payment_id);
        $this->assertEquals(234.82, (float) $sub->price_paid);

        $invoice = $payment->invoice;
        $this->assertSame('INV/2026-27/000001', $invoice->number);
        $this->assertSame([1791, 1791, 0], [$invoice->cgst_paise, $invoice->sgst_paise, $invoice->igst_paise]); // same state: CGST + SGST
        $this->assertSame(23482, $invoice->total_paise);
        Notification::assertSentTo($tenant, EventNotification::class, fn ($n) => $n->event === 'payment.success');

        $second = $this->buy($this->marketUser(['tenant']), 'subscription', $this->packageId('tenant-basic'), null, null, 'Kerala');
        $this->assertSame('INV/2026-27/000002', $second->invoice->number);
        $this->assertSame([0, 0, 3582], [$second->invoice->cgst_paise, $second->invoice->sgst_paise, $second->invoice->igst_paise]); // other state: IGST
    }

    public function test_invoice_numbers_restart_each_financial_year(): void
    {
        Carbon::setTestNow('2027-03-31 10:00:00');
        $a = $this->buy($this->marketUser(['tenant']), 'subscription', $this->packageId('tenant-basic'));
        Carbon::setTestNow('2027-04-01 10:00:00');
        $b = $this->buy($this->marketUser(['tenant']), 'subscription', $this->packageId('tenant-basic'));

        $this->assertSame('INV/2026-27/000001', $a->invoice->number);
        $this->assertSame('INV/2027-28/000001', $b->invoice->number);
    }

    public function test_the_signed_callback_over_http_activates_the_purchase(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->openPayment($tenant);
        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        $this->actingAs($tenant)->postJson(route('checkout.verify', $payment), ['order_id' => $payment->gateway_order_id, 'payment_id' => $id, 'signature' => $signature])
            ->assertOk()->assertJsonStructure(['redirect']);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->actingAs($tenant)->get('/billing')->assertOk()->assertSee($payment->invoice->number);
    }

    public function test_a_forged_signature_activates_nothing(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->openPayment($tenant);
        [$id] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        $this->actingAs($tenant)->post(route('checkout.verify', $payment), ['order_id' => $payment->gateway_order_id, 'payment_id' => $id, 'signature' => 'forged'])->assertSessionHasErrors('payment');

        $this->assertSame('created', $payment->fresh()->status);
        $this->assertSame(0, Subscription::count());
        $this->assertTrue(PaymentGatewayLog::where('event', 'signature.invalid')->exists());
    }

    public function test_a_payment_for_a_smaller_amount_is_rejected_and_flagged(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->openPayment($tenant);
        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, 100); // paid ₹1 instead of ₹234.82

        $this->actingAs($tenant)->post(route('checkout.verify', $payment), ['order_id' => $payment->gateway_order_id, 'payment_id' => $id, 'signature' => $signature])->assertSessionHasErrors('payment');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertStringContainsString('Amount mismatch', $payment->fresh()->failure_reason);
        $this->assertSame(0, Subscription::count());
    }

    public function test_payments_that_are_not_captured_or_belong_to_another_order_do_not_count(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->openPayment($tenant);
        $other = $this->openPayment($this->marketUser(['tenant']));

        [$pending, $sigPending] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise, 'created');
        $this->actingAs($tenant)->post(route('checkout.verify', $payment), ['order_id' => $payment->gateway_order_id, 'payment_id' => $pending, 'signature' => $sigPending])->assertSessionHasErrors('payment');

        [$foreign, $sigForeign] = FakeGateway::simulatePayment($other->gateway_order_id, (int) $payment->total_paise);
        $this->post(route('checkout.verify', $payment), ['order_id' => $payment->gateway_order_id, 'payment_id' => $foreign, 'signature' => hash_hmac('sha256', $payment->gateway_order_id.'|'.$foreign, FakeGateway::SECRET)])->assertSessionHasErrors('payment');
        $this->post(route('checkout.verify', $payment), ['order_id' => $other->gateway_order_id, 'payment_id' => $foreign, 'signature' => $sigForeign])->assertSessionHasErrors('payment');

        $this->assertSame(0, Subscription::count());
    }

    public function test_only_the_buyer_can_verify_their_payment(): void
    {
        $payment = $this->openPayment($this->marketUser(['tenant']));
        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        $this->actingAs($this->marketUser(['tenant']))->post(route('checkout.verify', $payment), ['order_id' => $payment->gateway_order_id, 'payment_id' => $id, 'signature' => $signature])->assertForbidden();
    }

    public function test_verifying_twice_fulfils_once(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->buy($tenant, 'subscription', $this->packageId('tenant-basic'));
        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        $this->actingAs($tenant)->post(route('checkout.verify', $payment), ['order_id' => $payment->gateway_order_id, 'payment_id' => $id, 'signature' => $signature])->assertSessionHasNoErrors();

        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, Invoice::count());
    }

    public function test_authorised_payments_are_captured_before_fulfilment(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->openPayment($tenant);
        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise, 'authorized');

        app(PaymentService::class)->verifyCheckout($payment, $payment->gateway_order_id, $id, $signature);

        $this->assertSame('paid', $payment->fresh()->status);
    }

    // ---------- webhooks ----------

    private function webhook(string $event, array $entity, ?string $signature = null)
    {
        $payload = ['event' => $event, 'payload' => ['payment' => ['entity' => $entity]]];
        $signature ??= hash_hmac('sha256', json_encode($payload), FakeGateway::WEBHOOK_SECRET);

        return $this->postJson('/webhooks/payments/fake', $payload, ['X-Razorpay-Signature' => $signature]);
    }

    public function test_a_webhook_completes_a_payment_the_customer_never_returned_from(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->openPayment($tenant);
        [$id] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        $this->webhook('payment.captured', ['id' => $id, 'order_id' => $payment->gateway_order_id, 'amount' => $payment->total_paise])->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(10, app(PermissionService::class)->limit($tenant, 'daily_interest'));

        $this->webhook('order.paid', ['id' => $id, 'order_id' => $payment->gateway_order_id, 'amount' => $payment->total_paise])->assertOk(); // retried delivery
        $this->assertSame(1, Subscription::count());
    }

    public function test_webhooks_need_a_valid_signature(): void
    {
        $payment = $this->openPayment($this->marketUser(['tenant']));
        [$id] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);

        $this->webhook('payment.captured', ['id' => $id, 'order_id' => $payment->gateway_order_id], 'bad-signature')->assertStatus(400);

        $this->assertSame('created', $payment->fresh()->status);
        $this->assertTrue(PaymentGatewayLog::where('event', 'webhook.invalid_signature')->exists());
    }

    public function test_a_webhook_cannot_claim_a_payment_the_gateway_does_not_confirm(): void
    {
        $payment = $this->openPayment($this->marketUser(['tenant']));

        $this->webhook('payment.captured', ['id' => 'pay_never_happened', 'order_id' => $payment->gateway_order_id, 'amount' => $payment->total_paise])->assertOk();

        $this->assertSame('created', $payment->fresh()->status); // the gateway lookup found nothing
        $this->assertSame(0, Subscription::count());
    }

    public function test_failure_and_unknown_order_webhooks(): void
    {
        $payment = $this->openPayment($this->marketUser(['tenant']));

        $this->webhook('payment.failed', ['id' => 'pay_x', 'order_id' => $payment->gateway_order_id, 'error_description' => 'Card declined'])->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('Card declined', $payment->fresh()->failure_reason);

        $this->webhook('payment.captured', ['id' => 'pay_y', 'order_id' => 'order_unknown', 'amount' => 100])->assertOk();
    }

    public function test_a_failed_attempt_can_still_be_paid_on_retry(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $payment = $this->openPayment($tenant);
        $this->webhook('payment.failed', ['id' => 'pay_x', 'order_id' => $payment->gateway_order_id]);

        [$id, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);
        app(PaymentService::class)->verifyCheckout($payment->fresh(), $payment->gateway_order_id, $id, $signature);

        $this->assertSame('paid', $payment->fresh()->status);
    }

    // ---------- purchases ----------

    public function test_renewing_extends_the_plan_without_losing_paid_days(): void
    {
        Package::where('slug', 'tenant-basic')->update(['renewal_discount_percent' => 10]);
        $tenant = $this->marketUser(['tenant']);
        $this->subscribeTo($tenant, 'tenant-basic');
        $sub = Subscription::firstOrFail();
        $oldEnd = $sub->ends_at->copy();

        $payment = $this->buy($tenant, 'subscription', $this->packageId('tenant-basic'));

        $this->assertSame(1, Subscription::count());
        $this->assertTrue($sub->fresh()->ends_at->equalTo($oldEnd->copy()->addDays(30)));
        $this->assertSame(1990, (int) $payment->discount_paise); // renewal discount
        $this->assertTrue($payment->meta['renewal']);
    }

    public function test_buying_a_different_plan_replaces_the_current_one(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $this->subscribeTo($tenant, 'tenant-basic');

        $this->buy($tenant, 'subscription', $this->packageId('tenant-premium'));

        $this->assertSame(['cancelled', 'active'], Subscription::orderBy('id')->pluck('status')->all());
        $this->assertSame(25, app(PermissionService::class)->limit($tenant, 'daily_interest'));
    }

    public function test_credit_packs_add_contact_credits(): void
    {
        $tenant = $this->marketUser(['tenant']);
        $pack = CreditPack::where('name', '10 contacts')->first();

        $payment = $this->buy($tenant, 'credit_pack', $pack->id);

        $this->assertSame(10, app(ContactCreditService::class)->balance($tenant));
        $this->assertSame(9900 + 1782, (int) $payment->total_paise);
        $this->assertDatabaseHas('contact_credit_transactions', ['user_id' => $tenant->id, 'type' => 'purchase', 'amount' => 10, 'reference' => $payment->txn_id]);
    }

    public function test_a_full_discount_needs_no_gateway(): void
    {
        $this->coupon(['code' => 'FREE', 'value' => 100]);
        $tenant = $this->marketUser(['tenant']);

        $payment = $this->buy($tenant, 'credit_pack', CreditPack::first()->id, 'FREE');

        $this->assertSame('paid', $payment->status);
        $this->assertSame('none', $payment->gateway);
        $this->assertSame(0, (int) $payment->total_paise);
        $this->assertSame(10, app(ContactCreditService::class)->balance($tenant));
        $this->assertSame(0, $payment->invoice->total_paise);
    }

    public function test_promotions_feature_a_listing_and_stack(): void
    {
        $owner = $this->marketUser(['owner']);
        $mine = $this->published(['user_id' => $owner->id]);
        $boosted = $this->published(['priority' => 5]);
        $seven = PromotionPackage::where('name', 'Featured, 7 days')->first();

        $this->buy($owner, 'promotion', $seven->id, null, $mine);

        $mine = $mine->fresh();
        $this->assertTrue($mine->isFeatured());
        $this->assertTrue($mine->featured_until->between(now()->addDays(6), now()->addDays(8)));
        $this->assertNull($mine->homepage_until);
        $this->assertSame($mine->public_id, $this->resultIds($this->get('/search'))[0]); // ahead of a higher-priority listing
        $this->assertSame($boosted->public_id, $this->resultIds($this->get('/search'))[1]);
        $this->get('/search')->assertSee('Featured');

        $this->buy($owner, 'promotion', $seven->id, null, $mine);
        $this->assertTrue($mine->fresh()->featured_until->between(now()->addDays(13), now()->addDays(15)));
    }

    public function test_homepage_promotions_appear_on_the_homepage(): void
    {
        $owner = $this->marketUser(['owner']);
        $mine = $this->published(['user_id' => $owner->id, 'title' => 'Promoted lakeside apartment for family']);

        $this->get('/')->assertDontSee('Featured'); // listed under Latest, but not featured
        $this->buy($owner, 'promotion', PromotionPackage::where('show_on_homepage', true)->value('id'), null, $mine);

        $this->get('/')->assertSee('Promoted lakeside apartment')->assertSee('Featured');
    }

    public function test_only_your_own_live_listings_can_be_promoted(): void
    {
        $owner = $this->marketUser(['owner']);
        $package = PromotionPackage::first();
        $prices = app(PriceCalculator::class);

        foreach ([$this->published(), $this->draft($owner)] as $property) {
            try {
                $prices->quote($owner, 'promotion', $package->id, null, $property);
                $this->fail('This listing should not be promotable.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('property', $e->errors());
            }
        }

        $this->expectException(ValidationException::class);
        $prices->quote($owner, 'promotion', $package->id, null, null);
    }

    // ---------- trials ----------

    public function test_free_trials_start_once_per_plan(): void
    {
        Package::where('slug', 'tenant-premium')->update(['trial_days' => 14]);
        $tenant = $this->marketUser(['tenant']);
        $package = Package::where('slug', 'tenant-premium')->first();

        $this->actingAs($tenant)->post(route('trial', $package))->assertSessionHasNoErrors();
        $sub = Subscription::firstOrFail();
        $this->assertSame('trial', $sub->source);
        $this->assertTrue($sub->ends_at->between(now()->addDays(13), now()->addDays(15)));
        $this->assertSame(25, app(PermissionService::class)->limit($tenant, 'daily_interest'));

        $this->post(route('trial', $package))->assertSessionHasErrors('trial');
        $this->post(route('trial', Package::where('slug', 'tenant-basic')->first()))->assertSessionHasErrors('trial'); // no trial defined
        $this->actingAs($this->marketUser(['buyer']))->post(route('trial', $package))->assertSessionHasErrors('trial'); // wrong role
    }

    // ---------- pricing page, reminders, safety ----------

    public function test_the_pricing_page_lists_plans_for_the_viewers_roles(): void
    {
        $this->get('/pricing')->assertOk()->assertSee('Log in to see the plans');
        $this->actingAs($this->marketUser(['tenant']))->get('/pricing')->assertOk()->assertSee('Tenant plans')->assertSee('Premium')->assertDontSee('Broker plans');
    }

    public function test_plan_expiry_reminders_are_sent_once_and_skip_trials(): void
    {
        $paid = $this->marketUser(['tenant']);
        $this->subscribeTo($paid, 'tenant-basic');
        Subscription::query()->update(['ends_at' => now()->addDays(3)]);

        $trialUser = $this->marketUser(['tenant']);
        app(\App\Services\SubscriptionService::class)->subscribe($trialUser, Package::where('slug', 'tenant-premium')->first(), null, 'trial', 0);
        Subscription::where('source', 'trial')->update(['ends_at' => now()->addDays(2)]);

        $this->artisan('marketplace:send-subscription-reminders')->assertSuccessful();
        $this->artisan('marketplace:send-subscription-reminders')->assertSuccessful();

        Notification::assertSentToTimes($paid, EventNotification::class, 1);
        Notification::assertNotSentTo($trialUser, EventNotification::class);
    }

    public function test_the_fake_gateway_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(\RuntimeException::class);
            app(PaymentGatewayManager::class)->gateway('fake');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_gateway_secrets_are_never_stored_with_the_order(): void
    {
        config(['payments.razorpay.key_secret' => 'super-secret-value']);
        $payment = $this->openPayment($this->marketUser(['tenant']));

        $this->assertStringNotContainsString('super-secret-value', json_encode($payment->fresh()->toArray()));
        $this->assertStringNotContainsString('super-secret-value', json_encode(PaymentGatewayLog::all()->toArray()));
    }
}
