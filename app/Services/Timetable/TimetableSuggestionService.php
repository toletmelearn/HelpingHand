<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

/**
 * Phase 3 (Smart Suggestions): given a placement TimetableConflictResolver
 * has already flagged, finds concrete, ALREADY-VALIDATED alternatives --
 * never a bare "clash!", always "here's what would actually work." Every
 * candidate is validated by re-running it through
 * TimetableConflictResolver::check() (the one authoritative constraint
 * mechanism); this service adds no conflict rules of its own, only the
 * search-and-rank logic over what the resolver already knows how to judge.
 *
 * Three independent capabilities, each usable on its own:
 *   - suggestForNewPlacement(): move the lesson trying to be placed.
 *   - suggestBlockerRelocation(): move whatever's already occupying the
 *     wanted period instead, freeing it up.
 *   - checkSwap(): validate trading two ALREADY-PLACED lessons' periods
 *     (the building block a future drag-and-drop "swap" gesture needs).
 */
class TimetableSuggestionService
{
    private TimetableConflictResolver $resolver;

    /**
     * candidateBellTimings() memoized per academic_year within THIS
     * instance's lifetime only -- checkConflictsApi() creates one instance
     * per request and calls both suggestForNewPlacement() and
     * suggestBlockerRelocation() on it back to back with the same
     * academic_year, which previously re-ran the identical query twice.
     * Never shared across requests/instances, so it can't go stale across
     * a mutation -- this service and its caller are read-only; nothing
     * here ever writes a TimetableSlot.
     *
     * @var array<string, Collection>
     */
    private array $candidateBellTimingsCache = [];

    public function __construct(?TimetableConflictResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new TimetableConflictResolver();
    }

    /**
     * @param array $placement Same shape TimetableConflictResolver::check() takes.
     * @return array<int, array{bell_timing_id:int, day_of_week:string, period_name:string, description:string}>
     */
    public function suggestForNewPlacement(array $placement, int $limit = 5): array
    {
        $suggestions = [];

        foreach ($this->candidateBellTimings($placement) as $candidate) {
            if ((int) $candidate->id === (int) ($placement['bell_timing_id'] ?? 0)) {
                continue;
            }

            $trial = array_merge($placement, ['bell_timing_id' => $candidate->id]);
            unset($trial['ignore_slot_id']); // a different period is a different natural key -- let check() re-resolve it.

            if (!$this->resolver->check($trial)['conflict']) {
                $suggestions[] = $this->describe($candidate, "Move to {$candidate->day_of_week} {$candidate->period_name}");
            }

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * Identifies whichever already-placed lesson is blocking the
     * requested period (the first conflict that names one -- teacher,
     * co-teacher, class/section, or room; constraint-only violations like
     * a daily-load cap or period type have no single "blocker" row to
     * relocate) and finds clean alternative periods for THAT lesson,
     * keeping its own teacher/class/subject unchanged.
     *
     * @return array<int, array{bell_timing_id:int, day_of_week:string, period_name:string, description:string, blocking_slot_id:int}>
     */
    public function suggestBlockerRelocation(array $placement, int $limit = 5): array
    {
        $result = $this->resolver->check($placement);
        $blockerId = null;
        foreach ($result['conflicts'] as $conflict) {
            if (!empty($conflict['blocking_slot_id'])) {
                $blockerId = $conflict['blocking_slot_id'];
                break;
            }
        }

        if (!$blockerId) {
            return [];
        }

        $blocker = TimetableSlot::find($blockerId);
        if (!$blocker) {
            return [];
        }

        $blockerPlacement = [
            'school_class_id' => $blocker->school_class_id,
            'section_id' => $blocker->section_id,
            'teacher_id' => $blocker->teacher_id,
            'co_teacher_id' => $blocker->co_teacher_id,
            'subject_id' => $blocker->subject_id,
            'room_number' => $blocker->room_number,
            'status' => $blocker->status,
            'ignore_slot_id' => $blocker->id,
        ];

        $suggestions = [];
        $wantedBellTimingId = (int) ($placement['bell_timing_id'] ?? 0);

        foreach ($this->candidateBellTimings($placement) as $candidate) {
            // The period we're trying to free up isn't a valid destination
            // for the blocker -- it would just recreate the same conflict.
            if ((int) $candidate->id === $wantedBellTimingId) {
                continue;
            }

            $trial = array_merge($blockerPlacement, ['bell_timing_id' => $candidate->id]);

            if (!$this->resolver->check($trial)['conflict']) {
                $subjectName = Subject::find($blocker->subject_id)->name ?? 'This lesson';
                $teacherName = Teacher::find($blocker->teacher_id)->name ?? '';
                $suggestions[] = array_merge(
                    $this->describe($candidate, "Move {$subjectName} ({$teacherName}) to {$candidate->day_of_week} {$candidate->period_name}, freeing this period"),
                    ['blocking_slot_id' => $blocker->id]
                );
            }

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * Validates trading two ALREADY-PLACED lessons' periods: slot A moves
     * to slot B's period and vice versa. Both must be clean in their new
     * position, with both original rows excluded from every check (each
     * would otherwise "conflict" with the very row it's trading places
     * with, or with its own current occupancy of the period it's leaving).
     *
     * @return array{ok: bool, a_result: array, b_result: array}
     */
    public function checkSwap(int $slotIdA, int $slotIdB): array
    {
        $a = TimetableSlot::findOrFail($slotIdA);
        $b = TimetableSlot::findOrFail($slotIdB);

        $ignoreBoth = [$a->id, $b->id];

        $aAtBsPeriod = $this->resolver->check([
            'school_class_id' => $a->school_class_id, 'section_id' => $a->section_id,
            'teacher_id' => $a->teacher_id, 'co_teacher_id' => $a->co_teacher_id,
            'subject_id' => $a->subject_id, 'room_number' => $a->room_number,
            'status' => $a->status, 'bell_timing_id' => $b->bell_timing_id,
            'ignore_slot_id' => $ignoreBoth,
        ]);

        $bAtAsPeriod = $this->resolver->check([
            'school_class_id' => $b->school_class_id, 'section_id' => $b->section_id,
            'teacher_id' => $b->teacher_id, 'co_teacher_id' => $b->co_teacher_id,
            'subject_id' => $b->subject_id, 'room_number' => $b->room_number,
            'status' => $b->status, 'bell_timing_id' => $a->bell_timing_id,
            'ignore_slot_id' => $ignoreBoth,
        ]);

        return [
            'ok' => !$aAtBsPeriod['conflict'] && !$bAtAsPeriod['conflict'],
            'a_result' => $aAtBsPeriod,
            'b_result' => $bAtAsPeriod,
        ];
    }

    /**
     * Active, teaching-type bell timings as the candidate pool -- the same
     * universe GeneratorService draws candidate slots from, so a
     * suggestion is never a period the generator itself would refuse to
     * use.
     */
    private function candidateBellTimings(array $placement): Collection
    {
        // Keyed on the academic_year value alone: that's the only part of
        // $placement this query's WHERE clauses depend on (see the query
        // below), so two calls with the same year -- even for otherwise
        // different placements/candidates -- always return the identical
        // result set.
        $cacheKey = (string) ($placement['academic_year'] ?? '');

        if (!array_key_exists($cacheKey, $this->candidateBellTimingsCache)) {
            $this->candidateBellTimingsCache[$cacheKey] = BellTiming::active()
                ->teachingType()
                ->when($placement['academic_year'] ?? null, fn ($q, $year) => $q->where('academic_year', $year))
                ->orderBy('day_of_week')
                ->orderBy('order_index')
                ->get();
        }

        return $this->candidateBellTimingsCache[$cacheKey];
    }

    private function describe(BellTiming $candidate, string $description): array
    {
        return [
            'bell_timing_id' => $candidate->id,
            'day_of_week' => $candidate->day_of_week,
            'period_name' => $candidate->period_name,
            'description' => $description,
        ];
    }
}
