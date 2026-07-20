<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->string('topic_name');
            $table->decimal('weightage_percentage', 5, 2);
            $table->string('competency_level'); // e.g. recall, understanding, application, analysis
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_blueprints');
    }
};
