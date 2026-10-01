<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $guests = Guest::withCount('bookings')
            ->withSum(['bookings as spend' => fn ($q) => $q->where('status', 'checked_out')], 'grand_total')
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->query('q').'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $t)->orWhere('last_name', 'like', $t)->orWhere('email', 'like', $t)->orWhere('phone', 'like', $t)
                    ->orWhereRaw("CONCAT(first_name,' ',last_name) like ?", [$t]));
            })
            ->when($request->query('flag') === 'vip', fn ($q) => $q->where('is_vip', true))
            ->when($request->query('flag') === 'blacklisted', fn ($q) => $q->where('is_blacklisted', true))
            ->orderBy('last_name')->paginate(25)->withQueryString();
        return view('admin.guests.index', ['guests' => $guests]);
    }

    public function show(Guest $guest)
    {
        $guest->load(['bookings' => fn ($q) => $q->with(['channel', 'activeVillas.villa'])->latest('arrival')]);
        return view('admin.guests.show', ['guest' => $guest, 'lifetime' => $guest->bookings->where('status', 'checked_out')->sum('grand_total')]);
    }

    public function edit(Guest $guest)
    {
        return view('admin.guests.edit', ['guest' => $guest]);
    }

    public function update(Request $request, Guest $guest)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:10'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'id_type' => ['nullable', Rule::in(['passport', 'nic', 'driving_licence'])],
            'id_number' => ['nullable', 'string', 'max:40'],
            'id_expiry' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:300'],
            'preferences' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'id_document' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
        ]);
        unset($data['id_document']);
        if (empty($data['id_number'])) unset($data['id_number']); // keep existing encrypted value
        $guest->fill($data + ['is_vip' => $request->boolean('is_vip'), 'is_blacklisted' => $request->boolean('is_blacklisted'), 'marketing_consent' => $request->boolean('marketing_consent')]);
        if ($request->hasFile('id_document')) {
            $guest->id_document_path = UploadService::privateDocument($request->file('id_document'), 'guest-ids');
        }
        AuditService::logChanges('guests', $guest);
        $guest->save();
        return redirect()->route('admin.guests.show', $guest)->with('success', 'Guest profile saved.');
    }

    /** ID/passport scans are private; streamed only to users with guests.manage. */
    public function idDocument(Guest $guest)
    {
        abort_unless($guest->id_document_path && Storage::disk('local')->exists($guest->id_document_path), 404);
        AuditService::log('guests', 'id_document_viewed', $guest);
        return Storage::disk('local')->response($guest->id_document_path);
    }
}
