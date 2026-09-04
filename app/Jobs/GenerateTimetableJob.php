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
 *
 * Item 4 (reliability): retryable, unlike before -- $tries/backoff below,
 * and handle()'s catch block now rethrows unconditionally instead of only
 * in the testing environment. That was safe to change only after
 * confirming this job is genuinely idempotent: GeneratorService::generate()
 * itself performs zero DB writes (a pure read-and-compute pass that resets
 * its own internal state on every call, confirmed by reading it -- no
 * grep hit for ::create()/->save()/->update()/DB::/::insert() anywhere in
 * the method), and the only writes happen in the DB::transaction() below,
 * which deletes this exact class/year's existing drafts and reinserts
 * fresh ones as a single atomic unit -- a mid-transaction failure rolls
 * back completely (Eloquent's own behavior), and a failure before the
 * transaction (inside generate()) touches the database not at all. A
 * retried attempt from scratch can never accumulate duplicate or
 * partial state on top of a previous failed one.
 */
class GenerateTimetableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout;

    /**
     * 3 attempts total, with growing backoff (1 min, then 5 min) between
     * them -- enough room for a transient issue (DB deadlock/lock
     * timeout, momentary resource contention) to clear before the queue
     * worker tries again automatically, which previously never happened
     * at all: every failure silently became a terminal, unretried one.
     */
    public $tries = 3;

    public $backoff = [60, 300];

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
        if (! $generation) {
            Log::error("GenerateTimetableJob: TimetableGeneration record not found: {$this->generationId}");

            return;
        }

        $generation->update(['status' => TimetableGeneration::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $classIds = $generation->school_class_ids;
            $classes = SchoolClass::whereIn('id', $classIds)->get();

            $result = $service->generate($generation->academic_year, $classes, $generation->academic_session_id, $generation->style);

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
                            'co_teacher_id' => $placement['co_teacher_id'] ?? null,
                            'combined_class_group_id' => $placement['combined_class_group_id'],
                            'academic_year' => $generation->academic_year,
                            'status' => TimetableSlot::STATUS_DRAFT,
                            'timetable_generation_id' => $generation->id,
                            // Phase 5 (Locked Lessons): a locked slot carried
                            // forward by GeneratorService::reserveLockedSlots()
                            // stays locked in the new draft too -- the lock
                            // survives regeneration, it isn't a one-time pin.
                            'is_locked' => $placement['is_locked'] ?? false,
                            'room_number' => $placement['room_number'] ?? null,
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
            Log::error('GenerateTimetableJob failed: '.$e->getMessage());
            $generation->update([
                'status' => TimetableGeneration::STATUS_FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            // Item 4: previously only rethrown in the testing environment,
            // so a production failure was marked FAILED, logged, and never
            // seen by the queue worker at all -- $tries/backoff above never
            // had a chance to engage. Always rethrowing lets Laravel's own
            // retry machinery do its job; each retry re-enters handle() from
            // the top (status flips back to RUNNING), which is safe -- see
            // the class docblock for why this job is idempotent.
            throw $e;
        }
    }

    /**
     * Called once the queue worker has exhausted every retry attempt (or
     * immediately for a non-retryable failure). handle()'s own catch block
     * above already marks the generation FAILED on every attempt,
     * including this last one -- this exists purely as Laravel's
     * documented safety net for the case that never reaches handle()'s
     * catch at all (e.g. the job payload itself fails to deserialize),
     * which would otherwise leave the generation stuck at RUNNING forever
     * with no record of why.
     */
    public function failed(\Throwable $exception): void
    {
        $generation = TimetableGeneration::find($this->generationId);
        if ($generation && $generation->status !== TimetableGeneration::STATUS_FAILED) {
            $generation->update([
                'status' => TimetableGeneration::STATUS_FAILED,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
