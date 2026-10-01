<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant POS (outlets, tables, menu, orders, KOT, payments, shifts, cash drawer)
 * and inventory (suppliers, stock items, recipes, stock movements).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20)->unique();
            $t->string('name', 100);
            $t->string('type', 20)->default('restaurant'); // restaurant|bar|room_service|pool_bar
            $t->string('folio_department', 30)->default('restaurant');
            $t->decimal('tax_pct', 5, 2)->default(0);
            $t->decimal('service_charge_pct', 5, 2)->default(0);
            $t->string('receipt_header', 300)->nullable();
            $t->string('receipt_footer', 300)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('pos_tables', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $t->string('name', 30);
            $t->string('area', 40)->default('Main');
            $t->unsignedTinyInteger('seats')->default(4);
            $t->string('shape', 10)->default('square'); // square|round|long
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['outlet_id', 'name']);
        });

        Schema::create('menu_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 80);
            $t->string('station', 20)->default('kitchen'); // kitchen|bar|pastry
            $t->string('color', 9)->default('#0E6B63');
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_category_id')->constrained()->restrictOnDelete();
            $t->string('code', 20)->nullable()->unique();
            $t->string('name', 120);
            $t->string('description', 500)->nullable();
            $t->decimal('price', 12, 2);
            $t->decimal('cost', 12, 2)->default(0);
            $t->string('station', 20)->nullable()->comment('Overrides category station');
            $t->string('image_path', 500)->nullable();
            $t->string('allergens', 200)->nullable();
            $t->unsignedSmallInteger('prep_minutes')->default(10);
            $t->boolean('is_available')->default(true)->comment('False = 86d / sold out');
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('modifier_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->unsignedTinyInteger('min_select')->default(0);
            $t->unsignedTinyInteger('max_select')->default(1);
            $t->timestamps();
        });

        Schema::create('modifiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->decimal('price', 12, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('menu_item_modifier_group', function (Blueprint $t) {
            $t->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $t->primary(['menu_item_id', 'modifier_group_id']);
        });

        Schema::create('pos_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->dateTime('opened_at');
            $t->dateTime('closed_at')->nullable();
            $t->decimal('opening_float', 12, 2)->default(0);
            $t->decimal('expected_cash', 12, 2)->nullable();
            $t->decimal('counted_cash', 12, 2)->nullable();
            $t->decimal('variance', 12, 2)->nullable();
            $t->string('status', 10)->default('open')->index(); // open|closed
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('notes', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pos_shift_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20); // float|cash_in|cash_out|sale|refund
            $t->decimal('amount', 12, 2);
            $t->string('reason', 200)->nullable();
            $t->unsignedBigInteger('pos_order_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('pos_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $t->string('order_no', 30)->unique();
            $t->string('invoice_no', 40)->nullable()->unique();
            $t->foreignId('pos_table_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20)->default('dine_in'); // dine_in|takeaway|room_service
            $t->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('pos_shift_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('covers')->default(1);
            $t->string('status', 20)->default('open')->index(); // open|billed|paid|charged_to_room|void|merged|refunded
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('villa_id')->nullable()->constrained()->nullOnDelete();
            $t->string('guest_name', 120)->nullable();
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->string('discount_type', 10)->nullable(); // percent|fixed
            $t->decimal('discount_value', 12, 2)->default(0);
            $t->decimal('discount_amount', 12, 2)->default(0);
            $t->string('discount_reason', 200)->nullable();
            $t->decimal('service_charge', 12, 2)->default(0);
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->decimal('paid_amount', 12, 2)->default(0);
            $t->decimal('refunded_amount', 12, 2)->default(0);
            $t->string('notes', 500)->nullable();
            $t->dateTime('opened_at');
            $t->dateTime('billed_at')->nullable();
            $t->dateTime('closed_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('void_reason', 200)->nullable();
            $t->foreignId('merged_into_id')->nullable()->constrained('pos_orders')->nullOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });

        Schema::create('kots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $t->string('kot_no', 30)->unique();
            $t->string('station', 20);
            $t->string('status', 20)->default('new')->index(); // new|preparing|ready|served|cancelled
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->timestamp('served_at')->nullable();
            $t->timestamps();
        });

        Schema::create('pos_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('kot_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->decimal('quantity', 8, 2);
            $t->decimal('unit_price', 12, 2);
            $t->json('modifiers')->nullable();
            $t->decimal('modifiers_total', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2);
            $t->string('notes', 200)->nullable();
            $t->unsignedTinyInteger('seat')->nullable();
            $t->string('station', 20)->default('kitchen');
            $t->string('status', 20)->default('pending'); // pending|fired|preparing|ready|served|void
            $t->string('void_reason', 200)->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('fired_at')->nullable();
            $t->timestamps();
        });

        Schema::create('pos_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('pos_shift_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 10)->default('payment'); // payment|refund
            $t->string('method', 20); // cash|card|online|room_charge
            $t->decimal('amount', 12, 2);
            $t->decimal('tendered', 12, 2)->nullable();
            $t->decimal('change_due', 12, 2)->nullable();
            $t->string('reference', 120)->nullable();
            $t->foreignId('folio_line_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 200)->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // ---------- Inventory ----------
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('contact_name', 100)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('address', 300)->nullable();
            $t->string('tax_id', 60)->nullable();
            $t->string('notes', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('stock_items', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 30)->unique();
            $t->string('name', 120);
            $t->string('category', 40)->default('Dry store');
            $t->string('unit', 15)->default('pcs'); // kg|g|l|ml|pcs|btl
            $t->decimal('current_qty', 14, 3)->default(0);
            $t->decimal('reorder_level', 14, 3)->default(0);
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('recipes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $t->decimal('quantity', 12, 3);
            $t->timestamps();
            $t->unique(['menu_item_id', 'stock_item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10)->index(); // in|out|sale|waste|adjust
            $t->decimal('quantity', 14, 3)->comment('Signed: + in, - out');
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->decimal('balance_after', 14, 3);
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reference', 80)->nullable();
            $t->string('reason', 200)->nullable();
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_movements', 'recipes', 'stock_items', 'suppliers', 'pos_payments', 'pos_order_items', 'kots',
            'pos_orders', 'cash_movements', 'pos_shifts', 'menu_item_modifier_group', 'modifiers', 'modifier_groups',
            'menu_items', 'menu_categories', 'pos_tables', 'outlets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
