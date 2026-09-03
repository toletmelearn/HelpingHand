<?php

namespace App\Services;

use App\Models\Room;
use Carbon\Carbon;

/**
 * Priority 1.3: a teacher scheduled in two different buildings with too
 * little gap between periods is a physically impossible placement --
 * nothing previously checked this at all (room conflicts only ever caught
 * the SAME room double-booked, never two DIFFERENT rooms too far apart to
 * reach in time). Deliberately separate from TimetableConflictResolver's
 * own room/teacher overlap checks: those apply to the SAME period,
 * this applies to two DIFFERENT (non-overlapping) periods for the same
 * person on the same day.
 */
class TransferTimeValidator
{
    /**
     * @param array{room_number: ?string, start: Carbon, end: Carbon} $slotA
     * @param array{room_number: ?string, start: Carbon, end: Carbon} $slotB
     * @return array{conflict: bool, message: ?string}
     */
    public function validateTransferTime(int $teacherId, array $slotA, array $slotB): array
    {
        if (empty($slotA['room_number']) || empty($slotB['room_number'])) {
            return ['conflict' => false, 'message' => null];
        }

        // Order by start time so the gap is always (later.start - earlier.end).
        [$earlier, $later] = $slotA['start']->lte($slotB['start']) ? [$slotA, $slotB] : [$slotB, $slotA];

        // Overlapping periods are TimetableConflictResolver's own
        // teacher-overlap conflict, not a transfer-time one.
        if ($later['start']->lt($earlier['end'])) {
            return ['conflict' => false, 'message' => null];
        }

        $roomA = Room::where('room_number', $earlier['room_number'])->first();
        $roomB = Room::where('room_number', $later['room_number'])->first();

        // A room not yet mapped to a building can't be judged -- treated as
        // "can't determine, don't block", never a false positive.
        if (!$roomA?->building_id || !$roomB?->building_id) {
            return ['conflict' => false, 'message' => null];
        }

        if ($roomA->building_id === $roomB->building_id) {
            return ['conflict' => false, 'message' => null];
        }

        $requiredMinutes = $roomA->building->transferTimeTo($roomB->building_id);
        $gapMinutes = $earlier['end']->diffInMinutes($later['start']);

        if ($gapMinutes >= $requiredMinutes) {
            return ['conflict' => false, 'message' => null];
        }

        return [
            'conflict' => true,
            'message' => "Only {$gapMinutes} minute(s) to get from {$roomA->building->name} to {$roomB->building->name} -- needs at least {$requiredMinutes} minute(s) to travel.",
        ];
    }
}
