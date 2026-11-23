<?php

use App\Http\Controllers\Admin\NotificationSettingController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware([AdminAuthenticate::class, EnsureSuperAdmin::class])->group(function () {
    Route::get('notification-settings', [NotificationSettingController::class, 'index'])->name('notifications.index');
    Route::put('notification-settings', [NotificationSettingController::class, 'update'])->name('notifications.update');
});
