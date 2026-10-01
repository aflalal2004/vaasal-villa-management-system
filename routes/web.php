<?php

use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\PasswordController;
use App\Modules\Billing\Http\Controllers\WebhookController;
use App\Modules\Channel\Http\Controllers\ChannelWebhookController;
use App\Modules\Core\Http\Controllers\ProfileController;
use App\Modules\Website\Http\Controllers\BookingFlowController;
use App\Modules\Website\Http\Controllers\CurrencyController;
use App\Modules\Website\Http\Controllers\ManageBookingController;
use App\Modules\Website\Http\Controllers\PublicPaymentController;
use App\Modules\Website\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer website (public)
|--------------------------------------------------------------------------
*/
Route::controller(SiteController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/about', 'about')->name('site.about');
    Route::get('/villas', 'villas')->name('site.villas');
    Route::get('/villas/{type:slug}', 'villa')->name('site.villa');
    Route::get('/services', 'services')->name('site.services');
    Route::get('/gallery', 'gallery')->name('site.gallery');
    Route::get('/offers', 'offers')->name('site.offers');
    Route::get('/contact', 'contact')->name('site.contact');
    Route::post('/contact', 'enquiry')->middleware('throttle:5,10')->name('site.enquiry');
    Route::get('/weather', 'weather')->middleware('throttle:30,1')->name('site.weather');
});

// Display currency (prices stay in LKR)
Route::post('/currency', [CurrencyController::class, 'switch'])->middleware('throttle:30,1')->name('currency.switch');

Route::controller(BookingFlowController::class)->prefix('book')->name('book.')->group(function () {
    Route::get('/', 'search')->name('search');
    Route::get('/details', 'details')->name('details');
    Route::post('/hold', 'hold')->middleware('throttle:10,1')->name('hold');
    Route::get('/confirmation/{token}', 'confirmation')->name('confirmation');
});
Route::controller(ManageBookingController::class)->prefix('my-booking')->name('manage.')->group(function () {
    Route::get('/', 'lookup')->name('lookup');
    Route::post('/', 'find')->middleware('throttle:10,5')->name('find');
    Route::get('/{token}', 'show')->name('show');
    Route::get('/{token}/voucher', 'voucher')->name('voucher');
    Route::post('/{token}/pay', 'pay')->middleware('throttle:10,5')->name('pay');
});

// Hosted-payment round trip (card data stays with the provider)
Route::controller(PublicPaymentController::class)->prefix('pay')->name('pay.')->group(function () {
    Route::get('/{token}/sandbox', 'sandbox')->name('sandbox');
    Route::post('/{token}/sandbox', 'sandboxComplete')->name('sandbox.complete');
    Route::get('/{token}/return', 'return')->name('return');
    Route::get('/{token}/cancel', 'cancel')->name('cancel');
});

// Provider webhooks (signature-verified, CSRF-exempt)
Route::post('/webhooks/payments/{provider}', [WebhookController::class, 'payment'])->name('webhooks.payment');
Route::post('/webhooks/channel/{provider}', [ChannelWebhookController::class, 'handle'])->name('webhooks.channel');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [PasswordController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/account/password', [PasswordController::class, 'changeForm'])->name('password.change');
    Route::post('/account/password', [PasswordController::class, 'change'])->name('password.change.update');
    Route::get('/account/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/account/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/account/theme', [ProfileController::class, 'theme'])->name('preferences.theme');
});
