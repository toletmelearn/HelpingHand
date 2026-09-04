<?php

namespace App\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use App\Services\TransferTimeValidator;
use Illuminate\Support\Collection;

/**
 * The single authoritative place that answers "can this lesson go here?"
 * for every INTERACTIVE, single-placement caller against real, persisted
 * TimetableSlot data: the manual grid editor, (future) drag-and-drop,
 * Auto-Fix, Rebalance, and the API. Before this existed, the same rules
 * were partially and inconsistently duplicated: TimetableController's own
 * checkSlotConflicts() only knew about teacher/room overlap (until this
 * session's repair pass added class/section overlap too), while teacher
 * availability, daily/weekly load caps, subject-per-day caps, and period
 * type were enforced ONLY inside GeneratorService's bulk solve -- meaning
 * a manual edit could silently violate rules the generator would never
 * have allowed.
 *
 * Deliberately NOT used by GeneratorService's own hot loop
 * (isHardLegal()): that method evaluates thousands of candidate slots
 * per lesson during backtracking against in-memory hash maps built once
 * per run (O(1) lookups) -- routing each candidate through this class's
 * per-call DB queries would be a severe performance regression for bulk
 * generation. The two are kept as separate implementations of the SAME
 * rules for two different access patterns (one candidate at a time
 * against live data, vs. thousands of candidates per second against an
 * in-memory snapshot) -- not a drifted duplicate needing a merge. If the
 * rules themselves ever change, both places must be updated; that's the
 * one seam this design accepts deliberately.
 */
class TimetableConflictResolver
{
    private const DEFAULT_MAX_PER_DAY = 7;

    /**
     * @param array{
     *   school_class_id?: int|string|null,
     *   section_id?: int|string|null,
     *   bell_timing_id?: int|string|null,
     *   teacher_id?: int|string|null,
     *   co_teacher_id?: int|string|null,
     *   subject_id?: int|string|null,
     *   room_number?: string|null,
     *   status?: string,
     *   academic_year?: string|null,
     *   ignore_slot_id?: int|string|array|null,
     * } $placement A proposed (not yet saved) slot. ignore_slot_id excludes
     *   that row (or, as an array, several rows -- e.g. both sides of a
     *   TimetableSuggestionService swap check) from every check -- editing
     *   a cell in place must never conflict with the very row(s) being
     *   replaced. Left unset, it's auto-resolved to whatever row already
     *   occupies this placement's own natural key, if any. academic_year
     *   left unset defaults to the current AcademicSession's code -- it
     *   scopes which bell timings and teacher_class_subject_assignment
     *   rows count as "real" (see overlappingBellTimingIds()), so a
     *   retired/other-year period or assignment (e.g. a school's own past
     *   test/walkthrough data) can never masquerade as a live collision.
     * @return array{conflict: bool, type: ?string, message: ?string, conflicts: array}
     *                                                                                  'conflict'/'type'/'message' mirror the first violation found, for
     *                                                                                  callers that only care whether the save should be blocked at all
     *                                                                                  (unchanged shape from the pre-existing checkSlotConflicts()).
     *                                                                                  'conflicts' holds EVERY violation found, for callers that need the
     *                                                                                  full picture (Auto-Fix ranking alternatives, Rebalance explaining
     *                                                                                  what broke, an API consumer building its own UI).
     */
    public function check(array $placement): array
    {
        $teacherId = $placement['teacher_id'] ?? null;
        $bellTimingId = $placement['bell_timing_id'] ?? null;

        if (! $teacherId || ! $bellTimingId) {
            return $this->result([]);
        }

        $bellTiming = BellTiming::find($bellTimingId);
        if (! $bellTiming) {
            return $this->result([]);
        }

        // The manual editor upserts by natural key (class, section,
        // bell_timing, status), not by row id -- resubmitting the exact
        // same cell (e.g. re-saving with a new room number) never sends an
        // id at all. Auto-resolving the row that occupies this placement's
        // own natural key -- if any -- and treating it as implicitly
        // ignored means every check below is naturally self-consistent:
        // "does this violate a constraint against some OTHER row" rather
        // than "does it violate a constraint against the row it's about to
        // replace." An explicitly passed ignore_slot_id always wins.
        $placement['ignore_slot_id'] = $placement['ignore_slot_id'] ?? $this->resolveSelfId($placement);

        // Which academic year "other periods that day" / "other periods
        // that overlap" should be drawn from -- defaults to whatever
        // session is current, matching the wizard's own
        // currentAcademicYear() pattern. Applied tolerantly (only when
        // known) to BellTiming/assignment queries below AND, as of the
        // Hardening pass, to the TimetableSlot queries in baseQuery(),
        // teacherLoadConflicts(), and subjectPerDayConflicts() too: every
        // current write path (store(), update(), storeCombined(),
        // GenerateTimetableJob) now stamps academic_year on every row it
        // creates, so a stale published slot left over from a prior year
        // (e.g. a class that wasn't regenerated when the school rolled into
        // a new session) no longer counts as "busy" against a same-period,
        // same-teacher placement in the CURRENT year -- it was only ever a
        // false-positive risk (blocking a legitimate edit), never a
        // false-negative one, since the filter only narrows an already
        // status-scoped match, but it's worth closing regardless. The
        // filter is "same year OR untagged" rather than a strict equals:
        // an untagged legacy row (academic_year null -- e.g. a slot
        // created directly, outside the app's own write paths, before
        // this stamping was consistent) still counts as a real occupant,
        // exactly as it always has; only a row explicitly tagged with a
        // DIFFERENT year is excluded.
        $academicYear = $placement['academic_year'] ?? AcademicSession::current()->first()?->code;

        $overlappingIds = $this->overlappingBellTimingIds($bellTiming, $academicYear);
        $sameDayIds = $this->sameDayBellTimingIds($bellTiming, $academicYear);

        $conflicts = [];
        $conflicts = array_merge($conflicts, $this->periodTypeConflicts($bellTiming));
        $conflicts = array_merge($conflicts, $this->teacherOverlapConflicts($placement, $overlappingIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->classSectionOverlapConflicts($placement, $overlappingIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->roomOverlapConflicts($placement, $overlappingIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->transferTimeConflicts($placement, $bellTiming, $sameDayIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->teacherAvailabilityConflicts($placement, $bellTiming));
        $conflicts = array_merge($conflicts, $this->teacherLoadConflicts($placement, $sameDayIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->subjectPerDayConflicts($placement, $bellTiming, $sameDayIds, $academicYear));

        return $this->result($conflicts);
    }

    /** The id of the existing row, if any, that occupies this exact (class, section, bell_timing, status) natural key -- the row updateOrCreate() would overwrite. */
    private function resolveSelfId(array $placement): ?int
    {
        if (empty($placement['school_class_id']) || empty($placement['bell_timing_id'])) {
            return null;
        }

        return TimetableSlot::where('school_class_id', $placement['school_class_id'])
            ->where('section_id', $placement['section_id'] ?? null)
            ->where('bell_timing_id', $placement['bell_timing_id'])
            ->where('status', $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED)
            ->value('id');
    }

    private function result(array $conflicts): array
    {
        $first = $conflicts[0] ?? null;

        return [
            'conflict' => ! empty($conflicts),
            'type' => $first['type'] ?? null,
            'message' => $first['message'] ?? null,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return Collection<int,int> every bell_timing_id on the same day
     *                             whose time range overlaps the target's. Scoped to active,
     *                             current-year bell timings -- a retired/other-year period sharing
     *                             the same day+time (e.g. leftover test-data timings) must never be
     *                             treated as a real collision target. is_active is the hard filter
     *                             (a deactivated timing is never real); academic_year is a tolerant
     *                             filter (only applied when one is known, so untagged legitimate
     *                             rows -- there are none in practice, see check() -- would still
     *                             never be silently hidden).
     */
    private function overlappingBellTimingIds(BellTiming $bellTiming, ?string $academicYear): Collection
    {
        $startTime = $bellTiming->start_time->format('H:i:s');
        $endTime = $bellTiming->end_time->format('H:i:s');

        return BellTiming::where('is_active', true)
            ->where('day_of_week', $bellTiming->day_of_week)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->pluck('id');
    }

    /** @return Collection<int,int> every active, current-year bell_timing_id on the same day -- see overlappingBellTimingIds() for the same-reasoning scoping. */
    private function sameDayBellTimingIds(BellTiming $bellTiming, ?string $academicYear): Collection
    {
        return BellTiming::where('is_active', true)
            ->where('day_of_week', $bellTiming->day_of_week)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->pluck('id');
    }

    private function baseQuery(array $placement, Collection $bellTimingIds, ?string $academicYear = null): \Illuminate\Database\Eloquent\Builder
    {
        $status = $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        return $this->applyIgnore(
            TimetableSlot::whereIn('bell_timing_id', $bellTimingIds)
                ->where('status', $status)
                ->when($academicYear, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('academic_year')->orWhere('academic_year', $academicYear))),
            $placement['ignore_slot_id'] ?? null
        );
    }

    /**
     * ignore_slot_id excludes rows the placement being checked would
     * itself replace (or, for TimetableSuggestionService's swap check,
     * BOTH rows trading periods) -- accepts a single id or an array.
     */
    private function applyIgnore(\Illuminate\Database\Eloquent\Builder $query, $ignore): \Illuminate\Database\Eloquent\Builder
    {
        if (empty($ignore)) {
            return $query;
        }

        return is_array($ignore) ? $query->whereNotIn('id', $ignore) : $query->where('id', '!=', $ignore);
    }

    /**
     * A teaching lesson cannot be placed into a non-teaching period
     * (break/assembly/prayer/zero/dispersal) unless the period type is
     * itself 'teaching'. The manual grid's own bell-timing dropdown was
     * never filtered to teaching periods (BellTiming::teachingType()) the
     * way the generator's candidate slots always are -- this closes that
     * gap centrally rather than relying on the UI to filter it out.
     */
    private function periodTypeConflicts(BellTiming $bellTiming): array
    {
        if ($bellTiming->period_type === BellTiming::PERIOD_TYPE_TEACHING) {
            return [];
        }

        return [[
            'type' => 'period_type',
            'message' => "This is a {$bellTiming->period_type} period, not a teaching period -- a lesson can't be scheduled here.",
        ]];
    }

    /**
     * Both people on this lesson (if a co-teacher is given) must be free
     * -- checked against BOTH the teacher_id and co_teacher_id columns on
     * every existing row, so a person already busy elsewhere as either
     * the primary teacher or someone else's co-teacher is caught (the
     * cross-case GeneratorService::isHardLegal() already enforces for
     * auto-generation; the DB's own co-teacher unique index only catches
     * co-teacher-vs-co-teacher).
     */
    private function teacherOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array
    {
        $conflicts = [];
        $people = array_filter([$placement['teacher_id'] ?? null, $placement['co_teacher_id'] ?? null]);

        foreach ($people as $personId) {
            $busy = $this->baseQuery($placement, $overlappingIds, $academicYear)
                ->where(fn ($q) => $q->where('teacher_id', $personId)->orWhere('co_teacher_id', $personId))
                ->with('schoolClass')
                ->first();

            if ($busy) {
                $personName = Teacher::find($personId)->name ?? 'This teacher';
                $conflicts[] = [
                    'type' => 'teacher',
                    'message' => "{$personName} is already scheduled to teach ".($busy->schoolClass->name ?? 'another class').' during this period.',
                    'blocking_slot_id' => $busy->id,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * The T1a unique DB index can't catch this cross-case: a class-wide
     * slot (section_id NULL) and a specific-section slot of the SAME
     * class have different section_id_norm values, so neither the index
     * nor an exact-match query sees them as colliding -- but a class-wide
     * lesson covers every section, so they genuinely overlap. Only the
     * CROSS case is flagged: a class-wide save conflicts only with an
     * existing section-specific row (never another class-wide row --
     * that's either this exact cell being updated in place, or a genuine
     * duplicate the DB unique index already blocks); a section-specific
     * save conflicts only with an existing class-wide row (never the same
     * section's own row -- that's an update-in-place, not a collision).
     */
    private function classSectionOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array
    {
        $schoolClassId = $placement['school_class_id'] ?? null;
        if (! $schoolClassId) {
            return [];
        }

        $sectionId = $placement['section_id'] ?? null;

        $existing = $this->baseQuery($placement, $overlappingIds, $academicYear)
            ->where('school_class_id', $schoolClassId)
            ->when($sectionId, fn ($q) => $q->whereNull('section_id'), fn ($q) => $q->whereNotNull('section_id'))
            ->with('section')
            ->first();

        if (! $existing) {
            return [];
        }

        $message = $existing->section_id === null
            ? 'This class already has a whole-class lesson scheduled during this period -- it applies to every section.'
            : 'Section '.($existing->section->name ?? '').' of this class already has a lesson scheduled during this period.';

        return [[
            'type' => 'class',
            'message' => $message,
            'blocking_slot_id' => $existing->id,
        ]];
    }

    private function roomOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array
    {
        $roomNumber = $placement['room_number'] ?? null;
        if (! $roomNumber) {
            return [];
        }

        $existing = $this->baseQuery($placement, $overlappingIds, $academicYear)
            ->where('room_number', $roomNumber)
            ->with('schoolClass')
            ->first();

        if (! $existing) {
            return [];
        }

        return [[
            'type' => 'room',
            'message' => "Room {$roomNumber} is already occupied by Class ".($existing->schoolClass->name ?? 'another class').' during this period.',
            'blocking_slot_id' => $existing->id,
        ]];
    }

    /**
     * Priority 1.3: a teacher (or co-teacher) can't be placed into this
     * period if it leaves too little time to travel from -- or to -- a
     * DIFFERENT building they're already scheduled in that same day. Only
     * applies across buildings (TransferTimeValidator no-ops for the same
     * building, an unmapped room, or a genuinely overlapping period --
     * that's roomOverlapConflicts()/teacherOverlapConflicts()'s job, not
     * this one's).
     */
    private function transferTimeConflicts(array $placement, BellTiming $bellTiming, Collection $sameDayIds, ?string $academicYear = null): array
    {
        $roomNumber = $placement['room_number'] ?? null;
        if (! $roomNumber) {
            return [];
        }

        $people = array_filter([$placement['teacher_id'] ?? null, $placement['co_teacher_id'] ?? null]);
        if (empty($people)) {
            return [];
        }

        $validator = new TransferTimeValidator;
        $thisSlot = ['room_number' => $roomNumber, 'start' => $bellTiming->start_time->copy(), 'end' => $bellTiming->end_time->copy()];

        $conflicts = [];

        foreach ($people as $personId) {
            $otherSlots = $this->applyIgnore(
                TimetableSlot::whereIn('bell_timing_id', $sameDayIds)
                    ->where('status', $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED)
                    ->when($academicYear, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                    ->where(fn ($q) => $q->where('teacher_id', $personId)->orWhere('co_teacher_id', $personId))
                    ->whereNotNull('room_number')
                    ->with('bellTiming'),
                $placement['ignore_slot_id'] ?? null
            )->get();

            foreach ($otherSlots as $other) {
                if (! $other->bellTiming) {
                    continue;
                }

                $result = $validator->validateTransferTime((int) $personId, $thisSlot, [
                    'room_number' => $other->room_number,
                    'start' => $other->bellTiming->start_time->copy(),
                    'end' => $other->bellTiming->end_time->copy(),
                ]);

                if ($result['conflict']) {
                    $personName = Teacher::find($personId)->name ?? 'This teacher';
                    $conflicts[] = [
                        'type' => 'transfer_time',
                        'message' => "{$personName}: {$result['message']}",
                        'blocking_slot_id' => $other->id,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Mirrors GeneratorService's $teacherBlocked map: a teacher (or
     * co-teacher) with an explicit TeacherAvailability(is_available=false)
     * row for this EXACT bell timing is a hard block. The manual editor
     * never checked this at all before -- an admin could freely schedule
     * a teacher into a period they'd explicitly marked unavailable.
     */
    private function teacherAvailabilityConflicts(array $placement, BellTiming $bellTiming): array
    {
        $conflicts = [];
        $people = array_filter([$placement['teacher_id'] ?? null, $placement['co_teacher_id'] ?? null]);

        if (empty($people)) {
            return [];
        }

        $blocked = TeacherAvailability::whereIn('teacher_id', $people)
            ->where('bell_timing_id', $bellTiming->id)
            ->where('is_available', false)
            ->pluck('teacher_id');

        foreach ($blocked as $personId) {
            $personName = Teacher::find($personId)->name ?? 'This teacher';
            $conflicts[] = [
                'type' => 'availability',
                'message' => "{$personName} has been marked unavailable for this period.",
            ];
        }

        return $conflicts;
    }

    /**
     * Mirrors GeneratorService's teacherDayCount/teacherWeekCount checks:
     * a teacher (or co-teacher) can't be placed beyond their own
     * max_periods_per_day / max_periods_per_week. "Week" here means the
     * whole current timetable for that status, matching how the
     * generator accumulates it across a single solve -- this is a
     * repeating weekly grid, not calendar weeks.
     */
    private function teacherLoadConflicts(array $placement, Collection $sameDayIds, ?string $academicYear = null): array
    {
        $conflicts = [];
        $status = $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED;
        $people = array_filter([$placement['teacher_id'] ?? null, $placement['co_teacher_id'] ?? null]);

        foreach ($people as $personId) {
            $teacher = Teacher::find($personId);
            if (! $teacher) {
                continue;
            }

            $maxPerDay = $teacher->max_periods_per_day ?? self::DEFAULT_MAX_PER_DAY;
            $maxPerWeek = $teacher->max_periods_per_week ?? config('timetable.max_periods_per_week', 36);

            // ignore_slot_id (auto-resolved in check() to the row this
            // placement's own natural key already occupies, if any) keeps
            // a same-cell resubmission from counting its own current
            // occupant as an ADDITIONAL period on top of the one being placed.
            // Tolerant academic_year scoping (only applied when known) --
            // a prior-year published slot must not count toward this
            // year's daily/weekly load caps. A row with no academic_year
            // at all (untagged legacy data) still counts -- only a row
            // explicitly tagged with a DIFFERENT year is excluded.
            $personQuery = fn () => $this->applyIgnore(
                TimetableSlot::where('status', $status)
                    ->when($academicYear, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                    ->where(fn ($q) => $q->where('teacher_id', $personId)->orWhere('co_teacher_id', $personId)),
                $placement['ignore_slot_id'] ?? null
            );

            $dayCount = (clone $personQuery())->whereIn('bell_timing_id', $sameDayIds)->count();
            if ($dayCount + 1 > $maxPerDay) {
                $conflicts[] = [
                    'type' => 'teacher_day_limit',
                    'message' => "{$teacher->name} is already scheduled for {$dayCount} period(s) that day -- their daily limit is {$maxPerDay}.",
                ];

                continue;
            }

            $weekCount = (clone $personQuery())->count();
            if ($weekCount + 1 > $maxPerWeek) {
                $conflicts[] = [
                    'type' => 'teacher_week_limit',
                    'message' => "{$teacher->name} is already scheduled for {$weekCount} period(s) this week -- their weekly limit is {$maxPerWeek}.",
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Mirrors GeneratorService's classSubjectDay cap: a class-section
     * can't have the same subject more than once a day, unless a matching
     * teacher_class_subject_assignment marks it require_consecutive (cap
     * 2, for a genuine double period). A manual placement with no
     * matching assignment row at all (the admin free-picked a
     * subject/teacher combo) defaults to the safe cap of 1.
     */
    private function subjectPerDayConflicts(array $placement, BellTiming $bellTiming, Collection $sameDayIds, ?string $academicYear): array
    {
        $schoolClassId = $placement['school_class_id'] ?? null;
        $subjectId = $placement['subject_id'] ?? null;
        if (! $schoolClassId || ! $subjectId) {
            return [];
        }

        $sectionId = $placement['section_id'] ?? null;
        $status = $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        // Tolerant academic_year scoping (only applied when known) --
        // otherwise a retired/other-year assignment row (e.g. leftover
        // test-data) could wrongly mark this subject as a double period.
        $requiresConsecutive = TeacherClassSubjectAssignment::where('class_id', $schoolClassId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $placement['teacher_id'] ?? null)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->value('require_consecutive');

        $cap = $requiresConsecutive ? 2 : 1;

        // ignore_slot_id excludes a same-cell resubmission's own current
        // occupant from counting as an extra period (see check()). Tolerant
        // academic_year scoping (only applied when known) -- a prior-year
        // published slot must not count toward this year's subject-per-day
        // cap either; an untagged legacy row still counts.
        $existingCount = $this->applyIgnore(
            TimetableSlot::where('status', $status)
                ->where('school_class_id', $schoolClassId)
                ->where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->when($academicYear, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                ->whereIn('bell_timing_id', $sameDayIds),
            $placement['ignore_slot_id'] ?? null
        )->count();

        if ($existingCount + 1 > $cap) {
            $subjectName = \App\Models\Subject::find($subjectId)->name ?? 'This subject';
            $dayName = $bellTiming->day_of_week;

            return [[
                'type' => 'subject_per_day',
                'message' => "{$subjectName} already has {$existingCount} period(s) on {$dayName} for this class".($cap > 1 ? " (double-period limit is {$cap})" : ' (once a day unless marked as a double period)').'.',
            ]];
        }

        return [];
    }
}
