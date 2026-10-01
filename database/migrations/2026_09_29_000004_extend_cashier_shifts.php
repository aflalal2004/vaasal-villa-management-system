<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier day-end: extends pos_shifts and cash_movements (no new shift tables).
 *  - cash_movements: reference + notes; new types deposit | adjustment (type is a string column).
 *  - pos_shifts: denomination count, frozen closing report, manager review, audited reopen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $t) {
            $t->string('reference', 80)->nullable()->after('reason');
            $t->string('notes', 500)->nullable()->after('reference');
            $t->index(['type', 'created_at']);
        });

        Schema::table('pos_shifts', function (Blueprint $t) {
            $t->json('denominations')->nullable()->after('counted_cash');
            $t->json('closing_report')->nullable()->after('notes')->comment('Z report frozen at close');
            $t->string('review_status', 12)->default('pending')->after('closing_report'); // pending|approved|flagged
            $t->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $t->string('review_notes', 500)->nullable()->after('reviewed_at');
            $t->foreignId('reopened_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $t->timestamp('reopened_at')->nullable()->after('reopened_by');
            $t->string('reopen_reason', 300)->nullable()->after('reopened_at');
            $t->index(['status', 'opened_at']);
        });

        // Shifts closed before manager review existed are marked accepted (with a note) rather than flooding the review queue.
        \Illuminate\Support\Facades\DB::table('pos_shifts')->where('status', 'closed')->update([
            'review_status' => 'approved', 'reviewed_at' => \Illuminate\Support\Facades\DB::raw('closed_at'),
            'review_notes' => 'Closed before manager review was introduced — accepted automatically.',
        ]);
    }

    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropConstrainedForeignId('reopened_by');
            $t->dropIndex(['status', 'opened_at']);
            $t->dropColumn(['denominations', 'closing_report', 'review_status', 'reviewed_at', 'review_notes', 'reopened_at', 'reopen_reason']);
        });
        Schema::table('cash_movements', function (Blueprint $t) {
            $t->dropIndex(['type', 'created_at']);
            $t->dropColumn(['reference', 'notes']);
        });
    }
};
