<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\PropertyModerationController;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(AdminAuthenticate::class)->group(function () {
    // Moderation: super admin and staff
    Route::get('properties', [PropertyModerationController::class, 'index'])->name('properties.index');
    Route::get('properties/{property}', [PropertyModerationController::class, 'show'])->name('properties.show');
    Route::post('properties/{property}/approve', [PropertyModerationController::class, 'approve'])->name('properties.approve');
    Route::post('properties/{property}/reject', [PropertyModerationController::class, 'reject'])->name('properties.reject');

    // Catalogue (categories, amenities, locations): super admin only
    Route::middleware(EnsureSuperAdmin::class)->prefix('catalog')->name('catalog.')->group(function () {
        Route::get('{type}', [CatalogController::class, 'index'])->name('index');
        Route::post('{type}', [CatalogController::class, 'store'])->name('store');
        Route::put('{type}/{id}', [CatalogController::class, 'update'])->name('update');
    });
});
