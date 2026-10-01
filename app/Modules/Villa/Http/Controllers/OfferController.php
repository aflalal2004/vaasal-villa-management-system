<?php

namespace App\Modules\Villa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Property;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Offers, promotions and promo codes (shown on the website and applied in the booking engine). */
class OfferController extends Controller
{
    public function index()
    {
        return view('admin.villas.offers', ['offers' => Offer::latest()->get()]);
    }

    public function create()
    {
        return view('admin.villas.offer-form', ['offer' => new Offer(['is_active' => true, 'discount_type' => 'percent', 'min_nights' => 1])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $offer = Offer::create($data + ['property_id' => Property::current()->id, 'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(4))]);
        AuditService::log('rates', 'offer_created', $offer, $offer->title);
        return redirect()->route('admin.offers.index')->with('success', 'Offer published.');
    }

    public function edit(Offer $offer)
    {
        return view('admin.villas.offer-form', ['offer' => $offer]);
    }

    public function update(Request $request, Offer $offer)
    {
        $offer->fill($this->validated($request, $offer));
        AuditService::logChanges('rates', $offer, 'offer_updated');
        $offer->save();
        return redirect()->route('admin.offers.index')->with('success', 'Offer saved.');
    }

    public function destroy(Offer $offer)
    {
        if ($offer->used_count > 0) {
            $offer->update(['is_active' => false]);
            return back()->with('success', 'Offer has been used, so it was deactivated instead of deleted.');
        }
        $offer->delete();
        return back()->with('success', 'Offer deleted.');
    }

    private function validated(Request $request, ?Offer $offer = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:3000'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0', Rule::when($request->input('discount_type') === 'percent', ['max:100'])],
            'promo_code' => ['nullable', 'string', 'max:40', 'alpha_num', Rule::unique('offers', 'promo_code')->ignore($offer?->id)],
            'min_nights' => ['required', 'integer', 'min:1', 'max:60'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'image_file' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
            'image' => ['nullable', 'url', 'max:500'],
        ]);
        unset($data['image_file']);
        if ($request->hasFile('image_file')) {
            $data['image'] = UploadService::image($request->file('image_file'), 'offers');
        } elseif (! $request->filled('image')) {
            unset($data['image']);
        }
        if (! empty($data['promo_code'])) $data['promo_code'] = strtoupper($data['promo_code']);
        return $data + ['is_active' => $request->boolean('is_active'), 'is_featured' => $request->boolean('is_featured')];
    }
}
