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
        Schema::create('field_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type'); // e.g., 'student', 'teacher'
            $table->string('field_name'); // e.g., 'name', 'address'
            $table->string('role'); // e.g., 'class_teacher', 'admin'
            $table->enum('permission_level', ['editable', 'read_only', 'hidden']); // Permission level
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['model_type', 'role']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_permissions');
    }
};