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
            // Add fields needed for professional report card system
            $table->string('remarks')->nullable()->after('comments'); // Additional remarks field
            $table->integer('class_rank')->nullable()->after('remarks'); // Class rank
            $table->integer('section_rank')->nullable()->after('class_rank'); // Section rank
            $table->boolean('is_locked')->default(false)->after('section_rank'); // Locking mechanism
            $table->unsignedBigInteger('generated_by')->nullable()->after('is_locked'); // Who generated the result
            $table->timestamp('generated_at')->nullable()->after('generated_by'); // When result was generated
            $table->json('additional_data')->nullable()->after('generated_at'); // For future extensions
            
            // Add foreign key for generated_by
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
            
            // Add indexes for performance
            $table->index(['exam_id', 'class_rank']);
            $table->index(['exam_id', 'section_rank']);
            $table->index(['is_locked', 'generated_at']);
            $table->index(['academic_year', 'term']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['generated_by']);
            $table->dropIndex(['exam_id', 'class_rank']);
            $table->dropIndex(['exam_id', 'section_rank']);
            $table->dropIndex(['is_locked', 'generated_at']);
            $table->dropIndex(['academic_year', 'term']);
            
            $table->dropColumn([
                'remarks',
                'class_rank',
                'section_rank',
                'is_locked',
                'generated_by',
                'generated_at',
                'additional_data'
            ]);
        });
    }
};