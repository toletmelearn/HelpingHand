<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sync-audit loophole L-14: teacher_substitutions.absent_teacher_id/
     * substitute_teacher_id/class_id/section_id/subject_id were declared as
     * plain unsignedBigInteger columns with zero DB-level foreign keys --
     * every referential guarantee was enforced only in
     * TeacherSubstitutionController's application-level checks. A
     * hard-deleted teacher/class/section/subject left orphaned rows with
     * no DB-level trace. Restrict (not cascade): Teacher/SchoolClass/
     * Section/Subject all use SoftDeletes, so this only ever fires on an
     * explicit forceDelete() or raw SQL delete -- never the normal admin
     * "Delete" click, which soft-deletes and never touches the FK at all.
     */
    public function up(): void
    {
        $orphans = [
            'absent_teacher_id' => DB::table('teacher_substitutions')
                ->whereNotIn('absent_teacher_id', DB::table('teachers')->select('id'))->count(),
            'substitute_teacher_id' => DB::table('teacher_substitutions')
                ->whereNotNull('substitute_teacher_id')
                ->whereNotIn('substitute_teacher_id', DB::table('teachers')->select('id'))->count(),
            'class_id' => DB::table('teacher_substitutions')
                ->whereNotIn('class_id', DB::table('school_classes')->select('id'))->count(),
            'section_id' => DB::table('teacher_substitutions')
                ->whereNotIn('section_id', DB::table('sections')->select('id'))->count(),
            'subject_id' => DB::table('teacher_substitutions')
                ->whereNotIn('subject_id', DB::table('subjects')->select('id'))->count(),
        ];

        $bad = array_filter($orphans);
        if ($bad) {
            $summary = collect($bad)->map(fn ($n, $col) => "{$col}: {$n}")->implode(', ');
            throw new \RuntimeException(
                "Cannot add foreign keys to teacher_substitutions: orphaned references found ({$summary}). " .
                'Resolve them manually (reassign or delete those rows), then re-run migrate.'
            );
        }

        Schema::table('teacher_substitutions', function (Blueprint $table) {
            $table->foreign('absent_teacher_id')->references('id')->on('teachers')->onDelete('restrict');
            $table->foreign('substitute_teacher_id')->references('id')->on('teachers')->onDelete('restrict');
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('restrict');
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('restrict');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_substitutions', function (Blueprint $table) {
            $table->dropForeign(['absent_teacher_id']);
            $table->dropForeign(['substitute_teacher_id']);
            $table->dropForeign(['class_id']);
            $table->dropForeign(['section_id']);
            $table->dropForeign(['subject_id']);
        });
    }
};
