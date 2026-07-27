<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\Timetable\FeasibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable-module T1b: unit tests for FeasibilityService's computation
 * logic against a seeded mini-timetable, hand-counted below each
 * assertion so the numbers can be checked against the test itself.
 */
class FeasibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = '2026-2027';

    private function seedMiniTimetable(): array
    {
        AcademicSession::create([
            'name' => self::YEAR,
            'code' => self::YEAR,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        // 4 active teaching periods, global (no class_section scope):
        // Monday P1, Monday P2, Tuesday P1, Tuesday P2.
        $mon1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'academic_year' => self::YEAR]);
        $mon2 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'is_active' => true, 'is_break' => false, 'order_index' => 2, 'academic_year' => self::YEAR]);
        $tue1 = BellTiming::create(['day_of_week' => 'Tuesday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'academic_year' => self::YEAR]);
        $tue2 = BellTiming::create(['day_of_week' => 'Tuesday', 'period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'is_active' => true, 'is_break' => false, 'order_index' => 2, 'academic_year' => self::YEAR]);
        // A break period -- must NOT count toward capacity.
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Break', 'start_time' => '09:30', 'end_time' => '09:45', 'is_active' => true, 'is_break' => true, 'order_index' => 3, 'academic_year' => self::YEAR]);
        // An inactive period -- must NOT count toward capacity.
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P3', 'start_time' => '09:45', 'end_time' => '10:30', 'is_active' => false, 'is_break' => false, 'order_index' => 4, 'academic_year' => self::YEAR]);

        $classA = SchoolClass::create(['name' => 'Class A', 'class_order' => 1, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class B', 'class_order' => 2, 'is_active' => true]);

        // A class-specific extra period, scoped by name to Class A only --
        // Class A's capacity should be 5, Class B's should stay 4.
        BellTiming::create(['day_of_week' => 'Tuesday', 'period_name' => 'Extra', 'start_time' => '09:30', 'end_time' => '10:15', 'is_active' => true, 'is_break' => false, 'order_index' => 3, 'academic_year' => self::YEAR, 'class_section' => 'Class A']);

        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH1']);
        $teacher1 = Teacher::create(['name' => 'Teacher One', 'status' => 'active']);
        $teacher2 = Teacher::create(['name' => 'Teacher Two', 'status' => 'active']);

        // Class A: 3 of 5 placed (Mon P1, Mon P2 by Teacher One; Tue P1 by Teacher Two).
        TimetableSlot::create(['school_class_id' => $classA->id, 'section_id' => null, 'bell_timing_id' => $mon1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher1->id, 'academic_year' => self::YEAR]);
        TimetableSlot::create(['school_class_id' => $classA->id, 'section_id' => null, 'bell_timing_id' => $mon2->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher1->id, 'academic_year' => self::YEAR]);
        TimetableSlot::create(['school_class_id' => $classA->id, 'section_id' => null, 'bell_timing_id' => $tue1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher2->id, 'academic_year' => self::YEAR]);
        // Class B: nothing placed.

        return compact('classA', 'classB', 'teacher1', 'teacher2', 'subject', 'mon1', 'mon2', 'tue1', 'tue2');
    }

    public function test_grid_capacity_counts_placed_vs_capacity_per_class(): void
    {
        $this->seedMiniTimetable();

        $report = (new FeasibilityService())->build(self::YEAR);
        $rows = collect($report['grid_capacity'])->keyBy('class_name');

        // Class A: 3 placed out of 5 capacity (4 global + 1 class-specific).
        $this->assertSame(5, $rows['Class A']['capacity']);
        $this->assertSame(3, $rows['Class A']['placed']);
        $this->assertSame(2, $rows['Class A']['empty']);

        // Class B: 0 placed out of 4 capacity (global only, no class-specific period applies).
        $this->assertSame(4, $rows['Class B']['capacity']);
        $this->assertSame(0, $rows['Class B']['placed']);
        $this->assertSame(4, $rows['Class B']['empty']);
    }

    public function test_grid_capacity_sentence_is_human_readable_with_context(): void
    {
        $this->seedMiniTimetable();

        $report = (new FeasibilityService())->build(self::YEAR);
        $rows = collect($report['grid_capacity'])->keyBy('class_name');

        $this->assertStringContainsString('Class A', $rows['Class A']['sentence']);
        $this->assertStringContainsString('2', $rows['Class A']['sentence']);
        $this->assertStringContainsString('empty', $rows['Class A']['sentence']);
    }

    public function test_teacher_load_counts_placed_periods_and_busiest_day(): void
    {
        $this->seedMiniTimetable();

        $report = (new FeasibilityService())->build(self::YEAR);
        $rows = collect($report['teacher_load'])->keyBy('teacher_name');

        // Teacher One: Monday P1 + Monday P2 = 2 periods, busiest day Monday (2).
        $this->assertSame(2, $rows['Teacher One']['placed_periods']);
        $this->assertSame('Monday', $rows['Teacher One']['busiest_day']);
        $this->assertSame(2, $rows['Teacher One']['busiest_day_count']);

        // Teacher Two: Tuesday P1 = 1 period.
        $this->assertSame(1, $rows['Teacher Two']['placed_periods']);
        $this->assertSame('Tuesday', $rows['Teacher Two']['busiest_day']);
    }

    public function test_teacher_over_threshold_is_flagged_in_red(): void
    {
        config(['timetable.max_periods_per_week' => 1]);
        $this->seedMiniTimetable();

        $report = (new FeasibilityService())->build(self::YEAR);
        $rows = collect($report['teacher_load'])->keyBy('teacher_name');

        // Teacher One has 2 placed periods > threshold of 1.
        $this->assertTrue($rows['Teacher One']['over_threshold']);
        $this->assertStringContainsString('but the week has 1 slots', $rows['Teacher One']['sentence']);

        // Teacher Two has exactly 1 placed period, not over a threshold of 1.
        $this->assertFalse($rows['Teacher Two']['over_threshold']);
    }

    public function test_days_with_zero_free_periods_counts_fully_booked_days(): void
    {
        $data = $this->seedMiniTimetable();

        // Fully book Teacher One on Tuesday too (Tue P1 already taken by
        // Teacher Two -- book Tue P2 instead, then Teacher One only has
        // Monday fully booked, not Tuesday, since Tuesday has 2 periods
        // (P1 + Extra, both class-A only) and only P2 exists globally...
        // keep this simple: book Teacher One into Tuesday P2 as well so
        // Monday (2/2) is fully booked but Tuesday (1/2 relevant periods
        // globally) is not.
        TimetableSlot::create([
            'school_class_id' => $data['classB']->id,
            'section_id' => null,
            'bell_timing_id' => $data['tue2']->id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher1']->id,
            'academic_year' => self::YEAR,
        ]);

        $report = (new FeasibilityService())->build(self::YEAR);
        $rows = collect($report['teacher_load'])->keyBy('teacher_name');

        // Teacher One: Monday P1 + P2 (2/2 -> fully booked) and Tuesday P2
        // (1 of 2 global Tuesday periods -> not fully booked).
        $this->assertSame(1, $rows['Teacher One']['days_with_zero_free_periods']);
    }

    public function test_conflict_scan_is_empty_when_no_violations_exist(): void
    {
        $this->seedMiniTimetable();

        $report = (new FeasibilityService())->build(self::YEAR);

        $this->assertSame([], $report['conflicts']);
    }

    public function test_conflict_scan_flags_a_slot_referencing_an_inactive_teacher(): void
    {
        $data = $this->seedMiniTimetable();
        $data['teacher1']->update(['status' => 'inactive']);

        $report = (new FeasibilityService())->build(self::YEAR);

        $inactiveTeacherConflicts = collect($report['conflicts'])->where('type', 'inactive_teacher');
        $this->assertGreaterThan(0, $inactiveTeacherConflicts->count());
        $this->assertStringContainsString('Teacher One', $inactiveTeacherConflicts->first()['sentence']);
    }

    public function test_conflict_scan_flags_a_slot_referencing_an_inactive_subject(): void
    {
        $data = $this->seedMiniTimetable();
        $data['subject']->update(['is_active' => false]);

        $report = (new FeasibilityService())->build(self::YEAR);

        $inactiveSubjectConflicts = collect($report['conflicts'])->where('type', 'inactive_subject');
        $this->assertGreaterThan(0, $inactiveSubjectConflicts->count());
    }

    public function test_empty_timetable_reports_zero_placed_with_full_capacity(): void
    {
        // No slots placed at all -- proves the report doesn't error or
        // misreport when there's genuinely nothing scheduled yet (the
        // real state of this app's live DB today).
        AcademicSession::create(['name' => self::YEAR, 'code' => self::YEAR, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'academic_year' => self::YEAR]);
        SchoolClass::create(['name' => 'Class Only', 'class_order' => 1, 'is_active' => true]);

        $report = (new FeasibilityService())->build(self::YEAR);

        $this->assertSame(1, $report['grid_capacity'][0]['capacity']);
        $this->assertSame(0, $report['grid_capacity'][0]['placed']);
        $this->assertSame([], $report['conflicts']);
    }
}
