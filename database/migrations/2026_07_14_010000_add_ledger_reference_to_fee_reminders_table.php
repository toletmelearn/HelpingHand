<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * fee_reminders.fee_id was a required FK to the legacy fees table, which
     * nothing in the live fee flow writes to. ReminderEngineService now
     * reads outstanding dues from student_fee_ledgers instead, so new
     * reminders need a ledger-entry reference. fee_id is kept (nullable)
     * rather than dropped -- existing rows stay intact as read-only history.
     */
    public function up(): void
    {
        Schema::table('fee_reminders', function (Blueprint $table) {
            $table->foreignId('fee_id')->nullable()->change();
            $table->foreignId('student_fee_ledger_id')->nullable()->after('fee_id')
                ->constrained('student_fee_ledgers')->nullOnDelete();
            $table->index(['student_fee_ledger_id', 'rule', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('fee_reminders', function (Blueprint $table) {
            $table->dropForeign(['student_fee_ledger_id']);
            $table->dropIndex(['student_fee_ledger_id', 'rule', 'channel']);
            $table->dropColumn('student_fee_ledger_id');
            $table->foreignId('fee_id')->nullable(false)->change();
        });
    }
};
