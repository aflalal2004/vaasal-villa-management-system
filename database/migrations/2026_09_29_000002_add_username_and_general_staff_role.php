<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sign in with username OR email; General Staff role; cashier-shift review permission.
 * Extends the existing users / roles / permissions tables — no new identity tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('username', 50)->nullable()->unique()->after('name');
            });
        }

        // Backfill: username = local part of the email, made unique with a numeric suffix.
        $taken = DB::table('users')->whereNotNull('username')->pluck('username')->map(fn ($u) => strtolower($u))->all();
        DB::table('users')->whereNull('username')->orderBy('id')->get(['id', 'email'])->each(function ($u) use (&$taken) {
            $base = substr(preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($u->email, '@', true) ?: 'user')), 0, 40) ?: 'user';
            $name = $base;
            for ($i = 2; in_array($name, $taken, true); $i++) $name = $base.$i;
            $taken[] = $name;
            DB::table('users')->where('id', $u->id)->update(['username' => $name]);
        });

        $perm = function (string $slug, string $module, string $name): int {
            return DB::table('permissions')->where('slug', $slug)->value('id')
                ?? DB::table('permissions')->insertGetId(['slug' => $slug, 'module' => $module, 'name' => $name]);
        };
        $grant = function (string $role, array $permIds) {
            $roleId = DB::table('roles')->where('slug', $role)->value('id');
            if (! $roleId) return;
            foreach ($permIds as $pid) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $pid]);
            }
        };

        $review = $perm('pos.shift_review', 'pos', 'Review, approve & reopen closed cashier shifts');
        $grant('admin', [$review]);
        $grant('manager', [$review]);
        $grant('pos_manager', [$review]);

        // General staff: own profile, time card, leave, report maintenance issues.
        if (! DB::table('roles')->where('slug', 'staff')->exists()) {
            DB::table('roles')->insert(['slug' => 'staff', 'name' => 'General Staff', 'description' => 'Own profile, attendance, leave and assigned duties',
                'home_route' => 'admin.staff.my-timecard', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $grant('staff', DB::table('permissions')->whereIn('slug', ['attendance.self', 'leave.self', 'maintenance.report'])->pluck('id')->all());
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropUnique(['username']);
                $t->dropColumn('username');
            });
        }
        DB::table('roles')->where('slug', 'staff')->delete();
        DB::table('permissions')->where('slug', 'pos.shift_review')->delete();
    }
};
