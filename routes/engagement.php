<?php

use App\Http\Controllers\Account\EnquiryController;
use App\Http\Controllers\Account\LeadController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\RequirementController;
use App\Http\Controllers\Account\SavedController;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAccountActive::class])->group(function () {
    // Seeker: interest, enquiries, visits
    Route::post('property/{property}/interest', [EnquiryController::class, 'interest'])->middleware('throttle:30,1')->name('property.interest');
    Route::post('property/{property}/enquire', [EnquiryController::class, 'enquire'])->middleware('throttle:30,1')->name('property.enquire');
    Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::post('enquiries/{enquiry}/close', [EnquiryController::class, 'close'])->name('enquiries.close');
    Route::post('enquiries/{enquiry}/visit', [EnquiryController::class, 'requestVisit'])->name('enquiries.visit');
    Route::post('visits/{visit}/{action}', [EnquiryController::class, 'visitAction'])->whereIn('action', ['confirm', 'decline', 'complete', 'cancel'])->name('visits.action');

    // Poster: leads and the broker CRM
    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/', [LeadController::class, 'index'])->name('index');
        Route::get('performance', [LeadController::class, 'performance'])->name('performance');
        Route::get('{enquiry}', [LeadController::class, 'show'])->name('show');
        Route::post('{enquiry}/status', [LeadController::class, 'status'])->name('status');
        Route::post('{enquiry}/notes', [LeadController::class, 'note'])->name('note');
        Route::post('{enquiry}/follow-up', [LeadController::class, 'followUp'])->name('follow-up');
        Route::post('{enquiry}/visit', [LeadController::class, 'scheduleVisit'])->name('visit');
        Route::post('{enquiry}/contact', [LeadController::class, 'contact'])->middleware('throttle:20,1')->name('contact');
    });

    // Requirements marketplace
    Route::prefix('requirements')->name('requirements.')->group(function () {
        Route::get('/', [RequirementController::class, 'index'])->name('index');
        Route::get('create', [RequirementController::class, 'create'])->name('create');
        Route::post('/', [RequirementController::class, 'store'])->name('store');
        Route::get('browse', [RequirementController::class, 'browse'])->name('browse');
        Route::get('{requirement}', [RequirementController::class, 'show'])->whereNumber('requirement')->name('show');
        Route::post('{requirement}/close', [RequirementController::class, 'close'])->name('close');
        Route::post('{requirement}/respond', [RequirementController::class, 'respond'])->name('respond');
        Route::post('{requirement}/contact', [RequirementController::class, 'contactOwner'])->middleware('throttle:20,1')->name('contact');
        Route::post('{requirement}/responses/{response}/contact', [RequirementController::class, 'contactResponder'])->middleware('throttle:20,1')->name('responses.contact');
    });

    // Saved properties and searches
    Route::get('saved', [SavedController::class, 'properties'])->name('saved.properties');
    Route::post('property/{property}/save', [SavedController::class, 'save'])->name('property.save');
    Route::delete('property/{property}/save', [SavedController::class, 'unsave'])->name('property.unsave');
    Route::get('saved-searches', [SavedController::class, 'searches'])->name('saved.searches');
    Route::post('saved-searches', [SavedController::class, 'storeSearch'])->name('saved.searches.store');
    Route::delete('saved-searches/{search}', [SavedController::class, 'destroySearch'])->name('saved.searches.destroy');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
});
