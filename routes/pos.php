<?php

use App\Modules\Core\Http\Controllers\SocialLinkController;
use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\SupplierController;
use App\Modules\POS\Http\Controllers\KdsController;
use App\Modules\POS\Http\Controllers\MenuController;
use App\Modules\POS\Http\Controllers\OrderHistoryController;
use App\Modules\POS\Http\Controllers\OutletController;
use App\Modules\POS\Http\Controllers\PosAccessController;
use App\Modules\POS\Http\Controllers\PosDashboardController;
use App\Modules\POS\Http\Controllers\ReservationController;
use App\Modules\POS\Http\Controllers\SalesReportController;
use App\Modules\POS\Http\Controllers\PosApiController;
use App\Modules\POS\Http\Controllers\ShiftController;
use App\Modules\POS\Http\Controllers\TerminalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restaurant POS — separate module, same application and database.
| Email/password login with role-based access (POS Manager, Cashier, Waiter, Kitchen, Inventory).
|--------------------------------------------------------------------------
*/
// Entry point from the website / back office: signs in if needed, then opens the right POS screen for the role.
Route::get('/pos-access', PosAccessController::class)->name('pos.access');

Route::middleware(['auth', 'usertype:staff', 'perm:pos.access'])->prefix('pos')->name('pos.')->group(function () {

    Route::get('/dashboard', PosDashboardController::class)->name('dashboard');
    Route::get('/', [TerminalController::class, 'index'])->middleware('perm:pos.order|pos.bill')->name('terminal');

    // Table reservations
    Route::middleware('perm:pos.reservations')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
        Route::post('/reservations/{reservation}/status', [ReservationController::class, 'status'])->name('reservations.status');
    });

    // Restaurant sales (POS-scoped reports)
    Route::get('/sales/{type?}', SalesReportController::class)->middleware('perm:pos.reports|inventory.view')->name('sales');
    Route::get('/order/{order}', [TerminalController::class, 'order'])->middleware('perm:pos.order|pos.bill')->name('order');
    Route::get('/order/{order}/bill', [TerminalController::class, 'bill'])->middleware('perm:pos.order|pos.bill')->name('bill.print');
    Route::get('/order/{order}/kot/{kot}', [TerminalController::class, 'kot'])->middleware('perm:pos.order|pos.kds')->name('kot.print');
    Route::get('/order/{order}/receipt', [TerminalController::class, 'receipt'])->middleware('perm:pos.bill|pos.order')->name('receipt');

    // JSON endpoints used by the terminal (session auth + CSRF)
    Route::prefix('api')->name('api.')->middleware('perm:pos.order|pos.bill')->group(function () {
        Route::get('/tables', [PosApiController::class, 'tables'])->name('tables');
        Route::get('/menu', [PosApiController::class, 'menu'])->name('menu');
        Route::get('/in-house', [PosApiController::class, 'inHouse'])->name('in-house');
        Route::post('/orders', [PosApiController::class, 'open'])->name('orders.open');
        Route::get('/orders/{order}', [PosApiController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/items', [PosApiController::class, 'addItem'])->name('orders.items.add');
        Route::patch('/orders/{order}/items/{item}', [PosApiController::class, 'updateItem'])->name('orders.items.update');
        Route::delete('/orders/{order}/items/{item}', [PosApiController::class, 'removeItem'])->name('orders.items.remove');
        Route::post('/orders/{order}/fire', [PosApiController::class, 'fire'])->name('orders.fire');
        Route::post('/orders/{order}/transfer', [PosApiController::class, 'transfer'])->name('orders.transfer');
        Route::post('/orders/{order}/merge', [PosApiController::class, 'merge'])->name('orders.merge');
        Route::post('/orders/{order}/split', [PosApiController::class, 'split'])->name('orders.split');
        Route::post('/orders/{order}/discount', [PosApiController::class, 'discount'])->middleware('perm:pos.discount')->name('orders.discount');
        Route::post('/orders/{order}/void', [PosApiController::class, 'void'])->middleware('perm:pos.void')->name('orders.void');
        Route::post('/orders/{order}/bill', [PosApiController::class, 'printBill'])->name('orders.bill');
        Route::post('/orders/{order}/pay', [PosApiController::class, 'pay'])->middleware('perm:pos.bill')->name('orders.pay');
        Route::post('/orders/{order}/notes', [PosApiController::class, 'notes'])->name('orders.notes');
    });

    // Kitchen display
    Route::get('/kds', [KdsController::class, 'index'])->middleware('perm:pos.kds')->name('kds');
    Route::get('/kds/feed', [KdsController::class, 'feed'])->middleware('perm:pos.kds|pos.order')->name('kds.feed');
    Route::post('/kds/{kot}/status', [KdsController::class, 'status'])->middleware('perm:pos.kds')->name('kds.status');

    // Orders history, refunds
    Route::get('/orders', [OrderHistoryController::class, 'index'])->middleware('perm:pos.bill|pos.reports')->name('orders.index');
    Route::post('/orders/{order}/refund', [OrderHistoryController::class, 'refund'])->middleware('perm:pos.refund')->name('orders.refund');

    // Shifts & cash drawer
    Route::middleware('perm:pos.shift|pos.reports|pos.shift_review')->group(function () {
        Route::get('/today', [ShiftController::class, 'today'])->name('today');
        Route::get('/cash-movements', [ShiftController::class, 'movements'])->name('cash-movements');
        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::get('/shifts/{shift}/print', [ShiftController::class, 'print'])->name('shifts.print');
    });
    Route::middleware('perm:pos.shift')->group(function () {
        Route::post('/shifts/open', [ShiftController::class, 'open'])->name('shifts.open');
    });
    // Drawer movements and closing: the shift's own cashier, or a manager with pos.shift_review (checked in the controller)
    Route::middleware('perm:pos.shift|pos.shift_review')->group(function () {
        Route::get('/day-end', [ShiftController::class, 'dayEnd'])->name('day-end');
        Route::post('/shifts/{shift}/cash', [ShiftController::class, 'cash'])->name('shifts.cash');
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    });
    Route::middleware('perm:pos.shift_review')->group(function () {
        Route::post('/shifts/{shift}/review', [ShiftController::class, 'review'])->name('shifts.review');
        Route::post('/shifts/{shift}/reopen', [ShiftController::class, 'reopen'])->name('shifts.reopen');
    });

    // Menu, tables, outlets
    Route::middleware('perm:pos.menu')->group(function () {
        Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
        Route::post('/menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
        Route::put('/menu/categories/{category}', [MenuController::class, 'updateCategory'])->name('menu.categories.update');
        Route::get('/menu/items/create', [MenuController::class, 'create'])->name('menu.items.create');
        Route::post('/menu/items', [MenuController::class, 'store'])->name('menu.items.store');
        Route::get('/menu/items/{item}/edit', [MenuController::class, 'edit'])->name('menu.items.edit');
        Route::put('/menu/items/{item}', [MenuController::class, 'update'])->name('menu.items.update');
        Route::post('/menu/items/{item}/availability', [MenuController::class, 'toggle'])->name('menu.items.toggle');
        Route::delete('/menu/items/{item}', [MenuController::class, 'destroy'])->name('menu.items.destroy');
        Route::post('/menu/modifier-groups', [MenuController::class, 'storeGroup'])->name('menu.groups.store');
        Route::post('/menu/modifier-groups/{group}/modifiers', [MenuController::class, 'storeModifier'])->name('menu.modifiers.store');
        Route::delete('/menu/modifiers/{modifier}', [MenuController::class, 'destroyModifier'])->name('menu.modifiers.destroy');

        Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
        Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
        Route::put('/outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
        Route::post('/outlets/{outlet}/tables', [OutletController::class, 'storeTable'])->name('tables.store');
        Route::put('/tables/{table}', [OutletController::class, 'updateTable'])->name('tables.update');

        // Restaurant social links (same table as the website, restaurant scope)
        Route::get('/social', [SocialLinkController::class, 'index'])->name('social.index');
        Route::post('/social', [SocialLinkController::class, 'store'])->name('social.store');
        Route::put('/social/{link}', [SocialLinkController::class, 'update'])->name('social.update');
        Route::post('/social/{link}/toggle', [SocialLinkController::class, 'toggle'])->name('social.toggle');
        Route::delete('/social/{link}', [SocialLinkController::class, 'destroy'])->name('social.destroy');
    });

    // Reports shortcut
    Route::get('/reports', fn () => redirect()->route('pos.sales'))->middleware('perm:pos.reports')->name('reports');

    // Inventory
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [StockController::class, 'index'])->middleware('perm:inventory.view')->name('items.index');
        Route::get('/movements', [StockController::class, 'movements'])->middleware('perm:inventory.view')->name('movements');
        Route::middleware('perm:inventory.manage')->group(function () {
            Route::post('/items', [StockController::class, 'store'])->name('items.store');
            Route::put('/items/{item}', [StockController::class, 'update'])->name('items.update');
            Route::post('/stock-in', [StockController::class, 'stockIn'])->name('stock-in');
            Route::post('/stock-out', [StockController::class, 'stockOut'])->name('stock-out');
            Route::post('/count', [StockController::class, 'count'])->name('count');
            Route::get('/recipes', [StockController::class, 'recipes'])->name('recipes');
            Route::post('/recipes', [StockController::class, 'saveRecipe'])->name('recipes.save');
            Route::delete('/recipes/{recipe}', [StockController::class, 'destroyRecipe'])->name('recipes.destroy');
            Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);
        });
    });
});
