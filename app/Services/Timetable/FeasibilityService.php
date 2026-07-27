<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

/**
 * Timetable-module T1b: read-only feasibility report. Computes, for a
 * given academic year string (matching the free-text `academic_year`
 * column shared by bell_timings/timetable_slots -- there is no FK to
 * academic_sessions on either table):
 *
 *   1. Grid capacity per class-section: placed vs capacity (T2 will add
 *      "required" once periods_per_week exists on assignments).
 *   2. Teacher load: periods/week, busiest day, days with zero free
 *      periods, flagged over config('timetable.max_periods_per_week').
 *   3. Conflict scan: duplicate-key violations that predate the T1a DB
 *      constraints (should be zero -- this proves the constraints work),
 *      plus slots referencing inactive teachers/subjects/classes.
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
 * rather than silently trusted).
 */
class FeasibilityService
{
    public function build(?string $academicYear): array
    {
        $activeTimings = BellTiming::query()
            ->where('is_active', true)
            ->where('is_break', false)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $slots = TimetableSlot::with(['schoolClass', 'section', 'bellTiming', 'teacher', 'subject'])
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        return [
            'academic_year' => $academicYear,
            'grid_capacity' => $this->gridCapacity($activeTimings, $slots),
            'teacher_load' => $this->teacherLoad($activeTimings, $slots),
            'conflicts' => $this->conflictScan($academicYear),
            'threshold' => (int) config('timetable.max_periods_per_week', 36),
        ];
    }

    private function gridCapacity(Collection $activeTimings, Collection $slots): array
    {
        $classes = SchoolClass::active()->orderByOrder()->get();
        $rows = [];

        foreach ($classes as $class) {
            $classCapacity = $activeTimings
                ->filter(fn (BellTiming $t) => $t->class_section === null || $t->class_section === $class->name)
                ->count();

            $classSlots = $slots->where('school_class_id', $class->id);

            $sectionGroups = $classSlots->groupBy('section_id');

            if ($sectionGroups->isEmpty()) {
                $rows[] = $this->gridRow($class, null, $classCapacity, 0);
                continue;
            }

            foreach ($sectionGroups as $sectionId => $sectionSlots) {
                $section = $sectionId ? Section::find($sectionId) : null;
                $rows[] = $this->gridRow($class, $section, $classCapacity, $sectionSlots->count());
            }
        }

        return $rows;
    }

    private function gridRow(SchoolClass $class, ?Section $section, int $capacity, int $placed): array
    {
        $label = $section ? "{$class->name}{$section->name}" : $class->name;
        $empty = max(0, $capacity - $placed);

        if ($capacity === 0) {
            $sentence = "{$label} has no active teaching periods configured for this academic year.";
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
            'sentence' => $sentence,
        ];
    }

    private function teacherLoad(Collection $activeTimings, Collection $slots): array
    {
        $operatingDays = $activeTimings->pluck('day_of_week')->unique()->values();
        $threshold = (int) config('timetable.max_periods_per_week', 36);

        $teachers = Teacher::active()->orderBy('name')->get();
        $rows = [];

        foreach ($teachers as $teacher) {
            $teacherSlots = $slots->where('teacher_id', $teacher->id);
            $placed = $teacherSlots->count();

            $byDay = $teacherSlots->groupBy(fn (TimetableSlot $s) => $s->bellTiming?->day_of_week);

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

            if ($placed === 0) {
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
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

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
