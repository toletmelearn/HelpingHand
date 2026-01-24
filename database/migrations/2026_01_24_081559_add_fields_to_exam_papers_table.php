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
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_id')->nullable()->after('metadata');
            $table->integer('version')->default(1)->after('exam_id');
            $table->string('status')->default('draft')->after('version');
            $table->json('revision_notes')->nullable()->after('status');
            $table->integer('print_count')->default(0)->after('revision_notes');
            $table->boolean('confidential_flag')->default(false)->after('print_count');
            $table->string('template_used')->nullable()->after('confidential_flag');
            $table->json('questions_data')->nullable()->after('template_used');
            $table->integer('auto_calculated_total')->nullable()->after('questions_data');
            
            // Add foreign key constraint for exam_id
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('set null');
            
            // Add indexes for new columns
            $table->index(['exam_id', 'status']);
            $table->index(['version', 'status']);
            $table->index(['confidential_flag', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_papers', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['exam_id']);
            
            // Drop the columns
            $table->dropColumn([
                'exam_id',
                'version',
                'status',
                'revision_notes',
                'print_count',
                'confidential_flag',
                'template_used',
                'questions_data',
                'auto_calculated_total',
            ]);
        });
    }
};
