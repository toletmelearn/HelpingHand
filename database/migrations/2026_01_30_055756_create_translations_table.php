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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->onDelete('cascade');
            $table->string('key');
            $table->text('value');
            $table->string('module')->default('general'); // general, admin, student, teacher, parent
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            
            $table->index(['language_id', 'key']);
            $table->index(['module', 'is_published']);
            $table->unique(['language_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
