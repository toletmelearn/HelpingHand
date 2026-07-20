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
            $table->unsignedBigInteger('created_by')->nullable()->after('show_to_parents');
            $table->unsignedBigInteger('modified_by')->nullable()->after('created_by');
            
            // Add foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['modified_by']);
            $table->dropColumn(['created_by', 'modified_by']);
        });
    }
};