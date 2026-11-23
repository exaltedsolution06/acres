<?php

use App\Http\Controllers\Account\VerificationController;
use App\Http\Controllers\Public\PropertyReportController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAccountActive::class])->group(function () {
    Route::get('verification', [VerificationController::class, 'index'])->name('verification.index');
    Route::post('verification', [VerificationController::class, 'store'])->middleware('throttle:10,60')->name('verification.store');
    Route::get('verification/documents/{document}', [VerificationController::class, 'download'])->name('verification.download');

    Route::post('property/{property}/report', [PropertyReportController::class, 'store'])->middleware('throttle:20,60')->name('property.report');
});
