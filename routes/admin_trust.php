<?php

use App\Http\Controllers\Admin\ReportAdminController;
use App\Http\Controllers\Admin\VerificationAdminController;
use App\Http\Middleware\AdminAuthenticate;
use Illuminate\Support\Facades\Route;

// Staff and super admins both review verifications and reports.
Route::prefix('admin')->name('admin.')->middleware(AdminAuthenticate::class)->group(function () {
    Route::get('verifications', [VerificationAdminController::class, 'index'])->name('verifications.index');
    Route::get('verifications/documents/{document}', [VerificationAdminController::class, 'download'])->name('verifications.download');
    Route::get('verifications/{verification}', [VerificationAdminController::class, 'show'])->name('verifications.show');
    Route::post('verifications/{verification}/approve', [VerificationAdminController::class, 'approve'])->name('verifications.approve');
    Route::post('verifications/{verification}/reject', [VerificationAdminController::class, 'reject'])->name('verifications.reject');
    Route::post('verifications/{verification}/revoke', [VerificationAdminController::class, 'revoke'])->name('verifications.revoke');

    Route::get('reports', [ReportAdminController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportAdminController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/dismiss', [ReportAdminController::class, 'dismiss'])->name('reports.dismiss');
    Route::post('reports/{report}/action', [ReportAdminController::class, 'action'])->name('reports.action');
});
