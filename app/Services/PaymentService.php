<?php

namespace App\Services;

use App\Models\CouponRedemption;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentGatewayLog;
use App\Models\Property;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Orders, verification and fulfilment. Nothing is activated from the browser's word alone:
 * every route to "paid" goes through markPaid() after the gateway itself has confirmed the amount.
 */
class PaymentService
{
    public function __construct(
        private PriceCalculator $prices,
        private PaymentGatewayManager $gateways,
        private InvoiceService $invoices,
        private SubscriptionService $subscriptions,
        private ContactCreditService $credits,
        private PromotionService $promotions,
        private Notifier $notifier,
    ) {
    }

    /** Price the purchase on the server, record the payment and create the gateway order. */
    public function checkout(User $user, string $purpose, int $itemId, array $billing, ?string $couponCode = null, ?Property $property = null): Payment
    {
        $quote = $this->prices->quote($user, $purpose, $itemId, $couponCode, $property);
        $free = $quote['total'] === 0; // a 100% coupon needs no gateway
        $gateway = $free ? null : $this->gateways->gateway();

        $payment = Payment::create([
            'txn_id' => $this->newTxnId(),
            'user_id' => $user->id, 'purpose' => $purpose, 'item_id' => $itemId, 'property_id' => $property?->id,
            'description' => $quote['description'],
            'subtotal_paise' => $quote['subtotal'], 'discount_paise' => $quote['discount'], 'tax_paise' => $quote['tax'],
            'total_paise' => $quote['total'], 'gst_percent' => $quote['gst'], 'currency' => config('payments.currency'),
            'coupon_id' => $quote['coupon']?->id, 'status' => 'created', 'gateway' => $gateway?->name() ?? 'none',
            'billing' => $billing, 'meta' => $quote['meta'],
        ]);

        if ($free) {
            return $this->markPaid($payment, 'free-'.$payment->txn_id, 0);
        }

        $order = $gateway->createOrder($payment);
        $payment->update(['gateway_order_id' => $order['order_id'], 'meta' => ($payment->meta ?? []) + ['checkout' => $order['checkout']]]);
        $this->log($payment, 'order.created', ['order_id' => $order['order_id']]);

        return $payment;
    }

    /** The browser says it paid: check the signature, then ask the gateway itself. */
    public function verifyCheckout(Payment $payment, string $orderId, string $paymentId, string $signature): Payment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        if ($payment->status === 'refunded' || $payment->gateway_order_id !== $orderId) {
            throw ValidationException::withMessages(['payment' => 'This payment does not match the order.']);
        }

        $gateway = $this->gateways->gateway($payment->gateway);

        if (! $gateway->verifyCheckoutSignature($orderId, $paymentId, $signature)) {
            $this->log($payment, 'signature.invalid', ['payment_id' => $paymentId]);

            throw ValidationException::withMessages(['payment' => 'Payment verification failed. If money was deducted it will be refunded.']);
        }

        return $this->confirmWithGateway($payment, $gateway, $paymentId);
    }

    /** Webhook entry point (also the safety net when the customer closes the tab after paying). */
    public function handleWebhook(string $gatewayName, string $rawBody, string $signature): void
    {
        $gateway = $this->gateways->gateway($gatewayName);

        if (! $gateway->verifyWebhookSignature($rawBody, $signature)) {
            PaymentGatewayLog::create(['gateway' => $gatewayName, 'event' => 'webhook.invalid_signature', 'payload' => ['ip' => request()->ip()]]);

            throw new HttpException(400, 'Invalid signature.');
        }

        $data = json_decode($rawBody, true) ?: [];
        $event = $data['event'] ?? 'unknown';
        $entity = $data['payload']['payment']['entity'] ?? null;
        $payment = $entity ? Payment::where('gateway_order_id', $entity['order_id'] ?? '__none__')->first() : null;

        $this->log($payment, 'webhook.'.$event, ['entity_id' => $entity['id'] ?? null], $gatewayName);

        if (in_array($event, ['payment.captured', 'order.paid'], true) && $payment && $entity) {
            try {
                $this->confirmWithGateway($payment, $gateway, $entity['id']);
            } catch (ValidationException $e) {
                $this->log($payment, 'webhook.rejected', $e->errors(), $gatewayName);
            }
        } elseif ($event === 'payment.failed' && $payment) {
            $this->markFailed($payment, $entity['error_description'] ?? 'Payment failed at the gateway.');
        } elseif ($event === 'refund.processed') {
            $refundId = $data['payload']['refund']['entity']['id'] ?? null;
            Refund::where('gateway_refund_id', $refundId)->whereNull('processed_at')->update(['processed_at' => now(), 'status' => 'processed']);
        }
    }

    public function markFailed(Payment $payment, string $reason): void
    {
        Payment::whereKey($payment->id)->where('status', 'created')->update(['status' => 'failed', 'failure_reason' => mb_substr($reason, 0, 300)]);
    }

    /** Idempotent: calling it again for a paid payment changes nothing. Fulfilment is atomic with the status change. */
    public function markPaid(Payment $payment, string $gatewayPaymentId, int $amountPaise): Payment
    {
        $newlyPaid = false;

        $paid = DB::transaction(function () use ($payment, $gatewayPaymentId, $amountPaise, &$newlyPaid) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (in_array($p->status, ['paid', 'refunded'], true)) {
                return $p;
            }

            if ($amountPaise !== (int) $p->total_paise) {
                throw ValidationException::withMessages(['payment' => 'The amount paid does not match the order.']);
            }

            $p->forceFill(['status' => 'paid', 'paid_at' => now(), 'gateway_payment_id' => $gatewayPaymentId, 'failure_reason' => null])->save();
            $this->fulfil($p);
            $p->forceFill(['fulfilled_at' => now()])->save();
            $this->invoices->issueForPayment($p);

            if ($p->coupon_id) {
                CouponRedemption::firstOrCreate(['payment_id' => $p->id], ['coupon_id' => $p->coupon_id, 'user_id' => $p->user_id]);
            }

            $newlyPaid = true;

            return $p;
        });

        if ($newlyPaid) {
            $this->notifier->send($paid->user, 'payment.success', 'Payment received', "{$paid->description}: {$paid->total()} paid. Thank you.", route('billing.index'));
        }

        return $paid;
    }

    private function confirmWithGateway(Payment $payment, PaymentGateway $gateway, string $paymentId): Payment
    {
        $remote = $gateway->fetchPayment($paymentId);
        $this->log($payment, 'payment.fetched', $remote);

        if (($remote['order_id'] ?? null) !== $payment->gateway_order_id) {
            throw ValidationException::withMessages(['payment' => 'This payment belongs to a different order.']);
        }

        if ($remote['status'] === 'authorized') {
            $remote = $gateway->capture($paymentId, (int) $payment->total_paise, $payment->currency);
        }

        if ($remote['status'] !== 'captured') {
            throw ValidationException::withMessages(['payment' => 'The payment has not been completed.']);
        }

        if ($remote['amount'] !== (int) $payment->total_paise || $remote['currency'] !== $payment->currency) {
            $this->log($payment, 'amount.mismatch', $remote);
            Payment::whereKey($payment->id)->where('status', 'created')->update(['status' => 'failed', 'failure_reason' => 'Amount mismatch: needs manual review and refund.']);

            throw ValidationException::withMessages(['payment' => 'The amount paid does not match the order.']);
        }

        return $this->markPaid($payment, $paymentId, $remote['amount']);
    }

    private function fulfil(Payment $payment): void
    {
        $meta = $payment->meta ?? [];

        switch ($payment->purpose) {
            case 'subscription':
                $package = Package::withTrashed()->with('role')->findOrFail($payment->item_id);
                $active = $this->subscriptions->activeFor($payment->user, $package->role->slug);
                $paidRupees = $payment->total_paise / 100;

                if ($active && $active->package_id === $package->id) {
                    $subscription = $this->subscriptions->renew($active, $package, $payment->id, $paidRupees);
                    $meta['renewal'] = true;
                } else {
                    $subscription = $this->subscriptions->subscribe($payment->user, $package, $payment->id, 'purchase', $paidRupees);
                    $meta['renewal'] = false;
                }

                $meta['subscription_id'] = $subscription->id;
                break;

            case 'credit_pack':
                $this->credits->add($payment->user, (int) $meta['credits'], 'purchase', $payment->txn_id);
                break;

            case 'promotion':
                $meta['promotion_id'] = $this->promotions->activate($payment)->id;
                break;
        }

        $payment->forceFill(['meta' => $meta])->save();
    }

    private function newTxnId(): string
    {
        do {
            $id = 'TXN'.now()->format('ymd').strtoupper(Str::random(8));
        } while (Payment::where('txn_id', $id)->exists());

        return $id;
    }

    private function log(?Payment $payment, string $event, array $payload = [], ?string $gateway = null): void
    {
        PaymentGatewayLog::create(['payment_id' => $payment?->id, 'gateway' => $gateway ?? $payment?->gateway ?? 'unknown', 'event' => $event, 'payload' => $payload]);
    }
}
