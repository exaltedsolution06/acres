<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay Orders + Checkout. The browser result is never trusted on its own: the signature is checked
 * and the payment is fetched from Razorpay's API before anything is activated.
 */
class RazorpayGateway implements PaymentGateway
{
    public function __construct(private array $config)
    {
    }

    public function name(): string
    {
        return 'razorpay';
    }

    public function createOrder(Payment $payment): array
    {
        $order = $this->http()->post('/orders', [
            'amount' => $payment->total_paise,
            'currency' => $payment->currency,
            'receipt' => $payment->txn_id,
            'notes' => ['txn_id' => $payment->txn_id, 'purpose' => $payment->purpose],
        ])->throw()->json();

        return [
            'order_id' => $order['id'],
            'checkout' => ['key' => $this->config['key_id'], 'amount' => $payment->total_paise, 'currency' => $payment->currency],
        ];
    }

    public function verifyCheckoutSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) $this->config['key_secret']);

        return hash_equals($expected, $signature);
    }

    public function fetchPayment(string $paymentId): array
    {
        return $this->normalise($this->http()->get('/payments/'.rawurlencode($paymentId))->throw()->json());
    }

    public function capture(string $paymentId, int $amountPaise, string $currency): array
    {
        return $this->normalise($this->http()->post('/payments/'.rawurlencode($paymentId).'/capture', [
            'amount' => $amountPaise, 'currency' => $currency,
        ])->throw()->json());
    }

    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    public function refund(string $paymentId, int $amountPaise, string $note): array
    {
        $refund = $this->http()->post('/payments/'.rawurlencode($paymentId).'/refund', [
            'amount' => $amountPaise, 'notes' => ['reason' => mb_substr($note, 0, 200)],
        ])->throw()->json();

        return ['id' => $refund['id'], 'status' => $refund['status'] ?? 'pending'];
    }

    private function http(): PendingRequest
    {
        if (empty($this->config['key_id']) || empty($this->config['key_secret'])) {
            throw new RuntimeException('Razorpay keys are not configured (RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET).');
        }

        return Http::baseUrl($this->config['api_base'])->withBasicAuth($this->config['key_id'], $this->config['key_secret'])->acceptJson()->timeout(20);
    }

    private function normalise(array $p): array
    {
        return [
            'id' => $p['id'], 'status' => $p['status'], 'amount' => (int) $p['amount'],
            'currency' => $p['currency'] ?? 'INR', 'order_id' => $p['order_id'] ?? null,
        ];
    }
}
