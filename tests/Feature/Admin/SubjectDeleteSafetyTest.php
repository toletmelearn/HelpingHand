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
 * Priority audit finding F1: Admin\SubjectController::destroy() previously
 * deleted (soft-deleted) any Subject with zero dependency check -- even one
 * actively referenced by a timetable slot or a teacher class/subject
 * assignment. Since Subject uses SoftDeletes, the real DB foreign keys on
 * both tables (ON DELETE CASCADE) never fire on this path -- same class of
 * bug already fixed for Sections and Teachers. Proves the fix blocks
 * deletion when either dependency exists, and still allows it when
 * genuinely unused.
 */
class SubjectDeleteSafetyTest extends TestCase
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

    public function test_admin_can_delete_a_subject_with_no_dependencies(): void
    {
        $subject = Subject::create(['name' => 'Unused Subject', 'code' => 'UNU' . uniqid()]);

        $response = $this->actingAs($this->admin)->delete(route('admin.subjects.destroy', $subject->id));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);
    }

    public function test_subject_referenced_by_a_timetable_slot_cannot_be_deleted(): void
    {
        $subject = Subject::create(['name' => 'In Use Subject', 'code' => 'IUS' . uniqid()]);
        $class = SchoolClass::create(['name' => 'Grade X', 'class_order' => 1, 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'T']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.subjects.destroy', $subject->id));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'deleted_at' => null]);
    }

    public function test_subject_referenced_by_a_teacher_assignment_cannot_be_deleted(): void
    {
        $subject = Subject::create(['name' => 'Assigned Subject', 'code' => 'ASG' . uniqid()]);
        $class = SchoolClass::create(['name' => 'Grade Y', 'class_order' => 2, 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'T2']);

        DB::table('teacher_class_subject_assignments')->insert([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'is_class_teacher' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.subjects.destroy', $subject->id));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'deleted_at' => null]);
    }

    public function test_subject_referenced_by_an_exam_cannot_be_deleted(): void
    {
        $subject = Subject::create(['name' => 'Chemistry', 'code' => 'CHM' . uniqid()]);
        $class = SchoolClass::create(['name' => 'Grade Z', 'class_order' => 3, 'is_active' => true]);

        \App\Models\Exam::create([
            'name' => 'Term Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name,
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.subjects.destroy', $subject->id));

        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_delete_a_subject(): void
    {
        $subject = Subject::create(['name' => 'Protected Subject', 'code' => 'PRO' . uniqid()]);
        $clerk = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $clerk->roles()->attach($role->id);

        $response = $this->actingAs($clerk)->delete(route('admin.subjects.destroy', $subject->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'deleted_at' => null]);
    }
}
