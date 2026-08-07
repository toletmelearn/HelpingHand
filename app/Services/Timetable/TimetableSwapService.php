<?php

namespace App\Services\Timetable;

use App\Models\TimetableSlot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Swap Engine: exchanges the periods of two ALREADY-PLACED lessons.
 *
 * "Swap" means exactly one thing in this schema: trade bell_timing_id
 * between the two existing rows. Everything else each row already owns
 * (class, section, subject, teacher, co-teacher, room, status,
 * academic_year, combined-group membership) stays exactly as it was --
 * same row identity, same activity history, same relationships. This is
 * the only interpretation consistent with "preserve slot IDs, never
 * delete+recreate" and with TimetableSuggestionService::checkSwap()'s own
 * existing (already-tested) validation, which this class is the first
 * caller to actually apply.
 *
 * Validation is NOT reimplemented here -- checkSwap() already builds the
 * correct combined proposed post-swap state (both sides' NEW placement,
 * each ignoring both original rows) and runs it through the one
 * authoritative TimetableConflictResolver. This class adds only what
 * checkSwap() deliberately doesn't own: the swap-specific guardrails
 * (self-swap, combined-group, archived, cross-status, cross-academic-year)
 * and the actual atomic write + activity log.
 */
class TimetableSwapService
{
    private TimetableSuggestionService $suggestions;

    public function __construct(?TimetableSuggestionService $suggestions = null)
    {
        $this->suggestions = $suggestions ?? new TimetableSuggestionService();
    }

    /**
     * Read-only: validates the swap without writing anything. Used by the
     * preview step so the UI can show conflicts before the user commits.
     *
     * @return array{ok: bool, message: ?string, conflicts: array, slot_a: ?TimetableSlot, slot_b: ?TimetableSlot, a_result: ?array, b_result: ?array}
     */
    public function preview(int $slotIdA, int $slotIdB): array
    {
        $guard = $this->guardAgainstInvalidPair($slotIdA, $slotIdB);
        if ($guard !== null) {
            return $guard;
        }

        $a = TimetableSlot::with(['subject', 'teacher', 'coTeacher', 'schoolClass', 'section', 'bellTiming'])->find($slotIdA);
        $b = TimetableSlot::with(['subject', 'teacher', 'coTeacher', 'schoolClass', 'section', 'bellTiming'])->find($slotIdB);

        $ineligible = $this->rejectIfIneligible($a, $b);
        if ($ineligible !== null) {
            return $ineligible;
        }

        $swapCheck = $this->suggestions->checkSwap($a->id, $b->id);

        return [
            'ok' => $swapCheck['ok'],
            'message' => $swapCheck['ok'] ? null : 'Swap cannot be completed.',
            'conflicts' => array_merge($swapCheck['a_result']['conflicts'] ?? [], $swapCheck['b_result']['conflicts'] ?? []),
            'slot_a' => $a,
            'slot_b' => $b,
            'a_result' => $swapCheck['a_result'],
            'b_result' => $swapCheck['b_result'],
        ];
    }

    /**
     * Applies the swap. Re-validates from scratch against live, row-locked
     * data immediately before writing -- never trusts a caller's earlier
     * preview(), since another admin could have changed either slot in
     * between. Fully atomic: either both rows are updated and both
     * activity-log entries are written, or nothing happens at all.
     *
     * @return array{applied: bool, message: string, conflicts: array, slot_a: ?TimetableSlot, slot_b: ?TimetableSlot}
     */
    public function apply(int $slotIdA, int $slotIdB): array
    {
        $guard = $this->guardAgainstInvalidPair($slotIdA, $slotIdB);
        if ($guard !== null) {
            return ['applied' => false, 'message' => $guard['message'], 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        return DB::transaction(function () use ($slotIdA, $slotIdB) {
            // Lock in a fixed order (ascending id) regardless of which the
            // caller labelled A/B, so two concurrent swaps that share one
            // row can never lock it in opposite orders and deadlock each
            // other -- one simply waits for the other's transaction to
            // finish, then re-validates against whatever that left behind.
            [$lowId, $highId] = $slotIdA < $slotIdB ? [$slotIdA, $slotIdB] : [$slotIdB, $slotIdA];
            $low = TimetableSlot::lockForUpdate()->find($lowId);
            $high = TimetableSlot::lockForUpdate()->find($highId);

            $a = $slotIdA === $lowId ? $low : $high;
            $b = $slotIdA === $lowId ? $high : $low;

            $ineligible = $this->rejectIfIneligible($a, $b);
            if ($ineligible !== null) {
                return ['applied' => false, 'message' => $ineligible['message'], 'conflicts' => $ineligible['conflicts'], 'slot_a' => null, 'slot_b' => null];
            }

            $swapCheck = $this->suggestions->checkSwap($a->id, $b->id);

            if (!$swapCheck['ok']) {
                return [
                    'applied' => false,
                    'message' => 'Swap cannot be completed.',
                    'conflicts' => array_merge($swapCheck['a_result']['conflicts'] ?? [], $swapCheck['b_result']['conflicts'] ?? []),
                    'slot_a' => null,
                    'slot_b' => null,
                ];
            }

            $before = [
                'a' => $a->only(['id', 'bell_timing_id']),
                'b' => $b->only(['id', 'bell_timing_id']),
            ];

            $aOriginalStatus = $a->status;
            $aOriginalTiming = $a->bell_timing_id;
            $bOriginalTiming = $b->bell_timing_id;

            // The DB's own unique indexes (teacher_active_key,
            // class_bell_active_key) check every UPDATE individually --
            // they have no concept of "these two rows are trading places,"
            // unlike TimetableConflictResolver's application-level
            // ignore_slot_id. Two sequential updates (A -> B's old period,
            // then B -> A's old period) would transiently put A at B's old
            // period WHILE B is still sitting there too, violating the
            // constraint even though the FINAL state is perfectly valid --
            // this bites any swap where the two lessons share a teacher,
            // class, or room. The schema already has a documented escape
            // hatch for exactly this: an 'archived' row's active-key
            // columns are NULL, so it's excluded from both unique indexes
            // entirely (see the migration that added them). Parking A
            // there for one statement, entirely inside this transaction
            // and invisible to any other connection, removes the
            // transient collision without touching FKs or inventing a
            // fake sentinel value.
            $a->update(['status' => 'archived']);
            $b->update(['bell_timing_id' => $aOriginalTiming]);
            $a->update(['status' => $aOriginalStatus, 'bell_timing_id' => $bOriginalTiming]);

            $after = [
                'a' => $a->only(['id', 'bell_timing_id']),
                'b' => $b->only(['id', 'bell_timing_id']),
            ];

            activity()->causedBy(Auth::user())->performedOn($a)
                ->withProperties(['before' => $before, 'after' => $after, 'swapped_with_slot_id' => $b->id])
                ->log('timetable_slot_swapped');

            activity()->causedBy(Auth::user())->performedOn($b)
                ->withProperties(['before' => $before, 'after' => $after, 'swapped_with_slot_id' => $a->id])
                ->log('timetable_slot_swapped');

            return ['applied' => true, 'message' => 'Lessons swapped.', 'conflicts' => [], 'slot_a' => $a->fresh(), 'slot_b' => $b->fresh()];
        });
    }

    /** Cheap, pre-query checks that don't need the rows loaded yet. */
    private function guardAgainstInvalidPair(int $slotIdA, int $slotIdB): ?array
    {
        if ($slotIdA === $slotIdB) {
            return ['ok' => false, 'applied' => false, 'message' => 'Cannot swap a lesson with itself.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null, 'a_result' => null, 'b_result' => null];
        }

        return null;
    }

    /**
     * Guardrails checkSwap() deliberately doesn't own -- mirrors
     * TimetableController::update()'s existing combined-group/archived
     * rules exactly (same messages, same reasoning), plus two swap-only
     * additions: two rows only make sense to trade periods if they're in
     * the same lifecycle bucket (both draft or both published -- swapping
     * a live lesson's period with an unrelated draft proposal is not a
     * real operation), and never across academic years.
     */
    private function rejectIfIneligible(?TimetableSlot $a, ?TimetableSlot $b): ?array
    {
        if (!$a || !$b) {
            return ['ok' => false, 'message' => 'One or both lessons no longer exist -- someone may have already changed them.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        foreach (['a' => $a, 'b' => $b] as $slot) {
            if ($slot->combined_class_group_id) {
                return ['ok' => false, 'message' => 'This is a combined-group lesson -- it can\'t be swapped from a single cell. Clear it and re-place it via Combined Groups instead.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
            }

            if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
                return ['ok' => false, 'message' => 'This slot is archived history from a past publish -- it can no longer be swapped.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
            }
        }

        if ($a->status !== $b->status) {
            return ['ok' => false, 'message' => 'Cannot swap a published lesson with a draft lesson -- they belong to different timetable states.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        if ($a->academic_year !== $b->academic_year) {
            return ['ok' => false, 'message' => 'Cannot swap lessons from different academic years.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        return null;
    }
}
