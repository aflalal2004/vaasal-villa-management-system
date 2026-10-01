<?php

use App\Modules\Booking\Exceptions\InventoryConflictException;
use App\Modules\Core\Exceptions\BusinessRuleException;
use App\Modules\Core\Http\Middleware\EnsureUserType;
use App\Modules\Core\Http\Middleware\RequirePermission;
use App\Modules\Core\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/pos.php'));
            Route::middleware('web')->group(base_path('routes/operator.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'perm' => RequirePermission::class,
            'usertype' => EnsureUserType::class,
        ]);
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $r) => route($r->user()->homeRoute()));
        // Gateway / channel webhooks are verified by signature instead of CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        // Display-currency preference only (no secret), readable/settable by the site script.
        $middleware->encryptCookies(except: ['vv_currency']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BusinessRuleException|InventoryConflictException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode ?? 'CONFLICT'], 422);
            }
            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
