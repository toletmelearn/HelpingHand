<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A parent's "I paid via UPI, here's my UTR" claim -- no receipt/ledger
     * credit happens until the matching engine (or an accountant) confirms
     * it against an imported bank statement row. bank_statement_row_id is
     * added without an FK constraint here since bank_statement_rows
     * doesn't exist yet -- the constraint is added once that table is
     * created in the next migration.
     */
    public function up(): void
    {
        Schema::create('payment_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('reference_token')->unique();
            $table->string('utr', 32)->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('screenshot_path')->nullable();
            $table->string('status', 20)->default('claimed'); // claimed, matched, rejected, cancelled
            $table->unsignedBigInteger('bank_statement_row_id')->nullable();
            $table->foreignId('fee_collection_id')->nullable()->constrained('fee_collections')->nullOnDelete();
            $table->string('match_confidence', 20)->nullable(); // exact, narration, fuzzy
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_claims');
    }
};
