<?php

use App\Modules\Operators\Http\Controllers\PortalController;
use App\Modules\Operators\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tour operator portal (separate realm: users.user_type = operator)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/partners/register', [RegistrationController::class, 'create'])->name('operator.register');
    Route::post('/partners/register', [RegistrationController::class, 'store'])->middleware('throttle:5,10')->name('operator.register.store');
});

Route::middleware(['auth', 'usertype:operator'])->prefix('partners')->name('operator.')->controller(PortalController::class)->group(function () {
    Route::get('/', 'dashboard')->name('dashboard');
    Route::get('/availability', 'availability')->name('availability');
    Route::get('/bookings', 'bookings')->name('bookings');
    Route::get('/bookings/new', 'create')->name('bookings.create');
    Route::post('/bookings', 'store')->name('bookings.store');
    Route::get('/bookings/{booking}', 'show')->name('bookings.show');
    Route::post('/bookings/{booking}/rooming-list', 'roomingList')->name('bookings.rooming');
    Route::post('/bookings/{booking}/rooming-list/upload', 'uploadRoomingList')->name('bookings.rooming.upload');
    Route::get('/rooming-list-template.csv', 'template')->name('rooming.template');
    Route::post('/bookings/{booking}/cancel', 'cancel')->name('bookings.cancel');
    Route::get('/bookings/{booking}/voucher', 'voucher')->name('bookings.voucher');
    Route::get('/invoices', 'invoices')->name('invoices');
    Route::get('/invoices/{invoice}', 'invoice')->name('invoices.show');
    Route::post('/payments', 'payment')->name('payments.store');
    Route::get('/statement', 'statement')->name('statement');
    Route::get('/company', 'company')->name('company');
    Route::put('/company', 'updateCompany')->name('company.update');
});
