<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'registerWithEmail'])->name('register.email');
    Route::post('register/mobile', [RegisterController::class, 'startMobile'])->name('register.mobile');
    Route::get('register/verify', [RegisterController::class, 'showVerify'])->name('register.verify');
    Route::post('register/verify', [RegisterController::class, 'verify'])->name('register.verify.submit');
    Route::post('register/resend', [RegisterController::class, 'resend'])->name('register.resend');

    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.submit');
    Route::post('login/otp', [LoginController::class, 'sendOtp'])->name('login.otp.send');
    Route::get('login/otp', [LoginController::class, 'showOtp'])->name('login.otp');
    Route::post('login/otp/verify', [LoginController::class, 'verifyOtp'])->name('login.otp.verify');

    Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')->name('verification.verify');

Route::middleware(['auth', EnsureAccountActive::class])->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/{role}', [DashboardController::class, 'show'])->whereIn('role', config('marketplace.roles'))->name('dashboard.role');
    Route::post('role/switch', [DashboardController::class, 'switchRole'])->name('role.switch');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/mobile', [ProfileController::class, 'sendMobileOtp'])->name('profile.mobile.send');
    Route::post('profile/mobile/verify', [ProfileController::class, 'verifyMobile'])->name('profile.mobile.verify');
});

// Public site, listings and SEO pages (Milestone 4). Registered after the fixed routes above.
require __DIR__.'/properties.php';
require __DIR__.'/admin.php';
require __DIR__.'/admin_properties.php';
require __DIR__.'/engagement.php';
require __DIR__.'/admin_engagement.php';
require __DIR__.'/billing.php';
require __DIR__.'/admin_billing.php';
