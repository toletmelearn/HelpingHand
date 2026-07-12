<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A refundable liability, deliberately kept out of student_fee_ledgers
     * (which tracks tuition dues/revenue) -- this is its own record of how
     * much deposit money the school is currently holding per student, and
     * its own held -> refund_pending -> refunded|adjusted lifecycle.
     */
    public function up(): void
    {
        Schema::create('security_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            $table->foreignId('fee_collection_id')->nullable()->constrained('fee_collections')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('held'); // held, refund_pending, refunded, adjusted
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('refund_mode', 50)->nullable();
            $table->string('refund_ref')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_deposits');
    }
};
