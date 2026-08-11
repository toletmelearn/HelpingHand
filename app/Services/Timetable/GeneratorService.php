<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\ClassTeacherAssignment;
use App\Models\CombinedClassGroup;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

/**
 * T4a: backtracking constraint solver. Pure logic -- writes NOTHING to the
 * database (draft/publish, the job wrapper, and the UI are T4b). Builds a
 * lesson list from teacher_class_subject_assignments.periods_per_week and
 * from combined_class_groups rows that carry T4a's own periods_per_week/
 * teacher_id (added in the same migration batch as subjects.prefer_morning
 * -- 3655a4d), then places each lesson into a teaching-type bell_timing
 * slot honouring:
 *
 *   HARD: class free; teacher free; teacher availability
 *   (TeacherAvailability blocks); teacher max_periods_per_day/week; same
 *   subject max once per class per day, or twice only when
 *   require_consecutive AND the two periods are adjacent; a combined
 *   group's lesson is placed at the SAME period for every member class at
 *   once; non-teaching periods never used (the domain itself excludes
 *   them, since only BellTiming::teachingType() rows are ever loaded);
 *   T6 item 1 -- a class's class-teacher (teacher_class_subject_
 *   assignments.is_class_teacher, see reserveClassTeacherPeriod1() for why
 *   that field and not one of the other two "class teacher" concepts in
 *   this codebase) holds period 1 of their own class every day it runs,
 *   reserved BEFORE the normal solve as forced placements the rest of the
 *   solver just sees as already-committed state; T6 item 3 -- a class with
 *   school_classes.last_teaching_period set never gets a 'solo' lesson
 *   placed at an order_index beyond that ceiling (null = uncapped, every
 *   class's default); T6 item 4 (team teaching only -- club/elective
 *   blocks and the manual single-slot editor are deferred) -- an
 *   assignment's optional co_teacher_id occupies the SAME slot as
 *   teacher_id, one row, both teachers checked/reserved/limit-capped
 *   identically; a co-teacher busy elsewhere (as primary OR co-teacher of
 *   any other lesson) makes the slot illegal exactly like the primary
 *   teacher being busy would.
 *
 *   SOFT (slot ordering only -- never rejects an otherwise-legal slot):
 *   prefer morning / avoid last period for subjects.prefer_morning,
 *   prefer lighter days for a class (spreads a subject's periods out),
 *   prefer a slot adjacent to the teacher's other placements that day
 *   (minimise gaps).
 *
 * ALGORITHM: most-constrained-first (MRV -- fewest legal slots remaining,
 * tie-break highest periods_per_week), recomputed lazily: after a commit,
 * only pending lessons sharing that lesson's teacher or a class have their
 * cached legal-slot count invalidated, since nothing else could have
 * changed. When a lesson has zero legal slots, a bounded number of
 * already-committed SINGLE-PERIOD SOLO placements blocking its domain are
 * tried for relocation elsewhere (a local backtrack) before giving up --
 * double-period and combined-group placements are treated as fixed once
 * committed, a deliberate scope limit on the backtracking (documented in
 * the T4a session report) that keeps the solver's behaviour predictable.
 * A class-teacher's period-1 reservation (T6 item 1) is ALSO never
 * relocatable by this backtrack, despite being a single-period solo
 * commit -- it's a permanent fact decided before the normal solve even
 * starts, not an ordinary lesson placement (bug found by the T6 real-data
 * walkthrough: without this, an unrelated overloaded lesson elsewhere in
 * the same class could silently bump the reservation to make room for
 * itself; see the 'protected' flag in commit()).
 * If the backtrack budget for a lesson is exhausted, it is marked
 * UNPLACED with a human-readable reason naming the constraint that had
 * zero remaining slots, and the run continues -- best effort, never an
 * infinite loop. A wall-clock time budget
 * (config('timetable.generator.time_budget_seconds'), default 60s) stops
 * the whole run and returns the best state found so far.
 */
class GeneratorService
{
    /** T6 item 2: each subject's periods spread across different days (the original, still the default). */
    public const STYLE_ROTATING = 'rotating';

    /** T6 item 2: one day's pattern is solved once and repeated identically on every running day. */
    public const STYLE_FIXED_DAILY = 'fixed_daily';

    private int $backtrackBudgetPerLesson;

    private int $timeBudgetSeconds;

    private float $deadline;

    /** @var array<int,array{day_order:int,order_index:int,class_section:?string}> bell_timing_id => meta */
    private array $timingMeta = [];

    /** @var array<int,array<int,int>> day_order => sorted [bell_timing_id, ...] (teaching, active only) */
    private array $timingsByDay = [];

    /** @var array<string,bool> "teacherId|bellTimingId" */
    private array $teacherBusy = [];

    /** @var array<string,bool> "teacherId|bellTimingId" blocked by TeacherAvailability */
    private array $teacherBlocked = [];

    /** @var array<string,bool> "classId|sectionId|bellTimingId" */
    private array $classBusy = [];

    /** @var array<string,int> "teacherId|dayOrder" => periods placed that day */
    private array $teacherDayCount = [];

    /** @var array<int,int> teacherId => periods placed this week */
    private array $teacherWeekCount = [];

    /** @var array<string,int> "classId|sectionId|subjectId|dayOrder" => periods placed that day */
    private array $classSubjectDay = [];

    /** @var array<string,int> "classId|sectionId|dayOrder" => periods placed that day (soft: spread) */
    private array $classDayLoad = [];

    /** @var array<int,array{max_per_day:int,max_per_week:int}> teacherId => limits */
    private array $teacherLimits = [];

    /** @var array<int,array> placementId => committed lesson placement */
    private array $committed = [];

    /** @var array<string,int> "teacherId|bellTimingId" => placementId, single-period solo placements only */
    private array $placementByTeacherSlot = [];

    /** @var array<string,int> "classId|sectionId|bellTimingId" => placementId, single-period solo placements only */
    private array $placementByClassSlot = [];

    /** @var array<string,?int> className => last_teaching_period (order_index ceiling), null = uncapped. T6 item 3. */
    private array $classLastPeriod = [];

    private array $unplaced = [];

    private int $nextLessonId = 1;

    private int $nextPlacementId = 1;

    /**
     * @param  Collection<int,\App\Models\SchoolClass>  $schoolClasses  the set of classes to generate for
     * @param  ?string  $style  self::STYLE_ROTATING (default) or self::STYLE_FIXED_DAILY
     * @return array{placements: array, unplaced: array, warnings: array, stats: array}
     */
    public function generate(?string $academicYear, Collection $schoolClasses, ?int $academicSessionId = null, ?string $style = null): array
    {
        $style = $style ?? self::STYLE_ROTATING;
        $this->resetState();

        $this->timeBudgetSeconds = (int) config('timetable.generator.time_budget_seconds', 60);
        $this->backtrackBudgetPerLesson = (int) config('timetable.generator.backtrack_budget_per_lesson', 25);
        $startedAt = microtime(true);
        $this->deadline = $startedAt + $this->timeBudgetSeconds;

        $classIds = $schoolClasses->pluck('id');
        $classesById = $schoolClasses->keyBy('id');
        $this->classLastPeriod = $schoolClasses->pluck('last_teaching_period', 'name')->all();

        $timings = BellTiming::query()
            ->where('is_active', true)
            ->teachingType()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $this->loadTimingMeta($timings);

        $this->teacherBlocked = TeacherAvailability::where('is_available', false)
            ->whereIn('bell_timing_id', $timings->pluck('id'))
            ->get()
            ->mapWithKeys(fn ($a) => ["{$a->teacher_id}|{$a->bell_timing_id}" => true])
            ->all();

        // Teacher limits must be loaded before ANY commits, including the
        // class-teacher reservations below -- scoped by class, not by the
        // (not yet built) lesson list, since reservation happens first.
        $this->loadTeacherLimitsForClasses($classIds, $academicSessionId);

        $excludedAssignmentIds = [];
        $warnings = [];

        // Phase 5 (Locked Lessons): locked PUBLISHED slots are existing,
        // committed reality -- reserved first, before anything else claims
        // a period, exactly like the class-teacher period-1 reservation
        // below claims its own periods first. If a lock happens to collide
        // with what would otherwise be a period-1 reservation or a
        // fixed-daily slot, isHardLegal() catches it the same way it
        // catches every other reservation conflict, producing a warning
        // instead of a double-booking.
        $lockedCountsByAssignmentId = [];
        $forcedLessonAttempts = $this->reserveLockedSlots($classIds, $classesById, $academicYear, $lockedCountsByAssignmentId, $warnings);

        $forcedLessonAttempts += $this->reserveClassTeacherPeriod1($classIds, $classesById, $academicYear, $excludedAssignmentIds, $warnings);

        if ($style === self::STYLE_FIXED_DAILY) {
            $forcedLessonAttempts += $this->reserveFixedDailyLessons($classIds, $classesById, $academicYear, $excludedAssignmentIds, $warnings);
        }

        $lessons = $this->buildLessons($academicYear, $academicSessionId, $classIds, $classesById, $excludedAssignmentIds, $lockedCountsByAssignmentId);

        $pending = collect($lessons)->keyBy('lesson_id');
        $dirty = $pending->keys()->all();
        $domainCountCache = [];

        while ($pending->isNotEmpty()) {
            if (microtime(true) > $this->deadline) {
                foreach ($pending as $lesson) {
                    $this->unplaced[] = $this->unplacedResult(
                        $lesson,
                        "Could not place {$lesson['subject_name']} for {$lesson['label']}: the {$this->timeBudgetSeconds}-second generation time budget ran out before this lesson could be scheduled."
                    );
                }
                break;
            }

            foreach ($dirty as $id) {
                if ($pending->has($id)) {
                    $domainCountCache[$id] = count($this->legalSlots($pending[$id]));
                }
            }
            $dirty = [];

            $lessonId = $this->pickMostConstrained($pending, $domainCountCache);
            $lesson = $pending->pull($lessonId);
            unset($domainCountCache[$lessonId]);

            $legal = $this->legalSlots($lesson);
            if (empty($legal)) {
                $legal = $this->attemptBacktrack($lesson);
            }

            if (empty($legal)) {
                $this->unplaced[] = $this->unplacedResult($lesson);
                continue;
            }

            $scored = array_map(fn ($slot) => ['slot' => $slot, 'score' => $this->softScore($lesson, $slot)], $legal);
            usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

            $this->commit($lesson, $scored[0]['slot']);

            // T6 item 4: a co-teacher (either side) makes a pending lesson's
            // cached domain count stale exactly like the primary teacher
            // does -- checked in both directions so a lesson pending on the
            // co-teacher side is invalidated too.
            $affectedTeacherIds = array_filter([$lesson['teacher_id'], $lesson['co_teacher_id'] ?? null], fn ($id) => $id !== null);
            foreach ($pending as $id => $other) {
                $otherTeacherIds = array_filter([$other['teacher_id'], $other['co_teacher_id'] ?? null], fn ($id) => $id !== null);
                if (array_intersect($otherTeacherIds, $affectedTeacherIds) || array_intersect($other['class_ids'], $lesson['class_ids'])) {
                    $dirty[] = $id;
                }
            }
        }

        $placements = [];
        foreach ($this->committed as $p) {
            foreach ($p['class_ids'] as $idx => $classId) {
                $placements[] = [
                    'school_class_id' => $classId,
                    'section_id' => $p['section_ids'][$idx] ?? null,
                    'subject_id' => $p['subject_id'],
                    'teacher_id' => $p['teacher_id'],
                    'co_teacher_id' => $p['co_teacher_id'] ?? null,
                    'bell_timing_ids' => $p['bell_timing_ids'],
                    'combined_class_group_id' => $p['combined_class_group_id'],
                    'is_locked' => $p['is_locked'],
                    'room_number' => $p['room_number'],
                ];
            }
        }

        return [
            'placements' => $placements,
            'unplaced' => $this->unplaced,
            'warnings' => $warnings,
            'stats' => [
                'style' => $style,
                'total_lessons' => count($lessons) + $forcedLessonAttempts,
                'placed_lessons' => count($this->committed),
                'placed_rows' => count($placements),
                'unplaced_lessons' => count($this->unplaced),
                'warnings_count' => count($warnings),
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
            ],
        ];
    }

    private function resetState(): void
    {
        $this->timingMeta = [];
        $this->timingsByDay = [];
        $this->teacherBusy = [];
        $this->teacherBlocked = [];
        $this->classBusy = [];
        $this->teacherDayCount = [];
        $this->teacherWeekCount = [];
        $this->classSubjectDay = [];
        $this->classDayLoad = [];
        $this->teacherLimits = [];
        $this->committed = [];
        $this->placementByTeacherSlot = [];
        $this->placementByClassSlot = [];
        $this->classLastPeriod = [];
        $this->unplaced = [];
        $this->nextLessonId = 1;
        $this->nextPlacementId = 1;
    }

    private function loadTimingMeta(Collection $timings): void
    {
        $byDay = [];
        foreach ($timings as $t) {
            $dayOrder = $t->day_order;
            $this->timingMeta[$t->id] = [
                'day_order' => $dayOrder,
                'order_index' => (int) $t->order_index,
                'class_section' => $t->class_section,
            ];
            $byDay[$dayOrder][] = $t->id;
        }
        foreach ($byDay as $day => $ids) {
            usort($ids, fn ($a, $b) => $this->timingMeta[$a]['order_index'] <=> $this->timingMeta[$b]['order_index']);
            $byDay[$day] = $ids;
        }
        $this->timingsByDay = $byDay;
    }

    /**
     * Scoped by class rather than derived from the lesson list, since the
     * class-teacher reservations (which need limits loaded first) commit
     * before the lesson list is even built.
     */
    private function loadTeacherLimitsForClasses(Collection $classIds, ?int $academicSessionId): void
    {
        $teacherIds = TeacherClassSubjectAssignment::whereIn('class_id', $classIds)
            ->whereNotNull('periods_per_week')
            ->pluck('teacher_id')
            ->merge(
                // T6 item 4: a co-teacher's own limits must be loaded too --
                // isHardLegal() checks their day/week caps exactly like the
                // primary teacher's.
                TeacherClassSubjectAssignment::whereIn('class_id', $classIds)
                    ->whereNotNull('periods_per_week')
                    ->whereNotNull('co_teacher_id')
                    ->pluck('co_teacher_id')
            )
            ->merge(
                CombinedClassGroup::whereNotNull('periods_per_week')
                    ->whereNotNull('teacher_id')
                    ->when($academicSessionId, fn ($q) => $q->where('academic_session_id', $academicSessionId))
                    ->pluck('teacher_id')
            )
            ->unique();

        $this->teacherLimits = Teacher::whereIn('id', $teacherIds)->get()
            ->mapWithKeys(fn ($t) => [$t->id => [
                'max_per_day' => (int) $t->max_periods_per_day,
                'max_per_week' => (int) $t->max_periods_per_week,
            ]])
            ->all();
    }

    /**
     * Phase 5 (Locked Lessons): a locked, currently-PUBLISHED slot must be
     * carried into the next draft unchanged, at the exact period it
     * already occupies -- committed directly like the class-teacher
     * period-1 reservation below, with the same 'protected' treatment
     * (see commit()) so nothing later, including this same run's own
     * local backtrack, can bump it.
     *
     * Only published locks are considered: a draft is regenerated fresh
     * each time by design (GenerateTimetableJob deletes the old draft
     * before inserting the new one), so a lock only has something stable
     * to protect once it's live. If a locked slot's own period is no
     * longer part of this run's active teaching-timing set (e.g. it was
     * deactivated since the lock was set), or it now collides with an
     * earlier, higher-priority reservation, it's reported as a warning
     * and skipped rather than crashing or silently dropping the lock.
     *
     * @param array<int,int> $lockedCountsByAssignmentId Filled in here:
     *   assignment_id => how many of its periods_per_week this locked slot
     *   already accounts for, so buildLessons() doesn't also place a fresh
     *   occurrence on top of it.
     * @return int total locked-slot attempts, placed or not -- for the stats.total_lessons count
     */
    private function reserveLockedSlots(Collection $classIds, Collection $classesById, ?string $academicYear, array &$lockedCountsByAssignmentId, array &$warnings): int
    {
        $lockedSlots = TimetableSlot::with(['subject', 'teacher'])
            ->published()
            ->locked()
            ->whereIn('school_class_id', $classIds)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $attempts = 0;

        foreach ($lockedSlots as $slot) {
            $class = $classesById->get($slot->school_class_id);
            if (!$class) {
                continue;
            }

            if (!isset($this->timingMeta[$slot->bell_timing_id])) {
                $warnings[] = "Locked lesson for {$class->name} (slot #{$slot->id}) could not be carried into this draft -- its period is no longer active.";
                continue;
            }

            $attempts++;

            $lesson = [
                'lesson_id' => $this->nextLessonId++,
                'type' => 'solo',
                'teacher_id' => $slot->teacher_id,
                'co_teacher_id' => $slot->co_teacher_id,
                'subject_id' => $slot->subject_id,
                'class_ids' => [$slot->school_class_id],
                'section_ids' => [$slot->section_id],
                'class_name' => $class->name,
                'room_number' => $slot->room_number,
                'require_consecutive' => false,
                'source' => ['locked' => true],
            ];
            $slotChoice = ['bell_timing_ids' => [$slot->bell_timing_id]];

            if ($this->isHardLegal($lesson, $slotChoice)) {
                $this->commit($lesson, $slotChoice);

                $assignmentId = TeacherClassSubjectAssignment::where('teacher_id', $slot->teacher_id)
                    ->where('class_id', $slot->school_class_id)
                    ->where('section_id', $slot->section_id)
                    ->where('subject_id', $slot->subject_id)
                    ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                    ->value('id');

                if ($assignmentId) {
                    $lockedCountsByAssignmentId[$assignmentId] = ($lockedCountsByAssignmentId[$assignmentId] ?? 0) + 1;
                }
            } else {
                $teacherName = optional($slot->teacher)->name ?? 'This teacher';
                $warnings[] = "Locked lesson for {$class->name} (slot #{$slot->id}, {$teacherName}) could not be carried into this draft -- it now conflicts with an already-reserved period. Unlock it or resolve the conflict manually.";
            }
        }

        return $attempts;
    }

    /**
     * T6 item 1: reserve period 1 of every teaching day for each requested
     * class's class-teacher (teacher_class_subject_assignments.
     * is_class_teacher = true, with periods_per_week set -- see the class
     * docblock for why this field). Committed directly, bypassing MRV
     * entirely, since these are mandatory-first, not solver choices. The
     * reserved assignment's periods_per_week is NOT separately honoured by
     * the normal per-period lesson loop (buildLessons() excludes it via
     * $excludedAssignmentIds) -- period 1 daily IS this assignment's
     * placement, not an addition on top of it.
     *
     * A class with no class-teacher, or whose class-teacher assignment has
     * no periods_per_week set, is simply skipped -- not an error, per the
     * plan's "a class with no class-teacher still generates" requirement.
     * A day the class has no teaching period at all is skipped too. Only a
     * genuine conflict (most commonly: the same teacher is class-teacher
     * of two requested classes that share the identical period-1 bell
     * timing on some day, since teacher_class_subject_assignments allows a
     * teacher to be class-teacher of up to 2 classes) produces a warning
     * instead of failing silently or double-booking.
     *
     * @return int total (day x class-teacher-assignment) attempts, placed or not -- for the stats.total_lessons count
     */
    private function reserveClassTeacherPeriod1(Collection $classIds, Collection $classesById, ?string $academicYear, array &$excludedAssignmentIds, array &$warnings): int
    {
        $classTeacherAssignments = TeacherClassSubjectAssignment::with('subject')
            ->whereIn('class_id', $classIds)
            ->where('is_class_teacher', true)
            ->whereNotNull('periods_per_week')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $attempts = 0;

        foreach ($classTeacherAssignments as $assignment) {
            $class = $classesById->get($assignment->class_id);
            $subject = $assignment->subject;
            if (! $class || ! $subject) {
                continue;
            }

            $excludedAssignmentIds[] = $assignment->id;

            $section = $assignment->section_id ? Section::find($assignment->section_id) : null;
            $label = $section ? "{$class->name}{$section->name}" : $class->name;

            $lesson = [
                'lesson_id' => $this->nextLessonId++,
                'type' => 'solo',
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
                'subject_name' => $subject->name,
                'prefer_morning' => (bool) $subject->prefer_morning,
                'require_consecutive' => false,
                'periods_needed' => 1,
                'periods_per_week' => 1,
                'class_ids' => [$assignment->class_id],
                'section_ids' => [$assignment->section_id],
                'class_name' => $class->name,
                'label' => $label,
                'source' => ['assignment_id' => $assignment->id, 'class_teacher_period1' => true],
            ];

            $daysPlaced = 0;
            foreach ($this->timingsByDay as $dayIds) {
                $periodOneSlot = $this->firstPeriodSlotForLesson($lesson, $dayIds);
                if ($periodOneSlot === null) {
                    continue; // this class has no teaching period at all on this day
                }

                $attempts++;

                if ($this->isHardLegal($lesson, $periodOneSlot)) {
                    $this->commit($lesson, $periodOneSlot);
                    $daysPlaced++;
                } else {
                    $teacherName = optional(Teacher::find($assignment->teacher_id))->name ?? "Teacher #{$assignment->teacher_id}";
                    $warnings[] = "Could not reserve period 1 for {$label}: {$teacherName} (class teacher) already has a commitment at that exact period -- most likely they're class-teacher of another class with the same period 1 that day.";
                }
            }

            if ($daysPlaced === 0 && $attempts === 0) {
                $warnings[] = "Could not reserve period 1 for {$label}: {$subject->name} has no teaching day to reserve on.";
            }
        }

        // T6 item 1 (revised): a class whose designated class-teacher (the
        // legacy class_teacher_assignments table -- see ClassTeacherAssignment
        // model) has no is_class_teacher-flagged subject in this class never
        // gets period 1 forced (there's no subject to anchor it to); this is
        // reported here as a warning naming the gap, not silently skipped --
        // distinct from a class with NO designated class-teacher at all
        // (App\Services\Timetable\FeasibilityService::classTeacherReadiness()
        // handles that case, as a readiness note rather than a per-generation
        // warning).
        $classIdsWithoutSubjectAssignment = $classIds->diff($classTeacherAssignments->pluck('class_id')->unique());

        if ($classIdsWithoutSubjectAssignment->isNotEmpty()) {
            $classNames = $classIdsWithoutSubjectAssignment
                ->map(fn ($id) => optional($classesById->get($id))->name)
                ->filter();

            $legacyAssignmentsByClassName = ClassTeacherAssignment::whereIn('assigned_class', $classNames)
                ->active()
                ->get()
                ->filter(fn (ClassTeacherAssignment $a) => $a->isCurrentlyAssigned())
                ->keyBy('assigned_class');

            foreach ($classIdsWithoutSubjectAssignment as $classId) {
                $class = $classesById->get($classId);
                if (! $class) {
                    continue;
                }

                $legacyAssignment = $legacyAssignmentsByClassName->get($class->name);
                if (! $legacyAssignment) {
                    continue; // no class-teacher designated at all -- FeasibilityService's readiness note, not a generation warning.
                }

                // class_teacher_assignments.teacher_id is FK'd to users.id
                // (see the migration + ClassTeacherAssignmentAuthorizationTest),
                // NOT teachers.id -- the model's own teacher() relationship/
                // getClassTeacherName() incorrectly queries Teacher by that
                // id and would silently resolve to "Unknown Teacher" on real
                // data, so resolve via Teacher.user_id here instead.
                $teacherName = Teacher::where('user_id', $legacyAssignment->teacher_id)->value('name') ?? 'Unknown Teacher';
                $warnings[] = "Class {$class->name}: class teacher {$teacherName} has no subject assigned for this class -- period 1 was not reserved. Assign them a subject for {$class->name} if you want them to take first period.";
            }
        }

        return $attempts;
    }

    /** The lowest-order_index domain slot for $lesson within one day's own sorted, filtered id list, or null if none apply. */
    private function firstPeriodSlotForLesson(array $lesson, array $dayIds): ?array
    {
        $cap = $lesson['type'] === 'solo' ? ($this->classLastPeriod[$lesson['class_name']] ?? null) : null;

        $filtered = array_values(array_filter($dayIds, function ($btId) use ($lesson, $cap) {
            if ($cap !== null && $this->timingMeta[$btId]['order_index'] > $cap) {
                return false;
            }

            $cs = $this->timingMeta[$btId]['class_section'];
            if ($cs === null || $cs === '') {
                return true;
            }

            return $lesson['type'] === 'solo' && $cs === $lesson['class_name'];
        }));

        return empty($filtered) ? null : ['bell_timing_ids' => [$filtered[0]]];
    }

    /**
     * T6 item 2, STYLE_FIXED_DAILY: every solo assignment not already
     * reserved for the class-teacher (Item 1's rule is already "fixed
     * daily" in nature -- same teacher/subject in period 1 every day --
     * so it needs no changes here and isn't touched by this method) is
     * solved ONCE per class and repeated identically on every day that
     * class runs, rather than spread across the week the way
     * STYLE_ROTATING does. Reuses isHardLegal()/commit() completely
     * unchanged -- each day's placement is just an ordinary single- or
     * double-period 'solo' commit, called once per running day with that
     * day's own bell_timing_id(s) at the chosen order_index(es).
     *
     * An assignment whose periods_per_week doesn't divide evenly across
     * the class's running days, or whose require_consecutive flag doesn't
     * match "exactly 2 periods/day" (consecutive) / "exactly 1 period/day"
     * (not), is reported as not fixed-daily-compatible -- never guessed
     * at partially. One that IS compatible but can't find a legal
     * order_index pattern free on every running day (a scheduling
     * conflict, not a math problem) is reported as a warning instead.
     * Both go through the same $warnings array Item 1's clash case uses.
     *
     * @return int total assignments attempted (compatible or not) -- for the stats.total_lessons count
     */
    private function reserveFixedDailyLessons(Collection $classIds, Collection $classesById, ?string $academicYear, array &$excludedAssignmentIds, array &$warnings): int
    {
        $assignments = TeacherClassSubjectAssignment::with('subject')
            ->whereIn('class_id', $classIds)
            ->whereNotNull('periods_per_week')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get()
            ->reject(fn ($a) => in_array($a->id, $excludedAssignmentIds, true));

        $sectionsById = Section::whereIn('id', $assignments->pluck('section_id')->filter()->unique())->get()->keyBy('id');

        $attempts = 0;

        foreach ($assignments->groupBy('class_id') as $classId => $classAssignments) {
            $class = $classesById->get($classId);
            if (! $class) {
                continue;
            }

            $runningDays = $this->runningDaysForClass($class->name);
            if (empty($runningDays)) {
                continue;
            }

            $commonOrderIndexes = $this->commonOrderIndexesForClass($class->name, $runningDays);
            $usedOrderIndexes = [];

            foreach ($classAssignments as $assignment) {
                $subject = $assignment->subject;
                if (! $subject) {
                    continue;
                }

                $excludedAssignmentIds[] = $assignment->id;
                $attempts++;

                $periodsPerWeek = (int) $assignment->periods_per_week;
                $requireConsecutive = (bool) $assignment->require_consecutive;
                $section = $assignment->section_id ? $sectionsById->get($assignment->section_id) : null;
                $label = $section ? "{$class->name}{$section->name}" : $class->name;
                $runningDayCount = count($runningDays);

                if ($periodsPerWeek % $runningDayCount !== 0) {
                    $warnings[] = "Could not use fixed-daily style for {$subject->name} in {$label}: {$periodsPerWeek} periods/week does not divide evenly across {$runningDayCount} running days -- not fixed-daily-compatible.";

                    continue;
                }

                $periodsPerDay = intdiv($periodsPerWeek, $runningDayCount);

                if ($requireConsecutive && $periodsPerDay !== 2) {
                    $warnings[] = "Could not use fixed-daily style for {$subject->name} in {$label}: a consecutive-flagged subject needs exactly 2 periods/day in fixed-daily mode, this works out to {$periodsPerDay} -- not fixed-daily-compatible.";

                    continue;
                }
                if (! $requireConsecutive && $periodsPerDay !== 1) {
                    $warnings[] = "Could not use fixed-daily style for {$subject->name} in {$label}: a non-consecutive subject needs exactly 1 period/day in fixed-daily mode, this works out to {$periodsPerDay} -- not fixed-daily-compatible.";

                    continue;
                }

                $lessonTemplate = [
                    'type' => 'solo',
                    'teacher_id' => $assignment->teacher_id,
                    'co_teacher_id' => $assignment->co_teacher_id,
                    'subject_id' => $assignment->subject_id,
                    'subject_name' => $subject->name,
                    'prefer_morning' => (bool) $subject->prefer_morning,
                    'require_consecutive' => $requireConsecutive,
                    'periods_needed' => $periodsPerDay,
                    'periods_per_week' => $periodsPerWeek,
                    'class_ids' => [$assignment->class_id],
                    'section_ids' => [$assignment->section_id],
                    'class_name' => $class->name,
                    'label' => $label,
                ];

                $candidate = $this->findFixedDailyCandidate($lessonTemplate, $runningDays, $commonOrderIndexes, $usedOrderIndexes);

                if ($candidate === null) {
                    $warnings[] = "Could not reserve a fixed-daily slot for {$subject->name} in {$label}: no order-index pattern is free on every running day.";

                    continue;
                }

                foreach ($candidate['orderIndexes'] as $oi) {
                    $usedOrderIndexes[] = $oi;
                }

                foreach ($runningDays as $dayOrder) {
                    $lesson = $lessonTemplate;
                    $lesson['lesson_id'] = $this->nextLessonId++;
                    $this->commit($lesson, ['bell_timing_ids' => $candidate['idsByDay'][$dayOrder]]);
                }
            }
        }

        return $attempts;
    }

    /** Day-order values where this class has at least one domain slot (same class_section matching every other lesson type uses). */
    private function runningDaysForClass(?string $className): array
    {
        $days = [];
        foreach ($this->timingsByDay as $dayOrder => $ids) {
            $hasAny = array_filter($ids, fn ($btId) => $this->slotAppliesToClass($btId, $className));
            if (! empty($hasAny)) {
                $days[] = $dayOrder;
            }
        }
        sort($days);

        return $days;
    }

    /** order_index values present in this class's domain on EVERY one of $runningDays -- the only values safely repeatable across all of them. */
    private function commonOrderIndexesForClass(?string $className, array $runningDays): array
    {
        $perDaySets = [];
        foreach ($runningDays as $dayOrder) {
            $ids = array_filter($this->timingsByDay[$dayOrder] ?? [], fn ($btId) => $this->slotAppliesToClass($btId, $className));
            $perDaySets[] = array_map(fn ($btId) => $this->timingMeta[$btId]['order_index'], $ids);
        }

        if (empty($perDaySets)) {
            return [];
        }

        $common = array_shift($perDaySets);
        foreach ($perDaySets as $set) {
            $common = array_intersect($common, $set);
        }

        $common = array_values(array_unique($common));
        sort($common);

        return $common;
    }

    private function slotAppliesToClass(int $btId, ?string $className): bool
    {
        $cap = $this->classLastPeriod[$className] ?? null;
        if ($cap !== null && $this->timingMeta[$btId]['order_index'] > $cap) {
            return false;
        }

        $cs = $this->timingMeta[$btId]['class_section'];

        return $cs === null || $cs === '' || $cs === $className;
    }

    /**
     * Try each not-yet-used order_index (or, for a 2-period/day lesson,
     * each adjacent pair -- integers 1 apart, both still available) from
     * $commonOrderIndexes, lowest first. A candidate is only accepted once
     * EVERY running day's corresponding bell_timing(s) pass the exact same
     * isHardLegal() check every other lesson type uses -- checked read-only
     * across all days before committing anything, so a candidate that
     * fails partway through never leaves a partial commit behind.
     */
    private function findFixedDailyCandidate(array $lessonTemplate, array $runningDays, array $commonOrderIndexes, array $usedOrderIndexes): ?array
    {
        $available = array_values(array_diff($commonOrderIndexes, $usedOrderIndexes));
        sort($available);

        $candidateGroups = [];
        if ($lessonTemplate['periods_needed'] === 1) {
            foreach ($available as $oi) {
                $candidateGroups[] = [$oi];
            }
        } else {
            for ($i = 0; $i < count($available) - 1; $i++) {
                if ($available[$i + 1] - $available[$i] === 1) {
                    $candidateGroups[] = [$available[$i], $available[$i + 1]];
                }
            }
        }

        foreach ($candidateGroups as $orderIndexes) {
            $idsByDay = [];
            $allLegal = true;

            foreach ($runningDays as $dayOrder) {
                $dayIds = [];
                foreach ($orderIndexes as $oi) {
                    $btId = $this->bellTimingIdForDayAndOrderIndex($dayOrder, $oi);
                    if ($btId === null) {
                        $allLegal = false;

                        break 2;
                    }
                    $dayIds[] = $btId;
                }

                if (! $this->isHardLegal($lessonTemplate, ['bell_timing_ids' => $dayIds])) {
                    $allLegal = false;

                    break;
                }

                $idsByDay[$dayOrder] = $dayIds;
            }

            if ($allLegal) {
                return ['orderIndexes' => $orderIndexes, 'idsByDay' => $idsByDay];
            }
        }

        return null;
    }

    private function bellTimingIdForDayAndOrderIndex(int $dayOrder, int $orderIndex): ?int
    {
        foreach ($this->timingsByDay[$dayOrder] ?? [] as $btId) {
            if ($this->timingMeta[$btId]['order_index'] === $orderIndex) {
                return $btId;
            }
        }

        return null;
    }

    /**
     * Build the lesson list: one unit per period, except require_consecutive
     * assignments which are pre-paired into double-period units (see
     * splitIntoPairs()) so adjacency is guaranteed by domain generation
     * rather than checked after the fact.
     */
    /**
     * @param array<int,int> $lockedCountsByAssignmentId Phase 5 (Locked
     *   Lessons): assignment_id => how many of its periods_per_week are
     *   already accounted for by a locked slot reserveLockedSlots() pinned
     *   before this ran. Subtracted from periods_per_week here rather than
     *   excluding the whole assignment (excludedAssignmentIds' approach) --
     *   a locked slot is normally ONE occurrence of a multi-period-per-week
     *   subject, not the assignment's entire requirement, so the rest still
     *   needs freshly placing around it.
     */
    private function buildLessons(?string $academicYear, ?int $academicSessionId, Collection $classIds, Collection $classesById, array $excludedAssignmentIds = [], array $lockedCountsByAssignmentId = []): array
    {
        $lessons = [];

        $assignments = TeacherClassSubjectAssignment::with(['subject'])
            ->whereIn('class_id', $classIds)
            ->whereNotNull('periods_per_week')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->orderBy('id')
            ->get()
            // T6 item 1: a class-teacher's own subject was already reserved
            // for period 1 every day by reserveClassTeacherPeriod1() -- that
            // IS this assignment's placement, not an addition on top of it.
            ->reject(fn ($a) => in_array($a->id, $excludedAssignmentIds, true));

        $sectionsById = Section::whereIn('id', $assignments->pluck('section_id')->filter()->unique())->get()->keyBy('id');

        foreach ($assignments as $assignment) {
            $class = $classesById->get($assignment->class_id);
            $subject = $assignment->subject;
            if (! $class || ! $subject) {
                continue;
            }

            $preferMorning = (bool) $subject->prefer_morning;
            $requireConsecutive = (bool) $assignment->require_consecutive;
            $periodsPerWeek = max(0, (int) $assignment->periods_per_week - ($lockedCountsByAssignmentId[$assignment->id] ?? 0));
            $section = $assignment->section_id ? $sectionsById->get($assignment->section_id) : null;
            $label = $section ? "{$class->name}{$section->name}" : $class->name;

            $units = $requireConsecutive ? $this->splitIntoPairs($periodsPerWeek) : array_fill(0, $periodsPerWeek, 1);

            foreach ($units as $periodsNeeded) {
                $lessons[] = [
                    'lesson_id' => $this->nextLessonId++,
                    'type' => 'solo',
                    'teacher_id' => $assignment->teacher_id,
                    'co_teacher_id' => $assignment->co_teacher_id,
                    'subject_id' => $assignment->subject_id,
                    'subject_name' => $subject->name,
                    'prefer_morning' => $preferMorning,
                    'require_consecutive' => $requireConsecutive,
                    'periods_needed' => $periodsNeeded,
                    'periods_per_week' => $periodsPerWeek,
                    'class_ids' => [$assignment->class_id],
                    'section_ids' => [$assignment->section_id],
                    'class_name' => $class->name,
                    'label' => $label,
                    'source' => ['assignment_id' => $assignment->id],
                ];
            }
        }

        $groups = CombinedClassGroup::with(['subject', 'members'])
            ->whereNotNull('periods_per_week')
            ->whereNotNull('teacher_id')
            ->when($academicSessionId, fn ($q) => $q->where('academic_session_id', $academicSessionId))
            ->get()
            ->filter(fn ($g) => $g->members->isNotEmpty() && $g->members->pluck('school_class_id')->diff($classIds)->isEmpty());

        foreach ($groups as $group) {
            $subject = $group->subject;
            if (! $subject) {
                continue;
            }

            $memberClassIds = $group->members->pluck('school_class_id')->all();
            $memberSectionIds = $group->members->pluck('section_id')->all();
            $periodsPerWeek = (int) $group->periods_per_week;

            for ($i = 0; $i < $periodsPerWeek; $i++) {
                $lessons[] = [
                    'lesson_id' => $this->nextLessonId++,
                    'type' => 'combined',
                    'teacher_id' => $group->teacher_id,
                    'subject_id' => $group->subject_id,
                    'subject_name' => $subject->name,
                    'prefer_morning' => (bool) $subject->prefer_morning,
                    'require_consecutive' => false,
                    'periods_needed' => 1,
                    'periods_per_week' => $periodsPerWeek,
                    'class_ids' => $memberClassIds,
                    'section_ids' => $memberSectionIds,
                    'class_name' => null,
                    'label' => $group->name,
                    'source' => ['group_id' => $group->id],
                ];
            }
        }

        return $lessons;
    }

    /** 5 -> [2, 2, 1]; 6 -> [2, 2, 2]; 1 -> [1]. */
    private function splitIntoPairs(int $periodsPerWeek): array
    {
        $units = array_fill(0, intdiv($periodsPerWeek, 2), 2);
        if ($periodsPerWeek % 2 === 1) {
            $units[] = 1;
        }

        return $units;
    }

    /**
     * Candidate slots for a lesson, ignoring current occupancy (that's
     * isHardLegal()'s job) -- just the domain: teaching bell_timings this
     * lesson is eligible for (class_section null, or -- for a solo lesson
     * only -- matching this class's name, same convention as
     * FeasibilityService's own class_section text match; also, for a solo
     * lesson only, at or before that class's own last_teaching_period
     * ceiling if it has one -- T6 item 3), as singles or, for a
     * double-period lesson, as adjacent pairs within one day's own
     * filtered/sorted teaching-period list (non-teaching periods are
     * already excluded from that list, so "adjacent in the list" already
     * means "the next period a student would actually sit through").
     *
     * @return array<int,array{bell_timing_ids: array<int,int>}>
     */
    private function domainSlotsForLesson(array $lesson): array
    {
        $slots = [];
        $cap = $lesson['type'] === 'solo' ? ($this->classLastPeriod[$lesson['class_name']] ?? null) : null;

        foreach ($this->timingsByDay as $ids) {
            $filtered = array_values(array_filter($ids, function ($btId) use ($lesson, $cap) {
                if ($cap !== null && $this->timingMeta[$btId]['order_index'] > $cap) {
                    return false;
                }

                $cs = $this->timingMeta[$btId]['class_section'];
                if ($cs === null || $cs === '') {
                    return true;
                }

                return $lesson['type'] === 'solo' && $cs === $lesson['class_name'];
            }));

            if ($lesson['periods_needed'] === 1) {
                foreach ($filtered as $btId) {
                    $slots[] = ['bell_timing_ids' => [$btId]];
                }

                continue;
            }

            for ($i = 0; $i < count($filtered) - 1; $i++) {
                $slots[] = ['bell_timing_ids' => [$filtered[$i], $filtered[$i + 1]]];
            }
        }

        return $slots;
    }

    private function legalSlots(array $lesson): array
    {
        return array_values(array_filter(
            $this->domainSlotsForLesson($lesson),
            fn ($slot) => $this->isHardLegal($lesson, $slot)
        ));
    }

    private function isHardLegal(array $lesson, array $slot): bool
    {
        $ids = $slot['bell_timing_ids'];
        $limits = $this->teacherLimits[$lesson['teacher_id']] ?? ['max_per_day' => 7, 'max_per_week' => 36];
        // T6 item 4: a team-taught lesson's co-teacher must be exactly as
        // free as the primary teacher -- both must be free, both get
        // reserved, both are bound by their own day/week limits. A plain
        // (non-team-taught) lesson simply has co_teacher_id === null and
        // every check below is skipped, unchanged from before this item.
        $coTeacherId = $lesson['co_teacher_id'] ?? null;
        $coLimits = $coTeacherId !== null ? ($this->teacherLimits[$coTeacherId] ?? ['max_per_day' => 7, 'max_per_week' => 36]) : null;

        foreach ($ids as $btId) {
            $teacherKey = "{$lesson['teacher_id']}|{$btId}";
            if (isset($this->teacherBusy[$teacherKey]) || isset($this->teacherBlocked[$teacherKey])) {
                return false;
            }

            if ($coTeacherId !== null) {
                $coTeacherKey = "{$coTeacherId}|{$btId}";
                if (isset($this->teacherBusy[$coTeacherKey]) || isset($this->teacherBlocked[$coTeacherKey])) {
                    return false;
                }
            }

            foreach ($lesson['class_ids'] as $idx => $classId) {
                $sectionId = $lesson['section_ids'][$idx] ?? null;
                if (isset($this->classBusy["{$classId}|{$sectionId}|{$btId}"])) {
                    return false;
                }
            }
        }

        $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
        $dayKey = "{$lesson['teacher_id']}|{$dayOrder}";
        if (($this->teacherDayCount[$dayKey] ?? 0) + count($ids) > $limits['max_per_day']) {
            return false;
        }
        if (($this->teacherWeekCount[$lesson['teacher_id']] ?? 0) + count($ids) > $limits['max_per_week']) {
            return false;
        }

        if ($coTeacherId !== null) {
            $coDayKey = "{$coTeacherId}|{$dayOrder}";
            if (($this->teacherDayCount[$coDayKey] ?? 0) + count($ids) > $coLimits['max_per_day']) {
                return false;
            }
            if (($this->teacherWeekCount[$coTeacherId] ?? 0) + count($ids) > $coLimits['max_per_week']) {
                return false;
            }
        }

        $cap = $lesson['require_consecutive'] ? 2 : 1;
        foreach ($lesson['class_ids'] as $idx => $classId) {
            $sectionId = $lesson['section_ids'][$idx] ?? null;
            $key = "{$classId}|{$sectionId}|{$lesson['subject_id']}|{$dayOrder}";
            if (($this->classSubjectDay[$key] ?? 0) + count($ids) > $cap) {
                return false;
            }
        }

        return true;
    }

    private function commit(array $lesson, array $slot): int
    {
        $ids = $slot['bell_timing_ids'];
        $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
        $coTeacherId = $lesson['co_teacher_id'] ?? null;

        foreach ($ids as $btId) {
            $this->teacherBusy["{$lesson['teacher_id']}|{$btId}"] = true;
            if ($coTeacherId !== null) {
                $this->teacherBusy["{$coTeacherId}|{$btId}"] = true;
            }
            foreach ($lesson['class_ids'] as $idx => $classId) {
                $sectionId = $lesson['section_ids'][$idx] ?? null;
                $this->classBusy["{$classId}|{$sectionId}|{$btId}"] = true;
            }
        }

        $dayKey = "{$lesson['teacher_id']}|{$dayOrder}";
        $this->teacherDayCount[$dayKey] = ($this->teacherDayCount[$dayKey] ?? 0) + count($ids);
        $this->teacherWeekCount[$lesson['teacher_id']] = ($this->teacherWeekCount[$lesson['teacher_id']] ?? 0) + count($ids);

        if ($coTeacherId !== null) {
            $coDayKey = "{$coTeacherId}|{$dayOrder}";
            $this->teacherDayCount[$coDayKey] = ($this->teacherDayCount[$coDayKey] ?? 0) + count($ids);
            $this->teacherWeekCount[$coTeacherId] = ($this->teacherWeekCount[$coTeacherId] ?? 0) + count($ids);
        }

        foreach ($lesson['class_ids'] as $idx => $classId) {
            $sectionId = $lesson['section_ids'][$idx] ?? null;
            $key = "{$classId}|{$sectionId}|{$lesson['subject_id']}|{$dayOrder}";
            $this->classSubjectDay[$key] = ($this->classSubjectDay[$key] ?? 0) + count($ids);
            $loadKey = "{$classId}|{$sectionId}|{$dayOrder}";
            $this->classDayLoad[$loadKey] = ($this->classDayLoad[$loadKey] ?? 0) + count($ids);
        }

        $placementId = $this->nextPlacementId++;

        if ($lesson['type'] === 'solo' && count($ids) === 1) {
            $btId = $ids[0];
            $classId = $lesson['class_ids'][0];
            $sectionId = $lesson['section_ids'][0] ?? null;
            $this->placementByTeacherSlot["{$lesson['teacher_id']}|{$btId}"] = $placementId;
            $this->placementByClassSlot["{$classId}|{$sectionId}|{$btId}"] = $placementId;
        }

        $this->committed[$placementId] = [
            'placement_id' => $placementId,
            'lesson_id' => $lesson['lesson_id'],
            'type' => $lesson['type'],
            'teacher_id' => $lesson['teacher_id'],
            'co_teacher_id' => $coTeacherId,
            'subject_id' => $lesson['subject_id'],
            'class_ids' => $lesson['class_ids'],
            'section_ids' => $lesson['section_ids'],
            'class_name' => $lesson['class_name'],
            'require_consecutive' => $lesson['require_consecutive'],
            'bell_timing_ids' => $ids,
            'combined_class_group_id' => $lesson['type'] === 'combined' ? $lesson['source']['group_id'] : null,
            // Phase 5 (Locked Lessons): carried through to the placements
            // list and on into the newly-written draft row -- a lock
            // survives regeneration, it isn't a one-time pin.
            'is_locked' => !empty($lesson['source']['locked'] ?? false),
            'room_number' => $lesson['room_number'] ?? null,
            // Bugfix (found by the T6 real-data walkthrough): a class-
            // teacher's period-1 reservation is committed BEFORE the normal
            // solve as a permanent fact, but until this flag existed
            // attemptBacktrack() couldn't tell it apart from an ordinary
            // relocatable lesson once it was sitting in $this->committed --
            // an unrelated overloaded lesson elsewhere in the same class
            // could silently bump it to make room for itself. See
            // attemptBacktrack()'s protected-blocker check. A locked slot
            // gets the exact same protection, for the exact same reason.
            'protected' => !empty($lesson['source']['class_teacher_period1'] ?? false) || !empty($lesson['source']['locked'] ?? false),
        ];

        return $placementId;
    }

    /** @return array the removed committed record, so the caller can rebuild a lesson-like array */
    private function uncommitPlacement(int $placementId): array
    {
        $p = $this->committed[$placementId];
        $ids = $p['bell_timing_ids'];
        $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
        $coTeacherId = $p['co_teacher_id'] ?? null;

        foreach ($ids as $btId) {
            unset($this->teacherBusy["{$p['teacher_id']}|{$btId}"]);
            if ($coTeacherId !== null) {
                unset($this->teacherBusy["{$coTeacherId}|{$btId}"]);
            }
            foreach ($p['class_ids'] as $idx => $classId) {
                $sectionId = $p['section_ids'][$idx] ?? null;
                unset($this->classBusy["{$classId}|{$sectionId}|{$btId}"]);
            }
        }

        $dayKey = "{$p['teacher_id']}|{$dayOrder}";
        $this->teacherDayCount[$dayKey] -= count($ids);
        $this->teacherWeekCount[$p['teacher_id']] -= count($ids);

        if ($coTeacherId !== null) {
            $coDayKey = "{$coTeacherId}|{$dayOrder}";
            $this->teacherDayCount[$coDayKey] -= count($ids);
            $this->teacherWeekCount[$coTeacherId] -= count($ids);
        }

        foreach ($p['class_ids'] as $idx => $classId) {
            $sectionId = $p['section_ids'][$idx] ?? null;
            $key = "{$classId}|{$sectionId}|{$p['subject_id']}|{$dayOrder}";
            $this->classSubjectDay[$key] -= count($ids);
            $loadKey = "{$classId}|{$sectionId}|{$dayOrder}";
            $this->classDayLoad[$loadKey] -= count($ids);
        }

        if ($p['type'] === 'solo' && count($ids) === 1) {
            $btId = $ids[0];
            $classId = $p['class_ids'][0];
            $sectionId = $p['section_ids'][0] ?? null;
            unset($this->placementByTeacherSlot["{$p['teacher_id']}|{$btId}"]);
            unset($this->placementByClassSlot["{$classId}|{$sectionId}|{$btId}"]);
        }

        unset($this->committed[$placementId]);

        return $p;
    }

    /**
     * Bounded local backtrack, single-period solo lessons only (see class
     * docblock). For each domain slot (up to the budget), if it's blocked
     * by exactly one relocatable placement, try moving that placement to
     * any other legal slot of its own; if that succeeds and the target
     * slot is now legal for the lesson we're trying to place, use it.
     * Otherwise the blocker is put back exactly where it was before
     * trying the next candidate slot.
     *
     * @return array<int,array{bell_timing_ids: array<int,int>}>
     */
    private function attemptBacktrack(array $lesson): array
    {
        if ($lesson['periods_needed'] !== 1 || $lesson['type'] !== 'solo') {
            return [];
        }

        $classId = $lesson['class_ids'][0];
        $sectionId = $lesson['section_ids'][0] ?? null;
        $attempts = 0;

        foreach ($this->domainSlotsForLesson($lesson) as $slot) {
            if ($attempts >= $this->backtrackBudgetPerLesson) {
                break;
            }

            $btId = $slot['bell_timing_ids'][0];
            $teacherBlockerId = $this->placementByTeacherSlot["{$lesson['teacher_id']}|{$btId}"] ?? null;
            $classBlockerId = $this->placementByClassSlot["{$classId}|{$sectionId}|{$btId}"] ?? null;

            if ($teacherBlockerId === null && $classBlockerId === null) {
                continue; // illegal here for a non-relocatable reason (availability, day/week cap, subject cap)
            }
            if ($teacherBlockerId !== null && $classBlockerId !== null && $teacherBlockerId !== $classBlockerId) {
                continue; // two different placements block this slot; relocating one alone can't free it
            }

            $blockerId = $teacherBlockerId ?? $classBlockerId;
            if (!empty($this->committed[$blockerId]['protected'] ?? false)) {
                continue; // a class-teacher's period-1 reservation is never relocatable -- see commit()
            }

            $attempts++;
            $removed = $this->uncommitPlacement($blockerId);
            $blockerLesson = [
                'lesson_id' => $removed['lesson_id'],
                'type' => 'solo',
                'teacher_id' => $removed['teacher_id'],
                // T6 item 4: preserve the blocker's own co-teacher (if any)
                // through relocation -- dropping it here would silently
                // "free" the co-teacher's busy state at the NEW slot,
                // letting a different lesson double-book them there.
                'co_teacher_id' => $removed['co_teacher_id'] ?? null,
                'subject_id' => $removed['subject_id'],
                'class_ids' => $removed['class_ids'],
                'section_ids' => $removed['section_ids'],
                'class_name' => $removed['class_name'],
                'require_consecutive' => false,
                'periods_needed' => 1,
            ];

            $alternate = null;
            foreach ($this->domainSlotsForLesson($blockerLesson) as $altSlot) {
                if ($altSlot['bell_timing_ids'][0] === $btId) {
                    continue;
                }
                if ($this->isHardLegal($blockerLesson, $altSlot)) {
                    $alternate = $altSlot;
                    break;
                }
            }

            if ($alternate === null) {
                $this->commit($blockerLesson, $slot);

                continue;
            }

            $newBlockerPlacementId = $this->commit($blockerLesson, $alternate);

            if ($this->isHardLegal($lesson, $slot)) {
                return [$slot];
            }

            // The relocation didn't end up freeing $slot for $lesson after
            // all (some other hard constraint on $lesson itself still
            // blocks it) -- put the blocker back exactly where it was
            // before trying the next candidate slot, per this method's
            // documented contract. Without this, the blocker stays
            // permanently (and pointlessly) relocated even though this
            // attempt never placed $lesson.
            $this->uncommitPlacement($newBlockerPlacementId);
            $this->commit($blockerLesson, $slot);
        }

        return [];
    }

    private function pickMostConstrained(Collection $pending, array $domainCountCache): int
    {
        $bestId = null;
        $bestCount = null;
        $bestPeriodsPerWeek = -1;

        foreach ($pending as $id => $lesson) {
            $count = $domainCountCache[$id] ?? 0;
            $periodsPerWeek = $lesson['periods_per_week'];

            if ($bestId === null || $count < $bestCount || ($count === $bestCount && $periodsPerWeek > $bestPeriodsPerWeek)) {
                $bestId = $id;
                $bestCount = $count;
                $bestPeriodsPerWeek = $periodsPerWeek;
            }
        }

        return $bestId;
    }

    /**
     * Slot ordering only -- every candidate here already passed
     * isHardLegal(). Combines the plan's four soft preferences: morning /
     * avoid-last-period (both governed by subjects.prefer_morning per the
     * migration that added it), spread across lighter days, and minimise
     * teacher gaps by preferring a slot next to one they're already
     * committed to that day.
     */
    private function softScore(array $lesson, array $slot): float
    {
        $firstId = $slot['bell_timing_ids'][0];
        $dayOrder = $this->timingMeta[$firstId]['day_order'];
        $dayIds = $this->timingsByDay[$dayOrder] ?? [];

        $maxOrderIndex = 0;
        foreach ($dayIds as $id) {
            $maxOrderIndex = max($maxOrderIndex, $this->timingMeta[$id]['order_index']);
        }
        $orderIndex = $this->timingMeta[$firstId]['order_index'];

        $score = 0.0;

        if ($lesson['prefer_morning']) {
            $score += ($maxOrderIndex - $orderIndex) * 10;
            if ($orderIndex === $maxOrderIndex) {
                $score -= 50;
            }
        }

        $classId = $lesson['class_ids'][0];
        $sectionId = $lesson['section_ids'][0] ?? null;
        $loadKey = "{$classId}|{$sectionId}|{$dayOrder}";
        $score -= ($this->classDayLoad[$loadKey] ?? 0) * 3;

        foreach ($slot['bell_timing_ids'] as $btId) {
            $oi = $this->timingMeta[$btId]['order_index'];
            foreach ($dayIds as $otherId) {
                if ($otherId === $btId) {
                    continue;
                }
                $otherOi = $this->timingMeta[$otherId]['order_index'];
                if (abs($otherOi - $oi) === 1 && isset($this->teacherBusy["{$lesson['teacher_id']}|{$otherId}"])) {
                    $score += 20;
                }
            }
        }

        return $score;
    }

    private function unplacedResult(array $lesson, ?string $reason = null): array
    {
        return [
            'lesson_id' => $lesson['lesson_id'],
            'type' => $lesson['type'],
            'teacher_id' => $lesson['teacher_id'],
            'subject_id' => $lesson['subject_id'],
            'class_ids' => $lesson['class_ids'],
            'label' => $lesson['label'],
            'reason' => $reason ?? $this->buildReason($lesson),
        ];
    }

    /** Tally which hard constraint has zero remaining slots and phrase the dominant one as a sentence. */
    private function buildReason(array $lesson): string
    {
        $teacherBlockCount = 0;
        $classBlockCount = 0;
        $dayCapCount = 0;
        $otherCount = 0;

        foreach ($this->domainSlotsForLesson($lesson) as $slot) {
            $ids = $slot['bell_timing_ids'];
            $teacherBad = false;
            $classBad = false;

            foreach ($ids as $btId) {
                $teacherKey = "{$lesson['teacher_id']}|{$btId}";
                if (isset($this->teacherBusy[$teacherKey]) || isset($this->teacherBlocked[$teacherKey])) {
                    $teacherBad = true;
                }
                foreach ($lesson['class_ids'] as $idx => $classId) {
                    $sectionId = $lesson['section_ids'][$idx] ?? null;
                    if (isset($this->classBusy["{$classId}|{$sectionId}|{$btId}"])) {
                        $classBad = true;
                    }
                }
            }

            if ($teacherBad) {
                $teacherBlockCount++;

                continue;
            }
            if ($classBad) {
                $classBlockCount++;

                continue;
            }

            $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
            $cap = $lesson['require_consecutive'] ? 2 : 1;
            $dayBad = false;
            foreach ($lesson['class_ids'] as $idx => $classId) {
                $sectionId = $lesson['section_ids'][$idx] ?? null;
                $key = "{$classId}|{$sectionId}|{$lesson['subject_id']}|{$dayOrder}";
                if (($this->classSubjectDay[$key] ?? 0) + count($ids) > $cap) {
                    $dayBad = true;
                }
            }

            if ($dayBad) {
                $dayCapCount++;
            } else {
                $otherCount++; // teacher day/week max on every remaining slot
            }
        }

        $teacherName = optional(Teacher::find($lesson['teacher_id']))->name ?? "Teacher #{$lesson['teacher_id']}";
        $subjectName = $lesson['subject_name'];
        $label = $lesson['label'];
        $prefix = "Could not place {$subjectName} for {$label}:";

        $max = max($teacherBlockCount, $classBlockCount, $dayCapCount, $otherCount);

        if ($max === 0) {
            return "{$prefix} no legal slot remained after exhausting available scheduling attempts.";
        }
        if ($teacherBlockCount === $max) {
            return "{$prefix} {$teacherName} has no remaining free slots on any day.";
        }
        if ($classBlockCount === $max) {
            return "{$prefix} every remaining period in {$label}'s week is already occupied.";
        }
        if ($dayCapCount === $max) {
            return "{$prefix} {$subjectName} already appears the maximum allowed times per day on every remaining day.";
        }

        return "{$prefix} {$teacherName} has reached their maximum periods for the day or week on every remaining slot.";
    }
}
