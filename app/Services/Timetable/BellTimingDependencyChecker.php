<?php

namespace App\Services\Timetable;

use App\Models\TeacherAvailability;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;

/**
 * Single source of truth for "what depends on this BellTiming row (or
 * rows)?" -- three tables have a foreign key on bell_timings.id, with two
 * different delete behaviors:
 *   - timetable_slots.bell_timing_id       ON DELETE CASCADE
 *   - teacher_availabilities.bell_timing_id ON DELETE CASCADE
 *   - teacher_substitutions.bell_timing_id  RESTRICT (no cascade specified)
 * An unguarded delete() can therefore either silently destroy a published
 * timetable slot / availability block, or throw a raw, unhandled
 * QueryException. Every place in the app that deletes BellTiming rows
 * must check the same thing here, once, instead of each maintaining its
 * own slightly different query -- starting with Bulk Delete, which needs
 * the per-id breakdown checkEach() provides.
 *
 * Deliberately pure: no HTTP, redirect, flash, or exception-throwing
 * behavior lives here -- callers decide how to react to a block (a
 * friendly redirect for the controller, etc).
 */
class BellTimingDependencyChecker
{
    /**
     * Checks a batch of BellTiming ids against all three dependency
     * tables, keyed per id -- needed wherever a caller must show or act
     * on per-record blocking (e.g. Bulk Delete's preview: "these 18 are
     * safe, these 3 are blocked, here's exactly which ones and why").
     * Exactly one query per table regardless of how many ids are passed,
     * so a caller checking a whole batch never causes N+1.
     *
     * @param array<int> $bellTimingIds
     * @return array<int, array{
     *     timetable_slots_total: int,
     *     timetable_slots_published: int,
     *     timetable_slots_other: int,
     *     teacher_substitutions: int,
     *     teacher_availabilities: int,
     * }> keyed by bell_timing_id
     */
    public function checkEach(array $bellTimingIds): array
    {
        $ids = collect($bellTimingIds)->flatten()->filter()->unique()->values()->all();

        if (empty($ids)) {
            return [];
        }

        $slotsByBellTiming = TimetableSlot::whereIn('bell_timing_id', $ids)
            ->get(['bell_timing_id', 'status'])
            ->groupBy('bell_timing_id');
        $substitutionsByBellTiming = TeacherSubstitution::whereIn('bell_timing_id', $ids)
            ->get(['bell_timing_id'])
            ->groupBy('bell_timing_id');
        $availabilitiesByBellTiming = TeacherAvailability::whereIn('bell_timing_id', $ids)
            ->get(['bell_timing_id'])
            ->groupBy('bell_timing_id');

        $result = [];

        foreach ($ids as $id) {
            $slots = $slotsByBellTiming->get($id, collect());
            $published = $slots->where('status', TimetableSlot::STATUS_PUBLISHED)->count();

            $result[$id] = [
                'timetable_slots_total' => $slots->count(),
                'timetable_slots_published' => $published,
                'timetable_slots_other' => $slots->count() - $published,
                'teacher_substitutions' => $substitutionsByBellTiming->get($id, collect())->count(),
                'teacher_availabilities' => $availabilitiesByBellTiming->get($id, collect())->count(),
            ];
        }

        return $result;
    }

    /**
     * @param array{timetable_slots_total: int, teacher_substitutions: int, teacher_availabilities: int} $dependencies
     */
    public function isBlocked(array $dependencies): bool
    {
        return $dependencies['timetable_slots_total'] > 0
            || $dependencies['teacher_substitutions'] > 0
            || $dependencies['teacher_availabilities'] > 0;
    }

    /**
     * Human-readable "used by ..." list, e.g. "1 published timetable
     * slot, 1 teacher substitution" -- published and non-published
     * timetable slots are called out separately since a published slot
     * is a live, in-use schedule while a draft/archived one is not.
     */
    public function summarize(array $dependencies): string
    {
        $parts = [];

        if ($dependencies['timetable_slots_published'] > 0) {
            $n = $dependencies['timetable_slots_published'];
            $parts[] = "{$n} published timetable slot" . ($n === 1 ? '' : 's');
        }
        if ($dependencies['timetable_slots_other'] > 0) {
            $n = $dependencies['timetable_slots_other'];
            $parts[] = "{$n} draft/archived timetable slot" . ($n === 1 ? '' : 's');
        }
        if ($dependencies['teacher_substitutions'] > 0) {
            $n = $dependencies['teacher_substitutions'];
            $parts[] = "{$n} teacher substitution" . ($n === 1 ? '' : 's');
        }
        if ($dependencies['teacher_availabilities'] > 0) {
            $n = $dependencies['teacher_availabilities'];
            $parts[] = "{$n} teacher availability record" . ($n === 1 ? '' : 's');
        }

        return implode(', ', $parts);
    }
}
