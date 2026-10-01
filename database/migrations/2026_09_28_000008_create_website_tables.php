<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website CMS content: services, gallery, testimonials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_services', function (Blueprint $t) {
            $t->id();
            $t->string('title', 120);
            $t->string('slug', 140)->unique();
            $t->string('summary', 300)->nullable();
            $t->text('description')->nullable();
            $t->string('icon', 40)->default('star');
            $t->string('image', 500)->nullable();
            $t->decimal('price_from', 12, 2)->nullable();
            $t->foreignId('charge_item_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $t) {
            $t->id();
            $t->string('category', 40)->default('villas')->index();
            $t->string('title', 150)->nullable();
            $t->string('type', 10)->default('image'); // image|video
            $t->string('path', 500);
            $t->string('thumbnail', 500)->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->string('guest_name', 100);
            $t->string('country', 80)->nullable();
            $t->unsignedTinyInteger('rating')->default(5);
            $t->text('content');
            $t->string('source', 40)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['testimonials', 'gallery_items', 'site_services'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
