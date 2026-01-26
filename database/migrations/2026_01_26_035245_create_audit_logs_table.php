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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type'); // e.g., 'teacher', 'admin'
            $table->unsignedBigInteger('user_id'); // ID of the user who made the change
            $table->string('model_type'); // e.g., 'student', 'teacher'
            $table->unsignedBigInteger('model_id'); // ID of the record that was changed
            $table->string('field_name'); // Name of the field that was changed
            $table->text('old_value')->nullable(); // Previous value
            $table->text('new_value')->nullable(); // New value
            $table->string('action'); // e.g., 'update', 'create', 'delete'
            $table->ipAddress('ip_address')->nullable(); // IP address of the user
            $table->text('user_agent')->nullable(); // Browser/device info
            $table->timestamp('performed_at')->useCurrent(); // When the action occurred
            
            $table->index(['user_id', 'user_type']);
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('performed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};