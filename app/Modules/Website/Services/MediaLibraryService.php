<?php

namespace App\Modules\Website\Services;

use App\Models\GalleryItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stock photo and video search for the website (Unsplash photos, Pexels photos and videos).
 *
 * - API keys stay on the server (config/vaasal.php → media); the browser only ever sees image/video URLs.
 * - Results are cached per query; a failed provider is skipped and never breaks a page.
 * - With no keys configured, search falls back to the property's own gallery, so the admin screen still works.
 * Every result is normalised to: id, provider, type, thumb, url, width, height, alt, author, author_url, source_url, video_url.
 */
class MediaLibraryService
{
    public function providers(): array
    {
        return [
            'unsplash' => (bool) config('vaasal.media.unsplash_key'),
            'pexels' => (bool) config('vaasal.media.pexels_key'),
        ];
    }

    /** @return array{items: array<int, array>, provider: string, fallback: bool} */
    public function search(string $query, string $type = 'photo', int $page = 1): array
    {
        $query = trim(mb_substr($query, 0, 80)) ?: 'luxury villa';
        $page = max(1, min(10, $page));
        $key = 'vv.media.'.md5($type.'|'.$query.'|'.$page);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $attempts = $type === 'video' ? ['pexels_video'] : ['unsplash', 'pexels_photo'];
        foreach ($attempts as $provider) {
            $items = $this->call($provider, $query, $page);
            if ($items) {
                $result = ['items' => $items, 'provider' => str_replace(['_photo', '_video'], '', $provider), 'fallback' => false];
                Cache::put($key, $result, now()->addMinutes(config('vaasal.media.cache_minutes')));
                return $result;
            }
        }
        return ['items' => $this->localLibrary($type), 'provider' => 'library', 'fallback' => true];
    }

    private function call(string $provider, string $query, int $page): array
    {
        try {
            return match ($provider) {
                'unsplash' => $this->unsplash($query, $page),
                'pexels_photo' => $this->pexelsPhotos($query, $page),
                'pexels_video' => $this->pexelsVideos($query, $page),
            };
        } catch (\Throwable $e) {
            Log::warning('Media provider failed', ['provider' => $provider, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function unsplash(string $query, int $page): array
    {
        $key = config('vaasal.media.unsplash_key');
        if (! $key) return [];
        $res = Http::timeout(config('vaasal.media.timeout'))->acceptJson()->withHeaders(['Authorization' => 'Client-ID '.$key, 'Accept-Version' => 'v1'])
            ->get('https://api.unsplash.com/search/photos', ['query' => $query, 'page' => $page, 'per_page' => 24, 'orientation' => 'landscape', 'content_filter' => 'high']);
        if (! $res->successful()) throw new \RuntimeException('Unsplash HTTP '.$res->status());
        return collect($res->json('results', []))->map(fn ($p) => [
            'id' => 'unsplash-'.$p['id'], 'provider' => 'unsplash', 'type' => 'photo',
            'thumb' => $p['urls']['small'] ?? null, 'url' => ($p['urls']['raw'] ?? $p['urls']['regular']).'&auto=format&fit=crop&w=1800&q=70',
            'width' => $p['width'] ?? null, 'height' => $p['height'] ?? null, 'alt' => $p['alt_description'] ?? $query,
            'author' => $p['user']['name'] ?? null, 'author_url' => ($p['user']['links']['html'] ?? null), 'source_url' => $p['links']['html'] ?? null, 'video_url' => null,
        ])->filter(fn ($i) => $i['thumb'])->values()->all();
    }

    private function pexelsPhotos(string $query, int $page): array
    {
        $key = config('vaasal.media.pexels_key');
        if (! $key) return [];
        $res = Http::timeout(config('vaasal.media.timeout'))->acceptJson()->withHeaders(['Authorization' => $key])
            ->get('https://api.pexels.com/v1/search', ['query' => $query, 'page' => $page, 'per_page' => 24, 'orientation' => 'landscape']);
        if (! $res->successful()) throw new \RuntimeException('Pexels HTTP '.$res->status());
        return collect($res->json('photos', []))->map(fn ($p) => [
            'id' => 'pexels-'.$p['id'], 'provider' => 'pexels', 'type' => 'photo',
            'thumb' => $p['src']['medium'] ?? null, 'url' => $p['src']['large2x'] ?? $p['src']['original'],
            'width' => $p['width'] ?? null, 'height' => $p['height'] ?? null, 'alt' => $p['alt'] ?: $query,
            'author' => $p['photographer'] ?? null, 'author_url' => $p['photographer_url'] ?? null, 'source_url' => $p['url'] ?? null, 'video_url' => null,
        ])->filter(fn ($i) => $i['thumb'])->values()->all();
    }

    private function pexelsVideos(string $query, int $page): array
    {
        $key = config('vaasal.media.pexels_key');
        if (! $key) return [];
        $res = Http::timeout(config('vaasal.media.timeout'))->acceptJson()->withHeaders(['Authorization' => $key])
            ->get('https://api.pexels.com/videos/search', ['query' => $query, 'page' => $page, 'per_page' => 15, 'orientation' => 'landscape']);
        if (! $res->successful()) throw new \RuntimeException('Pexels video HTTP '.$res->status());
        return collect($res->json('videos', []))->map(function ($v) use ($query) {
            // Prefer an HD MP4 no wider than 1920px: sharp enough for a hero, light enough to stream.
            $file = collect($v['video_files'] ?? [])->filter(fn ($f) => ($f['file_type'] ?? '') === 'video/mp4' && ($f['width'] ?? 0) <= 1920)
                ->sortByDesc('width')->first();
            return [
                'id' => 'pexels-video-'.$v['id'], 'provider' => 'pexels', 'type' => 'video',
                'thumb' => $v['image'] ?? null, 'url' => $v['image'] ?? null, 'width' => $file['width'] ?? null, 'height' => $file['height'] ?? null,
                'alt' => $query, 'author' => $v['user']['name'] ?? null, 'author_url' => $v['user']['url'] ?? null, 'source_url' => $v['url'] ?? null,
                'video_url' => $file['link'] ?? null, 'duration' => $v['duration'] ?? null,
            ];
        })->filter(fn ($i) => $i['video_url'] && $i['thumb'])->values()->all();
    }

    /** Offline / no-key fallback: the property's own gallery. */
    private function localLibrary(string $type): array
    {
        return GalleryItem::where('is_active', true)->where('type', $type === 'video' ? 'video' : 'image')->orderBy('sort_order')->limit(24)->get()
            ->map(fn (GalleryItem $g) => [
                'id' => 'gallery-'.$g->id, 'provider' => 'library', 'type' => $type, 'thumb' => $g->thumbUrl(), 'url' => $g->url(),
                'width' => null, 'height' => null, 'alt' => $g->title ?? 'Vaasal Villa', 'author' => 'Vaasal Villa', 'author_url' => null, 'source_url' => null,
                'video_url' => $type === 'video' ? $g->url() : null,
            ])->all();
    }
}
