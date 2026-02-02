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
        Schema::create('performance_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type');
            $table->decimal('metric_value', 10, 2);
            $table->date('date_recorded');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('module_accessed')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('additional_data')->nullable();
            $table->timestamps();
            
            $table->index(['metric_type', 'date_recorded']);
            $table->index(['module_accessed', 'date_recorded']);
            $table->index(['user_id', 'date_recorded']);
            $table->index('date_recorded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_analytics');
    }
};
