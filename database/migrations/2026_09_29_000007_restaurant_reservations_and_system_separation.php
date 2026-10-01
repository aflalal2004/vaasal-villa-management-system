<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Restaurant table reservations (POS only; optional link to an in-house booking for room-charge guests).
 * 2. Hotel PMS / Restaurant POS separation in the RBAC data:
 *    - "manager" becomes the Hotel Manager: no restaurant POS permissions;
 *    - "pos_manager" becomes the Restaurant Manager: lands on the POS dashboard and loses hotel folio posting
 *      (room charges still reach the folio through the POS billing service — the defined integration point).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('pos_table_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete()->comment('In-house hotel guest (integration)');
            $t->foreignId('pos_order_id')->nullable()->constrained()->nullOnDelete()->comment('Check opened when seated');
            $t->string('guest_name', 120);
            $t->string('phone', 40)->nullable();
            $t->unsignedTinyInteger('party_size')->default(2);
            $t->dateTime('reserved_for')->index();
            $t->unsignedSmallInteger('duration_minutes')->default(90);
            $t->string('status', 12)->default('pending')->index(); // pending|confirmed|seated|completed|cancelled|no_show
            $t->string('notes', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        $perm = fn (string $slug, string $name) => DB::table('permissions')->where('slug', $slug)->value('id')
            ?? DB::table('permissions')->insertGetId(['slug' => $slug, 'module' => 'pos', 'name' => $name]);
        $reservations = $perm('pos.reservations', 'Table reservations');
        $role = fn (string $slug) => DB::table('roles')->where('slug', $slug)->value('id');

        foreach (['admin', 'pos_manager', 'cashier', 'waiter'] as $r) {
            if ($id = $role($r)) DB::table('permission_role')->insertOrIgnore(['role_id' => $id, 'permission_id' => $reservations]);
        }
        if ($id = $role('manager')) {
            $posPerms = DB::table('permissions')->where('slug', 'like', 'pos.%')->pluck('id');
            DB::table('permission_role')->where('role_id', $id)->whereIn('permission_id', $posPerms)->delete();
            DB::table('roles')->where('id', $id)->update(['name' => 'Hotel Manager', 'description' => 'Hotel PMS: bookings, front office, billing, villas, housekeeping, staff']);
        }
        if ($id = $role('pos_manager')) {
            $folio = DB::table('permissions')->where('slug', 'folio.post')->value('id');
            DB::table('permission_role')->where('role_id', $id)->where('permission_id', $folio)->delete();
            DB::table('roles')->where('id', $id)->update(['name' => 'Restaurant Manager', 'home_route' => 'pos.dashboard',
                'description' => 'Restaurant POS: dashboard, orders, menu, tables, reservations, kitchen, cashier, stock, sales']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('table_reservations');
        DB::table('permissions')->where('slug', 'pos.reservations')->delete();
        DB::table('roles')->where('slug', 'pos_manager')->update(['home_route' => 'pos.terminal']);
    }
};
