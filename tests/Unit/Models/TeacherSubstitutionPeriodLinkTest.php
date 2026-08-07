<?php

namespace Tests\Unit\Models;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T3 item 1: teacher_substitutions.bell_timing_id links a substitution to
 * the real timetable instead of a free-typed period_number.
 */
class TeacherSubstitutionPeriodLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubstitution(BellTiming $timing): TeacherSubstitution
    {
        $class = SchoolClass::create(['name' => 'Class S', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher']);

        return TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $absentTeacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'bell_timing_id' => $timing->id,
            'status' => 'pending',
            'created_by' => 1,
        ]);
    }

    public function test_period_name_is_derived_from_the_linked_bell_timing(): void
    {
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 3', 'start_time' => '10:00', 'end_time' => '10:45', 'is_active' => true, 'is_break' => false, 'order_index' => 3]);

        $substitution = $this->makeSubstitution($timing);

        $this->assertSame('Period 3', $substitution->fresh()->period_name);
    }

    public function test_period_name_updates_if_bell_timing_changes(): void
    {
        $timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 3', 'start_time' => '10:00', 'end_time' => '10:45', 'is_active' => true, 'is_break' => false, 'order_index' => 3]);
        $timing2 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 5', 'start_time' => '12:00', 'end_time' => '12:45', 'is_active' => true, 'is_break' => false, 'order_index' => 5]);

        $substitution = $this->makeSubstitution($timing1);
        $substitution->update(['bell_timing_id' => $timing2->id]);

        $this->assertSame('Period 5', $substitution->fresh()->period_name);
    }

    public function test_bell_timing_id_is_required_at_the_database_level(): void
    {
        $class = SchoolClass::create(['name' => 'Class S', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $absentTeacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'status' => 'pending',
            'created_by' => 1,
        ]);
    }
}
