<?php

namespace Tests\Unit\Models;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable-module T2a, items 1-3: schema layer sanity. Not the "Tests:
 * availability toggling, assignment validation, upgraded feasibility
 * math" from item 7 (those live in their own dedicated test files, added
 * alongside the UI/service work each covers) -- this file only proves
 * the migrations and models behave as the schema promises.
 */
class TimetableT2aSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_has_default_max_periods(): void
    {
        $teacher = Teacher::create(['name' => 'Default Max Teacher'])->fresh();

        $this->assertSame(7, $teacher->max_periods_per_day);
        $this->assertSame(36, $teacher->max_periods_per_week);
    }

    public function test_assignment_periods_per_week_is_nullable_and_require_consecutive_defaults_false(): void
    {
        $teacher = Teacher::create(['name' => 'T']);
        $class = SchoolClass::create(['name' => 'C1', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Math', 'code' => 'M1']);

        $assignment = TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ])->fresh();

        $this->assertNull($assignment->periods_per_week);
        $this->assertFalse($assignment->require_consecutive);

        $assignment2 = TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => Subject::create(['name' => 'Science', 'code' => 'S1'])->id,
            'periods_per_week' => 6,
            'require_consecutive' => true,
        ]);

        $this->assertSame(6, $assignment2->periods_per_week);
        $this->assertTrue($assignment2->require_consecutive);
    }

    public function test_teacher_availability_derives_day_from_bell_timing_automatically(): void
    {
        $teacher = Teacher::create(['name' => 'T']);
        $timing = BellTiming::create([
            'day_of_week' => 'Wednesday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);

        // Client-supplied `day` (deliberately wrong) must be overridden by
        // the model's own derivation from the bell timing, not trusted.
        $availability = TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'day' => 99,
            'bell_timing_id' => $timing->id,
            'is_available' => false,
        ]);

        $this->assertSame(3, $availability->fresh()->day); // Wednesday = 3
    }

    public function test_teacher_availability_unique_constraint_blocks_a_direct_duplicate(): void
    {
        $teacher = Teacher::create(['name' => 'T']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);

        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $timing->id, 'is_available' => false]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $timing->id, 'is_available' => false]);
    }
}
