<?php

use App\Http\Controllers\Account\ListingController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PropertyController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

$verbs = implode('|', array_keys(config('property.url_verbs')));

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('search', [SearchController::class, 'search'])->middleware('throttle:60,1')->name('search');

Route::get('property/{slug}', [PropertyController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('property.show');

// SEO landing pages: /buy-property-in-kolkata, /rent-property-in-salt-lake-kolkata, /buy-property-in-kolkata/apartment-flat
Route::get('{verb}-property-in-{area}', [SearchController::class, 'landing'])
    ->where(['verb' => $verbs, 'area' => '[a-z0-9-]+'])->name('landing');
Route::get('{verb}-property-in-{area}/{category}', [SearchController::class, 'categoryLanding'])
    ->where(['verb' => $verbs, 'area' => '[a-z0-9-]+', 'category' => '[a-z0-9-]+'])->name('landing.category');

Route::middleware(['auth', EnsureAccountActive::class])->group(function () {
    Route::post('property/{property}/contact', [PropertyController::class, 'contact'])->middleware('throttle:20,1')->name('property.contact');

    Route::post('role/add', [ListingController::class, 'addRole'])->name('role.add');

    Route::prefix('listings')->name('listings.')->group(function () {
        Route::get('/', [ListingController::class, 'index'])->name('index');
        Route::get('create', [ListingController::class, 'create'])->name('create');
        Route::post('/', [ListingController::class, 'store'])->name('store');
        Route::get('{property}/edit', [ListingController::class, 'edit'])->name('edit');
        Route::put('{property}', [ListingController::class, 'update'])->name('update');
        Route::delete('{property}', [ListingController::class, 'destroy'])->name('destroy');

        foreach (['submit', 'pause', 'resume', 'sold', 'rented', 'renew', 'archive'] as $action) {
            Route::post("{property}/{$action}", [ListingController::class, $action])->name($action);
        }

        Route::post('{property}/images', [ListingController::class, 'addImages'])->name('images.add');
        Route::delete('{property}/images/{image}', [ListingController::class, 'removeImage'])->name('images.remove');
        Route::post('{property}/images/{image}/cover', [ListingController::class, 'coverImage'])->name('images.cover');
        Route::post('{property}/video', [ListingController::class, 'setVideo'])->name('video.set');
        Route::delete('{property}/video', [ListingController::class, 'removeVideo'])->name('video.remove');
    });
});
