<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A stand-in gateway for local development and tests. It keeps "remote" payments in the cache,
 * so verification still goes through the same server-side checks as a real gateway.
 */
class FakeGateway implements PaymentGateway
{
    public const SECRET = 'fake-checkout-secret';
    public const WEBHOOK_SECRET = 'fake-webhook-secret';

    public static bool $failRefunds = false;

    public function name(): string
    {
        return 'fake';
    }

    public function createOrder(Payment $payment): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('PAYMENT_DRIVER=fake is not allowed in production.');
        }

        return ['order_id' => 'order_fake_'.Str::lower(Str::random(12)), 'checkout' => ['fake' => true, 'amount' => $payment->total_paise, 'currency' => $payment->currency]];
    }

    /** What a customer paying at the gateway would produce. Returns [payment_id, signature]. */
    public static function simulatePayment(string $orderId, int $amountPaise, string $status = 'captured', string $currency = 'INR'): array
    {
        $id = 'pay_fake_'.Str::lower(Str::random(12));
        Cache::put('fakegw:'.$id, ['id' => $id, 'status' => $status, 'amount' => $amountPaise, 'currency' => $currency, 'order_id' => $orderId], 3600);

        return [$id, hash_hmac('sha256', $orderId.'|'.$id, self::SECRET)];
    }

    public function verifyCheckoutSignature(string $orderId, string $paymentId, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, self::SECRET), $signature);
    }

    public function fetchPayment(string $paymentId): array
    {
        return Cache::get('fakegw:'.$paymentId) ?? ['id' => $paymentId, 'status' => 'not_found', 'amount' => 0, 'currency' => 'INR', 'order_id' => null];
    }

    public function capture(string $paymentId, int $amountPaise, string $currency): array
    {
        $payment = $this->fetchPayment($paymentId);
        $payment['status'] = 'captured';
        Cache::put('fakegw:'.$paymentId, $payment, 3600);

        return $payment;
    }

    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $body, self::WEBHOOK_SECRET), $signature);
    }

    public function refund(string $paymentId, int $amountPaise, string $note): array
    {
        if (self::$failRefunds) {
            throw new RuntimeException('The gateway rejected the refund.');
        }

        return ['id' => 'rfnd_fake_'.Str::lower(Str::random(10)), 'status' => 'processed'];
    }
}
