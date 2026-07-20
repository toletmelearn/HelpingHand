<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add parent_id column to students table
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->foreign('parent_id')->references('id')->on('parents')->onDelete('set null');
        });

        // 2. Data Migration: Copy existing student_id mappings from parents to students
        if (Schema::hasTable('parents')) {
            $parentLinks = DB::table('parents')->whereNotNull('student_id')->get();
            foreach ($parentLinks as $link) {
                DB::table('students')
                    ->where('id', $link->student_id)
                    ->update(['parent_id' => $link->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
