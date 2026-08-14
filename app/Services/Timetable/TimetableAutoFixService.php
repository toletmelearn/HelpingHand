<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 (Auto-Fix): applies ONE of TimetableSuggestionService's
 * relocate-blocker suggestions as a single atomic action -- move the
 * blocking lesson to its suggested new period, then place the new lesson
 * into the period it just freed. Both halves are re-validated against
 * TimetableConflictResolver immediately before writing anything, using
 * live data, not the (possibly now-stale) state the suggestion was
 * originally computed from -- another admin could have changed something
 * in between. Never applied silently: the caller must name both a
 * specific blocking_slot_id and a specific destination period, there is
 * no "just pick something" mode.
 *
 * previewChainFix()/applyChainFix() below generalise this to N hops: when
 * the single blocker itself has nowhere clean to go, they look for a
 * chain of relocations (the blocker's blocker, and so on, up to
 * config('timetable.autofix.max_chain_depth')) that collectively frees
 * the requested period. Every step is still validated exclusively through
 * TimetableConflictResolver::check() and TimetableSuggestionService's own
 * candidate pool -- no parallel conflict or candidate logic is
 * introduced, only the search over what those two already know how to
 * judge.
 */
class TimetableAutoFixService
{
    private TimetableConflictResolver $resolver;
    private TimetableSuggestionService $suggestions;

    public function __construct(?TimetableConflictResolver $resolver = null, ?TimetableSuggestionService $suggestions = null)
    {
        $this->resolver = $resolver ?? new TimetableConflictResolver();
        $this->suggestions = $suggestions ?? new TimetableSuggestionService($this->resolver);
    }

    /**
     * @param array $newPlacement Same shape TimetableConflictResolver::check() takes -- the lesson that was blocked.
     * @return array{applied: bool, message: string}
     */
    /**
     * Lock Integrity hardening: the blocker used to be fetched via a plain
     * find() BEFORE this method's transaction even opened, and every check
     * below (is_locked, both resolver checks) ran against that same
     * early, unlocked snapshot -- so a lock (or any other change) landing
     * on the row between that early read and the eventual write was
     * silently ignored, and the final $blocker->update() wrote through the
     * same stale in-memory object rather than re-reading it. The fetch now
     * happens INSIDE the transaction via lockForUpdate(), immediately
     * before every check and the write, matching the pattern
     * applyChainFix() and TimetableSwapService::apply() already use. The
     * try/catch below is the same DB-level backstop TimetableController::
     * store() already has for its own updateOrCreate() -- this method
     * never had one, so a genuine last-moment unique-constraint collision
     * (the app-level checks are the primary guard; this is what's left
     * over after them) would previously have leaked an unhandled
     * QueryException instead of a friendly response.
     */
    public function applyBlockerRelocation(array $newPlacement, int $blockingSlotId, int $blockerNewBellTimingId): array
    {
        try {
            return DB::transaction(function () use ($newPlacement, $blockingSlotId, $blockerNewBellTimingId) {
                $blocker = TimetableSlot::lockForUpdate()->find($blockingSlotId);
                if (!$blocker) {
                    return ['applied' => false, 'message' => 'The lesson this fix was supposed to move no longer exists -- someone may have already changed it.'];
                }

                if ($blocker->is_locked) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- the lesson it planned to move has since been locked.'];
                }

                $blockerAtNewPeriod = [
                    'school_class_id' => $blocker->school_class_id,
                    'section_id' => $blocker->section_id,
                    'teacher_id' => $blocker->teacher_id,
                    'co_teacher_id' => $blocker->co_teacher_id,
                    'subject_id' => $blocker->subject_id,
                    'room_number' => $blocker->room_number,
                    'status' => $blocker->status,
                    'academic_year' => $blocker->academic_year,
                    'bell_timing_id' => $blockerNewBellTimingId,
                    'ignore_slot_id' => $blocker->id,
                ];

                $blockerCheck = $this->resolver->check($blockerAtNewPeriod);
                if ($blockerCheck['conflict']) {
                    return ['applied' => false, 'message' => "This fix is no longer valid -- moving the other lesson there would now conflict: {$blockerCheck['message']}"];
                }

                // Simulates "as if the blocker had already moved": the new
                // lesson's own check must ignore the blocker's CURRENT row,
                // since that's precisely the occupant this fix is about to
                // relocate.
                $newPlacementCheck = $this->resolver->check(array_merge($newPlacement, ['ignore_slot_id' => $blocker->id]));
                if ($newPlacementCheck['conflict']) {
                    return ['applied' => false, 'message' => "This fix is no longer valid -- your lesson would still conflict: {$newPlacementCheck['message']}"];
                }

                $originalBellTimingId = $blocker->bell_timing_id;

                $blocker->update(['bell_timing_id' => $blockerNewBellTimingId]);

                // Issue #14: a slot Auto-Fix creates while resolving an
                // unplaced lesson from a specific generation's draft review
                // must carry that generation's id through, or
                // publishGeneration()'s generation-scoped promote sweep
                // silently never reaches it -- the admin sees "scheduled",
                // the row stays draft forever. Only carried through when the
                // caller actually supplied one (never invented here), and
                // only added to the write when present so an update-path
                // match (an already-existing row) never gets its real
                // generation_id silently nulled out by an omission.
                $newPlacementValues = $newPlacement;
                if (array_key_exists('timetable_generation_id', $newPlacement)) {
                    $newPlacementValues['timetable_generation_id'] = $newPlacement['timetable_generation_id'];
                }

                TimetableSlot::updateOrCreate(
                    [
                        'school_class_id' => $newPlacement['school_class_id'],
                        'section_id' => $newPlacement['section_id'] ?? null,
                        'bell_timing_id' => $newPlacement['bell_timing_id'],
                        'status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED,
                    ],
                    array_merge($newPlacementValues, ['status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED])
                );

                activity()->causedBy(Auth::user())->performedOn($blocker)
                    ->withProperties([
                        'moved_from_bell_timing_id' => $originalBellTimingId,
                        'moved_to_bell_timing_id' => $blockerNewBellTimingId,
                        'freed_for' => $newPlacement,
                    ])
                    ->log('timetable_autofix_applied');

                return ['applied' => true, 'message' => 'Fix applied -- the other lesson was moved and your lesson is now scheduled.'];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Driver-agnostic (MySQL/SQLite/Postgres all throw this same
            // subclass in modern Laravel), unlike store()'s older
            // errorInfo[1]===1062 check which is MySQL-specific.
            return ['applied' => false, 'message' => 'This fix is no longer valid -- something else was scheduled in the moment this was being applied.'];
        }
    }

    /**
     * Attempts to find a chain of relocations that would let $newPlacement
     * be placed cleanly at its requested bell_timing_id, moving up to
     * max_chain_depth other lessons out of the way. Read-only -- writes
     * nothing; applyChainFix() re-validates and performs the actual moves.
     *
     * Only conflicts that name a single blocking_slot_id (teacher,
     * co-teacher, class/section, room) are chain-repairable -- a
     * constraint-only violation (daily/weekly load limit, subject-per-day
     * cap, non-teaching period) has no single row whose relocation would
     * fix it, so those are reported as unfixable the same way
     * TimetableSuggestionService::suggestBlockerRelocation() already
     * treats them.
     *
     * @param array $newPlacement Same shape TimetableConflictResolver::check() takes.
     * @return array{ok: bool, message: string, steps: array, final_placement: ?array}
     */
    public function previewChainFix(array $newPlacement, ?int $maxDepth = null): array
    {
        $maxDepth = $maxDepth ?? (int) config('timetable.autofix.max_chain_depth', 3);
        $budget = (int) config('timetable.autofix.search_budget', 150);

        $rootBellTimingId = (int) ($newPlacement['bell_timing_id'] ?? 0);
        if (!$rootBellTimingId) {
            return ['ok' => false, 'message' => 'No target period given.', 'steps' => [], 'final_placement' => null];
        }

        $movedSlotIds = [];
        $claimedBellTimingIds = [$rootBellTimingId];

        $steps = $this->discoverChain($newPlacement, $maxDepth, $movedSlotIds, $claimedBellTimingIds, $budget);

        if ($steps === null) {
            return [
                'ok' => false,
                'message' => $budget <= 0
                    ? 'Could not find a fix within the search budget -- the timetable may be too tightly packed, or this needs a manual move.'
                    : "Could not find a chain of moves that resolves this conflict within {$maxDepth} step(s).",
                'steps' => [],
                'final_placement' => null,
            ];
        }

        return [
            'ok' => true,
            'message' => empty($steps)
                ? 'No conflict -- this can be placed directly.'
                : 'Found a fix: ' . count($steps) . ' lesson(s) need to move first.',
            'steps' => array_map(fn ($step) => $this->describeStep($step), $steps),
            'final_placement' => $this->describePlacement($newPlacement),
        ];
    }

    /**
     * Re-validates and applies a chain previously returned by
     * previewChainFix() -- never trusts the preview itself, since another
     * admin could have changed something in the meantime. $steps must be
     * in EXECUTION order (deepest move first, exactly as previewChainFix()
     * returns them): each step's target period is vacated by the
     * PRECEDING step (or was already free), so applying them in that
     * order is naturally collision-free -- unlike a swap, a chain never
     * needs the archived-parking trick, because no two rows ever trade
     * places simultaneously.
     *
     * @param array $newPlacement Same shape as passed to previewChainFix().
     * @param array<int, array{slot_id: int, to_bell_timing_id: int}> $steps
     * @return array{applied: bool, message: string}
     */
    public function applyChainFix(array $newPlacement, array $steps): array
    {
        $rootBellTimingId = (int) ($newPlacement['bell_timing_id'] ?? 0);
        if (!$rootBellTimingId) {
            return ['applied' => false, 'message' => 'No target period given.'];
        }

        $slotIds = array_map(fn ($step) => (int) $step['slot_id'], $steps);
        if (count($slotIds) !== count(array_unique($slotIds))) {
            return ['applied' => false, 'message' => 'This fix is no longer valid -- it referenced the same lesson twice.'];
        }

        $allTargets = array_merge(array_map(fn ($step) => (int) $step['to_bell_timing_id'], $steps), [$rootBellTimingId]);
        if (count($allTargets) !== count(array_unique($allTargets))) {
            return ['applied' => false, 'message' => 'This fix is no longer valid -- two lessons in the chain would land on the same period.'];
        }

        return DB::transaction(function () use ($newPlacement, $steps, $slotIds, $rootBellTimingId) {
            // Lock every row this chain touches, in a fixed ascending-id
            // order, so two concurrent Auto-Fix runs sharing a row can
            // never deadlock against each other -- same reasoning as
            // TimetableSwapService::apply().
            sort($slotIds);
            $slots = TimetableSlot::whereIn('id', $slotIds)->lockForUpdate()->get()->keyBy('id');

            if ($slots->count() !== count($steps)) {
                return ['applied' => false, 'message' => 'This fix is no longer valid -- one or more of the lessons it planned to move no longer exist.'];
            }

            foreach ($slots as $slot) {
                if ($slot->combined_class_group_id) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- a combined-group lesson is now in the way and can\'t be auto-moved.'];
                }
                if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- one of the lessons it planned to move is now archived history.'];
                }
                if ($slot->is_locked) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- one of the lessons it planned to move has since been locked.'];
                }
            }

            // Re-validate every step against LIVE data, each ignoring
            // every OTHER slot in the chain (they're all moving in this
            // same transaction, so none of their current positions should
            // count as a real collision against each other).
            foreach ($steps as $step) {
                $slot = $slots->get((int) $step['slot_id']);

                $trial = [
                    'school_class_id' => $slot->school_class_id,
                    'section_id' => $slot->section_id,
                    'teacher_id' => $slot->teacher_id,
                    'co_teacher_id' => $slot->co_teacher_id,
                    'subject_id' => $slot->subject_id,
                    'room_number' => $slot->room_number,
                    'status' => $slot->status,
                    'academic_year' => $slot->academic_year,
                    'bell_timing_id' => (int) $step['to_bell_timing_id'],
                    'ignore_slot_id' => $slotIds,
                ];

                $check = $this->resolver->check($trial);
                if ($check['conflict']) {
                    return ['applied' => false, 'message' => "This fix is no longer valid -- moving one of the lessons would now conflict: {$check['message']}"];
                }
            }

            $newPlacementCheck = $this->resolver->check(array_merge($newPlacement, ['ignore_slot_id' => $slotIds]));
            if ($newPlacementCheck['conflict']) {
                return ['applied' => false, 'message' => "This fix is no longer valid -- your lesson would still conflict: {$newPlacementCheck['message']}"];
            }

            $before = [];
            foreach ($steps as $step) {
                $slot = $slots->get((int) $step['slot_id']);
                $before[$slot->id] = $slot->bell_timing_id;
                $slot->update(['bell_timing_id' => (int) $step['to_bell_timing_id']]);
            }

            // Issue #14: see the identical guard in applyBlockerRelocation()
            // above -- carries timetable_generation_id through only when the
            // caller actually supplied one, so a chain-fixed placement stays
            // linked to the generation it was resolving an unplaced lesson
            // for (publishGeneration()'s promote sweep is generation-scoped),
            // without ever inventing a value or risking clobbering an
            // existing row's generation_id on the update-path match.
            $newPlacementValues = $newPlacement;
            if (array_key_exists('timetable_generation_id', $newPlacement)) {
                $newPlacementValues['timetable_generation_id'] = $newPlacement['timetable_generation_id'];
            }

            $newSlot = TimetableSlot::updateOrCreate(
                [
                    'school_class_id' => $newPlacement['school_class_id'],
                    'section_id' => $newPlacement['section_id'] ?? null,
                    'bell_timing_id' => $rootBellTimingId,
                    'status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED,
                ],
                array_merge($newPlacementValues, ['status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED])
            );

            foreach ($steps as $step) {
                $slot = $slots->get((int) $step['slot_id']);
                activity()->causedBy(Auth::user())->performedOn($slot)
                    ->withProperties([
                        'moved_from_bell_timing_id' => $before[$slot->id],
                        'moved_to_bell_timing_id' => $step['to_bell_timing_id'],
                        'chain_length' => count($steps),
                        'freed_for' => $newPlacement,
                    ])
                    ->log('timetable_autofix_chain_applied');
            }

            activity()->causedBy(Auth::user())->performedOn($newSlot)
                ->withProperties(['chain_length' => count($steps), 'moved_slot_ids' => $slotIds])
                ->log('timetable_autofix_chain_placed');

            return [
                'applied' => true,
                'message' => empty($steps)
                    ? 'Lesson scheduled -- no other lessons needed to move.'
                    : 'Fix applied -- ' . count($steps) . ' lesson(s) moved and your lesson is now scheduled.',
            ];
        });
    }

    /**
     * Recursive, budgeted, greedy search: is $placement (at its given
     * bell_timing_id) already clean, or can it be MADE clean by relocating
     * whatever blocks it (and, if that in turn is blocked, whatever
     * blocks THAT, up to $depth levels)? Returns the list of moves needed
     * in EXECUTION order (deepest first), an empty array if nothing needs
     * to move, or null if no fix was found (either genuinely impossible
     * within $depth, or the search ran out of budget).
     *
     * $movedSlotIds and $claimedBellTimingIds are threaded by reference
     * and mutated as the search commits to and backtracks from candidate
     * moves -- every check() call ignores every slot already committed to
     * move in this attempt (their old positions don't count as real
     * occupants once they've been relocated), and every candidate search
     * skips every bell_timing_id already claimed by an earlier step in
     * the SAME chain (so two steps never target the same period).
     */
    private function discoverChain(array $placement, int $depth, array &$movedSlotIds, array &$claimedBellTimingIds, int &$budget): ?array
    {
        // Room safety pilot-completion pass: $placement can be blocked by
        // more than one UNRELATED occupant at once (e.g. one lesson blocks
        // it on teacher, a completely different lesson blocks it on room) --
        // resolving the first blocker found is not enough on its own, so
        // this loops, re-checking $placement after each successful
        // relocation, until it's genuinely clean or nothing more can be
        // moved. Previously this returned as soon as ONE blocker was
        // resolved, regardless of whether $placement was actually legal
        // yet -- previewChainFix() could report "found a fix" while a
        // second, untouched blocker (of a different conflict type) still
        // occupied the exact target period. applyChainFix() itself already
        // independently re-validates the complete placement before writing
        // anything (line ~274), so this never actually corrupted data --
        // only the preview was misleadingly optimistic.
        $allSteps = [];

        while (true) {
            if ($budget <= 0) {
                return null;
            }
            $budget--;

            $check = $this->resolver->check(array_merge($placement, ['ignore_slot_id' => $movedSlotIds]));
            if (!$check['conflict']) {
                return $allSteps;
            }

            if ($depth <= 0) {
                return null;
            }

            $blockerId = null;
            foreach ($check['conflicts'] as $conflict) {
                if (!empty($conflict['blocking_slot_id']) && !in_array((int) $conflict['blocking_slot_id'], $movedSlotIds, true)) {
                    $blockerId = (int) $conflict['blocking_slot_id'];
                    break;
                }
            }

            if (!$blockerId) {
                return null; // constraint-only violation (load limit, subject-per-day, period type) -- no row to move.
            }

            $blocker = TimetableSlot::find($blockerId);
            // Phase 5 (Locked Lessons): a locked slot is never a candidate to
            // relocate -- the search simply treats it as an immovable wall and
            // reports "no fix found" if nothing else can be moved instead,
            // exactly like a combined-group or archived row already does.
            if (!$blocker || $blocker->combined_class_group_id || $blocker->status === TimetableSlot::STATUS_ARCHIVED || $blocker->is_locked) {
                return null;
            }

            $movedSlotIds[] = $blockerId;

            $blockerPlacement = [
                'school_class_id' => $blocker->school_class_id,
                'section_id' => $blocker->section_id,
                'teacher_id' => $blocker->teacher_id,
                'co_teacher_id' => $blocker->co_teacher_id,
                'subject_id' => $blocker->subject_id,
                'room_number' => $blocker->room_number,
                'status' => $blocker->status,
                'academic_year' => $blocker->academic_year,
            ];

            $resolvedThisBlocker = false;

            foreach ($this->suggestions->candidatePeriodsFor($blockerPlacement) as $candidate) {
                if ($budget <= 0) {
                    break;
                }
                $candidateId = (int) $candidate->id;
                if ($candidateId === (int) $blocker->bell_timing_id || in_array($candidateId, $claimedBellTimingIds, true)) {
                    continue;
                }

                $claimedBellTimingIds[] = $candidateId;

                $trial = array_merge($blockerPlacement, ['bell_timing_id' => $candidateId]);
                $subMoves = $this->discoverChain($trial, $depth - 1, $movedSlotIds, $claimedBellTimingIds, $budget);

                if ($subMoves !== null) {
                    $subMoves[] = ['slot_id' => $blockerId, 'to_bell_timing_id' => $candidateId, 'from_bell_timing_id' => (int) $blocker->bell_timing_id];
                    $allSteps = array_merge($allSteps, $subMoves);
                    $resolvedThisBlocker = true;
                    break;
                }

                array_pop($claimedBellTimingIds); // backtrack: this candidate didn't pan out.
            }

            if (!$resolvedThisBlocker) {
                // No candidate worked for this blocker within budget/depth -- back out of committing to move it.
                array_splice($movedSlotIds, array_search($blockerId, $movedSlotIds, true), 1);

                return null;
            }

            // This blocker is resolved -- loop back and re-check $placement:
            // it may now be clean, or another, still-unrelated blocker may
            // remain (e.g. the room conflict in the scenario above).
        }
    }

    private function describeStep(array $step): array
    {
        $slot = TimetableSlot::with(['subject', 'teacher', 'schoolClass', 'section', 'bellTiming'])->find($step['slot_id']);
        $toTiming = BellTiming::find($step['to_bell_timing_id']);

        return [
            'slot_id' => $step['slot_id'],
            'to_bell_timing_id' => $step['to_bell_timing_id'],
            'class' => $slot?->schoolClass?->name,
            'section' => $slot?->section?->name,
            'subject' => $slot?->subject?->name,
            'teacher' => $slot?->teacher?->name,
            'description' => sprintf(
                'Move %s (%s) from %s %s to %s %s',
                $slot?->subject?->name ?? 'this lesson',
                $slot?->teacher?->name ?? '',
                $slot?->bellTiming?->day_of_week ?? '?',
                $slot?->bellTiming?->period_name ?? '?',
                $toTiming?->day_of_week ?? '?',
                $toTiming?->period_name ?? '?'
            ),
        ];
    }

    private function describePlacement(array $placement): array
    {
        $timing = BellTiming::find($placement['bell_timing_id'] ?? null);
        $subject = Subject::find($placement['subject_id'] ?? null);
        $teacher = Teacher::find($placement['teacher_id'] ?? null);

        return [
            'bell_timing_id' => $placement['bell_timing_id'] ?? null,
            'day' => $timing?->day_of_week,
            'period' => $timing?->period_name,
            'subject' => $subject?->name,
            'teacher' => $teacher?->name,
        ];
    }
}
