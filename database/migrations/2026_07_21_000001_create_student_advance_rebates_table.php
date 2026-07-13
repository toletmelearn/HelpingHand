<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot table, same immutability pattern as student_discounts_applied
     * -- once a rebate is applied it's frozen here regardless of later rule
     * changes. status transitions applied -> clawed_back on mid-session TC.
     */
    public function up(): void
    {
        Schema::create('student_advance_rebates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('advance_rebate_rule_id')->constrained('advance_rebate_rules')->cascadeOnDelete();
            $table->string('academic_year');
            $table->foreignId('fee_collection_id')->nullable()->constrained('fee_collections')->nullOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            $table->decimal('rebate_amount', 10, 2);
            $table->string('status', 20)->default('applied'); // applied, clawed_back
            $table->decimal('clawback_amount', 10, 2)->nullable();
            $table->decimal('clawback_shortfall_amount', 10, 2)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('clawed_back_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_advance_rebates');
    }
};
