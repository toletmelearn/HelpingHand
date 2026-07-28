<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
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
 *   them, since only BellTiming::teachingType() rows are ever loaded).
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
 * If the backtrack budget for a lesson is exhausted, it is marked
 * UNPLACED with a human-readable reason naming the constraint that had
 * zero remaining slots, and the run continues -- best effort, never an
 * infinite loop. A wall-clock time budget
 * (config('timetable.generator.time_budget_seconds'), default 60s) stops
 * the whole run and returns the best state found so far.
 */
class GeneratorService
{
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

    private array $unplaced = [];

    private int $nextLessonId = 1;

    private int $nextPlacementId = 1;

    /**
     * @param  Collection<int,\App\Models\SchoolClass>  $schoolClasses  the set of classes to generate for
     * @return array{placements: array, unplaced: array, stats: array}
     */
    public function generate(?string $academicYear, Collection $schoolClasses, ?int $academicSessionId = null): array
    {
        $this->resetState();

        $this->timeBudgetSeconds = (int) config('timetable.generator.time_budget_seconds', 60);
        $this->backtrackBudgetPerLesson = (int) config('timetable.generator.backtrack_budget_per_lesson', 25);
        $startedAt = microtime(true);
        $this->deadline = $startedAt + $this->timeBudgetSeconds;

        $classIds = $schoolClasses->pluck('id');
        $classesById = $schoolClasses->keyBy('id');

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

        $lessons = $this->buildLessons($academicYear, $academicSessionId, $classIds, $classesById);
        $this->loadTeacherLimits(collect($lessons));

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

            foreach ($pending as $id => $other) {
                if ($other['teacher_id'] === $lesson['teacher_id'] || array_intersect($other['class_ids'], $lesson['class_ids'])) {
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
                    'bell_timing_ids' => $p['bell_timing_ids'],
                    'combined_class_group_id' => $p['combined_class_group_id'],
                ];
            }
        }

        return [
            'placements' => $placements,
            'unplaced' => $this->unplaced,
            'stats' => [
                'total_lessons' => count($lessons),
                'placed_lessons' => count($this->committed),
                'placed_rows' => count($placements),
                'unplaced_lessons' => count($this->unplaced),
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

    private function loadTeacherLimits(Collection $lessons): void
    {
        $teacherIds = $lessons->pluck('teacher_id')->unique();
        $this->teacherLimits = Teacher::whereIn('id', $teacherIds)->get()
            ->mapWithKeys(fn ($t) => [$t->id => [
                'max_per_day' => (int) $t->max_periods_per_day,
                'max_per_week' => (int) $t->max_periods_per_week,
            ]])
            ->all();
    }

    /**
     * Build the lesson list: one unit per period, except require_consecutive
     * assignments which are pre-paired into double-period units (see
     * splitIntoPairs()) so adjacency is guaranteed by domain generation
     * rather than checked after the fact.
     */
    private function buildLessons(?string $academicYear, ?int $academicSessionId, Collection $classIds, Collection $classesById): array
    {
        $lessons = [];

        $assignments = TeacherClassSubjectAssignment::with(['subject'])
            ->whereIn('class_id', $classIds)
            ->whereNotNull('periods_per_week')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $sectionsById = Section::whereIn('id', $assignments->pluck('section_id')->filter()->unique())->get()->keyBy('id');

        foreach ($assignments as $assignment) {
            $class = $classesById->get($assignment->class_id);
            $subject = $assignment->subject;
            if (! $class || ! $subject) {
                continue;
            }

            $preferMorning = (bool) $subject->prefer_morning;
            $requireConsecutive = (bool) $assignment->require_consecutive;
            $periodsPerWeek = (int) $assignment->periods_per_week;
            $section = $assignment->section_id ? $sectionsById->get($assignment->section_id) : null;
            $label = $section ? "{$class->name}{$section->name}" : $class->name;

            $units = $requireConsecutive ? $this->splitIntoPairs($periodsPerWeek) : array_fill(0, $periodsPerWeek, 1);

            foreach ($units as $periodsNeeded) {
                $lessons[] = [
                    'lesson_id' => $this->nextLessonId++,
                    'type' => 'solo',
                    'teacher_id' => $assignment->teacher_id,
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
     * FeasibilityService's own class_section text match), as singles or,
     * for a double-period lesson, as adjacent pairs within one day's own
     * filtered/sorted teaching-period list (non-teaching periods are
     * already excluded from that list, so "adjacent in the list" already
     * means "the next period a student would actually sit through").
     *
     * @return array<int,array{bell_timing_ids: array<int,int>}>
     */
    private function domainSlotsForLesson(array $lesson): array
    {
        $slots = [];

        foreach ($this->timingsByDay as $ids) {
            $filtered = array_values(array_filter($ids, function ($btId) use ($lesson) {
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

        foreach ($ids as $btId) {
            $teacherKey = "{$lesson['teacher_id']}|{$btId}";
            if (isset($this->teacherBusy[$teacherKey]) || isset($this->teacherBlocked[$teacherKey])) {
                return false;
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

        foreach ($ids as $btId) {
            $this->teacherBusy["{$lesson['teacher_id']}|{$btId}"] = true;
            foreach ($lesson['class_ids'] as $idx => $classId) {
                $sectionId = $lesson['section_ids'][$idx] ?? null;
                $this->classBusy["{$classId}|{$sectionId}|{$btId}"] = true;
            }
        }

        $dayKey = "{$lesson['teacher_id']}|{$dayOrder}";
        $this->teacherDayCount[$dayKey] = ($this->teacherDayCount[$dayKey] ?? 0) + count($ids);
        $this->teacherWeekCount[$lesson['teacher_id']] = ($this->teacherWeekCount[$lesson['teacher_id']] ?? 0) + count($ids);

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
            'subject_id' => $lesson['subject_id'],
            'class_ids' => $lesson['class_ids'],
            'section_ids' => $lesson['section_ids'],
            'class_name' => $lesson['class_name'],
            'require_consecutive' => $lesson['require_consecutive'],
            'bell_timing_ids' => $ids,
            'combined_class_group_id' => $lesson['type'] === 'combined' ? $lesson['source']['group_id'] : null,
        ];

        return $placementId;
    }

    /** @return array the removed committed record, so the caller can rebuild a lesson-like array */
    private function uncommitPlacement(int $placementId): array
    {
        $p = $this->committed[$placementId];
        $ids = $p['bell_timing_ids'];
        $dayOrder = $this->timingMeta[$ids[0]]['day_order'];

        foreach ($ids as $btId) {
            unset($this->teacherBusy["{$p['teacher_id']}|{$btId}"]);
            foreach ($p['class_ids'] as $idx => $classId) {
                $sectionId = $p['section_ids'][$idx] ?? null;
                unset($this->classBusy["{$classId}|{$sectionId}|{$btId}"]);
            }
        }

        $dayKey = "{$p['teacher_id']}|{$dayOrder}";
        $this->teacherDayCount[$dayKey] -= count($ids);
        $this->teacherWeekCount[$p['teacher_id']] -= count($ids);

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

            $attempts++;
            $blockerId = $teacherBlockerId ?? $classBlockerId;
            $removed = $this->uncommitPlacement($blockerId);
            $blockerLesson = [
                'lesson_id' => $removed['lesson_id'],
                'type' => 'solo',
                'teacher_id' => $removed['teacher_id'],
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

            $this->commit($blockerLesson, $alternate);

            if ($this->isHardLegal($lesson, $slot)) {
                return [$slot];
            }
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
