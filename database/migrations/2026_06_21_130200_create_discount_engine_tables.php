<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('discount_rules')) {
            Schema::create('discount_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type'); // sibling, staff_child, merit, category
                $table->json('config')->nullable();
                $table->integer('priority')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('student_discounts_applied')) {
            Schema::create('student_discounts_applied', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('discount_rule_id')->constrained('discount_rules')->onDelete('cascade');
                $table->foreignId('fee_type_id')->constrained('fee_types')->onDelete('cascade');
                $table->decimal('amount', 10, 2);
                $table->string('month');
                $table->string('academic_year');
                $table->timestamps();

                $table->index(['student_id', 'month', 'academic_year'], 'student_discounts_applied_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_discounts_applied');
        Schema::dropIfExists('discount_rules');
    }
};
