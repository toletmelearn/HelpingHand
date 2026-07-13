<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_session_id')->constrained('import_sessions')->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->date('transaction_date');
            $table->decimal('amount', 10, 2);
            // Not every bank exports a clean UTR column -- sometimes it's
            // only findable inside the narration text, so this stays
            // nullable and the matching engine also searches narration.
            $table->string('utr', 32)->nullable();
            $table->text('narration')->nullable();
            $table->string('status', 20)->default('unmatched'); // unmatched, matched, ignored
            $table->foreignId('payment_claim_id')->nullable()->constrained('payment_claims')->nullOnDelete();
            $table->timestamps();

            $table->index(['import_session_id', 'status']);
            $table->index('utr');
            $table->index('status');
        });

        // payment_claims.bank_statement_row_id was created without an FK
        // constraint in the previous migration since this table didn't
        // exist yet -- add the real constraint now that it does.
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->foreign('bank_statement_row_id')->references('id')->on('bank_statement_rows')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->dropForeign(['bank_statement_row_id']);
        });
        Schema::dropIfExists('bank_statement_rows');
    }
};
