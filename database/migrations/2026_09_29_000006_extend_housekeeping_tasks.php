<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Housekeeping task lifecycle: accept (claim) and pause/resume, with paused time excluded from cleaning duration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hk_tasks', function (Blueprint $t) {
            $t->timestamp('accepted_at')->nullable()->after('assigned_to');
            $t->timestamp('paused_at')->nullable()->after('started_at');
            $t->unsignedInteger('paused_minutes')->default(0)->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('hk_tasks', function (Blueprint $t) {
            $t->dropColumn(['accepted_at', 'paused_at', 'paused_minutes']);
        });
    }
};
