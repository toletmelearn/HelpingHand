<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the bank-cash-deposit claim path alongside UPI. claim_type
     * defaults to 'upi' so existing rows are unaffected; deposit_date/
     * branch are only populated for claim_type='bank_cash_deposit'.
     * bank_statement_rows.branch supports the new cash-deposit matching
     * tier (amount + date +-1 working day + branch).
     */
    public function up(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->string('claim_type', 20)->default('upi')->after('student_id'); // upi, bank_cash_deposit
            $table->date('deposit_date')->nullable()->after('utr');
            $table->string('branch', 100)->nullable()->after('deposit_date');
            $table->index(['claim_type', 'status']);
        });

        Schema::table('bank_statement_rows', function (Blueprint $table) {
            $table->string('branch', 100)->nullable()->after('narration');
        });
    }

    public function down(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->dropIndex(['claim_type', 'status']);
            $table->dropColumn(['claim_type', 'deposit_date', 'branch']);
        });

        Schema::table('bank_statement_rows', function (Blueprint $table) {
            $table->dropColumn('branch');
        });
    }
};
