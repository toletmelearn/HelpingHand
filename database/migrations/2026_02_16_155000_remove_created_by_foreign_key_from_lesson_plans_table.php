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
        Schema::table('lesson_plans', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['created_by']);
            $table->dropForeign(['modified_by']);
            
            // Make the columns nullable to accept teacher IDs
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->unsignedBigInteger('modified_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->unsignedBigInteger('modified_by')->nullable(false)->change();
            
            // Add back the foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};