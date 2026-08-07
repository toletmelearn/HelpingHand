<?php

namespace App\Services\Timetable;

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
 */
class TimetableAutoFixService
{
    private TimetableConflictResolver $resolver;

    public function __construct(?TimetableConflictResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new TimetableConflictResolver();
    }

    /**
     * @param array $newPlacement Same shape TimetableConflictResolver::check() takes -- the lesson that was blocked.
     * @return array{applied: bool, message: string}
     */
    public function applyBlockerRelocation(array $newPlacement, int $blockingSlotId, int $blockerNewBellTimingId): array
    {
        $blocker = TimetableSlot::find($blockingSlotId);
        if (!$blocker) {
            return ['applied' => false, 'message' => 'The lesson this fix was supposed to move no longer exists -- someone may have already changed it.'];
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
        // lesson's own check must ignore the blocker's CURRENT row, since
        // that's precisely the occupant this fix is about to relocate.
        $newPlacementCheck = $this->resolver->check(array_merge($newPlacement, ['ignore_slot_id' => $blocker->id]));
        if ($newPlacementCheck['conflict']) {
            return ['applied' => false, 'message' => "This fix is no longer valid -- your lesson would still conflict: {$newPlacementCheck['message']}"];
        }

        DB::transaction(function () use ($blocker, $blockerNewBellTimingId, $newPlacement) {
            $originalBellTimingId = $blocker->bell_timing_id;

            $blocker->update(['bell_timing_id' => $blockerNewBellTimingId]);

            TimetableSlot::updateOrCreate(
                [
                    'school_class_id' => $newPlacement['school_class_id'],
                    'section_id' => $newPlacement['section_id'] ?? null,
                    'bell_timing_id' => $newPlacement['bell_timing_id'],
                    'status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED,
                ],
                array_merge($newPlacement, ['status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED])
            );

            activity()->causedBy(Auth::user())->performedOn($blocker)
                ->withProperties([
                    'moved_from_bell_timing_id' => $originalBellTimingId,
                    'moved_to_bell_timing_id' => $blockerNewBellTimingId,
                    'freed_for' => $newPlacement,
                ])
                ->log('timetable_autofix_applied');
        });

        return ['applied' => true, 'message' => 'Fix applied -- the other lesson was moved and your lesson is now scheduled.'];
    }
}
