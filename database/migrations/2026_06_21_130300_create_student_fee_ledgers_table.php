<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_fee_ledgers')) {
            Schema::create('student_fee_ledgers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->date('date');
                $table->string('description');
                $table->string('reference_type')->nullable(); // fee_collection, fee_structure_item, transport_due, discount_applied, late_fine
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('debit', 10, 2)->default(0.00);
                $table->decimal('credit', 10, 2)->default(0.00);
                $table->decimal('running_balance', 10, 2)->default(0.00);
                $table->timestamps();

                $table->index(['student_id', 'date'], 'student_ledger_student_date_index');
                $table->index(['reference_type', 'reference_id'], 'student_ledger_reference_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_ledgers');
    }
};
