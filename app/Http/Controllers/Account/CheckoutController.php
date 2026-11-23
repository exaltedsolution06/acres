<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PromotionPackage;
use App\Models\Subscription;
use App\Services\PaymentService;
use App\Services\Payments\FakeGateway;
use App\Services\PriceCalculator;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(private PaymentService $payments, private PriceCalculator $prices)
    {
    }

    public function show(Request $request, string $purpose, int $id)
    {
        $user = $request->user();
        $property = $request->filled('property') ? Property::where('public_id', $request->query('property'))->first() : null;

        try {
            $quote = $this->prices->quote($user, $purpose, $id, $request->query('coupon'), $property);
        } catch (ValidationException $e) {
            // Show the plan without a coupon, with the reason the coupon was refused.
            $quote = $this->prices->quote($user, $purpose, $id, null, $property);
            session()->now('couponError', collect($e->errors())->flatten()->first());
        }

        return view('billing.checkout', [
            'purpose' => $purpose, 'itemId' => $id, 'quote' => $quote, 'property' => $property,
            'coupon' => $request->query('coupon'), 'states' => config('payments.states'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purpose' => ['required', Rule::in(['subscription', 'credit_pack', 'promotion'])],
            'item_id' => ['required', 'integer'],
            'property' => ['nullable', 'string', 'size:8'],
            'coupon' => ['nullable', 'string', 'max:40'],
            'billing_name' => ['required', 'string', 'max:120'],
            'billing_state' => ['required', Rule::in(config('payments.states'))],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
        ]);

        $property = ! empty($data['property']) ? Property::where('public_id', $data['property'])->first() : null;

        $payment = $this->payments->checkout($request->user(), $data['purpose'], (int) $data['item_id'], [
            'name' => $data['billing_name'], 'state' => $data['billing_state'], 'gstin' => $data['gstin'] ?? null,
        ], $data['coupon'] ?? null, $property);

        return $payment->isPaid()
            ? redirect()->route('billing.index')->with('status', 'Done. Your purchase is active.')
            : redirect()->route('checkout.pay', $payment);
    }

    public function pay(Request $request, Payment $payment)
    {
        $this->authorizeOwner($request, $payment);

        if ($payment->isPaid()) {
            return redirect()->route('billing.index');
        }

        abort_unless($payment->status === 'created' && $payment->gateway_order_id, 404);

        return view('billing.pay', ['payment' => $payment, 'checkout' => $payment->meta['checkout'] ?? [], 'user' => $request->user()]);
    }

    /** Called by the gateway's checkout script after payment. The result is verified server-side before anything is activated. */
    public function verify(Request $request, Payment $payment)
    {
        $this->authorizeOwner($request, $payment);
        $data = $request->validate(['order_id' => ['required', 'string'], 'payment_id' => ['required', 'string'], 'signature' => ['required', 'string']]);

        $this->payments->verifyCheckout($payment, $data['order_id'], $data['payment_id'], $data['signature']);

        return $request->expectsJson()
            ? response()->json(['redirect' => route('billing.index')])
            : redirect()->route('billing.index')->with('status', 'Payment received. Your purchase is active.');
    }

    /** Local development only: stands in for the customer paying at the gateway. */
    public function simulate(Request $request, Payment $payment)
    {
        abort_unless(config('payments.driver') === 'fake' && ! app()->isProduction(), 404);
        $this->authorizeOwner($request, $payment);

        [$paymentId, $signature] = FakeGateway::simulatePayment($payment->gateway_order_id, (int) $payment->total_paise);
        $this->payments->verifyCheckout($payment, $payment->gateway_order_id, $paymentId, $signature);

        return redirect()->route('billing.index')->with('status', 'Simulated payment received.');
    }

    /** Free trial: once per plan, only when the plan defines trial days. */
    public function trial(Request $request, Package $package, SubscriptionService $subscriptions)
    {
        $user = $request->user();

        if ($package->status !== 'active' || $package->trial_days < 1 || ! $user->roles()->where('roles.id', $package->role_id)->exists()) {
            throw ValidationException::withMessages(['trial' => 'No free trial is available for this plan.']);
        }

        if (Subscription::where('user_id', $user->id)->where('package_id', $package->id)->exists()) {
            throw ValidationException::withMessages(['trial' => 'You have already used this plan.']);
        }

        $subscriptions->subscribe($user, $package, null, 'trial', 0);

        return redirect()->route('billing.index')->with('status', "Your {$package->trial_days}-day free trial has started.");
    }

    private function authorizeOwner(Request $request, Payment $payment): void
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
    }
}
