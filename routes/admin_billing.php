<?php

use App\Http\Controllers\Admin\BillingCatalogController;
use App\Http\Controllers\Admin\PaymentAdminController;
use App\Http\Controllers\Admin\RefundAdminController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware([AdminAuthenticate::class, EnsureSuperAdmin::class])->group(function () {
    Route::get('payments', [PaymentAdminController::class, 'index'])->name('payments.index');
    Route::get('payments/export', [PaymentAdminController::class, 'export'])->name('payments.export');
    Route::get('payments/{payment}', [PaymentAdminController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/refund', [PaymentAdminController::class, 'refund'])->name('payments.refund');
    Route::get('finance', [PaymentAdminController::class, 'finance'])->name('finance');

    Route::get('refunds', [RefundAdminController::class, 'index'])->name('refunds.index');
    Route::post('refunds/{refund}/approve', [RefundAdminController::class, 'approve'])->name('refunds.approve');
    Route::post('refunds/{refund}/reject', [RefundAdminController::class, 'reject'])->name('refunds.reject');

    Route::get('billing/{type}', [BillingCatalogController::class, 'index'])->name('billing.index');
    Route::post('billing/{type}', [BillingCatalogController::class, 'store'])->name('billing.store');
    Route::put('billing/{type}/{id}', [BillingCatalogController::class, 'update'])->name('billing.update');
});
