<?php

namespace App\Services\Payments;

use RuntimeException;

class PaymentGatewayManager
{
    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= config('payments.driver');

        return match ($name) {
            'razorpay' => new RazorpayGateway(config('payments.razorpay')),
            'fake' => app()->isProduction() ? throw new RuntimeException('PAYMENT_DRIVER=fake is not allowed in production.') : new FakeGateway,
            default => throw new RuntimeException("Unknown payment gateway [{$name}]."),
        };
    }

    public function activeName(): string
    {
        return $this->gateway()->name();
    }
}
