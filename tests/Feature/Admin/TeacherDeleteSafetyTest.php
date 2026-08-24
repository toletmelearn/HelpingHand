<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Teacher delete-safety fix: TeacherController::destroy() (web + API)
 * previously deleted (soft-deleted) any teacher with zero dependency
 * check, silently dropping an actively-scheduled teacher from
 * Timetable/Substitution/class-assignment views with no warning -- the
 * same class of gap already fixed for Sections. Proves the fix blocks
 * deletion when a live dependency exists, and still allows it when
 * genuinely unused.
 */
class TeacherDeleteSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($role->id);
    }

    private function makeTeacher(string $name = 'Test Teacher'): Teacher
    {
        return Teacher::create(['name' => $name, 'status' => 'active']);
    }

    public function test_admin_can_delete_a_teacher_with_no_dependencies(): void
    {
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($this->admin)->delete(route('admin.teachers.destroy', $teacher->id));

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('teachers', ['id' => $teacher->id]);
    }

    public function test_teacher_with_a_timetable_slot_cannot_be_deleted(): void
    {
        $teacher = $this->makeTeacher();
        $class = SchoolClass::create(['name' => 'Test Class', 'class_order' => 1]);
        $subject = Subject::create(['name' => 'Test Subject', 'code' => 'TST' . uniqid()]);
        $timing = \App\Models\BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        \App\Models\TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'room_number' => 'R1',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.teachers.destroy', $teacher->id));

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'deleted_at' => null]);
    }

    public function test_teacher_with_an_active_substitution_cannot_be_deleted(): void
    {
        $teacher = $this->makeTeacher();
        $substitute = $this->makeTeacher('Substitute Teacher');
        $class = SchoolClass::create(['name' => 'Test Class 2', 'class_order' => 2]);
        $subject = Subject::create(['name' => 'Test Subject 2', 'code' => 'TST2' . uniqid()]);
        $timing = \App\Models\BellTiming::create([
            'day_of_week' => 'Tuesday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);

        $section = \App\Models\Section::create(['name' => 'A', 'capacity' => 40]);

        DB::table('teacher_substitutions')->insert([
            'absent_teacher_id' => $teacher->id,
            'substitute_teacher_id' => $substitute->id,
            'bell_timing_id' => $timing->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'substitution_date' => now()->toDateString(),
            'status' => 'assigned',
            'created_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.teachers.destroy', $teacher->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'deleted_at' => null]);
    }

    /** A cancelled substitution must NOT block deletion -- it no longer represents a live dependency. */
    public function test_teacher_with_only_a_cancelled_substitution_can_be_deleted(): void
    {
        $teacher = $this->makeTeacher();
        $substitute = $this->makeTeacher('Substitute Teacher 2');
        $class = SchoolClass::create(['name' => 'Test Class 3', 'class_order' => 3]);
        $subject = Subject::create(['name' => 'Test Subject 3', 'code' => 'TST3' . uniqid()]);
        $timing = \App\Models\BellTiming::create([
            'day_of_week' => 'Wednesday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);

        $section = \App\Models\Section::create(['name' => 'A', 'capacity' => 40]);

        DB::table('teacher_substitutions')->insert([
            'absent_teacher_id' => $teacher->id,
            'substitute_teacher_id' => $substitute->id,
            'bell_timing_id' => $timing->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'substitution_date' => now()->toDateString(),
            'status' => 'cancelled',
            'created_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.teachers.destroy', $teacher->id));

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('teachers', ['id' => $teacher->id]);
    }

    public function test_teacher_with_a_class_subject_assignment_cannot_be_deleted(): void
    {
        $teacher = $this->makeTeacher();
        $class = SchoolClass::create(['name' => 'Test Class 4', 'class_order' => 4]);
        $subject = Subject::create(['name' => 'Test Subject 4', 'code' => 'TST4' . uniqid()]);

        DB::table('teacher_class_subject_assignments')->insert([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'is_class_teacher' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.teachers.destroy', $teacher->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'deleted_at' => null]);
    }

    public function test_api_delete_also_enforces_the_same_dependency_check(): void
    {
        $teacher = $this->makeTeacher();
        $class = SchoolClass::create(['name' => 'Test Class 5', 'class_order' => 5]);
        $subject = Subject::create(['name' => 'Test Subject 5', 'code' => 'TST5' . uniqid()]);

        DB::table('teacher_class_subject_assignments')->insert([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'is_class_teacher' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = app(\App\Http\Controllers\API\TeacherController::class)->destroy($teacher->id);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['success']);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'deleted_at' => null]);
    }
}
