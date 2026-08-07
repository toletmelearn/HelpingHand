<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\ClassTeacherAssignment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

/**
 * Timetable-module T1b: read-only feasibility report. Computes, for a
 * given academic year string (matching the free-text `academic_year`
 * column shared by bell_timings/timetable_slots -- there is no FK to
 * academic_sessions on either table):
 *
 *   1. Grid capacity per class-section: placed vs capacity, plus (T2a)
 *      required periods -- the sum of periods_per_week across that
 *      class-section's teacher_class_subject_assignments -- flagged when
 *      required exceeds what the week can hold.
 *   2. Teacher load: periods/week, busiest day, days with zero free
 *      periods, flagged over config('timetable.max_periods_per_week');
 *      plus (T2a) required periods (sum of periods_per_week across that
 *      teacher's assignments) vs how many periods they're actually
 *      available for (active periods minus their blocked
 *      TeacherAvailability rows). T6 item 4 fix: a team-taught slot lists
 *      the teacher in EITHER teacher_id or co_teacher_id -- both count
 *      toward a teacher's own placed/required periods, or a co-teacher's
 *      load was invisible to their own row.
 *   3. Conflict scan: duplicate-key violations that predate the T1a DB
 *      constraints (should be zero -- this proves the constraints work),
 *      plus slots referencing inactive teachers/subjects/classes.
 *   4. (T6 item 1, revised) Class-teacher readiness: classes with no
 *      designated class-teacher at all (neither an is_class_teacher-
 *      flagged assignment nor an active legacy ClassTeacherAssignment
 *      row), listed as plain notes -- NOT conflicts -- since this is
 *      normal/expected for a school still being set up.
 *
 * T2b: capacity math now filters on bell_timings.period_type = 'teaching'
 * (was is_break = false) -- assembly/prayer/zero/dispersal periods are
 * real, printed periods but aren't teaching capacity. A combined-class
 * group's placement writes one TimetableSlot row per member class
 * (T2b item 3); grid_capacity counts those correctly with zero changes
 * needed (each member class's own row is genuinely occupied once), but
 * teacher_load's placed-period counts collapse a combined group's N
 * member rows back down to the ONE period they actually represent on
 * that teacher's day (see the `unique()` calls in teacherLoad()) --
 * otherwise a single combined period would be counted N times against
 * the teacher's week.
 *
 * Data-model note that shapes #1's design: `sections` is a shared,
 * unscoped label pool in this schema (sections.class_id is NULL for
 * every row in the live data, despite the column existing) -- a
 * "class-section" is not a pre-declared relationship, it only exists as
 * a fact once a timetable_slots row actually places a (school_class_id,
 * section_id) pair. So grid capacity is reported per SchoolClass, broken
 * down by whichever section_id values actually appear in that class's
 * placed slots (plus a "whole class / unsectioned" row when
 * section_id is null) -- driven by real data, not an assumed FK that
 * doesn't hold here. Capacity itself also accounts for
 * bell_timings.class_section, a free-text column that can scope a
 * bell timing to one class by name match (best-effort string match
 * against SchoolClass::name -- the same kind of legacy-string field this
 * codebase has needed several targeted fixes for elsewhere; flagged here
 * rather than silently trusted). T6 item 3: a class's capacity also
 * excludes any order_index beyond its own school_classes.
 * last_teaching_period (null = uncapped), matching the same ceiling the
 * generator now honours.
 */
class FeasibilityService
{
    public function build(?string $academicYear): array
    {
        $activeTimings = BellTiming::query()
            ->where('is_active', true)
            ->teachingType()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $slots = TimetableSlot::with(['schoolClass', 'section', 'bellTiming', 'teacher', 'subject'])
            ->published() // T4b: the feasibility report is about the LIVE timetable, never a draft proposal
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        return [
            'academic_year' => $academicYear,
            'grid_capacity' => $this->gridCapacity($activeTimings, $slots),
            'teacher_load' => $this->teacherLoad($activeTimings, $slots),
            'conflicts' => $this->conflictScan($academicYear),
            'class_teacher_readiness' => $this->classTeacherReadiness(),
            'threshold' => (int) config('timetable.max_periods_per_week', 36),
        ];
    }

    /**
     * T6 item 1 (revised): classes with NO designated class-teacher at all
     * -- neither an is_class_teacher-flagged teacher_class_subject_
     * assignments row nor an active legacy class_teacher_assignments row
     * (App\Models\ClassTeacherAssignment) -- surfaced here as a plain
     * readiness note, not an error (contrast with GeneratorService's
     * per-generation warning for the narrower case of a class that HAS a
     * designated class-teacher but no subject assigned to anchor period 1).
     */
    private function classTeacherReadiness(): array
    {
        $classes = SchoolClass::active()->orderByOrder()->get();

        $flaggedClassIds = TeacherClassSubjectAssignment::whereIn('class_id', $classes->pluck('id'))
            ->where('is_class_teacher', true)
            ->pluck('class_id')
            ->unique();

        $legacyClassNames = ClassTeacherAssignment::whereIn('assigned_class', $classes->pluck('name'))
            ->active()
            ->get()
            ->filter(fn (ClassTeacherAssignment $a) => $a->isCurrentlyAssigned())
            ->pluck('assigned_class')
            ->unique();

        $rows = [];
        foreach ($classes as $class) {
            if ($flaggedClassIds->contains($class->id) || $legacyClassNames->contains($class->name)) {
                continue;
            }

            $rows[] = [
                'school_class_id' => $class->id,
                'class_name' => $class->name,
                'sentence' => "{$class->name} has no class teacher assigned.",
            ];
        }

        return $rows;
    }

    private function gridCapacity(Collection $activeTimings, Collection $slots): array
    {
        $classes = SchoolClass::active()->orderByOrder()->get();
        $assignments = TeacherClassSubjectAssignment::whereIn('class_id', $classes->pluck('id'))->get();
        $rows = [];

        foreach ($classes as $class) {
            $classCapacity = $activeTimings
                ->filter(fn (BellTiming $t) => $t->class_section === null || $t->class_section === $class->name)
                ->filter(fn (BellTiming $t) => ! $class->last_teaching_period || $t->order_index <= $class->last_teaching_period)
                ->count();

            // A combined group's slots naturally count once per member
            // class here already -- each member class gets exactly one
            // TimetableSlot row of its own, so no dedup is needed on this
            // side (contrast with teacherLoad(), which must dedup by
            // combined_class_group_id).
            $classSlots = $slots->where('school_class_id', $class->id);
            $classAssignments = $assignments->where('class_id', $class->id);

            $sectionGroups = $classSlots->groupBy('section_id');

            if ($sectionGroups->isEmpty()) {
                $required = $this->requiredPeriods($classAssignments, null);
                $rows[] = $this->gridRow($class, null, $classCapacity, 0, $required);
                continue;
            }

            foreach ($sectionGroups as $sectionId => $sectionSlots) {
                $section = $sectionId ? Section::find($sectionId) : null;
                $required = $this->requiredPeriods($classAssignments, $sectionId);
                $rows[] = $this->gridRow($class, $section, $classCapacity, $sectionSlots->count(), $required);
            }
        }

        return $rows;
    }

    /**
     * Sum of periods_per_week for assignments that apply to this
     * class-section: a class-wide assignment (section_id null) covers
     * every section, matching TimetableSlotPolicy's own null-matching
     * semantics.
     */
    private function requiredPeriods(Collection $classAssignments, $sectionId): int
    {
        return (int) $classAssignments
            ->filter(fn (TeacherClassSubjectAssignment $a) => $a->section_id === null || $a->section_id === $sectionId)
            ->sum('periods_per_week');
    }

    private function gridRow(SchoolClass $class, ?Section $section, int $capacity, int $placed, int $required = 0): array
    {
        $label = $section ? "{$class->name}{$section->name}" : $class->name;
        $empty = max(0, $capacity - $placed);

        if ($capacity === 0) {
            $sentence = "{$label} has no active teaching periods configured for this academic year.";
        } elseif ($required > $capacity) {
            $sentence = "{$label} requires {$required} periods but the week has {$capacity}.";
        } elseif ($placed === 0) {
            $sentence = "{$label} has 0 of {$capacity} periods placed -- the whole week is empty.";
        } elseif ($empty > 0) {
            $sentence = "{$label} has {$empty} empty " . ($empty === 1 ? 'period' : 'periods') . " out of {$capacity}.";
        } else {
            $sentence = "{$label} is fully placed ({$placed} of {$capacity} periods).";
        }

        return [
            'school_class_id' => $class->id,
            'class_name' => $class->name,
            'section_id' => $section?->id,
            'section_name' => $section?->name,
            'label' => $label,
            'capacity' => $capacity,
            'placed' => $placed,
            'empty' => $empty,
            'required' => $required,
            'over_required' => $required > $capacity,
            'sentence' => $sentence,
        ];
    }

    private function teacherLoad(Collection $activeTimings, Collection $slots): array
    {
        $operatingDays = $activeTimings->pluck('day_of_week')->unique()->values();
        $threshold = (int) config('timetable.max_periods_per_week', 36);
        $activeTimingIds = $activeTimings->pluck('id');

        $teachers = Teacher::active()->orderBy('name')->get();
        $requiredByTeacher = TeacherClassSubjectAssignment::whereIn('teacher_id', $teachers->pluck('id'))
            ->selectRaw('teacher_id, SUM(periods_per_week) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');
        // T6 item 4 fix: a co-teacher's own required periods were invisible
        // here before -- an assignment's periods_per_week was only ever
        // summed against the PRIMARY teacher_id.
        $requiredByCoTeacher = TeacherClassSubjectAssignment::whereIn('co_teacher_id', $teachers->pluck('id'))
            ->selectRaw('co_teacher_id, SUM(periods_per_week) as total')
            ->groupBy('co_teacher_id')
            ->pluck('total', 'co_teacher_id');
        $blockedByTeacher = TeacherAvailability::whereIn('teacher_id', $teachers->pluck('id'))
            ->where('is_available', false)
            ->whereIn('bell_timing_id', $activeTimingIds)
            ->selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $rows = [];

        foreach ($teachers as $teacher) {
            // T6 item 4 fix: a team-taught slot lists this teacher in
            // EITHER teacher_id or co_teacher_id -- both must count toward
            // their own load, or a co-teacher's periods were invisible to
            // their own row entirely.
            $teacherSlots = $slots->filter(
                fn (TimetableSlot $s) => $s->teacher_id === $teacher->id || $s->co_teacher_id === $teacher->id
            );

            // A combined-class group writes one TimetableSlot row per
            // member class, all for the SAME period -- collapse those
            // back to the single period they represent on this teacher's
            // day before counting anything. Solo rows (combined_class_
            // group_id null) each keep their own identity via their own
            // row id, so they're never accidentally collapsed together.
            $distinctTeacherSlots = $teacherSlots->unique(
                fn (TimetableSlot $s) => $s->combined_class_group_id ?? 'solo_' . $s->id
            );

            $placed = $distinctTeacherSlots->count();

            $byDay = $distinctTeacherSlots->groupBy(fn (TimetableSlot $s) => $s->bellTiming?->day_of_week);

            $busiestDay = null;
            $busiestCount = 0;
            foreach ($byDay as $day => $daySlots) {
                if ($day !== null && $daySlots->count() > $busiestCount) {
                    $busiestDay = $day;
                    $busiestCount = $daySlots->count();
                }
            }

            $daysWithZeroFreePeriods = 0;
            foreach ($operatingDays as $day) {
                $dayCapacity = $activeTimings->where('day_of_week', $day)->count();
                $dayPlaced = $byDay->get($day, collect())->count();
                if ($dayCapacity > 0 && $dayPlaced >= $dayCapacity) {
                    $daysWithZeroFreePeriods++;
                }
            }

            $overThreshold = $placed > $threshold;

            $required = (int) ($requiredByTeacher[$teacher->id] ?? 0) + (int) ($requiredByCoTeacher[$teacher->id] ?? 0);
            $available = $activeTimingIds->count() - (int) ($blockedByTeacher[$teacher->id] ?? 0);
            $overAvailable = $required > $available;

            if ($overAvailable) {
                $sentence = "{$teacher->name} requires {$required} periods but is available for only {$available}.";
            } elseif ($placed === 0) {
                $sentence = "{$teacher->name} has no periods placed.";
            } elseif ($overThreshold) {
                $sentence = "{$teacher->name} is placed for {$placed} periods but the week has {$threshold} slots.";
            } else {
                $sentence = "{$teacher->name} is placed for {$placed} periods" .
                    ($busiestDay ? ", busiest on {$busiestDay} ({$busiestCount})." : '.');
            }

            $rows[] = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->name,
                'placed_periods' => $placed,
                'busiest_day' => $busiestDay,
                'busiest_day_count' => $busiestCount,
                'days_with_zero_free_periods' => $daysWithZeroFreePeriods,
                'over_threshold' => $overThreshold,
                'required_periods' => $required,
                'available_periods' => $available,
                'over_available' => $overAvailable,
                'sentence' => $sentence,
            ];
        }

        return $rows;
    }

    private function conflictScan(?string $academicYear): array
    {
        $conflicts = [];

        // These predate the T1a unique constraints and should now be
        // structurally impossible -- this query proves it, live, rather
        // than just trusting the migration ran.
        $classDupes = TimetableSlot::query()
            ->published()
            ->select('school_class_id', 'section_id', 'bell_timing_id')
            ->selectRaw('count(*) as c')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->groupBy('school_class_id', 'section_id', 'bell_timing_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($classDupes as $dupe) {
            $conflicts[] = [
                'type' => 'class_duplicate',
                'sentence' => "Class/section {$dupe->school_class_id}/{$dupe->section_id} has {$dupe->c} slots at the same period (bell timing {$dupe->bell_timing_id}) -- this should be impossible after T1a.",
            ];
        }

        $teacherDupes = TimetableSlot::query()
            ->published()
            ->select('teacher_id', 'bell_timing_id')
            ->selectRaw('count(*) as c')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->groupBy('teacher_id', 'bell_timing_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($teacherDupes as $dupe) {
            $conflicts[] = [
                'type' => 'teacher_duplicate',
                'sentence' => "Teacher {$dupe->teacher_id} has {$dupe->c} slots at the same period (bell timing {$dupe->bell_timing_id}) -- this should be impossible after T1a.",
            ];
        }

        $slots = TimetableSlot::with(['teacher', 'subject', 'schoolClass'])
            ->published()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        // T1a's own documented gap, closed here: a class-wide slot
        // (section_id NULL) and a specific-section slot of the SAME class
        // at the SAME period have different section_id values, so the
        // exact-match groupBy above (and the DB unique index it mirrors)
        // never sees them as the same key -- they still overlap in
        // practice (a class-wide lesson covers every section). Grouped
        // in-memory off the already-loaded $slots rather than a second
        // query.
        $byClassAndPeriod = $slots->groupBy(fn ($slot) => $slot->school_class_id . '|' . $slot->bell_timing_id);
        foreach ($byClassAndPeriod as $key => $rows) {
            $classWide = $rows->firstWhere('section_id', null);
            $sectionSpecific = $rows->first(fn ($r) => $r->section_id !== null);
            if ($classWide && $sectionSpecific) {
                [$classId, $bellTimingId] = explode('|', $key);
                $className = $classWide->schoolClass->name ?? "Class {$classId}";
                $sectionName = $sectionSpecific->section->name ?? 'section-specific';
                $conflicts[] = [
                    'type' => 'class_wide_section_overlap',
                    'sentence' => "{$className} has both a whole-class slot and a {$sectionName} slot at the same period (bell timing {$bellTimingId}) -- these overlap even though they aren't an exact duplicate.",
                ];
            }
        }

        foreach ($slots as $slot) {
            if (!$slot->teacher || $slot->teacher->status !== 'active') {
                $conflicts[] = [
                    'type' => 'inactive_teacher',
                    'sentence' => "Timetable slot #{$slot->id} references " . ($slot->teacher ? "an inactive teacher ({$slot->teacher->name})" : 'a teacher that no longer exists') . '.',
                ];
            }
            if (!$slot->subject || !$slot->subject->is_active) {
                $conflicts[] = [
                    'type' => 'inactive_subject',
                    'sentence' => "Timetable slot #{$slot->id} references " . ($slot->subject ? "an inactive subject ({$slot->subject->name})" : 'a subject that no longer exists') . '.',
                ];
            }
            if (!$slot->schoolClass || !$slot->schoolClass->is_active) {
                $conflicts[] = [
                    'type' => 'inactive_class',
                    'sentence' => "Timetable slot #{$slot->id} references " . ($slot->schoolClass ? "an inactive class ({$slot->schoolClass->name})" : 'a class that no longer exists') . '.',
                ];
            }
        }

        return $conflicts;
    }
}
