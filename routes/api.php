<?php

use App\Modules\Api\Http\Controllers\PublicApiController;
use App\Modules\KeyCards\Http\Controllers\LockBridgeApiController;
use App\Modules\KeyCards\Http\Controllers\RfidApiController;
use App\Modules\Staff\Http\Controllers\DeviceApiController;
use App\Modules\Website\Http\Controllers\CurrencyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1  (prefix /api/v1)
|--------------------------------------------------------------------------
| Public, read-only website data (rate-limited), and machine-to-machine endpoints
| for the on-premise Lock Bridge and attendance devices (bearer token auth).
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/availability', [PublicApiController::class, 'availability'])->name('availability');
        Route::get('/villa-types', [PublicApiController::class, 'villaTypes'])->name('villa-types');
        Route::get('/villa-types/{type:slug}', [PublicApiController::class, 'villaType'])->name('villa-type');
        Route::get('/offers', [PublicApiController::class, 'offers'])->name('offers');
        Route::get('/quote', [PublicApiController::class, 'quote'])->name('quote');
        Route::get('/currencies', [CurrencyController::class, 'rates'])->name('currencies');
    });

    // Lock Bridge (Authorization: Bearer <LOCK_BRIDGE_TOKEN>)
    Route::prefix('lock-bridge')->name('bridge.')->middleware('throttle:240,1')->controller(LockBridgeApiController::class)->group(function () {
        Route::post('/heartbeat', 'heartbeat')->name('heartbeat');
        Route::get('/jobs', 'jobs')->name('jobs');
        Route::post('/jobs/{uuid}/result', 'result')->name('result');
        Route::post('/events', 'events')->name('events');
    });

    // RFID readers: door access and/or staff time clock (Authorization: Bearer <device token>). See docs/RFID.md.
    Route::post('/rfid/scan', [RfidApiController::class, 'scan'])->middleware('throttle:240,1')->name('rfid.scan');

    // Attendance devices: RFID / QR / biometric terminals (Authorization: Bearer <device token>)
    Route::post('/attendance/punch', [DeviceApiController::class, 'punch'])->middleware('throttle:120,1')->name('attendance.punch');
});
