<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest folio (stay account), charges, payments, gateway intents, invoices,
 * commissions, channel-manager integration (mappings, outbox, webhook inbox).
 *
 * Folio lines and payments are insert-only: corrections are reversing entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_items', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name', 120);
            $t->string('department', 30)->index();
            $t->decimal('price', 12, 2)->default(0);
            $t->boolean('taxable')->default(true);
            $t->boolean('service_chargeable')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('folios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->string('folio_no', 30)->unique();
            $t->string('payer_type', 20)->default('guest'); // guest | operator | company
            $t->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 10)->default('open')->index(); // open | closed
            $t->char('currency', 3);
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('folio_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $t->date('business_date')->index();
            $t->string('department', 30)->index();
            $t->foreignId('charge_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description', 255);
            $t->decimal('quantity', 10, 2)->default(1);
            $t->decimal('unit_price', 12, 2);
            $t->decimal('amount', 14, 2)->comment('Net amount');
            $t->decimal('tax_amount', 14, 2)->default(0);
            $t->decimal('service_amount', 14, 2)->default(0);
            $t->decimal('total', 14, 2);
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('reverses_id')->nullable()->constrained('folio_lines')->nullOnDelete();
            $t->boolean('is_reversed')->default(false);
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['source_type', 'source_id']);
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number', 40)->unique();
            $t->string('type', 20)->index(); // invoice|receipt|proforma|credit_note|operator_invoice
            $t->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $t->string('bill_to_name', 160);
            $t->text('bill_to_details')->nullable();
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('tax_total', 14, 2)->default(0);
            $t->decimal('service_total', 14, 2)->default(0);
            $t->decimal('discount_total', 14, 2)->default(0);
            $t->decimal('grand_total', 14, 2)->default(0);
            $t->decimal('paid_total', 14, 2)->default(0);
            $t->decimal('balance', 14, 2)->default(0);
            $t->char('currency', 3);
            $t->string('status', 20)->default('issued'); // issued|partially_paid|paid|void
            $t->date('due_date')->nullable();
            $t->json('lines');
            $t->string('notes', 500)->nullable();
            $t->string('hash', 64)->nullable();
            $t->dateTime('issued_at'); // DATETIME: avoids MariaDB implicit ON UPDATE on first TIMESTAMP
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 30)->unique();
            $t->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20)->default('payment'); // payment | deposit | refund
            $t->string('method', 20); // cash|card|bank_transfer|online|city_ledger|cheque
            $t->decimal('amount', 14, 2)->comment('Negative for refunds');
            $t->char('currency', 3);
            $t->string('gateway', 30)->nullable();
            $t->string('gateway_ref', 120)->nullable()->unique();
            $t->string('status', 20)->default('completed'); // pending|completed|failed
            $t->string('proof_path')->nullable();
            $t->string('notes', 500)->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('paid_at'); // DATETIME: avoids MariaDB implicit ON UPDATE on first TIMESTAMP
            $t->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $t) {
            $t->id();
            $t->string('token', 64)->unique();
            $t->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $t->decimal('amount', 14, 2);
            $t->char('currency', 3);
            $t->string('purpose', 20)->default('deposit'); // deposit | full | balance
            $t->string('gateway', 30);
            $t->string('gateway_session_id', 190)->nullable()->index();
            $t->string('status', 20)->default('pending'); // pending|paid|failed|expired
            $t->string('customer_email')->nullable();
            $t->json('payload')->nullable();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('basis_amount', 14, 2);
            $t->decimal('pct', 5, 2);
            $t->decimal('amount', 14, 2);
            $t->string('status', 20)->default('accrued'); // accrued|settled|cancelled
            $t->timestamp('settled_at')->nullable();
            $t->timestamps();
            $t->unique('booking_id');
        });

        Schema::create('channel_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('villa_type_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->string('external_room_code', 80);
            $t->string('external_rate_code', 80)->nullable();
            $t->timestamps();
            $t->unique(['channel_id', 'external_room_code', 'external_rate_code'], 'channel_map_unique');
        });

        Schema::create('channel_sync_logs', function (Blueprint $t) {
            $t->id();
            $t->string('direction', 5); // out | in
            $t->string('type', 40); // ari_push | reservation | full_sync
            $t->string('status', 20)->default('pending')->index(); // pending|sent|failed|processed|skipped
            $t->date('date_from')->nullable();
            $t->date('date_to')->nullable();
            $t->json('payload')->nullable();
            $t->text('response')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('webhook_inbox', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 30);
            $t->string('event_id', 150);
            $t->string('event_type', 80)->nullable();
            $t->json('payload');
            $t->timestamp('processed_at')->nullable();
            $t->string('error', 500)->nullable();
            $t->timestamps();
            $t->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_inbox', 'channel_sync_logs', 'channel_mappings', 'commissions', 'payment_intents', 'payments',
            'invoices', 'folio_lines', 'folios', 'charge_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
