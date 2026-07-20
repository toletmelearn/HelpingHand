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
        Schema::table('results', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('results', 'uploaded_by_teacher_id')) {
                $table->unsignedBigInteger('uploaded_by_teacher_id')->nullable()->after('generated_by');
                $table->foreign('uploaded_by_teacher_id')->references('id')->on('teachers')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('results', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('uploaded_by_teacher_id');
            }
            
            if (!Schema::hasColumn('results', 'status')) {
                $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft')->after('uploaded_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'uploaded_by_teacher_id')) {
                $table->dropForeign(['uploaded_by_teacher_id']);
                $table->dropColumn('uploaded_by_teacher_id');
            }
            
            if (Schema::hasColumn('results', 'uploaded_at')) {
                $table->dropColumn('uploaded_at');
            }
            
            if (Schema::hasColumn('results', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
