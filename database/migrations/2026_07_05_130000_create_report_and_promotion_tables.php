<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('layout_config')->nullable();
            $table->json('scholastic_sections')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->string('class_name');
            $table->decimal('min_overall_percentage', 5, 2);
            $table->integer('max_failed_subjects');
            $table->decimal('min_attendance_percentage', 5, 2);
            $table->string('academic_year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_templates');
        Schema::dropIfExists('promotion_rules');
    }
};
