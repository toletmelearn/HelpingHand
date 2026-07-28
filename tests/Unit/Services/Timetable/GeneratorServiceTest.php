<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\CombinedClassGroupMember;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Services\Timetable\GeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T4a: the 5 test scenarios the plan explicitly requires for
 * GeneratorService (a tiny solvable school, a deliberately infeasible
 * input, the consecutive-flag adjacency guarantee, combined-group
 * simultaneous placement, and a realistic 12-section fixture within the
 * time budget), plus generic hard-constraint assertion helpers reused
 * across them.
 */
class GeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int,int> created BellTiming ids, in day-then-period order */
    private function makeGrid(array $days, int $periodsPerDay, string $year): array
    {
        $ids = [];
        foreach ($days as $day) {
            for ($p = 1; $p <= $periodsPerDay; $p++) {
                $timing = BellTiming::create([
                    'day_of_week' => $day,
                    'period_name' => "P{$p}",
                    'start_time' => sprintf('%02d:00:00', 7 + $p),
                    'end_time' => sprintf('%02d:45:00', 7 + $p),
                    'is_active' => true,
                    'is_break' => false,
                    'order_index' => $p,
                    'academic_year' => $year,
                ]);
                $ids[] = $timing->id;
            }
        }

        return $ids;
    }

    private function assertNoTeacherDoubleBooked(array $placements): void
    {
        $seen = [];
        foreach ($placements as $p) {
            foreach ($p['bell_timing_ids'] as $btId) {
                $key = "{$p['teacher_id']}|{$btId}";
                $this->assertArrayNotHasKey($key, $seen, "Teacher {$p['teacher_id']} double-booked at bell timing {$btId}");
                $seen[$key] = true;
            }
        }
    }

    private function assertNoClassDoubleBooked(array $placements): void
    {
        $seen = [];
        foreach ($placements as $p) {
            foreach ($p['bell_timing_ids'] as $btId) {
                $key = "{$p['school_class_id']}|{$p['section_id']}|{$btId}";
                $this->assertArrayNotHasKey($key, $seen, "Class {$p['school_class_id']} double-booked at bell timing {$btId}");
                $seen[$key] = true;
            }
        }
    }

    private function assertSubjectAppearsAtMostOncePerDay(array $placements, int $capPerDay = 1): void
    {
        $allIds = collect($placements)->flatMap(fn ($p) => $p['bell_timing_ids'])->unique();
        $dayOrderById = BellTiming::whereIn('id', $allIds)->get()->keyBy('id')->map(fn ($t) => $t->day_order);

        $counts = [];
        foreach ($placements as $p) {
            foreach ($p['bell_timing_ids'] as $btId) {
                $day = $dayOrderById[$btId];
                $key = "{$p['school_class_id']}|{$p['section_id']}|{$p['subject_id']}|{$day}";
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        foreach ($counts as $key => $count) {
            $this->assertLessThanOrEqual($capPerDay, $count, "Subject exceeded its per-day cap at {$key}");
        }
    }

    public function test_tiny_solvable_school_places_100_percent_and_respects_hard_constraints(): void
    {
        $year = 'T4A-TINY';
        $this->makeGrid(['Monday', 'Tuesday', 'Wednesday', 'Thursday'], 4, $year);

        $classA = SchoolClass::create(['name' => 'Class A', 'class_order' => 1, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class B', 'class_order' => 2, 'is_active' => true]);

        $math = Subject::create(['name' => 'Math', 'code' => 'MTH-T1']);
        $science = Subject::create(['name' => 'Science', 'code' => 'SCI-T1', 'prefer_morning' => true]);
        $english = Subject::create(['name' => 'English', 'code' => 'ENG-T1']);

        $t1 = Teacher::create(['name' => 'Teacher Math', 'status' => 'active']);
        $t2 = Teacher::create(['name' => 'Teacher Science', 'status' => 'active']);
        $t3 = Teacher::create(['name' => 'Teacher English', 'status' => 'active']);

        foreach ([$classA, $classB] as $class) {
            foreach ([[$t1, $math], [$t2, $science], [$t3, $english]] as [$teacher, $subject]) {
                TeacherClassSubjectAssignment::create([
                    'teacher_id' => $teacher->id,
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'periods_per_week' => 4,
                    'academic_year' => $year,
                ]);
            }
        }

        $result = (new GeneratorService())->generate($year, collect([$classA, $classB]));

        $this->assertSame(24, $result['stats']['total_lessons']); // 2 classes x 3 subjects x 4 periods
        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertCount(24, $result['placements']);

        $this->assertNoTeacherDoubleBooked($result['placements']);
        $this->assertNoClassDoubleBooked($result['placements']);
        $this->assertSubjectAppearsAtMostOncePerDay($result['placements']);
    }

    public function test_infeasible_input_reports_unplaced_with_reasons_and_does_not_hang(): void
    {
        $year = 'T4A-INFEASIBLE';
        $ids = $this->makeGrid(['Monday', 'Tuesday'], 2, $year); // 4 slots total

        $class = SchoolClass::create(['name' => 'Class X', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Overloaded', 'code' => 'OVL-T2']);
        $teacher = Teacher::create(['name' => 'Busy Teacher', 'status' => 'active']);

        // Block 3 of the 4 slots for this teacher, leaving exactly 1 free.
        foreach (array_slice($ids, 0, 3) as $btId) {
            TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $btId, 'is_available' => false]);
        }

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 3,
            'academic_year' => $year,
        ]);

        $start = microtime(true);
        $result = (new GeneratorService())->generate($year, collect([$class]));
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5.0, $elapsed, 'A small infeasible instance must resolve quickly, not hang');
        $this->assertCount(1, $result['placements']);
        $this->assertCount(2, $result['unplaced']);

        foreach ($result['unplaced'] as $unplaced) {
            $this->assertStringContainsString('Busy Teacher', $unplaced['reason']);
            $this->assertStringContainsString('no remaining free slots', $unplaced['reason']);
        }
    }

    public function test_consecutive_flag_places_double_periods_adjacently(): void
    {
        $year = 'T4A-CONSECUTIVE';
        $this->makeGrid(['Monday'], 4, $year);

        $class = SchoolClass::create(['name' => 'Class Y', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Double Lab', 'code' => 'DBL-T3']);
        $teacher = Teacher::create(['name' => 'Lab Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 2,
            'require_consecutive' => true,
            'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertCount(1, $result['placements']);

        $placement = $result['placements'][0];
        $this->assertCount(2, $placement['bell_timing_ids']);

        $orderIndexes = BellTiming::whereIn('id', $placement['bell_timing_ids'])
            ->pluck('order_index')
            ->sort()
            ->values();

        $this->assertSame(1, $orderIndexes[1] - $orderIndexes[0], 'The two periods of a consecutive-flagged double lesson must be adjacent');
    }

    public function test_combined_group_lessons_place_simultaneously_for_all_member_classes(): void
    {
        $year = 'T4A-COMBINED';
        $this->makeGrid(['Monday', 'Tuesday'], 2, $year); // 4 slots, class_section null

        $session = AcademicSession::create([
            'name' => $year, 'code' => $year, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);
        $classA = SchoolClass::create(['name' => 'Combined A', 'class_order' => 1, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Combined B', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Sanskrit', 'code' => 'SANS-T4']);
        $teacher = Teacher::create(['name' => 'Combined Teacher', 'status' => 'active']);

        $group = CombinedClassGroup::create([
            'name' => 'Sanskrit Combined',
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 2,
        ]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classA->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classB->id]);

        $result = (new GeneratorService())->generate($year, collect([$classA, $classB]), $session->id);

        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertCount(4, $result['placements']); // 2 periods x 2 member classes

        $byBellTiming = collect($result['placements'])->groupBy(fn ($p) => $p['bell_timing_ids'][0]);
        $this->assertCount(2, $byBellTiming); // 2 distinct periods used

        foreach ($byBellTiming as $rows) {
            $this->assertSame(
                [$classA->id, $classB->id],
                $rows->pluck('school_class_id')->sort()->values()->all(),
                'Both member classes must be placed at the same bell timing'
            );
            $this->assertSame([$teacher->id], $rows->pluck('teacher_id')->unique()->values()->all());
            foreach ($rows as $row) {
                $this->assertSame($group->id, $row['combined_class_group_id']);
            }
        }
    }

    public function test_realistic_twelve_section_fixture_solves_within_time_budget(): void
    {
        $year = 'T4A-REALISTIC';
        $this->makeGrid(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 8, $year); // 48 slots/week

        $classes = collect(range(1, 12))->map(fn ($i) => SchoolClass::create([
            'name' => "Section {$i}", 'class_order' => $i, 'is_active' => true,
        ]));

        $subjects = collect([
            Subject::create(['name' => 'Math', 'code' => 'MTH-T5', 'prefer_morning' => true]),
            Subject::create(['name' => 'Science', 'code' => 'SCI-T5']),
            Subject::create(['name' => 'English', 'code' => 'ENG-T5']),
            Subject::create(['name' => 'Hindi', 'code' => 'HIN-T5']),
        ]);

        foreach ($subjects as $subject) {
            $teacherA = Teacher::create([
                'name' => "{$subject->name} Teacher A", 'status' => 'active',
                'max_periods_per_day' => 8, 'max_periods_per_week' => 40,
            ]);
            $teacherB = Teacher::create([
                'name' => "{$subject->name} Teacher B", 'status' => 'active',
                'max_periods_per_day' => 8, 'max_periods_per_week' => 40,
            ]);

            foreach ($classes->take(6) as $class) {
                TeacherClassSubjectAssignment::create([
                    'teacher_id' => $teacherA->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
                    'periods_per_week' => 5, 'academic_year' => $year,
                ]);
            }
            foreach ($classes->slice(6, 6) as $class) {
                TeacherClassSubjectAssignment::create([
                    'teacher_id' => $teacherB->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
                    'periods_per_week' => 5, 'academic_year' => $year,
                ]);
            }
        }

        $start = microtime(true);
        $result = (new GeneratorService())->generate($year, $classes);
        $elapsed = microtime(true) - $start;

        $this->assertSame(240, $result['stats']['total_lessons']); // 12 classes x 4 subjects x 5 periods
        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertLessThan(30.0, $elapsed, 'A realistic 12-section fixture should solve well inside the 60s budget');

        $this->assertNoTeacherDoubleBooked($result['placements']);
        $this->assertNoClassDoubleBooked($result['placements']);
    }
}
