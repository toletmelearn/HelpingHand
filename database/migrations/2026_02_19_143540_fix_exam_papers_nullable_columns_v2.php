<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make all strict columns nullable with appropriate defaults
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->string('exam_type', 50)->default('General')->nullable()->change();
            $table->string('class_section', 50)->nullable()->change();
            $table->string('file_name', 255)->nullable()->change();
            $table->string('subject', 100)->nullable()->change();
            $table->text('instructions')->nullable()->change();
            $table->longText('paper_content')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the changes - make columns NOT NULL again
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->string('exam_type', 50)->nullable(false)->change();
            $table->string('class_section', 50)->nullable(false)->change();
            $table->string('file_name', 255)->nullable(false)->change();
            $table->string('subject', 100)->nullable(false)->change();
            $table->text('instructions')->nullable(false)->change();
            $table->longText('paper_content')->nullable(false)->change();
        });
    }
};
