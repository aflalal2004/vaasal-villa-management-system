<?php

use App\Modules\Billing\Http\Controllers\ChargeItemController;
use App\Modules\Billing\Http\Controllers\FolioController;
use App\Modules\Billing\Http\Controllers\InvoiceController;
use App\Modules\Billing\Http\Controllers\PaymentController;
use App\Modules\Booking\Http\Controllers\BookingController;
use App\Modules\Booking\Http\Controllers\CalendarController;
use App\Modules\Booking\Http\Controllers\ConflictController;
use App\Modules\Booking\Http\Controllers\EnquiryController;
use App\Modules\Booking\Http\Controllers\GuestController;
use App\Modules\Channel\Http\Controllers\ChannelController;
use App\Modules\Core\Http\Controllers\AuditController;
use App\Modules\Core\Http\Controllers\CmsController;
use App\Modules\Core\Http\Controllers\MediaLibraryController;
use App\Modules\Core\Http\Controllers\RoleController;
use App\Modules\Core\Http\Controllers\SettingsController;
use App\Modules\Core\Http\Controllers\SocialLinkController;
use App\Modules\Core\Http\Controllers\UserController;
use App\Modules\FrontDesk\Http\Controllers\FrontDeskController;
use App\Modules\Housekeeping\Http\Controllers\HousekeepingController;
use App\Modules\Housekeeping\Http\Controllers\LostFoundController;
use App\Modules\KeyCards\Http\Controllers\AccessControlController;
use App\Modules\KeyCards\Http\Controllers\KeyCardController;
use App\Modules\Maintenance\Http\Controllers\MaintenanceController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Operators\Http\Controllers\OperatorController;
use App\Modules\Reports\Http\Controllers\DashboardController;
use App\Modules\Reports\Http\Controllers\ReportController;
use App\Modules\Staff\Http\Controllers\AttendanceController;
use App\Modules\Staff\Http\Controllers\EmployeeController;
use App\Modules\Staff\Http\Controllers\LeaveController;
use App\Modules\Staff\Http\Controllers\RosterController;
use App\Modules\Villa\Http\Controllers\OfferController;
use App\Modules\Villa\Http\Controllers\RateController;
use App\Modules\Villa\Http\Controllers\VillaController;
use App\Modules\Villa\Http\Controllers\VillaTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Back office / PMS  (staff only; every route guarded by a permission)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'usertype:staff'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->middleware('perm:dashboard.view')->name('dashboard');

    // Notifications (every staff user)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->middleware('throttle:120,1')->name('notifications.poll');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // ---------------- Bookings ----------------
    Route::middleware('perm:bookings.view')->group(function () {
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [BookingController::class, 'create'])->middleware('perm:bookings.manage')->name('bookings.create');
        Route::post('/bookings/quote', [BookingController::class, 'quote'])->middleware('perm:bookings.manage')->name('bookings.quote');
        Route::post('/bookings', [BookingController::class, 'store'])->middleware('perm:bookings.manage')->name('bookings.store');
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::get('/bookings/{booking}/voucher', [BookingController::class, 'voucher'])->name('bookings.voucher');
        Route::get('/bookings/{booking}/confirmation', [BookingController::class, 'confirmation'])->name('bookings.confirmation');
        Route::get('/bookings/{booking}/registration-card', [BookingController::class, 'registrationCard'])->name('bookings.registration');
        Route::middleware('perm:bookings.manage')->group(function () {
            Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
            Route::post('/bookings/{booking}/dates', [BookingController::class, 'changeDates'])->name('bookings.dates');
            Route::post('/bookings/{booking}/villas/{bookingVilla}/move', [BookingController::class, 'moveVilla'])->name('bookings.move');
            Route::post('/bookings/{booking}/notes', [BookingController::class, 'updateNotes'])->name('bookings.notes');
            Route::post('/bookings/{booking}/stay-guests', [BookingController::class, 'addStayGuest'])->name('bookings.stay-guests');
            Route::delete('/bookings/{booking}/stay-guests/{stayGuest}', [BookingController::class, 'removeStayGuest'])->name('bookings.stay-guests.destroy');
            Route::post('/bookings/{booking}/no-show', [BookingController::class, 'noShow'])->name('bookings.no-show');
            Route::post('/bookings/{booking}/send-confirmation', [BookingController::class, 'sendConfirmation'])->name('bookings.send');
            Route::post('/bookings/{booking}/payment-link', [BookingController::class, 'paymentLink'])->name('bookings.payment-link');
            Route::post('/bookings/{booking}/proforma', [BookingController::class, 'proforma'])->name('bookings.proforma');
        });
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('perm:bookings.cancel')->name('bookings.cancel');

        Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::put('/enquiries/{enquiry}', [EnquiryController::class, 'update'])->name('enquiries.update');
    });

    Route::get('/calendar', [CalendarController::class, 'index'])->middleware('perm:calendar.view')->name('calendar');
    Route::post('/calendar/blocks', [CalendarController::class, 'block'])->middleware('perm:villas.manage|bookings.manage')->name('calendar.block');
    Route::delete('/calendar/blocks/{block}', [CalendarController::class, 'unblock'])->middleware('perm:villas.manage|bookings.manage')->name('calendar.unblock');

    Route::middleware('perm:conflicts.manage')->group(function () {
        Route::get('/conflicts', [ConflictController::class, 'index'])->name('conflicts.index');
        Route::post('/conflicts/{conflict}/assign', [ConflictController::class, 'assign'])->name('conflicts.assign');
        Route::post('/conflicts/{conflict}/reject', [ConflictController::class, 'reject'])->name('conflicts.reject');
    });

    // ---------------- Guests ----------------
    Route::middleware('perm:guests.view')->group(function () {
        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::get('/guests/{guest}', [GuestController::class, 'show'])->name('guests.show');
        Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->middleware('perm:guests.manage')->name('guests.edit');
        Route::put('/guests/{guest}', [GuestController::class, 'update'])->middleware('perm:guests.manage')->name('guests.update');
        Route::get('/guests/{guest}/id-document', [GuestController::class, 'idDocument'])->middleware('perm:guests.manage')->name('guests.id-document');
    });

    // ---------------- Front desk ----------------
    Route::middleware('perm:frontdesk.checkin|frontdesk.checkout')->group(function () {
        Route::get('/front-desk', [FrontDeskController::class, 'index'])->name('frontdesk.index');
        Route::get('/front-desk/walk-in', [FrontDeskController::class, 'walkIn'])->middleware('perm:bookings.manage')->name('frontdesk.walk-in');
        Route::get('/front-desk/{booking}/check-in', [FrontDeskController::class, 'checkInForm'])->middleware('perm:frontdesk.checkin')->name('frontdesk.checkin');
        Route::post('/front-desk/{booking}/check-in', [FrontDeskController::class, 'checkIn'])->middleware('perm:frontdesk.checkin')->name('frontdesk.checkin.store');
        Route::get('/front-desk/{booking}/check-out', [FrontDeskController::class, 'checkOutForm'])->middleware('perm:frontdesk.checkout')->name('frontdesk.checkout');
        Route::post('/front-desk/{booking}/check-out', [FrontDeskController::class, 'checkOut'])->middleware('perm:frontdesk.checkout')->name('frontdesk.checkout.store');
        Route::post('/front-desk/{booking}/restaurant-settlement', [FrontDeskController::class, 'requestRestaurantSettlement'])->middleware(['perm:frontdesk.checkout', 'throttle:10,1'])->name('frontdesk.restaurant-settlement');
        Route::post('/front-desk/night-audit', [FrontDeskController::class, 'nightAudit'])->middleware('perm:frontdesk.night_audit')->name('frontdesk.night-audit');
    });

    // ---------------- Folio / billing ----------------
    Route::post('/folios/{folio}/charges', [FolioController::class, 'postCharge'])->middleware('perm:folio.post')->name('folios.charge');
    Route::post('/folio-lines/{line}/reverse', [FolioController::class, 'reverse'])->middleware('perm:folio.adjust')->name('folios.reverse');
    Route::post('/folios/{folio}/payments', [FolioController::class, 'payment'])->middleware('perm:payments.receive')->name('folios.payment');
    Route::post('/folios/{folio}/refunds', [FolioController::class, 'refund'])->middleware('perm:payments.refund')->name('folios.refund');
    Route::post('/folios/{folio}/interim-invoice', [FolioController::class, 'interimInvoice'])->middleware('perm:folio.post')->name('folios.interim');
    Route::get('/folios/{folio}/print', [FolioController::class, 'print'])->middleware('perm:invoices.view|folio.post')->name('folios.print');

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('perm:payments.receive|invoices.view')->name('payments.index');
    Route::get('/payments/{payment}/proof', [PaymentController::class, 'proof'])->middleware('perm:payments.receive|operators.finance')->name('payments.proof');
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('perm:invoices.view')->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('perm:invoices.view|folio.post')->name('invoices.show');
    Route::post('/invoices/{invoice}/email', [InvoiceController::class, 'email'])->middleware('perm:invoices.view')->name('invoices.email');

    Route::resource('charge-items', ChargeItemController::class)->except(['show', 'destroy'])->middleware('perm:folio.post');

    // ---------------- Villas & rates ----------------
    Route::get('/villas', [VillaController::class, 'index'])->middleware('perm:villas.view')->name('villas.index');
    Route::middleware('perm:villas.manage')->group(function () {
        Route::get('/villas/create', [VillaController::class, 'create'])->name('villas.create');
        Route::post('/villas', [VillaController::class, 'store'])->name('villas.store');
        Route::get('/villas/{villa}/edit', [VillaController::class, 'edit'])->name('villas.edit');
        Route::put('/villas/{villa}', [VillaController::class, 'update'])->name('villas.update');
        Route::delete('/villas/{villa}', [VillaController::class, 'destroy'])->name('villas.destroy');
        Route::post('/villas/{villa}/media', [VillaController::class, 'addMedia'])->name('villas.media');
        Route::resource('villa-types', VillaTypeController::class)->except(['show']);
        Route::post('/villa-types/{villa_type}/media', [VillaTypeController::class, 'addMedia'])->name('villa-types.media');
        Route::delete('/media/{media}', [VillaTypeController::class, 'deleteMedia'])->name('media.destroy');
        Route::post('/media/{media}/cover', [VillaTypeController::class, 'coverMedia'])->name('media.cover');
        Route::post('/facilities', [VillaTypeController::class, 'storeFacility'])->name('facilities.store');
    });
    Route::get('/villas/{villa}', [VillaController::class, 'show'])->middleware('perm:villas.view')->name('villas.show');

    Route::middleware('perm:rates.manage')->group(function () {
        Route::get('/rates', [RateController::class, 'index'])->name('rates.index');
        Route::post('/rates/matrix', [RateController::class, 'saveMatrix'])->name('rates.matrix');
        Route::post('/rates/seasons', [RateController::class, 'storeSeason'])->name('rates.seasons.store');
        Route::put('/rates/seasons/{season}', [RateController::class, 'updateSeason'])->name('rates.seasons.update');
        Route::delete('/rates/seasons/{season}', [RateController::class, 'destroySeason'])->name('rates.seasons.destroy');
        Route::post('/rates/plans', [RateController::class, 'storePlan'])->name('rates.plans.store');
        Route::put('/rates/plans/{plan}', [RateController::class, 'updatePlan'])->name('rates.plans.update');
        Route::resource('offers', OfferController::class)->except(['show']);
    });

    // ---------------- Housekeeping ----------------
    Route::get('/housekeeping', [HousekeepingController::class, 'index'])->middleware('perm:housekeeping.view')->name('housekeeping.index');
    Route::get('/housekeeping/my-tasks', [HousekeepingController::class, 'my'])->middleware('perm:housekeeping.work')->name('housekeeping.my');
    Route::get('/housekeeping/tasks/{task}', [HousekeepingController::class, 'show'])->middleware('perm:housekeeping.view|housekeeping.work')->name('housekeeping.tasks.show');
    Route::middleware('perm:housekeeping.work|housekeeping.manage')->group(function () {
        Route::post('/housekeeping/tasks/{task}/accept', [HousekeepingController::class, 'accept'])->name('housekeeping.tasks.accept');
        Route::post('/housekeeping/tasks/{task}/start', [HousekeepingController::class, 'start'])->name('housekeeping.tasks.start');
        Route::post('/housekeeping/tasks/{task}/pause', [HousekeepingController::class, 'pause'])->name('housekeeping.tasks.pause');
        Route::post('/housekeeping/tasks/{task}/items/{item}', [HousekeepingController::class, 'toggle'])->name('housekeeping.tasks.toggle');
        Route::post('/housekeeping/tasks/{task}/submit', [HousekeepingController::class, 'submit'])->name('housekeeping.tasks.submit');
    });
    Route::middleware('perm:housekeeping.manage')->group(function () {
        Route::post('/housekeeping/tasks', [HousekeepingController::class, 'store'])->name('housekeeping.tasks.store');
        Route::post('/housekeeping/tasks/{task}/assign', [HousekeepingController::class, 'assign'])->name('housekeeping.tasks.assign');
        Route::post('/housekeeping/tasks/{task}/cancel', [HousekeepingController::class, 'cancel'])->name('housekeeping.tasks.cancel');
        Route::post('/housekeeping/villas/{villa}/status', [HousekeepingController::class, 'setStatus'])->name('housekeeping.villa-status');
        Route::post('/housekeeping/stayovers', [HousekeepingController::class, 'stayovers'])->name('housekeeping.stayovers');
        Route::get('/housekeeping/linen', [HousekeepingController::class, 'linen'])->name('housekeeping.linen');
        Route::post('/housekeeping/linen', [HousekeepingController::class, 'saveLinen'])->name('housekeeping.linen.save');
        Route::get('/housekeeping/checklists', [HousekeepingController::class, 'checklists'])->name('housekeeping.checklists');
        Route::post('/housekeeping/checklists', [HousekeepingController::class, 'saveChecklist'])->name('housekeeping.checklists.save');
    });
    Route::middleware('perm:housekeeping.inspect')->group(function () {
        Route::post('/housekeeping/tasks/{task}/approve', [HousekeepingController::class, 'approve'])->name('housekeeping.tasks.approve');
        Route::post('/housekeeping/tasks/{task}/reject', [HousekeepingController::class, 'reject'])->name('housekeeping.tasks.reject');
    });
    Route::resource('lost-found', LostFoundController::class)->except(['show', 'destroy'])->parameters(['lost-found' => 'item'])->middleware('perm:lostfound.manage');

    // ---------------- Maintenance ----------------
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->middleware('perm:maintenance.view|maintenance.report')->name('maintenance.index');
    Route::get('/maintenance/create', [MaintenanceController::class, 'create'])->middleware('perm:maintenance.report')->name('maintenance.create');
    Route::post('/maintenance', [MaintenanceController::class, 'store'])->middleware('perm:maintenance.report')->name('maintenance.store');
    Route::get('/maintenance/{ticket}', [MaintenanceController::class, 'show'])->middleware('perm:maintenance.view|maintenance.report')->name('maintenance.show');
    Route::post('/maintenance/{ticket}/status', [MaintenanceController::class, 'status'])->middleware('perm:maintenance.manage')->name('maintenance.status');
    Route::post('/maintenance/{ticket}/assign', [MaintenanceController::class, 'assign'])->middleware('perm:maintenance.manage')->name('maintenance.assign');

    // ---------------- Key cards ----------------
    Route::middleware('perm:keycards.view')->group(function () {
        Route::get('/key-cards', [KeyCardController::class, 'index'])->name('keycards.index');
        Route::get('/key-cards/access-logs', [KeyCardController::class, 'logs'])->name('keycards.logs');
        Route::get('/key-cards/lock-bridge', [KeyCardController::class, 'bridge'])->name('keycards.bridge');
        Route::get('/key-cards/{card}', [KeyCardController::class, 'show'])->name('keycards.show');
    });
    Route::middleware('perm:keycards.issue')->group(function () {
        Route::post('/key-cards/issue/{booking}', [KeyCardController::class, 'issue'])->name('keycards.issue');
        Route::post('/key-cards/assignments/{assignment}/revoke', [KeyCardController::class, 'revoke'])->name('keycards.revoke');
        Route::post('/key-cards/assignments/{assignment}/extend', [KeyCardController::class, 'extend'])->name('keycards.extend');
        Route::post('/key-cards/{card}/lost', [KeyCardController::class, 'lost'])->name('keycards.lost');
        Route::post('/key-cards/jobs/{job}/retry', [KeyCardController::class, 'retry'])->name('keycards.retry');
    });
    Route::middleware('perm:keycards.manage')->group(function () {
        Route::post('/key-cards', [KeyCardController::class, 'store'])->name('keycards.store');
        Route::post('/key-cards/{card}/block', [KeyCardController::class, 'block'])->name('keycards.block');
        Route::post('/key-cards/{card}/unblock', [KeyCardController::class, 'unblock'])->name('keycards.unblock');
        Route::post('/key-cards/staff-issue', [KeyCardController::class, 'issueStaff'])->name('keycards.staff-issue');
        Route::post('/key-cards/simulate-access', [KeyCardController::class, 'simulateAccess'])->name('keycards.simulate');
        Route::post('/key-cards/lock-bridge/token', [KeyCardController::class, 'rotateToken'])->name('keycards.bridge.token');
        // Access control: RFID readers + simulator
        Route::get('/access/devices', [AccessControlController::class, 'devices'])->name('access.devices');
        Route::post('/access/devices', [AccessControlController::class, 'storeDevice'])->name('access.devices.store');
        Route::put('/access/devices/{device}', [AccessControlController::class, 'updateDevice'])->name('access.devices.update');
        Route::post('/access/devices/{device}/token', [AccessControlController::class, 'rotateToken'])->name('access.devices.token');
        Route::get('/access/simulator', [AccessControlController::class, 'simulator'])->name('access.simulator');
        Route::post('/access/simulator', [AccessControlController::class, 'simulate'])->middleware('throttle:60,1')->name('access.simulate');
    });

    // ---------------- Tour operators ----------------
    Route::middleware('perm:operators.view')->group(function () {
        Route::get('/operators', [OperatorController::class, 'index'])->name('operators.index');
        Route::get('/operators/create', [OperatorController::class, 'create'])->middleware('perm:operators.manage')->name('operators.create');
        Route::post('/operators', [OperatorController::class, 'store'])->middleware('perm:operators.manage')->name('operators.store');
        Route::get('/operators/{operator}', [OperatorController::class, 'show'])->name('operators.show');
        Route::get('/operators/{operator}/statement', [OperatorController::class, 'statement'])->name('operators.statement');
        Route::middleware('perm:operators.manage')->group(function () {
            Route::get('/operators/{operator}/edit', [OperatorController::class, 'edit'])->name('operators.edit');
            Route::put('/operators/{operator}', [OperatorController::class, 'update'])->name('operators.update');
            Route::post('/operators/{operator}/approve', [OperatorController::class, 'approve'])->name('operators.approve');
            Route::post('/operators/{operator}/suspend', [OperatorController::class, 'suspend'])->name('operators.suspend');
            Route::post('/operators/{operator}/users', [OperatorController::class, 'addUser'])->name('operators.users');
            Route::post('/operators/{operator}/contracts', [OperatorController::class, 'storeContract'])->name('operators.contracts.store');
            Route::put('/operator-contracts/{contract}', [OperatorController::class, 'updateContract'])->name('operators.contracts.update');
        });
        Route::middleware('perm:operators.finance')->group(function () {
            Route::post('/operators/{operator}/payments', [OperatorController::class, 'payment'])->name('operators.payment');
            Route::post('/operators/{operator}/commissions/settle', [OperatorController::class, 'settleCommissions'])->name('operators.commissions.settle');
        });
    });

    // ---------------- Channel manager ----------------
    Route::middleware('perm:channels.manage')->group(function () {
        Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
        Route::put('/channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
        Route::post('/channels/mappings', [ChannelController::class, 'storeMapping'])->name('channels.mappings.store');
        Route::delete('/channels/mappings/{mapping}', [ChannelController::class, 'destroyMapping'])->name('channels.mappings.destroy');
        Route::post('/channels/full-sync', [ChannelController::class, 'fullSync'])->name('channels.full-sync');
        Route::post('/channels/retry', [ChannelController::class, 'retry'])->name('channels.retry');
        Route::post('/channels/simulate', [ChannelController::class, 'simulate'])->name('channels.simulate');
    });

    // ---------------- Staff ----------------
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/my-time-card', [AttendanceController::class, 'mine'])->middleware('perm:attendance.self')->name('my-timecard');
        Route::post('/clock', [AttendanceController::class, 'clock'])->middleware('perm:attendance.self')->name('clock');

        Route::get('/employees', [EmployeeController::class, 'index'])->middleware('perm:staff.view')->name('employees.index');
        Route::middleware('perm:staff.manage')->group(function () {
            Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::post('/employees/{employee}/login', [EmployeeController::class, 'createLogin'])->name('employees.login');
            Route::post('/employees/{employee}/terminate', [EmployeeController::class, 'terminate'])->name('employees.terminate');
            Route::get('/departments', [EmployeeController::class, 'departments'])->name('departments.index');
            Route::post('/departments', [EmployeeController::class, 'storeDepartment'])->name('departments.store');
            Route::post('/job-roles', [EmployeeController::class, 'storeJobRole'])->name('job-roles.store');

            Route::get('/roster', [RosterController::class, 'index'])->name('roster.index');
            Route::post('/roster', [RosterController::class, 'save'])->name('roster.save');
            Route::post('/shifts', [RosterController::class, 'storeShift'])->name('shifts.store');
            Route::put('/shifts/{shift}', [RosterController::class, 'updateShift'])->name('shifts.update');
            Route::get('/devices', [RosterController::class, 'devices'])->name('devices.index');
            Route::post('/devices', [RosterController::class, 'storeDevice'])->name('devices.store');
        });
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->middleware('perm:staff.view')->name('employees.show');

        Route::middleware('perm:attendance.manage')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance/{record}/correct', [AttendanceController::class, 'correct'])->name('attendance.correct');
            Route::post('/attendance/manual', [AttendanceController::class, 'manual'])->name('attendance.manual');
            Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
        });

        Route::get('/leave', [LeaveController::class, 'index'])->middleware('perm:leave.self|leave.approve')->name('leave.index');
        Route::post('/leave', [LeaveController::class, 'store'])->middleware('perm:leave.self|leave.approve')->name('leave.store');
        Route::post('/leave/{leave}/decide', [LeaveController::class, 'decide'])->middleware('perm:leave.approve')->name('leave.decide');
        Route::post('/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->middleware('perm:leave.self|leave.approve')->name('leave.cancel');
        Route::post('/leave-types', [LeaveController::class, 'storeType'])->middleware('perm:staff.manage')->name('leave-types.store');
    });

    // ---------------- Reports ----------------
    Route::get('/reports', [ReportController::class, 'index'])->middleware('perm:reports.operational|reports.financial')->name('reports.index'); // hotel reporting only; restaurant staff use /pos/sales
    Route::get('/reports/{type}', [ReportController::class, 'show'])->middleware('perm:reports.operational|reports.financial')->name('reports.show');

    // ---------------- Administration ----------------
    Route::middleware('perm:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
    Route::middleware('perm:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/currencies', [SettingsController::class, 'currencies'])->name('settings.currencies');
        Route::post('/settings/currencies/refresh', [SettingsController::class, 'refreshRates'])->name('settings.currencies.refresh');
    });
    Route::middleware('perm:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/logins', [AuditController::class, 'logins'])->name('audit.logins');
    });
    Route::middleware('perm:website.manage')->prefix('website')->name('cms.')->group(function () {
        Route::get('/', [CmsController::class, 'index'])->name('index');
        Route::put('/content', [CmsController::class, 'content'])->name('content');
        Route::post('/gallery', [CmsController::class, 'storeGallery'])->name('gallery.store');
        Route::delete('/gallery/{item}', [CmsController::class, 'destroyGallery'])->name('gallery.destroy');
        Route::post('/services', [CmsController::class, 'storeService'])->name('services.store');
        Route::put('/services/{service}', [CmsController::class, 'updateService'])->name('services.update');
        Route::post('/testimonials', [CmsController::class, 'storeTestimonial'])->name('testimonials.store');
        Route::delete('/testimonials/{testimonial}', [CmsController::class, 'destroyTestimonial'])->name('testimonials.destroy');
        Route::get('/media/search', [MediaLibraryController::class, 'search'])->middleware('throttle:60,1')->name('media.search');
        Route::post('/media/use', [MediaLibraryController::class, 'use'])->name('media.use');
    });
    Route::middleware('perm:website.manage')->prefix('website/social')->name('social.')->controller(SocialLinkController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{link}', 'update')->name('update');
        Route::post('/{link}/toggle', 'toggle')->name('toggle');
        Route::delete('/{link}', 'destroy')->name('destroy');
    });
});
