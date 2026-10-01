<?php

namespace App\Modules\Housekeeping\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Guest;
use App\Models\LostFoundItem;
use App\Models\Villa;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LostFoundController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'stored');
        return view('admin.housekeeping.lost-found', [
            'items' => LostFoundItem::with(['villa', 'finder', 'guest'])->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('found_at')->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.housekeeping.lost-found-form', ['item' => new LostFoundItem(['villa_id' => $request->query('villa_id'), 'found_at' => now(),
            'found_by' => $request->user()->employee?->id, 'status' => 'stored']), 'villas' => Villa::orderBy('sort_order')->pluck('code', 'id'),
            'staff' => Employee::where('status', 'active')->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->fullName()])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        // Suggest the guest who last stayed in the villa
        if (! empty($data['villa_id']) && empty($data['guest_id'])) {
            $data['guest_id'] = \App\Models\Booking::whereHas('villas', fn ($q) => $q->where('villa_id', $data['villa_id']))
                ->where('checked_in_at', '<=', $data['found_at'])->latest('checked_in_at')->value('guest_id');
        }
        $item = LostFoundItem::create($data + ['item_no' => DocumentNumberService::next('lost_found')]);
        AuditService::log('lostfound', 'logged', $item, $item->description);
        return redirect()->route('admin.lost-found.index')->with('success', 'Item '.$item->item_no.' logged.'.($item->guest_id ? ' Matched to the last guest in the villa — contact them to arrange return.' : ''));
    }

    public function edit(LostFoundItem $item)
    {
        return view('admin.housekeeping.lost-found-form', ['item' => $item, 'villas' => Villa::orderBy('sort_order')->pluck('code', 'id'),
            'staff' => Employee::orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->fullName()])]);
    }

    public function update(Request $request, LostFoundItem $item)
    {
        $data = $this->validated($request, $item);
        if (in_array($data['status'], ['returned', 'disposed', 'claimed'], true) && ! $item->resolved_at) {
            $data['resolved_at'] = now();
        }
        $item->fill($data);
        AuditService::logChanges('lostfound', $item);
        $item->save();
        return redirect()->route('admin.lost-found.index')->with('success', 'Item updated.');
    }

    private function validated(Request $request, ?LostFoundItem $item = null): array
    {
        $data = $request->validate([
            'villa_id' => ['nullable', 'exists:villas,id'],
            'found_location' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:300'],
            'category' => ['required', Rule::in(['electronics', 'jewellery', 'clothing', 'documents', 'other'])],
            'found_by' => ['nullable', 'exists:employees,id'],
            'found_at' => ['required', 'date'],
            'storage_location' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(array_keys(LostFoundItem::STATUSES))],
            'guest_id' => ['nullable', 'exists:guests,id'],
            'returned_to' => ['nullable', 'required_if:status,returned', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
        ]);
        unset($data['photo']);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = UploadService::image($request->file('photo'), 'lost-found');
        }
        return $data;
    }
}
