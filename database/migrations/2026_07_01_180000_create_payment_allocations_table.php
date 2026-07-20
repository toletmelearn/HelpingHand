<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                
                // FK constraints pointing to student_fee_ledgers table
                $table->foreignId('credit_ledger_id')
                    ->constrained('student_fee_ledgers')
                    ->onDelete('cascade');
                    
                $table->foreignId('debit_ledger_id')
                    ->constrained('student_fee_ledgers')
                    ->onDelete('cascade');
                    
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->string('allocation_type')->default('auto'); // auto, manual
                $table->timestamps();

                // Unique index to prevent duplicate allocations
                $table->unique(['credit_ledger_id', 'debit_ledger_id'], 'pay_alloc_unique_credit_debit');
                $table->index('student_id', 'pay_alloc_student_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
