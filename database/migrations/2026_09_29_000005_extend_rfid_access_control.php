<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RFID access control on top of the existing key-card and attendance-device architecture (no parallel RFID tables):
 *  - attendance_devices becomes the RFID reader registry: purpose (attendance | access | both), the door it guards
 *    (villa and/or zone) and whether it is a physical reader or the in-app simulator;
 *  - access_logs records who was granted or denied at which reader, and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $t) {
            $t->string('purpose', 12)->default('attendance')->after('type'); // attendance|access|both
            $t->foreignId('villa_id')->nullable()->after('location')->constrained()->nullOnDelete();
            $t->string('zone', 60)->nullable()->after('villa_id');
            $t->string('mode', 12)->default('hardware')->after('zone'); // hardware|simulator
        });

        Schema::table('access_logs', function (Blueprint $t) {
            $t->foreignId('employee_id')->nullable()->after('key_card_id')->constrained()->nullOnDelete();
            $t->foreignId('device_id')->nullable()->after('employee_id')->constrained('attendance_devices')->nullOnDelete();
            $t->string('zone', 60)->nullable()->after('lock_ref');
            $t->boolean('granted')->nullable()->after('event');
            $t->string('reason', 120)->nullable()->after('granted');
            $t->index(['granted', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $t) {
            $t->dropIndex(['granted', 'occurred_at']);
            $t->dropConstrainedForeignId('employee_id');
            $t->dropConstrainedForeignId('device_id');
            $t->dropColumn(['zone', 'granted', 'reason']);
        });
        Schema::table('attendance_devices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('villa_id');
            $t->dropColumn(['purpose', 'zone', 'mode']);
        });
    }
};
