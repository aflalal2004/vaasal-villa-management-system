<?php

namespace App\Modules\Core\Http\Controllers;

use App\Models\ChargeItem;
use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use App\Models\Setting;
use App\Models\SiteService;
use App\Models\Testimonial;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Website content: hero, about, contact details, social links, services, gallery, testimonials. */
class CmsController extends Controller
{
    public const KEYS = ['site_tagline', 'site_hero_title', 'site_hero_text', 'site_hero_video', 'site_about', 'contact_email', 'contact_phone', 'contact_address',
        'whatsapp_number', 'stat_guests', 'stat_rating', 'stat_years', 'youtube_tour_id', 'site_hero_image', 'seo_title', 'seo_description', 'seo_keywords'];

    public function index(Request $request)
    {
        return view('admin.cms.index', [
            'tab' => $request->query('tab', 'content'),
            'values' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)]),
            'gallery' => GalleryItem::orderBy('category')->orderBy('sort_order')->get(),
            'services' => SiteService::orderBy('sort_order')->get(),
            'testimonials' => Testimonial::orderBy('sort_order')->get(),
            'chargeItems' => ChargeItem::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function content(Request $request)
    {
        $rules = array_fill_keys(self::KEYS, ['nullable', 'string', 'max:3000']);
        $rules['site_hero_image'] = ['nullable', 'url', 'max:500'];
        $rules['seo_title'] = ['nullable', 'string', 'max:70'];
        $rules['seo_description'] = ['nullable', 'string', 'max:170'];
        $rules['seo_keywords'] = ['nullable', 'string', 'max:255'];
        $rules['contact_phone'] = ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()-]+$/'];
        $rules['whatsapp_number'] = ['nullable', 'regex:/^\+?[0-9 ]{8,20}$/'];
        $rules['contact_email'] = ['nullable', 'email'];
        $rules['site_hero_video'] = ['nullable', 'url', 'max:500'];
        $rules['youtube_tour_id'] = ['nullable', 'regex:/^[A-Za-z0-9_-]{6,20}$/'];
        $data = $request->validate($rules);
        if (! empty($data['whatsapp_number'])) $data['whatsapp_number'] = preg_replace('/\D/', '', $data['whatsapp_number']);
        foreach ($data as $k => $v) {
            Setting::put($k, $v);
        }
        AuditService::log('website', 'content_updated', null, implode(', ', array_keys($data)));
        return back()->with('success', 'Website content saved.');
    }

    public function storeGallery(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'in:villas,dining,grounds,spa,experiences'], 'title' => ['nullable', 'string', 'max:150'],
            'photos' => ['nullable', 'array', 'max:12'], 'photos.*' => ['file', 'max:'.config('vaasal.security.upload_max_kb')],
            'video_url' => ['nullable', 'url', 'regex:/^https:\/\/(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\//'],
        ]);
        $n = GalleryItem::max('sort_order') + 1;
        foreach ($request->file('photos', []) as $f) {
            $path = UploadService::image($f, 'gallery');
            GalleryItem::create(['category' => $data['category'], 'title' => $data['title'] ?? null, 'type' => 'image', 'path' => $path, 'thumbnail' => $path, 'sort_order' => $n++]);
        }
        if (! empty($data['video_url'])) {
            GalleryItem::create(['category' => $data['category'], 'title' => $data['title'] ?? 'Video', 'type' => 'video', 'path' => $data['video_url'], 'sort_order' => $n++]);
        }
        return back()->with('success', 'Gallery updated.');
    }

    public function destroyGallery(GalleryItem $item)
    {
        $item->delete();
        return back()->with('success', 'Removed from gallery.');
    }

    public function storeService(Request $request)
    {
        $data = $this->service($request);
        SiteService::create($data + ['slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(3))]);
        return back()->with('success', 'Service added.');
    }

    public function updateService(Request $request, SiteService $service)
    {
        $service->update($this->service($request) + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Service saved.');
    }

    public function storeTestimonial(Request $request)
    {
        $data = $request->validate(['guest_name' => ['required', 'string', 'max:100'], 'country' => ['nullable', 'string', 'max:80'], 'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'max:1000'], 'source' => ['nullable', 'string', 'max:40']]);
        Testimonial::create($data + ['sort_order' => Testimonial::max('sort_order') + 1]);
        return back()->with('success', 'Testimonial added.');
    }

    public function destroyTestimonial(Testimonial $testimonial)
    {
        $testimonial->delete();
        return back()->with('success', 'Testimonial removed.');
    }

    private function service(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'summary' => ['nullable', 'string', 'max:300'], 'description' => ['nullable', 'string', 'max:3000'],
            'icon' => ['nullable', 'string', 'max:40'], 'price_from' => ['nullable', 'numeric', 'min:0'], 'charge_item_id' => ['nullable', 'exists:charge_items,id'],
            'sort_order' => ['nullable', 'integer'], 'image_file' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')], 'image' => ['nullable', 'url'],
        ]);
        unset($data['image_file']);
        if ($request->hasFile('image_file')) $data['image'] = UploadService::image($request->file('image_file'), 'services');
        elseif (empty($data['image'])) unset($data['image']);
        return $data + ['icon' => $data['icon'] ?? 'star', 'sort_order' => $data['sort_order'] ?? 0];
    }
}
