<?php

namespace App\Modules\Channel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMapping;
use App\Models\ChannelSyncLog;
use App\Models\RatePlan;
use App\Models\VillaType;
use App\Models\WebhookInbox;
use App\Modules\Channel\Contracts\ChannelManager;
use App\Modules\Channel\Services\ChannelSyncService;
use App\Modules\Channel\Services\OtaReservationService;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    public function index(Request $request, ChannelManager $manager)
    {
        return view('admin.channels.index', [
            'channels' => Channel::withCount(['bookings as bookings_mtd' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth())])->orderBy('type')->orderBy('name')->get(),
            'mappings' => ChannelMapping::with(['channel', 'villaType', 'ratePlan'])->orderBy('channel_id')->get(),
            'logs' => ChannelSyncLog::latest('id')->limit(25)->get(),
            'inbox' => WebhookInbox::latest('id')->limit(10)->get(),
            'types' => VillaType::orderBy('sort_order')->pluck('name', 'id'),
            'plans' => RatePlan::pluck('name', 'id'),
            'otas' => Channel::where('type', 'ota')->pluck('name', 'code'),
            'driver' => $manager->name(),
            'enabled' => $manager->isEnabled(),
        ]);
    }

    public function update(Request $request, Channel $channel)
    {
        $data = $request->validate(['commission_pct' => ['required', 'numeric', 'min:0', 'max:50']]);
        $channel->fill($data + ['is_active' => $request->boolean('is_active')]);
        AuditService::logChanges('channels', $channel);
        $channel->save();
        return back()->with('success', $channel->name.' saved.');
    }

    public function storeMapping(Request $request)
    {
        $data = $request->validate([
            'channel_id' => ['required', 'exists:channels,id'], 'villa_type_id' => ['required', 'exists:villa_types,id'],
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'], 'external_room_code' => ['required', 'string', 'max:80'],
            'external_rate_code' => ['nullable', 'string', 'max:80'],
        ]);
        ChannelMapping::updateOrCreate(['channel_id' => $data['channel_id'], 'external_room_code' => $data['external_room_code'], 'external_rate_code' => $data['external_rate_code'] ?? null], $data);
        return back()->with('success', 'Mapping saved.');
    }

    public function destroyMapping(ChannelMapping $mapping)
    {
        $mapping->delete();
        return back()->with('success', 'Mapping removed.');
    }

    public function fullSync(ChannelSyncService $sync)
    {
        $log = $sync->fullSync(365);
        return back()->with($log?->status === 'failed' ? 'error' : 'success', 'Full availability sync: '.($log ? $log->status.' — '.$log->response : 'error'));
    }

    public function retry(ChannelSyncService $sync)
    {
        return back()->with('success', $sync->retryFailed().' failed push(es) retried.');
    }

    /** Local test harness: feed a reservation through the same ingest path the webhook uses. */
    public function simulate(Request $request, OtaReservationService $ota)
    {
        $data = $request->validate([
            'channel_code' => ['required', 'exists:channels,code'], 'action' => ['required', Rule::in(['new', 'modified', 'cancelled'])],
            'external_ref' => ['nullable', 'string', 'max:60'], 'room_code' => ['required', 'string', 'max:80'],
            'arrival' => ['required', 'date'], 'departure' => ['required', 'date', 'after:arrival'], 'adults' => ['required', 'integer', 'min:1'],
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'], 'email' => ['nullable', 'email'], 'amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $result = $ota->ingest([
            'event_id' => 'sim-'.Str::uuid(), 'action' => $data['action'], 'channel_code' => $data['channel_code'],
            'external_ref' => $data['external_ref'] ?: strtoupper(substr($data['channel_code'], 0, 3)).'-'.random_int(1000000, 9999999),
            'room_code' => $data['room_code'], 'arrival' => $data['arrival'], 'departure' => $data['departure'], 'adults' => $data['adults'],
            'children' => 0, 'amount' => (float) ($data['amount'] ?? 0),
            'guest' => ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'] ?? null],
        ], 'simulator');
        $flash = match ($result['status']) { 'conflict' => 'warning', 'created', 'modified', 'cancelled' => 'success', default => 'info' };
        return back()->with($flash, 'Simulated OTA '.$data['action'].': '.$result['message']);
    }
}
