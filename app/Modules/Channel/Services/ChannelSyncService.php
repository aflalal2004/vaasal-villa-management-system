<?php

namespace App\Modules\Channel\Services;

use App\Models\ChannelMapping;
use App\Models\ChannelSyncLog;
use App\Modules\Booking\Services\AvailabilityService;
use App\Modules\Channel\Contracts\ChannelManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Outbound ARI (availability) sync. Every inventory change writes an outbox row
 * (channel_sync_logs) which is then pushed to the channel manager. Failures stay in the
 * outbox for retry; a booking never fails because the channel manager is unreachable.
 */
class ChannelSyncService
{
    public function __construct(private ChannelManager $manager) {}

    public function queueAri(Carbon|string $from, Carbon|string $to, string $type = 'ari_push'): ?ChannelSyncLog
    {
        try {
            $from = Carbon::parse($from)->startOfDay();
            $to = Carbon::parse($to)->startOfDay();
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }
            $matrix = app(AvailabilityService::class)->availabilityMatrix($from, $to);
            $mappings = ChannelMapping::get();

            $rows = [];
            foreach ($matrix as $typeId => $dates) {
                $codes = $mappings->where('villa_type_id', $typeId)->pluck('external_room_code')->unique();
                foreach ($codes as $code) {
                    foreach ($dates as $date => $count) {
                        $rows[] = ['room_code' => $code, 'date' => $date, 'available' => $count, 'villa_type_id' => $typeId];
                    }
                }
            }

            $log = ChannelSyncLog::create([
                'direction' => 'out', 'type' => $type, 'status' => 'pending',
                'date_from' => $from, 'date_to' => $to, 'payload' => ['rows' => $rows, 'matrix' => $matrix],
            ]);
            return $this->push($log);
        } catch (\Throwable $e) {
            Log::error('ARI queue failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function push(ChannelSyncLog $log): ChannelSyncLog
    {
        $rows = $log->payload['rows'] ?? [];
        if (! $this->manager->isEnabled() || ! $rows) {
            $log->update(['status' => 'skipped', 'processed_at' => now(), 'attempts' => $log->attempts + 1,
                'response' => ! $rows ? 'No channel mappings configured.' : 'Channel manager not configured (CHANNEL_DRIVER='.$this->manager->name().').']);
            return $log;
        }
        try {
            $result = $this->manager->pushAvailability(array_map(fn ($r) => array_diff_key($r, ['villa_type_id' => 1]), $rows));
            $log->update(['status' => $result['ok'] ? 'sent' : 'failed', 'response' => $result['message'], 'processed_at' => now(), 'attempts' => $log->attempts + 1]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'response' => $e->getMessage(), 'attempts' => $log->attempts + 1]);
        }
        return $log;
    }

    public function retryFailed(): int
    {
        $n = 0;
        ChannelSyncLog::where('direction', 'out')->where('status', 'failed')->where('attempts', '<', 10)->each(function ($log) use (&$n) {
            $this->push($log);
            $n++;
        });
        return $n;
    }

    public function fullSync(int $days = 365): ?ChannelSyncLog
    {
        return $this->queueAri(now(), now()->addDays($days), 'full_sync');
    }
}
