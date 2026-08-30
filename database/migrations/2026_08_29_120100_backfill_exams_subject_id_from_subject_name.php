<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * exams.subject_id has existed (with a real FK to subjects) since
     * 2026_02_15_081807, same as class_id -- but unlike class_id it was
     * never backfilled by 2026_07_26_202042 (that one only covered
     * class_id) and Admin\ExamController::store()/update() never wrote it
     * either (only the free-text `subject` column -- fixed alongside this
     * migration). Every exam created through the admin UI to date has
     * NULL subject_id. Backfills by exact `subject` -> subjects.name
     * match, the same strategy already used for class_id. Any exam whose
     * subject doesn't match a subjects row exactly is left NULL and
     * logged -- never guessed at -- for someone to resolve by hand before
     * the NOT NULL migration can run.
     */
    public function up(): void
    {
        $unmapped = [];

        $exams = DB::table('exams')->whereNull('subject_id')->orderBy('id')->get();

        foreach ($exams as $exam) {
            $subject = DB::table('subjects')
                ->where('name', $exam->subject)
                ->first();

            if ($subject) {
                DB::table('exams')->where('id', $exam->id)->update(['subject_id' => $subject->id]);
            } else {
                $unmapped[] = "exam id={$exam->id} name=\"{$exam->name}\" subject=\"{$exam->subject}\"";
            }
        }

        if (!empty($unmapped)) {
            Log::warning('Exams left with NULL subject_id after backfill (no matching subjects.name): ' . implode('; ', $unmapped));
        }
    }

    /**
     * Data backfill only -- no schema change to reverse. Deliberately does
     * not null subject_id back out; the column existed before this
     * migration and other exams may have since been created using it.
     */
    public function down(): void
    {
    }
};
