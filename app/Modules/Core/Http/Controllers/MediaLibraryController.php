<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use App\Models\Setting;
use App\Modules\Core\Services\AuditService;
use App\Modules\Website\Services\MediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin → Website content → Stock media: search Unsplash / Pexels server-side and use results on the website. */
class MediaLibraryController extends Controller
{
    /** Hosts a chosen photo/video may come from (the providers' CDNs, or this site). */
    private const HOSTS = ['images.unsplash.com', 'plus.unsplash.com', 'images.pexels.com', 'videos.pexels.com', 'player.vimeo.com'];

    public function __construct(private MediaLibraryService $media) {}

    public function search(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'type' => ['nullable', Rule::in(['photo', 'video'])], 'page' => ['nullable', 'integer', 'min:1', 'max:10']]);
        return response()->json($this->media->search($data['q'] ?? '', $data['type'] ?? 'photo', (int) ($data['page'] ?? 1)) + ['providers' => $this->media->providers()]);
    }

    public function use(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['gallery', 'hero_image', 'hero_video'])],
            'url' => ['required', 'url:https', 'max:1000'],
            'thumb' => ['nullable', 'url:https', 'max:1000'],
            'video_url' => ['nullable', 'url:https', 'max:1000', 'required_if:action,hero_video'],
            'title' => ['nullable', 'string', 'max:150'],
            'author' => ['nullable', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', Rule::in(['villas', 'dining', 'grounds', 'spa', 'experiences'])],
        ]);
        foreach (['url', 'thumb', 'video_url'] as $k) {
            if (! empty($data[$k])) $this->assertHost($k, $data[$k]);
        }
        $credit = $data['author'] ? 'Photo: '.$data['author'].($data['provider'] && $data['provider'] !== 'library' ? ' / '.ucfirst($data['provider']) : '') : null;

        switch ($data['action']) {
            case 'gallery':
                $isVideo = ! empty($data['video_url']);
                GalleryItem::create(['category' => $data['category'] ?? 'grounds', 'title' => $data['title'] ?? $credit, 'type' => $isVideo ? 'video' : 'image',
                    'path' => $isVideo ? $data['video_url'] : $data['url'], 'thumbnail' => $data['thumb'] ?? $data['url'], 'sort_order' => (int) GalleryItem::max('sort_order') + 1]);
                $msg = 'Added to the gallery.';
                break;
            case 'hero_image':
                Setting::put('site_hero_image', $data['url']);
                $msg = 'Home page hero image updated.';
                break;
            default:
                Setting::put('site_hero_video', $data['video_url']);
                Setting::put('site_hero_video_poster', $data['thumb'] ?? $data['url']);
                $msg = 'Home page background video updated.';
        }
        AuditService::log('website', 'stock_media_'.$data['action'], null, ($data['video_url'] ?? $data['url']).($credit ? ' · '.$credit : ''));
        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $msg]) : back()->with('success', $msg);
    }

    private function assertHost(string $field, string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        if ($host !== $own && ! in_array($host, self::HOSTS, true)) {
            throw ValidationException::withMessages([$field => 'Media must come from Unsplash, Pexels, Vimeo or this website.']);
        }
    }
}
