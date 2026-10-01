<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\User;
use App\Modules\Billing\Gateways\PaymentGateway;
use App\Modules\Billing\Gateways\SandboxGateway;
use App\Modules\Billing\Gateways\StripeGateway;
use App\Modules\Channel\Contracts\ChannelManager;
use App\Modules\Channel\Drivers\ChannexChannelManager;
use App\Modules\Channel\Drivers\NullChannelManager;
use App\Modules\KeyCards\Contracts\LockProvider;
use App\Modules\KeyCards\Providers\SimulatorLockProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Integration drivers are chosen by environment configuration.
        $this->app->bind(PaymentGateway::class, fn () => match (config('vaasal.payments.driver')) {
            'stripe' => new StripeGateway(),
            default => new SandboxGateway(),
        });
        $this->app->bind(ChannelManager::class, fn () => match (config('vaasal.channel.driver')) {
            'channex' => new ChannexChannelManager(),
            default => new NullChannelManager(),
        });
        $this->app->bind(LockProvider::class, SimulatorLockProvider::class);
        $this->app->singleton(\App\Modules\Core\Services\CurrencyService::class);
    }

    public function boot(): void
    {
        // RBAC: every permission slug is a Gate ability, so @can('bookings.manage') works in Blade.
        Gate::before(fn (User $user, string $ability) => str_contains($ability, '.') && $user->hasPermission($ability) ? true : null);

        Password::defaults(fn () => Password::min(config('vaasal.security.password_min'))->letters()->mixedCase()->numbers());

        Paginator::defaultView('components.pagination');

        Blade::if('perm', fn (string $permissions) => auth()->check() && auth()->user()->hasPermission($permissions));

        View::composer(['layouts.admin', 'layouts.pos'], function ($view) {
            $user = auth()->user();
            if ($user) {
                $view->with('unreadCount', AppNotification::visibleTo($user)->unreadBy($user)->count());
                $view->with('lastNotificationId', (int) (AppNotification::visibleTo($user)->max('id') ?? 0));
            }
        });
    }
}
