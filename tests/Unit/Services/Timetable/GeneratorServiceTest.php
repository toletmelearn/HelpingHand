<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\ClassTeacherAssignment;
use App\Models\CombinedClassGroup;
use App\Models\CombinedClassGroupMember;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;
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

    /**
     * T6 item 1: the class-teacher period-1 rule, built on
     * teacher_class_subject_assignments.is_class_teacher per the approved
     * REPORT-THEN-STOP finding.
     */
    public function test_class_teacher_holds_period_1_every_day(): void
    {
        $year = 'T6-CT-DAILY';
        $ids = $this->makeGrid(['Monday', 'Tuesday', 'Wednesday', 'Thursday'], 4, $year);

        $class = SchoolClass::create(['name' => 'CT Class', 'class_order' => 1, 'is_active' => true]);
        $ctSubject = Subject::create(['name' => 'Maths', 'code' => 'CTM-T6']);
        $ctTeacher = Teacher::create(['name' => 'Class Teacher', 'status' => 'active']);
        $otherSubject = Subject::create(['name' => 'Science', 'code' => 'CTS-T6']);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $ctTeacher->id, 'class_id' => $class->id, 'subject_id' => $ctSubject->id,
            'periods_per_week' => 4, 'is_class_teacher' => true, 'academic_year' => $year,
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $otherTeacher->id, 'class_id' => $class->id, 'subject_id' => $otherSubject->id,
            'periods_per_week' => 3, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertSame([], $result['warnings']);

        // Period 1 of every day: the 4 lowest-order_index bell timings, one per day.
        $period1Ids = BellTiming::where('order_index', 1)->pluck('id');
        $this->assertCount(4, $period1Ids);

        $placementsAtPeriod1 = collect($result['placements'])->filter(
            fn ($p) => in_array($p['bell_timing_ids'][0], $period1Ids->all(), true) && count($p['bell_timing_ids']) === 1
        );

        $this->assertCount(4, $placementsAtPeriod1, 'The class teacher must hold exactly period 1 on all 4 days');
        foreach ($placementsAtPeriod1 as $p) {
            $this->assertSame($ctTeacher->id, $p['teacher_id']);
            $this->assertSame($ctSubject->id, $p['subject_id']);
        }
    }

    public function test_class_with_no_class_teacher_still_generates(): void
    {
        $year = 'T6-NO-CT';
        $this->makeGrid(['Monday', 'Tuesday'], 2, $year);

        $class = SchoolClass::create(['name' => 'No CT Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Art', 'code' => 'NCT-T6']);
        $teacher = Teacher::create(['name' => 'Art Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 2, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertSame([], $result['warnings']);
        $this->assertCount(2, $result['placements']);
    }

    public function test_same_teacher_class_teacher_of_two_classes_sharing_period_1_reports_a_warning(): void
    {
        $year = 'T6-CT-CLASH';
        // class_section left null on every timing, so period 1 of a given
        // day is the SAME physical bell_timing_id for every class -- this
        // is what makes one teacher holding period 1 in two classes
        // simultaneously structurally impossible.
        $this->makeGrid(['Monday', 'Tuesday'], 2, $year);

        $classA = SchoolClass::create(['name' => 'Clash Class A', 'class_order' => 1, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Clash Class B', 'class_order' => 2, 'is_active' => true]);
        $subjectA = Subject::create(['name' => 'Maths', 'code' => 'CLA-T6']);
        $subjectB = Subject::create(['name' => 'Hindi', 'code' => 'CLB-T6']);
        $sharedTeacher = Teacher::create(['name' => 'Double Class Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $sharedTeacher->id, 'class_id' => $classA->id, 'subject_id' => $subjectA->id,
            'periods_per_week' => 2, 'is_class_teacher' => true, 'academic_year' => $year,
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $sharedTeacher->id, 'class_id' => $classB->id, 'subject_id' => $subjectB->id,
            'periods_per_week' => 2, 'is_class_teacher' => true, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$classA, $classB]));

        $this->assertNotEmpty($result['warnings'], 'A teacher who is class-teacher of two classes sharing period 1 must produce a readable warning, not fail silently or double-book.');
        foreach ($result['warnings'] as $warning) {
            $this->assertStringContainsString('class teacher', $warning);
        }

        // No double-booking: the shared teacher never appears twice at the
        // same bell_timing across the two classes' placements.
        $this->assertNoTeacherDoubleBooked($result['placements']);
    }

    /**
     * T6 item 1 bugfix (found by the real-data walkthrough against the
     * actual Pushp Niketan aSc PDFs): attemptBacktrack() must never
     * relocate a class-teacher's period-1 reservation to make room for an
     * unrelated, overloaded lesson elsewhere in the same class.
     *
     * 2 days x 3 periods. The class-teacher (Maths) reserves period 1
     * every day. Science's same-subject-per-day cap (1/day, non-
     * consecutive) means only 2 of its 3 requested periods can ever place,
     * regardless of physical slot availability -- period 3 of each day
     * stays genuinely UNOCCUPIED (nobody is busy there), just off-limits
     * to Science specifically once its daily cap is used. That free
     * period-3 slot is exactly what gives the class-teacher's Monday
     * reservation a real, legal escape route once uncommitted: relocating
     * it there is NOT rejected by isHardLegal, so before the fix
     * attemptBacktrack() actually succeeds in bumping it out of period 1
     * to make room for Science's unplaceable 3rd lesson. (An earlier
     * version of this test used a 2-period grid with no escape route at
     * all, where the relocation attempt failed for an unrelated reason
     * regardless of the fix -- a false negative that never actually
     * exercised the bug; this is the corrected reproduction.)
     */
    public function test_backtracking_never_relocates_a_class_teachers_period_1_reservation(): void
    {
        $year = 'T6-CT-BACKTRACK-PROTECTED';
        $this->makeGrid(['Monday', 'Tuesday'], 3, $year); // 6 slots: Mon/Tue x P1,P2,P3

        $class = SchoolClass::create(['name' => 'Backtrack Class', 'class_order' => 1, 'is_active' => true]);
        $ctSubject = Subject::create(['name' => 'Maths', 'code' => 'BTP-CT-T6']);
        $ctTeacher = Teacher::create(['name' => 'Class Teacher', 'status' => 'active']);
        $otherSubject = Subject::create(['name' => 'Science', 'code' => 'BTP-SCI-T6']);
        $otherTeacher = Teacher::create(['name' => 'Science Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $ctTeacher->id, 'class_id' => $class->id, 'subject_id' => $ctSubject->id,
            'periods_per_week' => 2, 'is_class_teacher' => true, 'academic_year' => $year,
        ]);
        // 3 periods/week, non-consecutive, in a 2-day week: the per-day
        // cap of 1 (not slot scarcity) makes the 3rd unplaceable at a
        // legal slot -- exactly the pressure that pushes attemptBacktrack()
        // to look at the class-teacher's period-1 slots as candidates.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $otherTeacher->id, 'class_id' => $class->id, 'subject_id' => $otherSubject->id,
            'periods_per_week' => 3, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $period1Ids = BellTiming::where('order_index', 1)->pluck('id')->all();
        $ctPlacementsAtPeriod1 = collect($result['placements'])->filter(
            fn ($p) => $p['teacher_id'] === $ctTeacher->id && in_array($p['bell_timing_ids'][0], $period1Ids, true)
        );

        $this->assertCount(2, $ctPlacementsAtPeriod1, 'The class teacher must still hold period 1 on BOTH days -- backtracking must never touch it.');
        foreach ($ctPlacementsAtPeriod1 as $p) {
            $this->assertSame($ctSubject->id, $p['subject_id']);
        }

        // Science's own placements must never land on a period-1 slot --
        // whether all 3 fit (using the freed-up period-3 escape route
        // legitimately) or the 3rd goes unplaced, period 1 stays the
        // class-teacher's alone either way.
        $sciencePlacements = collect($result['placements'])->where('teacher_id', $otherTeacher->id);
        foreach ($sciencePlacements as $p) {
            $this->assertNotContains($p['bell_timing_ids'][0], $period1Ids, 'Science must never have stolen a period-1 slot from the class teacher.');
        }
    }

    /**
     * T6 item 1 (revised): a class WITH a designated class-teacher (via the
     * legacy class_teacher_assignments table) but no is_class_teacher-
     * flagged subject assignment gets a warning naming the gap, not a
     * forced (and impossible) period-1 reservation.
     */
    public function test_designated_class_teacher_with_no_subject_reports_a_warning_and_generates_normally(): void
    {
        $year = 'T6-CT-NO-SUBJECT';
        $this->makeGrid(['Monday', 'Tuesday'], 2, $year);

        $class = SchoolClass::create(['name' => '9B', 'class_order' => 1, 'is_active' => true]);
        $user = User::factory()->create(['name' => 'Amar Saxena']);
        Teacher::create(['name' => 'Amar Saxena', 'status' => 'active', 'user_id' => $user->id]);

        // The legacy table names Amar Saxena as 9B's class-teacher, but he
        // has no teacher_class_subject_assignments row here at all.
        ClassTeacherAssignment::create([
            'teacher_id' => $user->id, 'assigned_class' => '9B', 'is_active' => true,
        ]);

        // A normal subject so the class still generates something.
        $subject = Subject::create(['name' => 'Art', 'code' => 'CTNS-T6']);
        $teacher = Teacher::create(['name' => 'Art Teacher', 'status' => 'active']);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 2, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Class 9B: class teacher Amar Saxena has no subject assigned for this class', $result['warnings'][0]);
        $this->assertStringContainsString('Assign them a subject for 9B', $result['warnings'][0]);

        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertCount(2, $result['placements']);
    }

    /**
     * T6 item 2: the style toggle. STYLE_FIXED_DAILY solves one day's
     * pattern and repeats it identically on every running day.
     */
    public function test_fixed_daily_produces_identical_pattern_every_day(): void
    {
        $year = 'T6-FIXED-DAILY';
        $this->makeGrid(['Monday', 'Tuesday', 'Wednesday', 'Thursday'], 4, $year);

        $class = SchoolClass::create(['name' => 'FD Class', 'class_order' => 1, 'is_active' => true]);
        $mathsSubject = Subject::create(['name' => 'Maths', 'code' => 'FDM-T6']);
        $mathsTeacher = Teacher::create(['name' => 'Maths Teacher', 'status' => 'active']);
        $scienceSubject = Subject::create(['name' => 'Science', 'code' => 'FDS-T6']);
        $scienceTeacher = Teacher::create(['name' => 'Science Teacher', 'status' => 'active']);

        // 4 periods/week across 4 running days = 1/day each -- evenly divisible.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $mathsTeacher->id, 'class_id' => $class->id, 'subject_id' => $mathsSubject->id,
            'periods_per_week' => 4, 'academic_year' => $year,
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $scienceTeacher->id, 'class_id' => $class->id, 'subject_id' => $scienceSubject->id,
            'periods_per_week' => 4, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]), null, GeneratorService::STYLE_FIXED_DAILY);

        $this->assertSame([], $result['warnings']);
        $this->assertSame(0, $result['stats']['unplaced_lessons']);

        // Group placements by their order_index; every day must show the
        // identical (teacher_id, subject_id) at each order_index.
        $orderIndexById = BellTiming::pluck('order_index', 'id');
        $byOrderIndex = collect($result['placements'])->groupBy(fn ($p) => $orderIndexById[$p['bell_timing_ids'][0]]);

        foreach ($byOrderIndex as $orderIndex => $rows) {
            $signatures = $rows->map(fn ($r) => "{$r['teacher_id']}|{$r['subject_id']}")->unique();
            $this->assertCount(1, $signatures, "Order index {$orderIndex} must have the identical teacher/subject on every day, got: " . $signatures->implode(', '));
        }

        // 4 days x 2 subjects = 8 placement rows total.
        $this->assertCount(8, $result['placements']);
    }

    public function test_rotating_style_explicitly_still_spreads_subjects_across_days(): void
    {
        $year = 'T6-ROTATING';
        $this->makeGrid(['Monday', 'Tuesday', 'Wednesday', 'Thursday'], 4, $year);

        $class = SchoolClass::create(['name' => 'Rot Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'English', 'code' => 'ROT-T6']);
        $teacher = Teacher::create(['name' => 'English Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 4, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]), null, GeneratorService::STYLE_ROTATING);

        $this->assertSame([], $result['warnings']);
        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertSubjectAppearsAtMostOncePerDay($result['placements']);
    }

    public function test_class_teacher_period_1_rule_holds_in_fixed_daily_mode(): void
    {
        $year = 'T6-FD-CT';
        $this->makeGrid(['Monday', 'Tuesday', 'Wednesday'], 3, $year);

        $class = SchoolClass::create(['name' => 'FD CT Class', 'class_order' => 1, 'is_active' => true]);
        $ctSubject = Subject::create(['name' => 'Maths', 'code' => 'FDCTM-T6']);
        $ctTeacher = Teacher::create(['name' => 'FD Class Teacher', 'status' => 'active']);
        $otherSubject = Subject::create(['name' => 'Hindi', 'code' => 'FDCTH-T6']);
        $otherTeacher = Teacher::create(['name' => 'FD Other Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $ctTeacher->id, 'class_id' => $class->id, 'subject_id' => $ctSubject->id,
            'periods_per_week' => 3, 'is_class_teacher' => true, 'academic_year' => $year,
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $otherTeacher->id, 'class_id' => $class->id, 'subject_id' => $otherSubject->id,
            'periods_per_week' => 3, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]), null, GeneratorService::STYLE_FIXED_DAILY);

        $period1Ids = BellTiming::where('order_index', 1)->pluck('id')->all();
        $placementsAtPeriod1 = collect($result['placements'])->filter(fn ($p) => in_array($p['bell_timing_ids'][0], $period1Ids, true));

        $this->assertCount(3, $placementsAtPeriod1);
        foreach ($placementsAtPeriod1 as $p) {
            $this->assertSame($ctTeacher->id, $p['teacher_id']);
            $this->assertSame($ctSubject->id, $p['subject_id']);
        }
    }

    public function test_indivisible_periods_per_week_in_fixed_daily_reports_a_warning(): void
    {
        $year = 'T6-FD-INDIVISIBLE';
        $this->makeGrid(['Monday', 'Tuesday', 'Wednesday', 'Thursday'], 4, $year);

        $class = SchoolClass::create(['name' => 'FD Indivisible Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Art', 'code' => 'FDI-T6']);
        $teacher = Teacher::create(['name' => 'Art Teacher', 'status' => 'active']);

        // 3 periods/week across 4 running days does not divide evenly.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 3, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]), null, GeneratorService::STYLE_FIXED_DAILY);

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('not fixed-daily-compatible', $result['warnings'][0]);
        $this->assertCount(0, $result['placements'], 'An incompatible assignment gets no placement attempt at all, never a partial/guessed one.');
    }

    public function test_consecutive_subject_in_fixed_daily_places_a_daily_double_period(): void
    {
        $year = 'T6-FD-CONSECUTIVE';
        $this->makeGrid(['Monday', 'Tuesday'], 4, $year);

        $class = SchoolClass::create(['name' => 'FD Consecutive Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Lab Science', 'code' => 'FDC-T6']);
        $teacher = Teacher::create(['name' => 'Lab Teacher', 'status' => 'active']);

        // 4 periods/week across 2 running days = 2/day -- a daily double period.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 4, 'require_consecutive' => true, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]), null, GeneratorService::STYLE_FIXED_DAILY);

        $this->assertSame([], $result['warnings']);
        $this->assertCount(2, $result['placements']); // one row per day, each carrying 2 bell_timing_ids

        $orderIndexById = BellTiming::pluck('order_index', 'id');
        foreach ($result['placements'] as $p) {
            $this->assertCount(2, $p['bell_timing_ids']);
            $orderIndexes = collect($p['bell_timing_ids'])->map(fn ($id) => $orderIndexById[$id])->sort()->values();
            $this->assertSame(1, $orderIndexes[1] - $orderIndexes[0], 'The daily double period must be adjacent.');
        }

        // Both days must use the identical order_index pair.
        $pairsUsed = collect($result['placements'])->map(
            fn ($p) => collect($p['bell_timing_ids'])->map(fn ($id) => $orderIndexById[$id])->sort()->values()->all()
        )->unique();
        $this->assertCount(1, $pairsUsed, 'Both days must repeat the identical order-index pair.');
    }

    /**
     * T6 item 3: school_classes.last_teaching_period caps how far into the
     * day a class's 'solo' lessons may be placed, per the approved
     * REPORT-THEN-STOP finding.
     */
    public function test_class_capped_at_last_teaching_period_never_places_beyond_it(): void
    {
        $year = 'T6-CAP-SOLO';
        $this->makeGrid(['Monday'], 8, $year); // order_index 1..8, one day

        $class = SchoolClass::create([
            'name' => 'Senior Class', 'class_order' => 1, 'is_active' => true, 'last_teaching_period' => 6,
        ]);

        // 7 single-period subjects: only 6 legal slots exist (periods 1-6),
        // so the 7th must go unplaced -- it must NEVER spill into the
        // physically-free periods 7/8.
        for ($i = 1; $i <= 7; $i++) {
            $subject = Subject::create(['name' => "Subject {$i}", 'code' => "CAP{$i}-T6"]);
            $teacher = Teacher::create(['name' => "Teacher {$i}", 'status' => 'active']);
            TeacherClassSubjectAssignment::create([
                'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
                'periods_per_week' => 1, 'academic_year' => $year,
            ]);
        }

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertCount(6, $result['placements']);
        $this->assertCount(1, $result['unplaced']);

        $orderIndexById = BellTiming::pluck('order_index', 'id');
        foreach ($result['placements'] as $p) {
            $orderIndex = $orderIndexById[$p['bell_timing_ids'][0]];
            $this->assertLessThanOrEqual(6, $orderIndex, 'A capped class must never be placed beyond its last_teaching_period.');
        }
    }

    public function test_uncapped_class_is_unaffected_by_another_classs_cap(): void
    {
        $year = 'T6-CAP-UNAFFECTED';
        $this->makeGrid(['Monday'], 8, $year); // order_index 1..8, one day

        $cappedClass = SchoolClass::create([
            'name' => 'Capped Class', 'class_order' => 1, 'is_active' => true, 'last_teaching_period' => 6,
        ]);
        $fullClass = SchoolClass::create([
            'name' => 'Full Class', 'class_order' => 2, 'is_active' => true,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            $subject = Subject::create(['name' => "Capped Subject {$i}", 'code' => "CAPU{$i}-T6"]);
            $teacher = Teacher::create(['name' => "Capped Teacher {$i}", 'status' => 'active']);
            TeacherClassSubjectAssignment::create([
                'teacher_id' => $teacher->id, 'class_id' => $cappedClass->id, 'subject_id' => $subject->id,
                'periods_per_week' => 1, 'academic_year' => $year,
            ]);
        }

        for ($i = 1; $i <= 8; $i++) {
            $subject = Subject::create(['name' => "Full Subject {$i}", 'code' => "FULU{$i}-T6"]);
            $teacher = Teacher::create(['name' => "Full Teacher {$i}", 'status' => 'active']);
            TeacherClassSubjectAssignment::create([
                'teacher_id' => $teacher->id, 'class_id' => $fullClass->id, 'subject_id' => $subject->id,
                'periods_per_week' => 1, 'academic_year' => $year,
            ]);
        }

        $result = (new GeneratorService())->generate($year, collect([$cappedClass, $fullClass]));

        $this->assertSame(0, $result['stats']['unplaced_lessons']);

        $orderIndexById = BellTiming::pluck('order_index', 'id');
        $fullClassOrderIndexes = collect($result['placements'])
            ->where('school_class_id', $fullClass->id)
            ->map(fn ($p) => $orderIndexById[$p['bell_timing_ids'][0]]);

        $this->assertTrue($fullClassOrderIndexes->contains(8), 'An uncapped class must still be able to use period 8 even when another class in the same run is capped.');

        $cappedClassOrderIndexes = collect($result['placements'])
            ->where('school_class_id', $cappedClass->id)
            ->map(fn ($p) => $orderIndexById[$p['bell_timing_ids'][0]]);
        foreach ($cappedClassOrderIndexes as $orderIndex) {
            $this->assertLessThanOrEqual(6, $orderIndex);
        }
    }

    /**
     * T6 item 4 (team teaching only, per the approved REPORT-THEN-STOP):
     * two teachers sharing one slot must NOT be treated as a clash against
     * EACH OTHER -- both are legitimately in that one slot together.
     */
    public function test_team_teaching_places_both_teachers_at_the_same_slot_without_a_false_clash(): void
    {
        $year = 'T6-TEAM-CLEAN';
        $this->makeGrid(['Monday', 'Tuesday'], 2, $year);

        $class = SchoolClass::create(['name' => 'CS Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Computer Science', 'code' => 'CS-T6']);
        $primary = Teacher::create(['name' => 'Garisht Singh', 'status' => 'active']);
        $co = Teacher::create(['name' => 'Rajesh', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $primary->id, 'co_teacher_id' => $co->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 2, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertSame(0, $result['stats']['unplaced_lessons'], 'A team-taught lesson must not be rejected as a false clash between its own two teachers.');
        $this->assertCount(2, $result['placements']);

        foreach ($result['placements'] as $p) {
            $this->assertSame($primary->id, $p['teacher_id']);
            $this->assertSame($co->id, $p['co_teacher_id']);
        }

        // Both teachers occupy the identical bell_timing per placement --
        // proof this is one shared slot, not two competing bookings.
        $this->assertNoTeacherDoubleBooked($result['placements']);
    }

    /**
     * T6 item 4: a team-teacher is still busy that period for their OWN
     * other classes -- they can't simultaneously co-teach one class and
     * solo-teach another at the identical period. Grid deliberately has
     * exactly ONE slot in the whole world, so both lessons compete for it
     * and the solver must place one and report the other unplaced --
     * never double-book the shared teacher.
     */
    public function test_team_teacher_cannot_be_double_booked_elsewhere_in_the_same_period(): void
    {
        $year = 'T6-TEAM-CLASH';
        $this->makeGrid(['Monday'], 1, $year); // exactly one bell_timing exists

        $classX = SchoolClass::create(['name' => 'XI Science', 'class_order' => 1, 'is_active' => true]);
        $classY = SchoolClass::create(['name' => '9B', 'class_order' => 2, 'is_active' => true]);

        $subjectCs = Subject::create(['name' => 'Computer Science', 'code' => 'CS-T6C']);
        $subjectMaths = Subject::create(['name' => 'Maths', 'code' => 'MTH-T6C']);

        $primary = Teacher::create(['name' => 'Garisht Singh', 'status' => 'active']);
        $shared = Teacher::create(['name' => 'Rajesh', 'status' => 'active']); // co-teacher in X, solo teacher in Y

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $primary->id, 'co_teacher_id' => $shared->id, 'class_id' => $classX->id, 'subject_id' => $subjectCs->id,
            'periods_per_week' => 1, 'academic_year' => $year,
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $shared->id, 'class_id' => $classY->id, 'subject_id' => $subjectMaths->id,
            'periods_per_week' => 1, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$classX, $classY]));

        $this->assertCount(1, $result['placements'], 'Only one of the two competing lessons can use the single available slot.');
        $this->assertCount(1, $result['unplaced'], 'The other lesson, which needed the same shared teacher at the same instant, must go unplaced -- never double-booked.');

        // Whichever lesson won, Rajesh (as primary OR co-teacher) appears
        // at the one bell_timing at most once across every placement.
        $sharedTeacherAppearances = 0;
        foreach ($result['placements'] as $p) {
            if ($p['teacher_id'] === $shared->id) {
                $sharedTeacherAppearances++;
            }
            if (($p['co_teacher_id'] ?? null) === $shared->id) {
                $sharedTeacherAppearances++;
            }
        }
        $this->assertSame(1, $sharedTeacherAppearances, 'The shared teacher must never appear twice (as primary or co-teacher) at the same period.');
    }

    // --- Phase 5: Locked Lessons -----------------------------------------------------

    /**
     * A locked, PUBLISHED slot must be carried into the next draft at the
     * exact period it already occupies, and the rest of its assignment's
     * periods_per_week (not the whole assignment) must still be freshly
     * placed around it -- proves both halves of reserveLockedSlots()'s
     * contract in one realistic scenario.
     */
    public function test_a_locked_published_slot_is_carried_forward_and_the_rest_of_its_periods_are_placed_fresh(): void
    {
        $year = 'T5-LOCK-CARRY';
        [$mon, $tue] = $this->makeGrid(['Monday', 'Tuesday'], 1, $year);

        $class = SchoolClass::create(['name' => 'Locked Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'LOCK-T5-1']);
        $teacher = Teacher::create(['name' => 'Locked Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 2, 'academic_year' => $year,
        ]);

        // The "already-live" published slot for Monday, locked.
        \App\Models\TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $mon,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => 'published', 'academic_year' => $year, 'is_locked' => true,
            'room_number' => 'Room 7',
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertCount(2, $result['placements'], 'Both the carried-forward lock and the freshly-placed 2nd period must appear.');

        $mondayPlacement = collect($result['placements'])->firstWhere('bell_timing_ids.0', $mon);
        $this->assertNotNull($mondayPlacement, 'The locked Monday slot must be carried forward at its own period.');
        $this->assertTrue($mondayPlacement['is_locked']);
        $this->assertSame('Room 7', $mondayPlacement['room_number']);
        $this->assertSame($teacher->id, $mondayPlacement['teacher_id']);

        $tuesdayPlacement = collect($result['placements'])->firstWhere('bell_timing_ids.0', $tue);
        $this->assertNotNull($tuesdayPlacement, 'The 2nd, still-unlocked period must be freshly placed on the only other free day.');
        $this->assertFalse($tuesdayPlacement['is_locked']);
    }

    /**
     * Mirrors test_backtracking_never_relocates_a_class_teachers_period_1_reservation
     * exactly, but for a locked slot instead of a class-teacher reservation --
     * proves attemptBacktrack() treats a lock with the same immunity.
     */
    public function test_backtracking_never_relocates_a_locked_slot(): void
    {
        $year = 'T5-LOCK-BACKTRACK';
        [$monP1, $monP2, $monP3, $tueP1, $tueP2, $tueP3] = $this->makeGrid(['Monday', 'Tuesday'], 3, $year);

        $class = SchoolClass::create(['name' => 'Backtrack Lock Class', 'class_order' => 1, 'is_active' => true]);
        $lockedSubject = Subject::create(['name' => 'Locked Subject', 'code' => 'LOCK-T5-BT']);
        $lockedTeacher = Teacher::create(['name' => 'Locked Teacher', 'status' => 'active']);
        $otherSubject = Subject::create(['name' => 'Science', 'code' => 'LOCK-T5-SCI']);
        $otherTeacher = Teacher::create(['name' => 'Science Teacher', 'status' => 'active']);

        // Locked at Monday period 1 -- an assignment covers it so the count
        // math is realistic, but the lock itself is what must survive.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $lockedTeacher->id, 'class_id' => $class->id, 'subject_id' => $lockedSubject->id,
            'periods_per_week' => 1, 'academic_year' => $year,
        ]);
        \App\Models\TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $monP1,
            'subject_id' => $lockedSubject->id, 'teacher_id' => $lockedTeacher->id,
            'status' => 'published', 'academic_year' => $year, 'is_locked' => true,
        ]);

        // Same pressure as the class-teacher backtrack test: a 3rd
        // same-subject period that can't legally place (per-day cap),
        // tempting the solver to look at already-committed single-period
        // placements -- including the lock -- for relocation.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $otherTeacher->id, 'class_id' => $class->id, 'subject_id' => $otherSubject->id,
            'periods_per_week' => 3, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $lockedPlacement = collect($result['placements'])->firstWhere('teacher_id', $lockedTeacher->id);
        $this->assertNotNull($lockedPlacement);
        $this->assertSame($monP1, $lockedPlacement['bell_timing_ids'][0], 'The lock must still be at Monday period 1 -- backtracking must never touch it.');

        foreach (collect($result['placements'])->where('teacher_id', $otherTeacher->id) as $p) {
            $this->assertNotSame($monP1, $p['bell_timing_ids'][0], 'Science must never have stolen the locked period.');
        }
    }

    public function test_a_locked_slot_whose_period_is_no_longer_active_is_skipped_with_a_warning_not_a_crash(): void
    {
        $year = 'T5-LOCK-DEAD-PERIOD';
        $this->makeGrid(['Monday'], 1, $year);

        $class = SchoolClass::create(['name' => 'Dead Period Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'LOCK-T5-DEAD']);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        // Locked at a period that is NOT active -- simulates the bell
        // timing having been retired since the lock was set.
        $deadTiming = BellTiming::create([
            'day_of_week' => 'Wednesday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => false, 'is_break' => false, 'order_index' => 1, 'academic_year' => $year,
        ]);
        \App\Models\TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $deadTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => 'published', 'academic_year' => $year, 'is_locked' => true,
        ]);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 1, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertSame(0, $result['stats']['unplaced_lessons']);
        $this->assertCount(1, $result['placements'], 'The 1 period must be freshly placed on the active Monday slot -- the dead-period lock contributes nothing.');
        $this->assertStringContainsString('no longer active', implode(' ', $result['warnings']));
    }

    public function test_a_locked_draft_slot_is_not_specially_preserved_across_regeneration(): void
    {
        $year = 'T5-LOCK-DRAFT-NOT-CARRIED';
        [$mon] = $this->makeGrid(['Monday'], 1, $year);

        $class = SchoolClass::create(['name' => 'Draft Lock Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'LOCK-T5-DRAFT']);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        // A locked DRAFT (not published) slot -- documented scope boundary:
        // only published locks are honoured by the generator, since a
        // draft is regenerated fresh by design every time.
        \App\Models\TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $mon,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => 'draft', 'academic_year' => $year, 'is_locked' => true,
        ]);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'periods_per_week' => 1, 'academic_year' => $year,
        ]);

        $result = (new GeneratorService())->generate($year, collect([$class]));

        $this->assertCount(1, $result['placements']);
        $this->assertFalse($result['placements'][0]['is_locked'], 'A locked DRAFT slot is not carried forward as a lock -- only published locks are.');
    }
}
