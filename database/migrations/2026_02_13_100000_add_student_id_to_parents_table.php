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
        if (Schema::hasTable('parents')) {
            Schema::table('parents', function (Blueprint $table) {
                if (!Schema::hasColumn('parents', 'student_id')) {
                    $table->unsignedBigInteger('student_id')->nullable()->after('id');
                    $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('parents')) {
            Schema::table('parents', function (Blueprint $table) {
                if (Schema::hasColumn('parents', 'student_id')) {
                    $table->dropForeign(['student_id']);
                    $table->dropColumn('student_id');
                }
            });
        }
    }
};