<?php

namespace App\Services;

use App\Models\Datesheet;
use App\Models\Exam;
use App\Services\Exam\ExamWriteValidator;
use Illuminate\Support\Facades\DB;

/**
 * The one place a Datesheet touches the existing Exam table. Publishing a
 * Datesheet creates (or, on a revision, links to) the real Exam rows the
 * already-working Marks/Result/Grade/Admit Card chain consumes entirely
 * unchanged -- confirmed this session that AdmitCard only ever reads
 * $exam->exam_date, with zero awareness of where the Exam record came
 * from, so no other existing code needs to change for this integration.
 *
 * Never duplicates an Exam: if this exact DatesheetEntry already has
 * exam_id set (a republish, or a revision superseding a prior published
 * version whose entries already produced Exam rows), the existing row is
 * reused and only its date/time/room-derived fields are refreshed.
 */
class DatesheetPublishService
{
    public function __construct(private ExamWriteValidator $writeValidator)
    {
    }

    /**
     * @return array{exams_created: int, exams_linked: int}
     */
    public function publish(Datesheet $datesheet, int $publishedByUserId): array
    {
        if (! $datesheet->canTransitionTo(Datesheet::STATUS_PUBLISHED)) {
            throw new \RuntimeException('This datesheet cannot be published from its current status.');
        }

        $academicYear = $datesheet->academicSession->name;
        $created = 0;
        $linked = 0;

        DB::transaction(function () use ($datesheet, $academicYear, &$created, &$linked, $publishedByUserId) {
            foreach ($datesheet->entries()->with(['schoolClass', 'subject'])->get() as $entry) {
                if ($entry->exam_id) {
                    $entry->exam()->update([
                        'exam_date' => $entry->exam_date,
                        'start_time' => $entry->start_time,
                        'end_time' => $entry->end_time,
                    ]);
                    $linked++;
                    continue;
                }

                // Sync-audit loophole L-09: this used to call Exam::create()
                // directly, bypassing the duplicate-exam and marks
                // validation Admin\ExamController enforces on every other
                // creation path. Same checks, same service, so the two
                // paths can't drift out of sync with each other.
                if (!$this->writeValidator->marksValid((float) $entry->total_marks, (float) $entry->passing_marks)) {
                    throw new \RuntimeException(
                        "Cannot publish: passing marks exceed total marks for {$entry->subject->name} ({$entry->schoolClass->name})."
                    );
                }
                if ($this->writeValidator->duplicateExists((int) $entry->school_class_id, $entry->subject->name, $academicYear, $datesheet->exam_type)) {
                    throw new \RuntimeException(
                        "Cannot publish: an exam for {$entry->schoolClass->name} / {$entry->subject->name} / {$academicYear} / {$datesheet->exam_type} already exists."
                    );
                }

                $exam = Exam::create([
                    'name' => $datesheet->name . ' - ' . $entry->subject->name,
                    'exam_type' => $datesheet->exam_type,
                    'class_id' => $entry->school_class_id,
                    'class_name' => $entry->schoolClass->name,
                    'subject_id' => $entry->subject_id,
                    'subject' => $entry->subject->name,
                    'exam_date' => $entry->exam_date,
                    'start_time' => $entry->start_time,
                    'end_time' => $entry->end_time,
                    'total_marks' => $entry->total_marks,
                    'passing_marks' => $entry->passing_marks,
                    'academic_year' => $academicYear,
                    'term' => $datesheet->exam_type,
                    'status' => 'scheduled',
                ]);

                $entry->update(['exam_id' => $exam->id]);
                $created++;
            }

            $datesheet->update([
                'status' => Datesheet::STATUS_PUBLISHED,
                'published_by' => $publishedByUserId,
                'published_at' => now(),
            ]);

            // If this datesheet is itself a revision of an earlier
            // published one, that earlier one is marked superseded only
            // now that THIS one has actually published successfully --
            // Admin\DatesheetController::revise() records the link in the
            // other direction (new.revises_id) when the draft is created;
            // this is the moment the old one stops being "the" published
            // version.
            if ($datesheet->revises_id) {
                Datesheet::where('id', $datesheet->revises_id)
                    ->whereNull('superseded_by_id')
                    ->update(['superseded_by_id' => $datesheet->id]);
            }
        });

        return ['exams_created' => $created, 'exams_linked' => $linked];
    }
}
