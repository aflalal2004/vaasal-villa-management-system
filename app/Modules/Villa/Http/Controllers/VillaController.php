<?php

namespace App\Modules\Villa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Media;
use App\Models\Property;
use App\Models\Villa;
use App\Models\VillaType;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $villas = Villa::with(['type', 'currentStay.booking.guest'])
            ->when($request->filled('type'), fn ($q) => $q->where('villa_type_id', $request->query('type')))
            ->when($request->filled('hk'), fn ($q) => $q->where('hk_status', $request->query('hk')))
            ->orderBy('sort_order')->get();
        $nextArrivals = Booking::with('guest')->whereIn('bookings.status', ['confirmed', 'tentative'])->where('bookings.arrival', '>=', now()->toDateString())
            ->join('booking_villas', 'booking_villas.booking_id', '=', 'bookings.id')->where('booking_villas.status', 'active')
            ->orderBy('bookings.arrival')->get(['bookings.*', 'booking_villas.villa_id as bv_villa_id'])->groupBy('bv_villa_id')->map->first();

        return view('admin.villas.index', ['villas' => $villas, 'types' => VillaType::orderBy('sort_order')->pluck('name', 'id'), 'nextArrivals' => $nextArrivals]);
    }

    public function show(Villa $villa)
    {
        $villa->load(['type.facilities', 'media', 'type.media', 'currentStay.booking.guest', 'tickets' => fn ($q) => $q->latest()->limit(10), 'hkTasks' => fn ($q) => $q->latest()->limit(10)]);
        $bookings = Booking::with('guest', 'channel')->whereHas('villas', fn ($q) => $q->where('villa_id', $villa->id)->where('status', 'active'))
            ->where('departure', '>=', now()->subDays(60))->orderBy('arrival')->get();
        $year = now()->startOfYear();
        $sold = \App\Models\InventoryNight::where('villa_id', $villa->id)->whereNotNull('booking_villa_id')->whereBetween('stay_date', [$year, now()])->count();
        $occupancyYtd = round($sold / max(1, $year->diffInDays(now()) + 1) * 100, 1);
        return view('admin.villas.show', ['villa' => $villa, 'bookings' => $bookings, 'occupancyYtd' => $occupancyYtd]);
    }

    public function create()
    {
        return view('admin.villas.form', ['villa' => new Villa(['is_active' => true, 'hk_status' => 'ready']), 'types' => VillaType::orderBy('sort_order')->pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $villa = Villa::create($data + ['property_id' => Property::current()->id, 'slug' => Str::slug($data['name'].'-'.$data['code'])]);
        AuditService::log('villas', 'created', $villa, $villa->code.' '.$villa->name);
        app(\App\Modules\Channel\Services\ChannelSyncService::class)->queueAri(now(), now()->addDays(90));
        return redirect()->route('admin.villas.show', $villa)->with('success', 'Villa '.$villa->code.' created and added to inventory.');
    }

    public function edit(Villa $villa)
    {
        return view('admin.villas.form', ['villa' => $villa->load('media'), 'types' => VillaType::orderBy('sort_order')->pluck('name', 'id')]);
    }

    public function update(Request $request, Villa $villa)
    {
        $villa->fill($this->validated($request, $villa));
        AuditService::logChanges('villas', $villa);
        $villa->save();
        app(\App\Modules\Channel\Services\ChannelSyncService::class)->queueAri(now(), now()->addDays(90));
        return redirect()->route('admin.villas.show', $villa)->with('success', 'Villa saved.');
    }

    /** Villas with booking history are archived (soft-deleted), never hard-deleted. */
    public function destroy(Villa $villa)
    {
        if ($villa->nights()->where('stay_date', '>=', now()->toDateString())->exists()) {
            return back()->with('error', 'This villa has future bookings or blocks. Move them before archiving.');
        }
        $villa->update(['is_active' => false]);
        $villa->delete();
        AuditService::log('villas', 'archived', $villa, $villa->code);
        return redirect()->route('admin.villas.index')->with('success', 'Villa archived. Its history is kept.');
    }

    public function addMedia(Request $request, Villa $villa)
    {
        $request->validate(['photos' => ['required', 'array', 'max:10'], 'photos.*' => ['file', 'max:'.config('vaasal.security.upload_max_kb')]]);
        $n = $villa->media()->count();
        foreach ($request->file('photos') as $file) {
            $path = UploadService::image($file, 'villas');
            $villa->media()->create(['type' => 'image', 'path' => $path, 'alt' => $villa->name, 'sort_order' => $n++, 'is_cover' => $n === 1]);
        }
        return back()->with('success', 'Photos uploaded.');
    }

    private function validated(Request $request, ?Villa $villa = null): array
    {
        $data = $request->validate([
            'villa_type_id' => ['required', 'exists:villa_types,id'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('villas', 'code')->ignore($villa?->id)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:100'],
            'zone' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:3000'],
            'rate_override' => ['nullable', 'numeric', 'min:0'],
            'lock_ref' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        return $data + ['is_active' => $request->boolean('is_active'), 'sort_order' => $data['sort_order'] ?? 0];
    }
}
