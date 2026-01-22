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
        // Add created_by and updated_by to exam papers
        Schema::table('exam_papers', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_papers', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('access_password');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('exam_papers', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Add updated_by to bell_timings
        Schema::table('bell_timings', function (Blueprint $table) {
            if (!Schema::hasColumn('bell_timings', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Add updated_by to attendances
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('marked_by');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys and columns for exam papers
        Schema::table('exam_papers', function (Blueprint $table) {
            if (Schema::hasColumn('exam_papers', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
            
            if (Schema::hasColumn('exam_papers', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });

        // Drop foreign keys and columns for bell_timings
        Schema::table('bell_timings', function (Blueprint $table) {
            if (Schema::hasColumn('bell_timings', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
        });

        // Drop foreign keys and columns for attendances
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
        });
    }
};