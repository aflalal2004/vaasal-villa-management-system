<?php

namespace App\Modules\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\GalleryItem;
use App\Models\Offer;
use App\Models\RatePlan;
use App\Models\SiteService;
use App\Models\Testimonial;
use App\Models\VillaType;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Website\Services\WeatherService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function home()
    {
        return view('website.home', [
            'types' => VillaType::with(['media', 'facilities'])->withCount('villas')->where('is_active', true)->orderBy('sort_order')->get(),
            'offers' => Offer::live()->where('is_featured', true)->latest()->limit(3)->get(),
            'services' => SiteService::where('is_active', true)->orderBy('sort_order')->limit(4)->get(),
            'gallery' => GalleryItem::where('is_active', true)->where('type', 'image')->orderBy('sort_order')->limit(6)->get(),
            'testimonials' => Testimonial::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function about()
    {
        return view('website.about', ['gallery' => GalleryItem::where('is_active', true)->where('type', 'image')->inRandomOrder()->limit(2)->get()]);
    }

    public function villas()
    {
        return view('website.villas', ['types' => VillaType::with(['media', 'facilities'])->withCount('villas')->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function villa(VillaType $type)
    {
        abort_unless($type->is_active, 404);
        $type->load(['media', 'facilities', 'villas' => fn ($q) => $q->where('is_active', true)]);
        return view('website.villa', [
            't' => $type,
            'plans' => RatePlan::where('is_active', true)->where('is_public', true)->get(),
            'seasonRates' => $type->rates()->with('season')->whereHas('season', fn ($q) => $q->where('end_date', '>=', now()))->get()->sortBy('season.start_date'),
            'others' => VillaType::with('media', 'facilities')->withCount('villas')->where('is_active', true)->where('id', '!=', $type->id)->limit(3)->get(),
            'offers' => Offer::live()->limit(2)->get(),
        ]);
    }

    public function services()
    {
        return view('website.services', ['services' => SiteService::with('chargeItem')->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function gallery()
    {
        $items = GalleryItem::where('is_active', true)->orderBy('sort_order')->get();
        return view('website.gallery', ['items' => $items, 'categories' => $items->pluck('category')->unique()->values()]);
    }

    public function offers()
    {
        return view('website.offers', ['offers' => Offer::live()->latest()->get()]);
    }

    public function contact()
    {
        return view('website.contact');
    }

    public function enquiry(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150'], 'message' => ['required', 'string', 'min:10', 'max:3000'],
            'arrival' => ['nullable', 'date', 'after_or_equal:today'], 'departure' => ['nullable', 'date', 'after:arrival'], 'guests' => ['nullable', 'integer', 'min:1', 'max:40'],
            'website' => ['prohibited'], // honeypot for bots
        ], ['website.prohibited' => 'Your message could not be sent.']);
        unset($data['website']);
        $e = Enquiry::create($data + ['ip_address' => $request->ip()]);
        NotificationService::notify('enquiry.new', 'Website enquiry from '.$e->name, $e->subject ?: \Illuminate\Support\Str::limit($e->message, 90), route('admin.enquiries.index'), 'info', 'bookings.view');
        return back()->with('success', 'Thank you, '.$e->name.'. We usually reply within a few hours — or message us on WhatsApp for a faster answer.');
    }

    public function weather(WeatherService $weather)
    {
        return response()->json($weather->forecast());
    }
}
