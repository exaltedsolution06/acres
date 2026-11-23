<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    /** Server-to-server from the gateway: no session, no CSRF; authenticated by the HMAC signature. */
    public function handle(Request $request, string $gateway, PaymentService $payments)
    {
        $payments->handleWebhook($gateway, $request->getContent(), (string) $request->header('X-Razorpay-Signature'));

        return response()->json(['ok' => true]);
    }
}
