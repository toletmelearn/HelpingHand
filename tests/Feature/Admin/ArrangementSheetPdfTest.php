<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T3 item 4: daily arrangement sheet PDF -- period x class grid of
 * substitution changes only, for one day.
 */
class ArrangementSheetPdfTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function seedOneSubstitution(): array
    {
        $date = '2026-08-03'; // Monday
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $class = SchoolClass::create(['name' => 'Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MATH' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher', 'status' => 'active']);
        $substituteTeacher = Teacher::create(['name' => 'Substitute Teacher', 'status' => 'active']);

        $substitution = TeacherSubstitution::create([
            'substitution_date' => $date,
            'absent_teacher_id' => $absentTeacher->id,
            'substitute_teacher_id' => $substituteTeacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'bell_timing_id' => $timing->id,
            'status' => 'assigned',
            'created_by' => 1,
        ]);

        return compact('date', 'timing', 'class', 'section', 'subject', 'absentTeacher', 'substituteTeacher', 'substitution');
    }

    public function test_returns_pdf_content_with_magic_bytes(): void
    {
        $admin = $this->admin();
        $data = $this->seedOneSubstitution();

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.arrangement-sheet', ['date' => $data['date']]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_shows_friendly_message_when_no_substitutions_that_day(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.arrangement-sheet', ['date' => '2026-08-05']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cancelled_substitutions_do_not_appear(): void
    {
        $admin = $this->admin();
        $data = $this->seedOneSubstitution();
        $data['substitution']->update(['status' => 'cancelled']);

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.arrangement-sheet', ['date' => $data['date']]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_unauthorized_role_gets_403(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);
        $user->roles()->attach($role->id);
        $data = $this->seedOneSubstitution();

        $response = $this->actingAs($user)->get(route('admin.teacher-substitutions.arrangement-sheet', ['date' => $data['date']]));

        $response->assertForbidden();
    }
}
