<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');

    Route::middleware(AdminAuthenticate::class)->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Users (super admin and staff)
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show')->withTrashed();
        Route::post('users/{user}/status', [UserController::class, 'status'])->name('users.status');
        Route::post('users/{user}/roles', [UserController::class, 'addRole'])->name('users.roles.add');
        Route::delete('users/{user}/roles/{role}', [UserController::class, 'removeRole'])->name('users.roles.remove');
        Route::post('users/{user}/subscription', [UserController::class, 'grantSubscription'])->name('users.subscription');
        Route::post('users/{user}/bonus', [UserController::class, 'grantBonus'])->name('users.bonus');
        Route::post('users/{user}/reset-usage', [UserController::class, 'resetUsage'])->name('users.reset-usage');
        Route::post('users/{user}/credits', [UserController::class, 'grantCredits'])->name('users.credits');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');

        // Business configuration: super admin only
        Route::middleware(EnsureSuperAdmin::class)->group(function () {
            Route::resource('packages', PackageController::class)->except('show');
            Route::post('packages/{package}/duplicate', [PackageController::class, 'duplicate'])->name('packages.duplicate');
            Route::post('packages/{package}/toggle', [PackageController::class, 'toggle'])->name('packages.toggle');

            Route::resource('features', FeatureController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        });
    });
});
