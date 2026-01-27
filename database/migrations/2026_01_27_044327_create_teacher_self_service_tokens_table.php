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
        Schema::create('teacher_self_service_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->string('token', 100)->unique();
            $table->string('device_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('permissions')->nullable(); // json array of allowed actions
            $table->timestamps();
            
            // Indexes
            $table->index(['teacher_id', 'is_active']);
            $table->index('expires_at');
            
            // Foreign key
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_self_service_tokens');
    }
};
