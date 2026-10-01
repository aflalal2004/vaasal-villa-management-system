<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'new');
        return view('admin.bookings.enquiries', [
            'status' => $status,
            'enquiries' => Enquiry::with('handler')->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest()->paginate(20)->withQueryString(),
            'counts' => Enquiry::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'replied', 'closed'])], 'reply_notes' => ['nullable', 'string', 'max:2000']]);
        $enquiry->update($data + ['handled_by' => auth()->id()]);
        return back()->with('success', 'Enquiry updated.');
    }
}
