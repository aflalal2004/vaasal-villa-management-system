<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Social media links managed in Admin → Website → Social media (and POS → Social for the restaurant).
 * Replaces the loose social_* settings keys; existing values are carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_links', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 20); // facebook|instagram|whatsapp|youtube|tiktok|x|linkedin|tripadvisor
            $t->string('label', 60)->nullable();
            $t->string('url', 255);
            $t->string('scope', 12)->default('website')->index(); // website|restaurant|both
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        $settings = DB::table('settings')->whereIn('key', ['social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'whatsapp_number'])->pluck('value', 'key');
        $defaults = [
            'facebook' => $settings['social_facebook'] ?? 'https://facebook.com/',
            'instagram' => $settings['social_instagram'] ?? 'https://instagram.com/',
            'whatsapp' => 'https://wa.me/'.preg_replace('/\D/', '', $settings['whatsapp_number'] ?? '94764413420'),
            'youtube' => $settings['social_youtube'] ?? 'https://youtube.com/',
            'tiktok' => $settings['social_tiktok'] ?? 'https://tiktok.com/',
        ];
        $i = 0;
        foreach ($defaults as $platform => $url) {
            if (! $url) continue;
            DB::table('social_links')->insert(['platform' => $platform, 'url' => $url, 'scope' => 'both', 'is_active' => true,
                'sort_order' => $i++, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
