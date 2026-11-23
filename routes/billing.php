<?php

use App\Http\Controllers\Account\BillingController;
use App\Http\Controllers\Account\CheckoutController;
use App\Http\Controllers\Public\PricingController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('pricing', [PricingController::class, 'index'])->name('pricing');

// Gateway to server: authenticated by signature, not by session or CSRF token.
Route::post('webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:120,1')->name('webhooks.payments');

Route::middleware(['auth', EnsureAccountActive::class])->group(function () {
    Route::get('checkout/{purpose}/{id}', [CheckoutController::class, 'show'])
        ->whereIn('purpose', ['subscription', 'credit_pack', 'promotion'])->whereNumber('id')->name('checkout.show');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');
    Route::get('checkout/pay/{payment}', [CheckoutController::class, 'pay'])->name('checkout.pay');
    Route::post('checkout/pay/{payment}/verify', [CheckoutController::class, 'verify'])->middleware('throttle:20,1')->name('checkout.verify');
    Route::post('checkout/pay/{payment}/simulate', [CheckoutController::class, 'simulate'])->name('checkout.simulate');
    Route::post('trial/{package}', [CheckoutController::class, 'trial'])->name('trial');

    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('billing/{payment}/invoice', [BillingController::class, 'invoice'])->name('billing.invoice');
    Route::get('billing/{payment}/credit-note', [BillingController::class, 'invoice'])->defaults('type', 'credit-note')->name('billing.credit-note');
    Route::post('billing/{payment}/refund', [BillingController::class, 'requestRefund'])->name('billing.refund');
    Route::get('promote/{property}', [BillingController::class, 'promote'])->name('promote');
});
