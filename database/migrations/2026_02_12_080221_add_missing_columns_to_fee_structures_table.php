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
        Schema::table('fee_structures', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_structures', 'academic_year')) {
                $table->string('academic_year')->nullable();
            }
            if (!Schema::hasColumn('fee_structures', 'installment_count')) {
                $table->integer('installment_count')->default(1);
            }
            if (!Schema::hasColumn('fee_structures', 'installment_frequency')) {
                $table->string('installment_frequency')->default('one-time');
            }
            if (!Schema::hasColumn('fee_structures', 'status')) {
                $table->string('status')->default('active');
            }
            if (!Schema::hasColumn('fee_structures', 'created_by')) {
                $table->integer('created_by')->nullable();
            }
            
            // Rename the old 'name' column to 'description' if it exists and add new 'name' column
            if (Schema::hasColumn('fee_structures', 'name') && !Schema::hasColumn('fee_structures', 'description')) {
                $table->renameColumn('name', 'description');
            }
            
            if (!Schema::hasColumn('fee_structures', 'name')) {
                $table->string('name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            if (Schema::hasColumn('fee_structures', 'academic_year')) {
                $table->dropColumn('academic_year');
            }
            if (Schema::hasColumn('fee_structures', 'installment_count')) {
                $table->dropColumn('installment_count');
            }
            if (Schema::hasColumn('fee_structures', 'installment_frequency')) {
                $table->dropColumn('installment_frequency');
            }
            if (Schema::hasColumn('fee_structures', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('fee_structures', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('fee_structures', 'name') && Schema::hasColumn('fee_structures', 'description')) {
                $table->renameColumn('description', 'name');
            }
            if (Schema::hasColumn('fee_structures', 'name') && !Schema::hasColumn('fee_structures', 'description')) {
                $table->dropColumn('name');
            }
        });
    }
};
