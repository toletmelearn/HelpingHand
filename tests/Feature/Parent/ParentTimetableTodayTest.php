<?php

namespace Tests\Feature\Parent;

use App\Models\BellTiming;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T5 item 1: the parent-portal "today's periods" view. Security follows
 * HomeworkController's exact pattern -- the parent guard's own linked
 * student, class-matched server-side, never a client-supplied id.
 */
class ParentTimetableTodayTest extends TestCase
{
    use RefreshDatabase;

    private function seedFamily(string $label): array
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => "{$label} Class", 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => "{$label} Subject", 'code' => strtoupper($label) . uniqid()]);
        $teacher = Teacher::create(['name' => "{$label} Teacher", 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        $student = Student::create([
            'name' => "{$label} Kid", 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-' . strtoupper($label) . '-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'school_class_id' => $class->id,
        ]);
        $slot = TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);
        $parent = ParentModel::create([
            'name' => "{$label} Parent",
            'email' => strtolower($label) . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'student_id' => $student->id,
        ]);

        return compact('class', 'subject', 'teacher', 'timing', 'student', 'slot', 'parent');
    }

    public function test_parent_sees_own_childs_periods_today(): void
    {
        $data = $this->seedFamily('Alpha');

        $response = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.today'));

        $response->assertOk();
        $response->assertSee('Alpha Subject');
        $response->assertSee('Alpha Teacher');
    }

    public function test_parent_does_not_see_a_different_familys_child_periods(): void
    {
        $ownFamily = $this->seedFamily('Alpha');
        $otherFamily = $this->seedFamily('Beta');

        $response = $this->actingAs($ownFamily['parent'], 'parent')->get(route('parent.timetable.today'));

        $response->assertOk();
        $response->assertSee('Alpha Subject');
        $response->assertDontSee('Beta Subject');
        $response->assertDontSee('Beta Teacher');
    }

    public function test_substitution_is_shown_as_an_arrangement(): void
    {
        $data = $this->seedFamily('Gamma');
        $substitute = Teacher::create(['name' => 'Gamma Substitute', 'status' => 'active']);
        $section = Section::create(['name' => 'A']);

        TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $data['teacher']->id,
            'substitute_teacher_id' => $substitute->id,
            'class_id' => $data['class']->id,
            'section_id' => $section->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'assigned',
            'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.today'));

        $response->assertOk();
        $response->assertSee('Gamma Substitute');
        $response->assertSee('Arrangement');
        $response->assertDontSee('Gamma Teacher<'); // original teacher no longer shown as the presenter
    }

    public function test_draft_slots_are_never_shown_to_a_parent(): void
    {
        $data = $this->seedFamily('Delta');
        $data['slot']->update(['status' => 'draft']);

        $response = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.today'));

        $response->assertOk();
        $response->assertDontSee('Delta Subject');
        $response->assertSee('No periods scheduled');
    }

    public function test_guest_is_redirected_to_parent_login(): void
    {
        $response = $this->get(route('parent.timetable.today'));

        $response->assertRedirect();
    }
}
