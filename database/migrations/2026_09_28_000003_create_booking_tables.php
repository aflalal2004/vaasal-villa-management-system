<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guests, channels, tour operators, bookings and the central inventory ledger.
 *
 * Double-booking guard: inventory_nights has UNIQUE(villa_id, stay_date). Every booking,
 * hold and block writes one row per villa per night inside a transaction, so a conflicting
 * write from any channel fails at the database instead of overwriting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $t) {
            $t->id();
            $t->string('title', 10)->nullable();
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->string('email')->nullable()->index();
            $t->string('phone', 40)->nullable()->index();
            $t->string('country', 80)->nullable();
            $t->string('nationality', 80)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('id_type', 20)->nullable(); // passport | nic | driving_licence
            $t->text('id_number')->nullable(); // encrypted
            $t->date('id_expiry')->nullable();
            $t->string('id_document_path')->nullable(); // private disk
            $t->string('address', 300)->nullable();
            $t->boolean('is_vip')->default(false);
            $t->boolean('is_blacklisted')->default(false);
            $t->boolean('marketing_consent')->default(false);
            $t->text('preferences')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('channels', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name', 80);
            $t->string('type', 20); // direct | ota | operator
            $t->decimal('commission_pct', 5, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('tour_operators', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20)->unique();
            $t->string('company_name', 150);
            $t->string('legal_name', 150)->nullable();
            $t->string('registration_no', 60)->nullable();
            $t->string('tax_id', 60)->nullable();
            $t->string('contact_name', 100);
            $t->string('email')->index();
            $t->string('phone', 40)->nullable();
            $t->string('address', 300)->nullable();
            $t->string('country', 80)->nullable();
            $t->string('website')->nullable();
            $t->string('logo_path')->nullable();
            $t->text('bank_details')->nullable();
            $t->string('status', 20)->default('pending'); // pending | active | suspended
            $t->decimal('credit_limit', 14, 2)->default(0);
            $t->unsignedSmallInteger('payment_terms_days')->default(30);
            $t->text('notes')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreign('tour_operator_id')->references('id')->on('tour_operators')->nullOnDelete();
        });

        Schema::create('operator_contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tour_operator_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->date('valid_from');
            $t->date('valid_to');
            $t->decimal('commission_pct', 5, 2)->default(0);
            $t->decimal('discount_pct', 5, 2)->default(0)->comment('Discount off public rate when no net rate');
            $t->decimal('deposit_pct', 5, 2)->default(25);
            $t->unsignedSmallInteger('release_days')->default(14);
            $t->unsignedSmallInteger('rooming_cutoff_days')->default(3);
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('contract_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operator_contract_id')->constrained()->cascadeOnDelete();
            $t->foreignId('villa_type_id')->constrained()->cascadeOnDelete();
            $t->decimal('net_rate', 12, 2);
            $t->timestamps();
            $t->unique(['operator_contract_id', 'villa_type_id']);
        });

        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('reference', 20)->unique();
            $t->foreignId('channel_id')->constrained()->restrictOnDelete();
            $t->string('source', 20)->index(); // website|admin|walk_in|phone|email|tour_operator|ota
            $t->string('external_ref', 80)->nullable();
            $t->foreignId('guest_id')->constrained()->restrictOnDelete();
            $t->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('operator_contract_id')->nullable()->constrained()->nullOnDelete();
            $t->string('group_name', 120)->nullable();
            $t->string('status', 20)->index(); // hold|tentative|confirmed|checked_in|checked_out|cancelled|no_show|expired
            $t->date('arrival')->index();
            $t->date('departure')->index();
            $t->unsignedTinyInteger('adults')->default(1);
            $t->unsignedTinyInteger('children')->default(0);
            $t->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $t->char('currency', 3);
            $t->decimal('room_total', 14, 2)->default(0);
            $t->decimal('discount_total', 14, 2)->default(0);
            $t->decimal('tax_total', 14, 2)->default(0);
            $t->decimal('service_total', 14, 2)->default(0);
            $t->decimal('grand_total', 14, 2)->default(0);
            $t->decimal('deposit_due', 14, 2)->default(0);
            $t->decimal('commission_pct', 5, 2)->default(0);
            $t->decimal('commission_amount', 14, 2)->default(0);
            $t->text('special_requests')->nullable();
            $t->string('arrival_time', 20)->nullable();
            $t->string('payment_mode', 20)->default('deposit'); // full|deposit|pay_at_property|credit
            $t->timestamp('hold_expires_at')->nullable()->index();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason', 300)->nullable();
            $t->decimal('cancellation_fee', 14, 2)->default(0);
            $t->timestamp('checked_in_at')->nullable();
            $t->timestamp('checked_out_at')->nullable();
            $t->string('manage_token', 64)->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['channel_id', 'external_ref']);
        });

        Schema::create('booking_villas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('villa_id')->constrained()->restrictOnDelete();
            $t->foreignId('villa_type_id')->constrained()->restrictOnDelete();
            $t->date('arrival');
            $t->date('departure');
            $t->unsignedTinyInteger('adults')->default(1);
            $t->unsignedTinyInteger('children')->default(0);
            $t->json('nightly_rates');
            $t->decimal('total', 14, 2);
            $t->string('status', 20)->default('active'); // active | cancelled
            $t->timestamps();
        });

        Schema::create('villa_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date')->comment('Exclusive');
            $t->string('reason', 20)->default('maintenance'); // maintenance|owner|other
            $t->string('notes', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('inventory_nights', function (Blueprint $t) {
            $t->id();
            $t->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $t->date('stay_date');
            $t->foreignId('booking_villa_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('villa_block_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['villa_id', 'stay_date'], 'inventory_villa_night_unique');
            $t->index('stay_date');
        });

        Schema::create('stay_guests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_villa_id')->constrained()->cascadeOnDelete();
            $t->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->string('nationality', 80)->nullable();
            $t->text('passport_no')->nullable(); // encrypted
            $t->date('date_of_birth')->nullable();
            $t->boolean('is_child')->default(false);
            $t->boolean('is_primary')->default(false);
            $t->string('flight_details', 120)->nullable();
            $t->string('notes', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('booking_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->string('event', 40);
            $t->string('description', 500)->nullable();
            $t->json('data')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('booking_conflicts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('channel_id')->constrained()->restrictOnDelete();
            $t->string('external_ref', 80);
            $t->string('guest_name', 160);
            $t->string('guest_email')->nullable();
            $t->foreignId('villa_type_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('villa_id')->nullable()->constrained()->nullOnDelete();
            $t->date('arrival');
            $t->date('departure');
            $t->decimal('amount', 14, 2)->default(0);
            $t->string('reason', 300);
            $t->json('payload');
            $t->string('status', 20)->default('open'); // open | resolved | rejected
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->string('resolution_notes', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('enquiries', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email');
            $t->string('phone', 40)->nullable();
            $t->string('subject', 150)->nullable();
            $t->text('message');
            $t->date('arrival')->nullable();
            $t->date('departure')->nullable();
            $t->unsignedTinyInteger('guests')->nullable();
            $t->string('status', 20)->default('new'); // new | replied | closed
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reply_notes')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['tour_operator_id']));
        foreach (['enquiries', 'booking_conflicts', 'booking_events', 'stay_guests', 'inventory_nights', 'villa_blocks',
            'booking_villas', 'bookings', 'contract_rates', 'operator_contracts', 'tour_operators', 'channels', 'guests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
