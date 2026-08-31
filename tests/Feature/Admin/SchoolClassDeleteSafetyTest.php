<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Priority audit finding F2: Admin\SchoolClassController::destroy() checked
 * only students -- never timetable_slots.school_class_id,
 * teacher_class_subject_assignments.class_id, or exam_papers.class_id, all
 * three of which carry a real DB foreign key (ON DELETE CASCADE) that
 * SoftDeletes prevents from ever firing on this path. Proves the fix blocks
 * deletion when any of those dependencies exist, still allows it when
 * genuinely unused, and leaves destroyWithStudents() (the explicit,
 * confirmed "delete this class and everyone in it" shortcut) unaffected.
 */
class SchoolClassDeleteSafetyTest extends TestCase
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

    public function test_admin_can_delete_a_class_with_no_dependencies(): void
    {
        $class = SchoolClass::create(['name' => 'Unused Class', 'class_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($this->admin)->delete(route('admin.school-classes.destroy', $class->id));

        $response->assertRedirect(route('admin.school-classes.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('school_classes', ['id' => $class->id]);
    }

    public function test_class_referenced_by_a_timetable_slot_cannot_be_deleted(): void
    {
        $class = SchoolClass::create(['name' => 'Timetabled Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Subj', 'code' => 'SCC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'T']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.school-classes.destroy', $class->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'deleted_at' => null]);
    }

    public function test_class_referenced_by_a_teacher_assignment_cannot_be_deleted(): void
    {
        $class = SchoolClass::create(['name' => 'Assigned Class', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Subj2', 'code' => 'SCC2' . uniqid()]);
        $teacher = Teacher::create(['name' => 'T2']);

        DB::table('teacher_class_subject_assignments')->insert([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'is_class_teacher' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.school-classes.destroy', $class->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'deleted_at' => null]);
    }

    public function test_class_referenced_by_an_exam_paper_cannot_be_deleted(): void
    {
        $class = SchoolClass::create(['name' => 'Exam Class', 'class_order' => 4, 'is_active' => true]);

        DB::table('exam_papers')->insert([
            'title' => 'Midterm', 'exam_type' => 'midterm', 'class_id' => $class->id, 'subject' => 'Mathematics',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.school-classes.destroy', $class->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'deleted_at' => null]);
    }

    public function test_class_referenced_by_an_exam_cannot_be_deleted(): void
    {
        $class = SchoolClass::create(['name' => 'Scheduled Class', 'class_order' => 6, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Physics', 'code' => 'PHY' . uniqid()]);

        \App\Models\Exam::create([
            'name' => 'Term Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name,
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.school-classes.destroy', $class->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_delete_a_class(): void
    {
        $class = SchoolClass::create(['name' => 'Protected Class', 'class_order' => 5, 'is_active' => true]);
        $clerk = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $clerk->roles()->attach($role->id);

        $response = $this->actingAs($clerk)->delete(route('admin.school-classes.destroy', $class->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'deleted_at' => null]);
    }
}
