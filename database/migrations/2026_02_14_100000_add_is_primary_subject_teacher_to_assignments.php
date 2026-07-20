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
        Schema::table('teacher_class_subject_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('teacher_class_subject_assignments', 'is_primary_subject_teacher')) {
                $table->boolean('is_primary_subject_teacher')->default(false)->after('is_class_teacher');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_class_subject_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_class_subject_assignments', 'is_primary_subject_teacher')) {
                $table->dropColumn('is_primary_subject_teacher');
            }
        });
    }
};
