<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RFID key cards + Lock Bridge job queue + access logs; housekeeping, linen,
 * lost & found; maintenance tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- Key cards ----------
        Schema::create('key_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('uid', 60)->unique()->comment('Factory card UID');
            $t->string('card_number', 40)->nullable()->unique()->comment('Printed card number');
            $t->string('type', 20)->default('guest'); // guest|staff|master|maintenance
            $t->string('status', 20)->default('available')->index(); // available|active|lost|blocked|damaged|retired
            $t->string('notes', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('lock_bridges', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('token_hash', 64)->unique();
            $t->string('vendor', 30)->default('simulator');
            $t->string('version', 20)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('key_card_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('key_card_id')->constrained()->restrictOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_villa_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('villa_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->string('access_level', 20)->default('guest'); // guest|zone|master|housekeeping|maintenance
            $t->string('zone', 60)->nullable();
            $t->json('lock_refs')->nullable();
            $t->dateTime('valid_from');
            $t->dateTime('valid_to');
            $t->string('issue_type', 20)->default('new'); // new|duplicate|replacement
            $t->string('status', 20)->default('pending')->index(); // pending|active|expired|revoked|lost|failed
            $t->string('encoder', 60)->nullable();
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoke_reason', 200)->nullable();
            $t->timestamps();
        });

        Schema::create('lock_jobs', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('key_card_assignment_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30); // encode_new|encode_duplicate|revoke|extend|read_card
            $t->json('payload');
            $t->string('status', 20)->default('pending')->index(); // pending|processing|completed|failed
            $t->json('result')->nullable();
            $t->string('error', 500)->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->foreignId('lock_bridge_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('picked_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('access_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('villa_id')->nullable()->constrained()->nullOnDelete();
            $t->string('lock_ref', 60)->nullable();
            $t->string('card_uid', 60)->nullable()->index();
            $t->foreignId('key_card_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event', 30); // open|denied|expired_card|blocked_card|low_battery|door_ajar|issued|revoked
            $t->string('source', 20)->default('online'); // online|audit_import|simulator|system
            $t->json('details')->nullable();
            $t->dateTime('occurred_at')->index();
            $t->timestamp('created_at')->useCurrent();
        });

        // ---------- Maintenance ----------
        Schema::create('maintenance_tickets', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_no', 20)->unique();
            $t->foreignId('villa_id')->nullable()->constrained()->nullOnDelete();
            $t->string('location', 120)->nullable();
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->string('category', 30)->default('general'); // plumbing|electrical|ac|furniture|pool|general
            $t->string('severity', 20)->default('medium'); // low|medium|high|blocking
            $t->string('status', 20)->default('open')->index(); // open|in_progress|on_hold|resolved|closed
            $t->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('photo_path')->nullable();
            $t->boolean('blocks_inventory')->default(false);
            $t->foreignId('villa_block_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->text('resolution_notes')->nullable();
            $t->decimal('cost', 12, 2)->nullable();
            $t->timestamps();
        });

        // ---------- Housekeeping ----------
        Schema::create('checklist_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('task_type', 20); // departure|stayover|adhoc|deep
            $t->json('items');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('hk_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20)->default('departure'); // departure|stayover|adhoc|deep
            $t->string('priority', 10)->default('normal'); // low|normal|high|urgent
            $t->string('status', 20)->default('pending')->index(); // pending|in_progress|inspection|completed|cancelled
            $t->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $t->date('scheduled_date')->index();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('inspected_at')->nullable();
            $t->string('inspection_result', 10)->nullable(); // pass|fail
            $t->string('inspection_notes', 500)->nullable();
            $t->unsignedTinyInteger('rejection_count')->default(0);
            $t->string('notes', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('hk_task_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hk_task_id')->constrained()->cascadeOnDelete();
            $t->string('label', 200);
            $t->boolean('is_checked')->default(false);
            $t->timestamp('checked_at')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('linen_items', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->unsignedSmallInteger('par_per_villa')->default(2);
            $t->integer('stock_qty')->default(0);
            $t->integer('in_laundry_qty')->default(0);
            $t->timestamps();
        });

        Schema::create('linen_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hk_task_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('linen_item_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('qty_out')->default(0)->comment('Fresh issued to villa');
            $t->unsignedSmallInteger('qty_in')->default(0)->comment('Soiled collected to laundry');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('lost_found_items', function (Blueprint $t) {
            $t->id();
            $t->string('item_no', 20)->unique();
            $t->foreignId('villa_id')->nullable()->constrained()->nullOnDelete();
            $t->string('found_location', 120)->nullable();
            $t->string('description', 300);
            $t->string('category', 30)->default('other'); // electronics|jewellery|clothing|documents|other
            $t->string('photo_path')->nullable();
            $t->foreignId('found_by')->nullable()->constrained('employees')->nullOnDelete();
            $t->dateTime('found_at');
            $t->string('storage_location', 120)->nullable();
            $t->string('status', 20)->default('stored')->index(); // stored|claimed|returned|disposed
            $t->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $t->string('returned_to', 150)->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->string('notes', 500)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['lost_found_items', 'linen_movements', 'linen_items', 'hk_task_items', 'hk_tasks', 'checklist_templates',
            'maintenance_tickets', 'access_logs', 'lock_jobs', 'key_card_assignments', 'lock_bridges', 'key_cards'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
