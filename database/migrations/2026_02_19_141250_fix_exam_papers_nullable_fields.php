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
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->string('exam_type', 255)->default('General')->nullable()->change();
            $table->string('class_section', 255)->nullable()->change();
            $table->text('instructions')->nullable()->change();
            $table->longText('paper_content')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->string('exam_type', 255)->nullable(false)->change();
            $table->string('class_section', 255)->nullable(false)->change();
            $table->text('instructions')->nullable(false)->change();
            $table->longText('paper_content')->nullable(false)->change();
        });
    }
};
