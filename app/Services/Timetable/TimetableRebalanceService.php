<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Rebalancing Engine: proposes a SMALL, bounded set of swaps/relocations
 * within ONE class-section's own already-placed lessons that measurably
 * reduce a deterministic, explainable "issue score" -- never by violating
 * a hard constraint, never by moving a locked lesson.
 *
 * Scope is deliberately narrow: only the target class-section's OWN rows
 * are ever candidates to move. Every OTHER class/section a shared teacher
 * also teaches is loaded read-only ("context") purely so teacher-gap and
 * workload scoring reflects that teacher's REAL whole-week schedule, not
 * an artificially incomplete slice -- but those context rows are never
 * touched. This keeps authorization simple (reuses TimetableSlotPolicy::
 * autoFix(), the same admin-only ability chain Auto-Fix already uses,
 * since this can still move a lesson taught by a teacher assigned only to
 * this one class), keeps the search bounded (one class's weekly grid, not
 * the whole school), and means "rebalance this class" never silently
 * reaches into a class the admin didn't ask about.
 *
 * Architecture, per the brief: this class introduces NO new conflict
 * engine and NO new swap algorithm.
 *   - Legality of a relocation candidate is decided EXCLUSIVELY by
 *     TimetableConflictResolver::check() -- the same authoritative rule
 *     engine every other interactive write already goes through.
 *   - Legality of a swap candidate is decided EXCLUSIVELY by
 *     TimetableSuggestionService::checkSwap(), which itself is just two
 *     TimetableConflictResolver::check() calls.
 *   - A swap movement is EXECUTED by TimetableSwapService::apply() --
 *     the exact same atomic, row-locked, re-validated, activity-logged
 *     write TimetableSwapTest already covers end to end. This class adds
 *     no swap-writing code of its own.
 *   - A relocation movement (no partner row to swap with -- moving to a
 *     genuinely empty period) has no existing single-row-move service to
 *     reuse, so applyMovements() below writes it directly, using the
 *     exact same validate-then-update-then-log idiom already established
 *     by TimetableAutoFixService::applyChainFix()'s per-step loop and
 *     ::applyBlockerRelocation() -- not a new pattern, the same one.
 *
 * Quality scoring is a fixed, documented, purely arithmetic function of
 * the timetable's own data (gap counts, workload spread, etc.) -- no
 * machine learning, no opaque weighting; every component is individually
 * countable and shown to the admin. See score() and self::WEIGHTS.
 *
 * Candidate search is a bounded GREEDY hill-climb: each iteration finds
 * the single best-scoring legal movement among the still-untouched
 * slots, accepts it, and repeats -- stopping the moment no movement
 * improves the score, or any of config('timetable.rebalance')'s limits
 * (max_candidate_evaluations / max_movements / max_iterations /
 * time_budget_seconds) is hit. This deliberately favours FEW, clearly
 * -attributable movements over an exhaustive whole-timetable optimum,
 * matching "prefer the smallest number of changes necessary."
 *
 * Within one analyze() call, once a slot has been used in an accepted
 * movement it is excluded from every later candidate this same pass --
 * so every candidate this method ever validates is checked against REAL,
 * unmodified database state (nothing simulated has been written), and no
 * two proposed movements in the same preview can ever touch the same
 * row. The in-memory "simulated" collections built for score comparison
 * are unsaved Eloquent clones used ONLY to compute a candidate's score if
 * it were applied -- see withSwappedSlots()/withMovedSlot() -- never
 * persisted during analyze().
 */
class TimetableRebalanceService
{
    /**
     * Fixed, documented weights -- the whole score is
     * sum(component_count * weight). Every component is an integer count
     * of a concrete, explainable condition (see score()), so the total is
     * always a deterministic integer for a given timetable snapshot.
     * Higher = more issues = worse; this is reported to the admin as an
     * "issue score" (lower is better), never dressed up as a fake 0-100
     * "quality %" that would imply false precision.
     */
    private const WEIGHTS = [
        'teacher_gap_periods' => 4,
        'class_gap_periods' => 3,
        'teacher_workload_imbalance' => 2,
        'prefer_morning_violations' => 2,
        'excessive_consecutive_periods' => 3,
        'room_switch_count' => 1,
    ];

    /** A run of this many consecutive taught periods (same teacher, same day) is normal; each period beyond it is penalised. */
    private const CONSECUTIVE_THRESHOLD = 3;

    private TimetableConflictResolver $resolver;
    private TimetableSuggestionService $suggestions;
    private TimetableSwapService $swapService;

    public function __construct(
        ?TimetableConflictResolver $resolver = null,
        ?TimetableSuggestionService $suggestions = null,
        ?TimetableSwapService $swapService = null
    ) {
        $this->resolver = $resolver ?? new TimetableConflictResolver();
        $this->suggestions = $suggestions ?? new TimetableSuggestionService($this->resolver);
        $this->swapService = $swapService ?? new TimetableSwapService($this->suggestions);
    }

    /**
     * Read-only. Writes nothing -- see the class docblock for why every
     * candidate it evaluates is still checked against live, unmodified
     * data despite this being a preview.
     *
     * @return array{
     *   ok: bool, message: ?string,
     *   baseline_score: ?array{total:int, breakdown:array<string,int>},
     *   proposed_score: ?array{total:int, breakdown:array<string,int>},
     *   movements: array, locked_excluded_count: int, evaluated_candidates: int,
     *   budget_exhausted: bool
     * }
     */
    public function analyze(int $schoolClassId, ?int $sectionId, ?string $academicYear, string $status = TimetableSlot::STATUS_PUBLISHED): array
    {
        $limits = config('timetable.rebalance');
        $startTime = microtime(true);

        $classSlots = TimetableSlot::with(['subject', 'teacher', 'coTeacher', 'bellTiming'])
            ->where('school_class_id', $schoolClassId)
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->where('status', $status)
            ->get();

        if ($classSlots->isEmpty()) {
            return $this->emptyResult('No lessons found for this class/section -- nothing to rebalance.');
        }

        $contextSlots = $this->loadContextSlots($classSlots, $status);
        [$timingMeta, $dayMeta] = $this->loadPeriodMeta($academicYear);

        $baseline = $this->score($classSlots, $contextSlots, $timingMeta, $dayMeta);

        $lockedExcludedCount = $classSlots->filter(fn ($s) => $s->is_locked)->count();
        $movableSlots = $classSlots
            ->reject(fn ($s) => $s->is_locked || $s->combined_class_group_id || $s->status === TimetableSlot::STATUS_ARCHIVED)
            ->values();

        if ($movableSlots->isEmpty()) {
            return array_merge($this->emptyResult(
                $lockedExcludedCount > 0
                    ? 'Every lesson in this class/section is locked -- nothing available to rebalance.'
                    : 'No movable lessons found for this class/section -- nothing to rebalance.'
            ), ['baseline_score' => $baseline, 'proposed_score' => $baseline, 'locked_excluded_count' => $lockedExcludedCount]);
        }

        $candidatePeriods = $this->suggestions->candidatePeriodsFor(['academic_year' => $academicYear]);

        $working = $classSlots;
        $currentScore = $baseline;
        $touchedIds = [];
        $movements = [];
        $evaluated = 0;
        $budgetExhausted = false;

        for ($iteration = 0; $iteration < $limits['max_iterations'] && count($movements) < $limits['max_movements']; $iteration++) {
            if ((microtime(true) - $startTime) >= $limits['time_budget_seconds']) {
                $budgetExhausted = true;
                break;
            }

            $best = $this->findBestCandidate(
                $movableSlots->pluck('id')->diff($touchedIds)->values(),
                $working,
                $contextSlots,
                $timingMeta,
                $dayMeta,
                $currentScore,
                $candidatePeriods,
                $academicYear,
                $evaluated,
                $limits['max_candidate_evaluations'],
                $budgetExhausted
            );

            if ($best === null) {
                break;
            }

            $working = $best['simulated'];
            $movements[] = $this->describeMovement($best, $classSlots, $currentScore['breakdown']);
            $currentScore = $best['score'];
            $touchedIds = array_merge($touchedIds, $best['type'] === 'swap' ? [$best['slot_a_id'], $best['slot_b_id']] : [$best['slot_id']]);
        }

        return [
            'ok' => true,
            'message' => empty($movements)
                ? 'No improving movement found -- this class/section is already well balanced (or every possible improvement would break a hard constraint).'
                : count($movements) . ' movement(s) proposed.',
            'baseline_score' => $baseline,
            'proposed_score' => $currentScore,
            'movements' => $movements,
            'locked_excluded_count' => $lockedExcludedCount,
            'evaluated_candidates' => $evaluated,
            'budget_exhausted' => $budgetExhausted,
        ];
    }

    /**
     * Applies a set of movements previously returned by analyze() --
     * never trusts them as still valid: every row is re-fetched and
     * locked, every movement is re-validated against LIVE data, and if
     * ANY movement is no longer valid the whole transaction throws and
     * rolls back everything, applying nothing. Swap movements are
     * executed by TimetableSwapService::apply() (which does its own
     * re-validation, row-locking, and activity logging); relocation
     * movements are validated by TimetableConflictResolver::check() and
     * written directly, mirroring TimetableAutoFixService's own
     * validate-then-update-then-log pattern for a single-row move.
     *
     * @param array<int, array{type:string, slot_a_id?:int, slot_b_id?:int, slot_id?:int, to_bell_timing_id?:int}> $movements
     * @return array{applied: bool, message: string, movements_applied: int}
     */
    public function apply(array $movements, ?string $academicYear = null): array
    {
        if (empty($movements)) {
            return ['applied' => false, 'message' => 'No movements to apply.', 'movements_applied' => 0];
        }

        try {
            $count = DB::transaction(function () use ($movements) {
                $applied = 0;

                foreach ($movements as $movement) {
                    $type = $movement['type'] ?? null;

                    if ($type === 'swap') {
                        $slotAId = (int) ($movement['slot_a_id'] ?? 0);
                        $slotBId = (int) ($movement['slot_b_id'] ?? 0);
                        if (!$slotAId || !$slotBId) {
                            throw new \RuntimeException('This rebalance is no longer valid -- a movement was malformed.');
                        }

                        $result = $this->swapService->apply($slotAId, $slotBId);
                        if (!$result['applied']) {
                            throw new \RuntimeException("This rebalance is no longer valid -- {$result['message']}");
                        }

                        $applied++;
                        continue;
                    }

                    if ($type === 'relocate') {
                        $this->applyRelocation($movement);
                        $applied++;
                        continue;
                    }

                    throw new \RuntimeException('This rebalance is no longer valid -- a movement had an unrecognised type.');
                }

                return $applied;
            });

            return ['applied' => true, 'message' => "Rebalance applied -- {$count} movement(s).", 'movements_applied' => $count];
        } catch (\RuntimeException $e) {
            return ['applied' => false, 'message' => $e->getMessage(), 'movements_applied' => 0];
        }
    }

    /**
     * A relocation has no partner row -- re-fetches and locks the single
     * slot, re-checks every guard analyze() already applied when it was
     * first proposed (locked/combined-group/archived can all have
     * changed since preview), re-validates the destination period via
     * the one authoritative TimetableConflictResolver, then writes and
     * logs -- the same idiom applyChainFix()'s per-step loop already
     * uses for a single-row move, not a new one.
     */
    private function applyRelocation(array $movement): void
    {
        $slotId = (int) ($movement['slot_id'] ?? 0);
        $toBellTimingId = (int) ($movement['to_bell_timing_id'] ?? 0);
        if (!$slotId || !$toBellTimingId) {
            throw new \RuntimeException('a movement was malformed.');
        }

        $slot = TimetableSlot::lockForUpdate()->find($slotId);
        if (!$slot) {
            throw new \RuntimeException('one of the lessons it planned to move no longer exists.');
        }
        if ($slot->is_locked) {
            throw new \RuntimeException('one of the lessons it planned to move has since been locked.');
        }
        if ($slot->combined_class_group_id) {
            throw new \RuntimeException('one of the lessons it planned to move is now part of a combined group.');
        }
        if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
            throw new \RuntimeException('one of the lessons it planned to move is now archived history.');
        }

        $trial = [
            'school_class_id' => $slot->school_class_id,
            'section_id' => $slot->section_id,
            'teacher_id' => $slot->teacher_id,
            'co_teacher_id' => $slot->co_teacher_id,
            'subject_id' => $slot->subject_id,
            'room_number' => $slot->room_number,
            'status' => $slot->status,
            'academic_year' => $slot->academic_year,
            'bell_timing_id' => $toBellTimingId,
            'ignore_slot_id' => $slot->id,
        ];

        $check = $this->resolver->check($trial);
        if ($check['conflict']) {
            throw new \RuntimeException("moving one of the lessons would now conflict: {$check['message']}");
        }

        $before = $slot->bell_timing_id;
        $slot->update(['bell_timing_id' => $toBellTimingId]);

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['moved_from_bell_timing_id' => $before, 'moved_to_bell_timing_id' => $toBellTimingId])
            ->log('timetable_rebalance_relocated');
    }

    /**
     * Every OTHER slot (any class/section) taught by a teacher or
     * co-teacher who ALSO appears in the target class/section's own
     * slots -- read-only context so teacher-gap/workload scoring reflects
     * that teacher's real whole-week schedule. Never a candidate to move.
     */
    private function loadContextSlots(Collection $classSlots, string $status): Collection
    {
        $teacherIds = $classSlots->pluck('teacher_id')
            ->merge($classSlots->pluck('co_teacher_id'))
            ->filter()
            ->unique()
            ->values();

        if ($teacherIds->isEmpty()) {
            return collect();
        }

        return TimetableSlot::where('status', $status)
            ->whereNotIn('id', $classSlots->pluck('id'))
            ->where(fn ($q) => $q->whereIn('teacher_id', $teacherIds)->orWhereIn('co_teacher_id', $teacherIds))
            ->get(['id', 'teacher_id', 'co_teacher_id', 'bell_timing_id']);
    }

    /**
     * @return array{0: array<int, array{day:string, position:int, period_name:string}>, 1: array<string, array{sequence:array<int,int>, max_position:int}>}
     */
    private function loadPeriodMeta(?string $academicYear): array
    {
        $timings = BellTiming::active()->teachingType()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $timingMeta = [];
        $dayMeta = [];

        foreach ($timings->groupBy('day_of_week') as $day => $group) {
            $ordered = $group->sortBy('order_index')->values();
            $sequence = [];

            foreach ($ordered as $position => $timing) {
                $timingMeta[$timing->id] = ['day' => $day, 'position' => $position, 'period_name' => $timing->period_name];
                $sequence[] = $timing->id;
            }

            $dayMeta[$day] = ['sequence' => $sequence, 'max_position' => count($sequence) - 1];
        }

        return [$timingMeta, $dayMeta];
    }

    /**
     * Deterministic, purely arithmetic breakdown -- see the class
     * docblock and self::WEIGHTS. $classSlots may be a real or simulated
     * (unsaved-clone) collection; $contextSlots never changes within one
     * analyze() call.
     *
     * @return array{total:int, breakdown:array<string,int>}
     */
    private function score(Collection $classSlots, Collection $contextSlots, array $timingMeta, array $dayMeta): array
    {
        $teacherDayPositions = [];
        $addTeacherPeriod = function ($teacherId, $btId) use (&$teacherDayPositions, $timingMeta) {
            if (!$teacherId || !isset($timingMeta[$btId])) {
                return;
            }
            $meta = $timingMeta[$btId];
            $teacherDayPositions[$teacherId][$meta['day']][$meta['position']] = true;
        };

        foreach ($classSlots as $slot) {
            $addTeacherPeriod($slot->teacher_id, $slot->bell_timing_id);
            if ($slot->co_teacher_id) {
                $addTeacherPeriod($slot->co_teacher_id, $slot->bell_timing_id);
            }
        }
        foreach ($contextSlots as $slot) {
            $addTeacherPeriod($slot->teacher_id, $slot->bell_timing_id);
            if ($slot->co_teacher_id) {
                $addTeacherPeriod($slot->co_teacher_id, $slot->bell_timing_id);
            }
        }

        $classDayPositions = [];
        foreach ($classSlots as $slot) {
            if (!isset($timingMeta[$slot->bell_timing_id])) {
                continue;
            }
            $meta = $timingMeta[$slot->bell_timing_id];
            $classDayPositions[$meta['day']][$meta['position']] = $slot;
        }

        $teacherGapPeriods = 0;
        $teacherWorkloadImbalance = 0;
        $excessiveConsecutivePeriods = 0;

        foreach ($teacherDayPositions as $days) {
            $dayCounts = [];
            foreach ($days as $day => $positions) {
                $positionKeys = array_keys($positions);
                sort($positionKeys);
                $dayCounts[$day] = count($positionKeys);

                if (count($positionKeys) >= 2) {
                    $span = end($positionKeys) - $positionKeys[0] + 1;
                    $teacherGapPeriods += $span - count($positionKeys);
                }

                $excessiveConsecutivePeriods += $this->penalizeConsecutiveRuns($positionKeys);
            }

            if (count($dayCounts) >= 2) {
                $teacherWorkloadImbalance += max($dayCounts) - min($dayCounts);
            }
        }

        $classGapPeriods = 0;
        $roomSwitchCount = 0;
        $anyRoomData = $classSlots->contains(fn ($s) => !empty($s->room_number));

        foreach ($classDayPositions as $positions) {
            $positionKeys = array_keys($positions);
            sort($positionKeys);

            if (count($positionKeys) >= 2) {
                $span = end($positionKeys) - $positionKeys[0] + 1;
                $classGapPeriods += $span - count($positionKeys);
            }

            if ($anyRoomData) {
                for ($i = 1; $i < count($positionKeys); $i++) {
                    if ($positionKeys[$i] === $positionKeys[$i - 1] + 1) {
                        $prevRoom = $positions[$positionKeys[$i - 1]]->room_number;
                        $curRoom = $positions[$positionKeys[$i]]->room_number;
                        if ($prevRoom && $curRoom && $prevRoom !== $curRoom) {
                            $roomSwitchCount++;
                        }
                    }
                }
            }
        }

        $preferMorningViolations = 0;
        foreach ($classSlots as $slot) {
            if (!$slot->subject || !$slot->subject->prefer_morning) {
                continue;
            }
            $meta = $timingMeta[$slot->bell_timing_id] ?? null;
            if (!$meta) {
                continue;
            }
            $maxPosition = $dayMeta[$meta['day']]['max_position'] ?? 0;
            if ($maxPosition > 0 && $meta['position'] > intdiv($maxPosition, 2)) {
                $preferMorningViolations++;
            }
        }

        $breakdown = [
            'teacher_gap_periods' => $teacherGapPeriods,
            'class_gap_periods' => $classGapPeriods,
            'teacher_workload_imbalance' => $teacherWorkloadImbalance,
            'prefer_morning_violations' => $preferMorningViolations,
            'excessive_consecutive_periods' => $excessiveConsecutivePeriods,
            'room_switch_count' => $roomSwitchCount,
        ];

        $total = 0;
        foreach ($breakdown as $key => $count) {
            $total += $count * self::WEIGHTS[$key];
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    /** @param array<int,int> $sortedPositions strictly ascending */
    private function penalizeConsecutiveRuns(array $sortedPositions): int
    {
        $penalty = 0;
        $runLength = 1;

        for ($i = 1; $i < count($sortedPositions); $i++) {
            if ($sortedPositions[$i] === $sortedPositions[$i - 1] + 1) {
                $runLength++;
            } else {
                $penalty += max(0, $runLength - self::CONSECUTIVE_THRESHOLD);
                $runLength = 1;
            }
        }
        $penalty += max(0, $runLength - self::CONSECUTIVE_THRESHOLD);

        return $penalty;
    }

    /**
     * One greedy iteration: the single best-scoring legal movement among
     * $candidateSlotIds, or null if none improves the score. $evaluated
     * is mutated by reference (shared budget across the whole analyze()
     * call, not reset per iteration) -- $budgetExhausted likewise.
     */
    private function findBestCandidate(
        Collection $candidateSlotIds,
        Collection $working,
        Collection $contextSlots,
        array $timingMeta,
        array $dayMeta,
        array $currentScore,
        Collection $candidatePeriods,
        ?string $academicYear,
        int &$evaluated,
        int $maxEvaluations,
        bool &$budgetExhausted
    ): ?array {
        $best = null;
        $occupiedBellTimingIds = $working->pluck('bell_timing_id')->all();

        foreach ($candidateSlotIds as $slotId) {
            if ($evaluated >= $maxEvaluations) {
                $budgetExhausted = true;
                break;
            }

            $slot = $working->firstWhere('id', $slotId);
            if (!$slot) {
                continue;
            }

            foreach ($candidateSlotIds as $otherId) {
                if ($otherId <= $slotId) {
                    continue; // unordered pair, evaluate once, deterministic by ascending id
                }
                if ($evaluated >= $maxEvaluations) {
                    $budgetExhausted = true;
                    break;
                }
                $evaluated++;

                $swapCheck = $this->suggestions->checkSwap($slotId, $otherId);
                if (!$swapCheck['ok']) {
                    continue;
                }

                $simulated = $this->withSwappedSlots($working, $slotId, $otherId);
                $newScore = $this->score($simulated, $contextSlots, $timingMeta, $dayMeta);
                $delta = $currentScore['total'] - $newScore['total'];

                if ($delta > 0 && ($best === null || $delta > $best['delta'])) {
                    $best = ['delta' => $delta, 'type' => 'swap', 'slot_a_id' => $slotId, 'slot_b_id' => $otherId, 'simulated' => $simulated, 'score' => $newScore];
                }
            }

            if ($budgetExhausted) {
                break;
            }

            foreach ($candidatePeriods as $period) {
                if ($evaluated >= $maxEvaluations) {
                    $budgetExhausted = true;
                    break;
                }
                if ((int) $period->id === (int) $slot->bell_timing_id) {
                    continue;
                }
                if (in_array($period->id, $occupiedBellTimingIds, true)) {
                    continue; // occupied by another slot of this class/section -- that needs a swap, not a relocation
                }
                $evaluated++;

                $trial = [
                    'school_class_id' => $slot->school_class_id,
                    'section_id' => $slot->section_id,
                    'teacher_id' => $slot->teacher_id,
                    'co_teacher_id' => $slot->co_teacher_id,
                    'subject_id' => $slot->subject_id,
                    'room_number' => $slot->room_number,
                    'status' => $slot->status,
                    'academic_year' => $academicYear,
                    'bell_timing_id' => $period->id,
                    'ignore_slot_id' => $slot->id,
                ];

                if ($this->resolver->check($trial)['conflict']) {
                    continue;
                }

                $simulated = $this->withMovedSlot($working, $slotId, $period->id);
                $newScore = $this->score($simulated, $contextSlots, $timingMeta, $dayMeta);
                $delta = $currentScore['total'] - $newScore['total'];

                if ($delta > 0 && ($best === null || $delta > $best['delta'])) {
                    $best = ['delta' => $delta, 'type' => 'relocate', 'slot_id' => $slotId, 'to_bell_timing_id' => $period->id, 'simulated' => $simulated, 'score' => $newScore];
                }
            }

            if ($budgetExhausted) {
                break;
            }
        }

        return $best;
    }

    /**
     * Unsaved Eloquent clones used ONLY for in-memory score comparison --
     * never persisted. id is copied back onto the clone (replicate() nulls
     * it, since it's normally meant for creating a genuinely new row) so
     * score()/describeMovement() can still key results by the original
     * slot's identity; this is safe only because these clones are never
     * saved.
     */
    private function withMovedSlot(Collection $classSlots, int $slotId, int $newBellTimingId): Collection
    {
        return $classSlots->map(function ($slot) use ($slotId, $newBellTimingId) {
            if ($slot->id !== $slotId) {
                return $slot;
            }
            $clone = $slot->replicate();
            $clone->id = $slot->id;
            $clone->bell_timing_id = $newBellTimingId;
            $clone->setRelation('subject', $slot->subject);

            return $clone;
        });
    }

    private function withSwappedSlots(Collection $classSlots, int $slotIdA, int $slotIdB): Collection
    {
        $btA = $classSlots->firstWhere('id', $slotIdA)->bell_timing_id;
        $btB = $classSlots->firstWhere('id', $slotIdB)->bell_timing_id;

        return $classSlots->map(function ($slot) use ($slotIdA, $slotIdB, $btA, $btB) {
            if ($slot->id === $slotIdA) {
                $clone = $slot->replicate();
                $clone->id = $slot->id;
                $clone->bell_timing_id = $btB;
                $clone->setRelation('subject', $slot->subject);

                return $clone;
            }
            if ($slot->id === $slotIdB) {
                $clone = $slot->replicate();
                $clone->id = $slot->id;
                $clone->bell_timing_id = $btA;
                $clone->setRelation('subject', $slot->subject);

                return $clone;
            }

            return $slot;
        });
    }

    /** Builds the human-readable movement entry the UI renders, citing exactly which score components changed and by how much. */
    private function describeMovement(array $candidate, Collection $originalSlots, array $beforeBreakdown): array
    {
        $afterBreakdown = $candidate['score']['breakdown'];
        $componentDeltas = [];
        foreach ($beforeBreakdown as $key => $before) {
            $after = $afterBreakdown[$key] ?? $before;
            if ($after !== $before) {
                $label = ucfirst(str_replace('_', ' ', $key));
                $componentDeltas[] = sprintf('%s %s%d', $label, $after < $before ? '-' : '+', abs($after - $before));
            }
        }
        $reason = empty($componentDeltas)
            ? "Improves overall schedule quality by {$candidate['delta']} point(s)."
            : implode('; ', $componentDeltas) . " (net improvement: {$candidate['delta']} point(s))";

        if ($candidate['type'] === 'swap') {
            $a = $originalSlots->firstWhere('id', $candidate['slot_a_id']);
            $b = $originalSlots->firstWhere('id', $candidate['slot_b_id']);

            return [
                'type' => 'swap',
                'slot_a_id' => $a->id,
                'slot_b_id' => $b->id,
                'a_subject' => $a->subject->name ?? null,
                'a_teacher' => $a->teacher->name ?? null,
                'a_from' => $this->describePeriod($a->bellTiming),
                'a_to' => $this->describePeriodById($b->bell_timing_id),
                'b_subject' => $b->subject->name ?? null,
                'b_teacher' => $b->teacher->name ?? null,
                'b_from' => $this->describePeriod($b->bellTiming),
                'b_to' => $this->describePeriodById($a->bell_timing_id),
                'delta' => $candidate['delta'],
                'reason' => "Swap: {$reason}",
            ];
        }

        $slot = $originalSlots->firstWhere('id', $candidate['slot_id']);
        $toTiming = BellTiming::find($candidate['to_bell_timing_id']);

        return [
            'type' => 'relocate',
            'slot_id' => $slot->id,
            'to_bell_timing_id' => $candidate['to_bell_timing_id'],
            'subject' => $slot->subject->name ?? null,
            'teacher' => $slot->teacher->name ?? null,
            'from' => $this->describePeriod($slot->bellTiming),
            'to' => $toTiming ? "{$toTiming->day_of_week} {$toTiming->period_name}" : null,
            'delta' => $candidate['delta'],
            'reason' => "Relocate: {$reason}",
        ];
    }

    private function describePeriod(?BellTiming $timing): ?string
    {
        return $timing ? "{$timing->day_of_week} {$timing->period_name}" : null;
    }

    private function describePeriodById(int $bellTimingId): ?string
    {
        $timing = BellTiming::find($bellTimingId);

        return $timing ? "{$timing->day_of_week} {$timing->period_name}" : null;
    }

    private function emptyResult(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'baseline_score' => null,
            'proposed_score' => null,
            'movements' => [],
            'locked_excluded_count' => 0,
            'evaluated_candidates' => 0,
            'budget_exhausted' => false,
        ];
    }
}
