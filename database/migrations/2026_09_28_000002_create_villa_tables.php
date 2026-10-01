<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Villa module: villa types, facilities, villas, media, seasons, rate plans, rates, offers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villa_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('slug', 120)->unique();
            $t->string('short_description', 300)->nullable();
            $t->text('description')->nullable();
            $t->unsignedTinyInteger('max_adults')->default(2);
            $t->unsignedTinyInteger('max_children')->default(0);
            $t->unsignedTinyInteger('bedrooms')->default(1);
            $t->unsignedTinyInteger('bathrooms')->default(1);
            $t->unsignedSmallInteger('size_sqm')->nullable();
            $t->string('bed_configuration', 120)->nullable();
            $t->decimal('base_rate', 12, 2);
            $t->decimal('extra_adult_rate', 12, 2)->default(0);
            $t->decimal('extra_child_rate', 12, 2)->default(0);
            $t->unsignedTinyInteger('base_occupancy')->default(2);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('facilities', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->string('icon', 40)->default('check');
            $t->string('category', 40)->default('general');
            $t->timestamps();
        });

        Schema::create('facility_villa_type', function (Blueprint $t) {
            $t->foreignId('villa_type_id')->constrained()->cascadeOnDelete();
            $t->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $t->primary(['villa_type_id', 'facility_id']);
        });

        Schema::create('villas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->foreignId('villa_type_id')->constrained()->restrictOnDelete();
            $t->string('code', 20);
            $t->string('name', 100);
            $t->string('slug', 120)->unique();
            $t->string('zone', 60)->nullable();
            $t->text('description')->nullable();
            $t->decimal('rate_override', 12, 2)->nullable();
            $t->string('lock_ref', 60)->nullable()->comment('Door lock ID in the lock vendor system');
            $t->string('occupancy_status', 20)->default('vacant')->index(); // vacant | occupied
            $t->string('hk_status', 20)->default('ready')->index(); // dirty|cleaning|inspection|clean|ready
            $t->string('maintenance_status', 20)->default('ok')->index(); // ok|issue|out_of_order
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->string('notes', 500)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['property_id', 'code']);
        });

        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->morphs('mediable');
            $t->string('type', 10)->default('image'); // image | video
            $t->string('path', 500); // storage path or absolute URL
            $t->string('title', 150)->nullable();
            $t->string('alt', 200)->nullable();
            $t->boolean('is_cover')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('seasons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->date('start_date');
            $t->date('end_date');
            $t->string('color', 9)->default('#0E6B63');
            $t->unsignedTinyInteger('priority')->default(1);
            $t->timestamps();
            $t->index(['start_date', 'end_date']);
        });

        Schema::create('rate_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20)->unique();
            $t->string('name', 100);
            $t->string('description', 500)->nullable();
            $t->string('meal_plan', 20)->default('room_only'); // room_only|bb|hb|fb|ai
            $t->boolean('is_refundable')->default(true);
            $t->unsignedSmallInteger('free_cancel_days')->default(7);
            $t->decimal('cancel_penalty_pct', 5, 2)->default(100);
            $t->decimal('deposit_pct', 5, 2)->default(30);
            $t->decimal('price_adjust_pct', 6, 2)->default(0)->comment('+/- % against base/seasonal rate');
            $t->boolean('is_public')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('villa_type_id')->constrained()->cascadeOnDelete();
            $t->foreignId('season_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->unsignedTinyInteger('min_stay')->default(1);
            $t->timestamps();
            $t->unique(['villa_type_id', 'season_id']);
        });

        Schema::create('offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('title', 150);
            $t->string('slug', 170)->unique();
            $t->string('summary', 300)->nullable();
            $t->text('description')->nullable();
            $t->string('image', 500)->nullable();
            $t->string('discount_type', 10)->default('percent'); // percent | fixed
            $t->decimal('discount_value', 12, 2)->default(0);
            $t->string('promo_code', 40)->nullable()->unique();
            $t->unsignedTinyInteger('min_nights')->default(1);
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->boolean('is_active')->default(true);
            $t->boolean('is_featured')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['offers', 'rates', 'rate_plans', 'seasons', 'media', 'villas', 'facility_villa_type', 'facilities', 'villa_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
