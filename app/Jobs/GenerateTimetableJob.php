<?php

namespace App\Jobs;

use App\Models\SchoolClass;
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Services\Timetable\GeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T4b item 2: runs GeneratorService and writes its proposal as 'draft'
 * timetable_slots rows -- the live ('published') timetable is never
 * touched by this job, only PUBLISH (TimetableController::publishGeneration)
 * touches it. The TimetableGeneration row is created by the controller
 * BEFORE dispatch (same pattern as StageYearClosingJob/FinancialYearClosing)
 * so the UI has an id to poll immediately, without waiting on the queue.
 */
class GenerateTimetableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout;

    /**
     * config('timetable.generator.time_budget_seconds') (default 60) caps
     * the SOLVER's own search time regardless of how many classes are in
     * this run -- whole-school generation doesn't scale that part
     * linearly, it just returns best-effort at the same mark. This
     * timeout is headroom for the surrounding DB work (deleting old
     * drafts, inserting every placement row), sized generously
     * (job_timeout_seconds, default 300s) so a genuinely large school's
     * batch insert never races the job's own kill switch.
     */
    public function __construct(public int $generationId)
    {
        $this->timeout = (int) config('timetable.generator.job_timeout_seconds', 300);
    }

    public function handle(GeneratorService $service): void
    {
        $generation = TimetableGeneration::find($this->generationId);
        if (!$generation) {
            Log::error("GenerateTimetableJob: TimetableGeneration record not found: {$this->generationId}");
            return;
        }

        $generation->update(['status' => TimetableGeneration::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $classIds = $generation->school_class_ids;
            $classes = SchoolClass::whereIn('id', $classIds)->get();

            $result = $service->generate($generation->academic_year, $classes, $generation->academic_session_id);

            DB::transaction(function () use ($generation, $classIds, $result) {
                // "Drafts for a session/class replace previous drafts only" --
                // never touches 'published'/'archived' rows.
                TimetableSlot::draft()
                    ->whereIn('school_class_id', $classIds)
                    ->where('academic_year', $generation->academic_year)
                    ->delete();

                foreach ($result['placements'] as $placement) {
                    foreach ($placement['bell_timing_ids'] as $bellTimingId) {
                        TimetableSlot::create([
                            'school_class_id' => $placement['school_class_id'],
                            'section_id' => $placement['section_id'],
                            'bell_timing_id' => $bellTimingId,
                            'subject_id' => $placement['subject_id'],
                            'teacher_id' => $placement['teacher_id'],
                            'combined_class_group_id' => $placement['combined_class_group_id'],
                            'academic_year' => $generation->academic_year,
                            'status' => TimetableSlot::STATUS_DRAFT,
                            'timetable_generation_id' => $generation->id,
                        ]);
                    }
                }
            });

            $generation->update([
                'status' => TimetableGeneration::STATUS_COMPLETED,
                'placed_count' => $result['stats']['placed_lessons'],
                'unplaced_count' => $result['stats']['unplaced_lessons'],
                'report' => $result,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateTimetableJob failed: ' . $e->getMessage());
            $generation->update([
                'status' => TimetableGeneration::STATUS_FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            if (app()->environment('testing')) {
                throw $e;
            }
        }
    }
}
