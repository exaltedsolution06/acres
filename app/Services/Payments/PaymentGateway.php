<?php

namespace App\Services\Payments;

use App\Models\Payment;

/** Everything the payment flow needs from a gateway. Razorpay is implemented; Cashfree can follow the same contract. */
interface PaymentGateway
{
    public function name(): string;

    /** Create the order at the gateway for this payment. @return array{order_id: string, checkout: array<string, mixed>} */
    public function createOrder(Payment $payment): array;

    /** Verify the signature the browser handed back after paying. */
    public function verifyCheckoutSignature(string $orderId, string $paymentId, string $signature): bool;

    /** Ask the gateway directly what happened. @return array{id: string, status: string, amount: int, currency: string, order_id: ?string} */
    public function fetchPayment(string $paymentId): array;

    /** Capture an authorised payment. @return array{id: string, status: string, amount: int, currency: string, order_id: ?string} */
    public function capture(string $paymentId, int $amountPaise, string $currency): array;

    public function verifyWebhookSignature(string $body, string $signature): bool;

    /** @return array{id: string, status: string} */
    public function refund(string $paymentId, int $amountPaise, string $note): array;
}
