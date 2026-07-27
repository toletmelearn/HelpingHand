<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Remediation Task 7: exams.class_id has existed (with a real FK to
     * school_classes) since 2026_02_15_081807, but nothing ever populated
     * it -- ExamController only ever wrote the free-text class_name, in a
     * different vocabulary ("Class 10") than students.class ("X"), which
     * is why AdmitCardController/ExamArrangementController matching on
     * `class_name` against `students.class` matched zero students for
     * every exam. Backfills the 3 existing exams by exact class_name ->
     * school_classes.name match. Any exam whose class_name doesn't match
     * a school_classes row exactly is left NULL and logged -- never
     * guessed at -- for someone to resolve by hand.
     */
    public function up(): void
    {
        $unmapped = [];

        $exams = DB::table('exams')->whereNull('class_id')->orderBy('id')->get();

        foreach ($exams as $exam) {
            $schoolClass = DB::table('school_classes')
                ->where('name', $exam->class_name)
                ->whereNull('deleted_at')
                ->first();

            if ($schoolClass) {
                DB::table('exams')->where('id', $exam->id)->update(['class_id' => $schoolClass->id]);
            } else {
                $unmapped[] = "exam id={$exam->id} name=\"{$exam->name}\" class_name=\"{$exam->class_name}\"";
            }
        }

        if (!empty($unmapped)) {
            Log::warning('Exams left with NULL class_id after backfill (no matching school_classes.name): ' . implode('; ', $unmapped));
        }
    }

    /**
     * Data backfill only -- no schema change to reverse. Deliberately
     * does not null class_id back out; the column existed before this
     * migration and other exams may have since been created using it.
     */
    public function down(): void
    {
    }
};
