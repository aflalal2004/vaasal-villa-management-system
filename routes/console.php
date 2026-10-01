<?php

use App\Modules\Booking\Services\BookingService;
use App\Modules\Channel\Services\ChannelSyncService;
use App\Modules\FrontDesk\Services\NightAuditService;
use App\Modules\KeyCards\Services\KeyCardService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs. On XAMPP/Windows run `php artisan schedule:work` in a terminal, or add a Task Scheduler
| entry running `php artisan schedule:run` every minute (see docs/SETUP.md).
*/
Artisan::command('hms:expire-holds', fn () => $this->info(app(BookingService::class)->expireHolds().' hold(s) expired'))
    ->purpose('Release website holds whose payment window has passed');
Artisan::command('hms:expire-cards', fn () => $this->info(app(KeyCardService::class)->expireDue().' card assignment(s) expired'))
    ->purpose('Expire key cards past their validity');
Artisan::command('hms:channel-retry', fn () => $this->info(app(ChannelSyncService::class)->retryFailed().' ARI push(es) retried'))
    ->purpose('Retry failed channel manager pushes');
Artisan::command('hms:night-audit', function () {
    $r = app(NightAuditService::class)->run(now());
    $this->table(array_keys($r), [array_values($r)]);
})->purpose('Post room charges, flag no-shows, expire holds/cards, create stay-over tasks');

Schedule::command('hms:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('hms:expire-cards')->everyFifteenMinutes();
Schedule::command('hms:channel-retry')->everyFiveMinutes();
Schedule::command('hms:night-audit')->dailyAt('02:00');
