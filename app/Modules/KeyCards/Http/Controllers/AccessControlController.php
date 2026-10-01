<?php

namespace App\Modules\KeyCards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\KeyCard;
use App\Models\Villa;
use App\Modules\Core\Services\AuditService;
use App\Modules\KeyCards\Services\RfidAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Access control → RFID devices and the RFID simulator. Cards, issuing and logs live in KeyCardController. */
class AccessControlController extends Controller
{
    public function devices()
    {
        return view('admin.access.devices', [
            'devices' => AttendanceDevice::with('villa')->withCount(['accessLogs as scans_today' => fn ($q) => $q->whereDate('occurred_at', today())])->orderBy('name')->get(),
            'villas' => Villa::orderBy('sort_order')->pluck('code', 'id'),
            'zones' => $this->zones(),
        ]);
    }

    public function storeDevice(Request $request)
    {
        $data = $this->validated($request);
        $token = Str::random(48);
        $device = AttendanceDevice::create($data + ['api_token_hash' => hash('sha256', $token)]);
        AuditService::log('access', 'device_registered', $device, $device->name.' ('.$device->purpose.')');
        return back()->with('success', 'Device '.$device->name.' registered. Copy its API token now — it is shown only once.')->with('device_token', $token);
    }

    public function updateDevice(Request $request, AttendanceDevice $device)
    {
        $device->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);
        AuditService::log('access', 'device_updated', $device, $device->name);
        return back()->with('success', 'Device '.$device->name.' saved.');
    }

    public function rotateToken(AttendanceDevice $device)
    {
        $token = Str::random(48);
        $device->update(['api_token_hash' => hash('sha256', $token)]);
        AuditService::log('access', 'device_token_rotated', $device, $device->name);
        return back()->with('success', 'New token for '.$device->name.'. The old token stops working immediately.')->with('device_token', $token);
    }

    public function simulator(Request $request)
    {
        abort_unless(config('vaasal.locks.rfid_simulator'), 404);
        return view('admin.access.simulator', [
            'villas' => Villa::orderBy('sort_order')->get(),
            'zones' => $this->zones(),
            'devices' => AttendanceDevice::where('is_active', true)->orderBy('name')->get(),
            'result' => session('rfid_result'),
            'recent' => AccessLog::with(['villa', 'device'])->whereIn('source', ['simulator', 'device'])->latest('occurred_at')->limit(12)->get(),
            'samples' => [
                'Guest cards (active)' => KeyCard::where('status', 'active')->whereHas('activeAssignment', fn ($q) => $q->whereNotNull('booking_id'))->with('activeAssignment.villa')->limit(4)->get()
                    ->map(fn ($c) => [$c->uid, 'Villa '.$c->activeAssignment?->villa?->code.' guest'])->all(),
                'Staff cards / badges' => KeyCard::where('status', 'active')->whereHas('activeAssignment', fn ($q) => $q->whereNotNull('employee_id'))->with('activeAssignment.employee')->limit(3)->get()
                    ->map(fn ($c) => [$c->uid, $c->activeAssignment?->employee?->fullName().' ('.$c->activeAssignment?->access_level.')'])
                    ->merge(Employee::whereNotNull('rfid_uid')->where('status', 'active')->limit(3)->get()->map(fn ($e) => [$e->rfid_uid, $e->fullName().' badge']))->all(),
                'Should be denied' => KeyCard::whereIn('status', ['lost', 'blocked'])->limit(2)->get()->map(fn ($c) => [$c->uid, ucfirst($c->status).' card'])
                    ->push(['04DEADBEEF00', 'Unknown card'])->all(),
            ],
        ]);
    }

    public function simulate(Request $request, RfidAccessService $access)
    {
        abort_unless(config('vaasal.locks.rfid_simulator'), 404);
        $data = $request->validate([
            'uid' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9:\- ]+$/'],
            'villa_id' => ['nullable', 'exists:villas,id'],
            'zone' => ['nullable', 'string', 'max:60'],
            'device_id' => ['nullable', 'exists:attendance_devices,id'],
        ]);
        $device = ! empty($data['device_id']) ? AttendanceDevice::find($data['device_id']) : null;
        $r = $access->scan($data['uid'], $device, ! empty($data['villa_id']) ? Villa::find($data['villa_id']) : null, $data['zone'] ?? null, now(), 'simulator');
        return back()->withInput()->with('rfid_result', [
            'granted' => $r['granted'], 'reason' => $r['reason'], 'holder' => $r['holder'], 'holder_type' => $r['holder_type'],
            'target' => $r['target'], 'uid' => RfidAccessService::normalise($data['uid']), 'attendance' => $r['attendance'], 'time' => now()->format('H:i:s'),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(AttendanceDevice::TYPES))],
            'purpose' => ['required', Rule::in(array_keys(AttendanceDevice::PURPOSES))],
            'location' => ['nullable', 'string', 'max:100'],
            'villa_id' => ['nullable', 'exists:villas,id'],
            'zone' => ['nullable', 'string', 'max:60'],
            'mode' => ['required', Rule::in(['hardware', 'simulator'])],
        ]);
    }

    private function zones(): array
    {
        return collect(config('vaasal.locks.guest_zones'))->merge(array_keys(config('vaasal.locks.staff_zones')))
            ->merge(Villa::whereNotNull('zone')->distinct()->pluck('zone'))->unique()->sort()->values()->all();
    }
}
