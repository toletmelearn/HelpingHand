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
 * (BellTimingController::destroy(), BellTimingTemplateService's Replace/
 * Customize/Copy-Matching excess-row deletion, and any future bulk
 * delete) must check the same thing here, once, instead of each
 * maintaining its own slightly different query.
 *
 * Deliberately pure: no HTTP, redirect, flash, or exception-throwing
 * behavior lives here -- callers decide how to react to a block (a
 * friendly redirect for the controller, a RuntimeException for the
 * Template Apply transaction, etc).
 */
class BellTimingDependencyChecker
{
    /**
     * @param int|array<int>|\Illuminate\Support\Collection<int, int> $bellTimingIds one or more bell_timings.id values
     * @return array{
     *     timetable_slots_total: int,
     *     timetable_slots_published: int,
     *     timetable_slots_other: int,
     *     teacher_substitutions: int,
     *     teacher_availabilities: int,
     * }
     */
    public function check($bellTimingIds): array
    {
        $ids = collect($bellTimingIds)->flatten()->filter()->unique()->values()->all();

        if (empty($ids)) {
            return $this->emptyResult();
        }

        // One whereIn query per table regardless of how many ids were
        // passed in -- avoids N+1 whether the caller is checking a single
        // record (the controller) or a whole batch of excess ids at once
        // (Template Apply's Replace/Customize/Copy-Matching deletion).
        $slots = TimetableSlot::whereIn('bell_timing_id', $ids)->get(['status']);
        $publishedSlots = $slots->where('status', TimetableSlot::STATUS_PUBLISHED)->count();

        return [
            'timetable_slots_total' => $slots->count(),
            'timetable_slots_published' => $publishedSlots,
            'timetable_slots_other' => $slots->count() - $publishedSlots,
            'teacher_substitutions' => TeacherSubstitution::whereIn('bell_timing_id', $ids)->count(),
            'teacher_availabilities' => TeacherAvailability::whereIn('bell_timing_id', $ids)->count(),
        ];
    }

    /**
     * Same three queries as check(), but keyed per id instead of collapsed
     * into one aggregate -- needed wherever a caller must show or act on
     * per-record blocking (e.g. Bulk Delete's preview: "these 18 are safe,
     * these 3 are blocked, here's exactly which ones and why"). Still
     * exactly one query per table regardless of how many ids are passed,
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
     * Per-record detail (not just counts) for every dependency on the given
     * Bell Timing ids -- powers the dependency-resolution screen, where an
     * admin needs to see WHICH class/subject/teacher/date each blocking
     * record actually is, and whether it can even be reassigned, not just
     * "1 draft/archived timetable slot". Read-only -- same N+1-safe,
     * one-query-per-table shape as checkEach(), just with relations eager
     * loaded instead of collapsed into a count. Does not change what
     * check()/checkEach()/isBlocked()/summarize() return or how any
     * existing caller (destroy(), Bulk Delete, Bulk Edit's warnings,
     * Template Replace) behaves -- purely additive.
     *
     * @param array<int> $bellTimingIds
     * @return array<int, array{
     *     timetable_slots: array<int, array{id: int, status: string, is_locked: bool, reassignable: bool, class_name: ?string, section_name: ?string, subject_name: ?string, teacher_name: ?string, co_teacher_name: ?string}>,
     *     teacher_substitutions: array<int, array{id: int, status: ?string, substitution_date: ?string, absent_teacher_name: ?string, class_name: ?string, section_name: ?string, subject_name: ?string}>,
     *     teacher_availabilities: array<int, array{id: int, teacher_id: int, teacher_name: ?string}>,
     * }> keyed by bell_timing_id
     */
    public function describe(array $bellTimingIds): array
    {
        $ids = collect($bellTimingIds)->flatten()->filter()->unique()->values()->all();

        if (empty($ids)) {
            return [];
        }

        $slotsByBellTiming = TimetableSlot::with(['schoolClass', 'section', 'subject', 'teacher', 'coTeacher'])
            ->whereIn('bell_timing_id', $ids)
            ->get()
            ->groupBy('bell_timing_id');

        $substitutionsByBellTiming = TeacherSubstitution::with(['absentTeacher', 'class', 'section', 'subject'])
            ->whereIn('bell_timing_id', $ids)
            ->get()
            ->groupBy('bell_timing_id');

        $availabilitiesByBellTiming = TeacherAvailability::with('teacher')
            ->whereIn('bell_timing_id', $ids)
            ->get()
            ->groupBy('bell_timing_id');

        $result = [];

        foreach ($ids as $id) {
            $slots = $slotsByBellTiming->get($id, collect())->map(function (TimetableSlot $slot) {
                return [
                    'id' => $slot->id,
                    'status' => $slot->status,
                    'is_locked' => (bool) $slot->is_locked,
                    // Archived slots can never be edited at all --
                    // Admin\TimetableController::update() refuses them
                    // outright -- and a locked slot must be unlocked
                    // elsewhere first. The view uses this flag to decide
                    // whether to show a Reassign link, rather than
                    // re-deriving (and potentially getting wrong) the same
                    // rule from status/is_locked itself.
                    'reassignable' => $slot->status !== TimetableSlot::STATUS_ARCHIVED && ! $slot->is_locked,
                    'class_name' => optional($slot->schoolClass)->name,
                    'section_name' => optional($slot->section)->name,
                    'subject_name' => optional($slot->subject)->name,
                    'teacher_name' => optional($slot->teacher)->name,
                    'co_teacher_name' => optional($slot->coTeacher)->name,
                ];
            })->values()->all();

            $substitutions = $substitutionsByBellTiming->get($id, collect())->map(function (TeacherSubstitution $sub) {
                return [
                    'id' => $sub->id,
                    'status' => $sub->status,
                    'substitution_date' => $sub->substitution_date?->toDateString(),
                    'absent_teacher_name' => optional($sub->absentTeacher)->name,
                    'class_name' => optional($sub->class)->name,
                    'section_name' => optional($sub->section)->name,
                    'subject_name' => optional($sub->subject)->name,
                ];
            })->values()->all();

            $availabilities = $availabilitiesByBellTiming->get($id, collect())->map(function (TeacherAvailability $avail) {
                return [
                    'id' => $avail->id,
                    // Phase B: TeacherAvailability has no safe single-record
                    // reassign path (its update() endpoint resyncs a
                    // teacher's WHOLE blocked-period grid from a submitted
                    // "desired state" array, not one row -- reassigning
                    // just this row risks clobbering an unrelated change
                    // made to the same grid in between). teacher_id is
                    // exposed here only so the view can link out to that
                    // teacher's own grid for a manual fix, never to build a
                    // single-row write path around it.
                    'teacher_id' => $avail->teacher_id,
                    'teacher_name' => optional($avail->teacher)->name,
                ];
            })->values()->all();

            $result[$id] = [
                'timetable_slots' => $slots,
                'teacher_substitutions' => $substitutions,
                'teacher_availabilities' => $availabilities,
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

    private function emptyResult(): array
    {
        return [
            'timetable_slots_total' => 0,
            'timetable_slots_published' => 0,
            'timetable_slots_other' => 0,
            'teacher_substitutions' => 0,
            'teacher_availabilities' => 0,
        ];
    }
}
