<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SocialLink extends BaseModel
{
    /** platform => [display name, x-icon name, URL host(s) accepted] */
    public const PLATFORMS = [
        'facebook' => ['Facebook', 'facebook', ['facebook.com', 'fb.com', 'fb.me']],
        'instagram' => ['Instagram', 'instagram', ['instagram.com']],
        'whatsapp' => ['WhatsApp', 'whatsapp-fill', ['wa.me', 'whatsapp.com', 'api.whatsapp.com']],
        'youtube' => ['YouTube', 'youtube', ['youtube.com', 'youtu.be']],
        'tiktok' => ['TikTok', 'tiktok', ['tiktok.com']],
        'x' => ['X (Twitter)', 'x-social', ['x.com', 'twitter.com']],
        'linkedin' => ['LinkedIn', 'linkedin', ['linkedin.com']],
        'tripadvisor' => ['Tripadvisor', 'tripadvisor', ['tripadvisor.com', 'tripadvisor.co.uk', 'tripadvisor.in']],
    ];

    public const SCOPES = ['both' => 'Website & restaurant', 'website' => 'Website only', 'restaurant' => 'Restaurant / POS only'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('vv.social'));
        static::deleted(fn () => Cache::forget('vv.social'));
    }

    public function name(): string { return $this->label ?: (self::PLATFORMS[$this->platform][0] ?? ucfirst($this->platform)); }
    public function icon(): string { return self::PLATFORMS[$this->platform][1] ?? 'link'; }

    /** Active links for a surface ("website" or "restaurant"), cached until any link changes. */
    public static function for(string $scope = 'website'): Collection
    {
        $all = rescue(fn () => Cache::rememberForever('vv.social', fn () => static::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()), collect(), false);
        return $all->filter(fn ($l) => $l->scope === 'both' || $l->scope === $scope)->values();
    }
}
