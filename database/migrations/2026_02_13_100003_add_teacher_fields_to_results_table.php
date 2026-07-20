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
            // Add teacher tracking fields
            if (!Schema::hasColumn('results', 'uploaded_by_teacher_id')) {
                $table->foreignId('uploaded_by_teacher_id')->nullable()
                    ->after('result_status')
                    ->constrained('teachers')
                    ->onDelete('set null');
            }
            
            if (!Schema::hasColumn('results', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('uploaded_by_teacher_id');
            }
            
            if (!Schema::hasColumn('results', 'status')) {
                $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                    ->default('draft')
                    ->after('uploaded_at');
            }
            
            if (!Schema::hasColumn('results', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->onDelete('set null');
            }
            
            if (!Schema::hasColumn('results', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            
            if (!Schema::hasColumn('results', 'remarks')) {
                $table->text('remarks')->nullable()->after('approved_at');
            }
            
            if (!Schema::hasColumn('results', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('remarks');
            }
            
            if (!Schema::hasColumn('results', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('is_locked');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'remarks')) {
                $table->dropColumn('remarks');
            }
            if (Schema::hasColumn('results', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('results', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('results', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('results', 'uploaded_at')) {
                $table->dropColumn('uploaded_at');
            }
            if (Schema::hasColumn('results', 'uploaded_by_teacher_id')) {
                $table->dropForeign(['uploaded_by_teacher_id']);
                $table->dropColumn('uploaded_by_teacher_id');
            }
        });
    }
};
