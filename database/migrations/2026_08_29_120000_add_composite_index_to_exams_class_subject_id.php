<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * exams.class_id/subject_id have carried real FKs to school_classes/
     * subjects since 2026_02_15_081807, but never had a composite index --
     * every lookup that filters by class+subject (duplicate-exam checks,
     * arrangement queries) has been scanning the class_name/exam_date index
     * instead. Adds the index the FK pair should have had from the start.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->index(['class_id', 'subject_id'], 'exams_class_id_subject_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_class_id_subject_id_index');
        });
    }
};
