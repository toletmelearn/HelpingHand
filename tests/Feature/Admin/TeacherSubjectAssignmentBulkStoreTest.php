<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk-assign-by-teacher: TeacherSubjectAssignmentController::bulkStore()
 * lets an admin create every class/section/subject row for one teacher in a
 * single request instead of resubmitting the single-record form per class.
 * It must enforce the same invariants as store()/update(): section ownership
 * (SchoolClass::validSectionIds()) per row, and class-teacher assignment
 * going through ClassTeacherAssignmentService (the sole canonical writer of
 * is_class_teacher) rather than a separate table.
 */
class TeacherSubjectAssignmentBulkStoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function fixtures(): array
    {
        $classFour = SchoolClass::create(['name' => 'BULK Class 4', 'class_order' => 970911, 'is_active' => true]);
        $classFive = SchoolClass::create(['name' => 'BULK Class 5', 'class_order' => 970912, 'is_active' => true]);

        $sectionA = Section::create(['name' => 'BULK-A']);
        $this->bridgeSectionToClass($classFour, $sectionA);
        $sectionB = Section::create(['name' => 'BULK-B']);
        $this->bridgeSectionToClass($classFour, $sectionB);

        // Section C is valid for Class 5 only -- not Class 4.
        $sectionC = Section::create(['name' => 'BULK-C']);
        $this->bridgeSectionToClass($classFive, $sectionC);

        $teacher = Teacher::create(['name' => 'Bulk Teacher', 'status' => 'active']);
        $english = Subject::create(['name' => 'Bulk English', 'code' => 'BE-' . uniqid(), 'is_active' => true]);
        $social = Subject::create(['name' => 'Bulk Social Studies', 'code' => 'BS-' . uniqid(), 'is_active' => true]);

        return compact('classFour', 'classFive', 'sectionA', 'sectionB', 'sectionC', 'teacher', 'english', 'social');
    }

    public function test_bulk_store_creates_multiple_assignments_for_one_teacher(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.teacher-subject-assignments.bulk-create'), [
            'teacher_id' => $f['teacher']->id,
            'assignments' => [
                ['class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id, 'subject_id' => $f['english']->id, 'periods_per_week' => 4],
                ['class_id' => $f['classFour']->id, 'section_id' => $f['sectionB']->id, 'subject_id' => $f['english']->id, 'periods_per_week' => 3],
                ['class_id' => $f['classFive']->id, 'section_id' => $f['sectionC']->id, 'subject_id' => $f['social']->id, 'periods_per_week' => 2],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'data' => ['assignments_created' => 3]]);

        $this->assertDatabaseCount('teacher_class_subject_assignments', 3);
        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'teacher_id' => $f['teacher']->id,
            'class_id' => $f['classFour']->id,
            'section_id' => $f['sectionA']->id,
            'periods_per_week' => 4,
        ]);
    }

    public function test_bulk_store_rejects_a_section_belonging_to_a_different_class(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.teacher-subject-assignments.bulk-create'), [
            'teacher_id' => $f['teacher']->id,
            'assignments' => [
                ['class_id' => $f['classFour']->id, 'section_id' => $f['sectionC']->id, 'subject_id' => $f['english']->id, 'periods_per_week' => 3],
            ],
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['teacher_id' => $f['teacher']->id]);
    }

    public function test_bulk_store_makes_class_teacher_via_the_canonical_service(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.teacher-subject-assignments.bulk-create'), [
            'teacher_id' => $f['teacher']->id,
            'assignments' => [
                ['class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id, 'subject_id' => $f['english']->id, 'periods_per_week' => 4],
            ],
            'make_class_teacher' => true,
            'class_teacher_class_id' => $f['classFour']->id,
            'class_teacher_section_id' => $f['sectionA']->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'teacher_id' => $f['teacher']->id,
            'class_id' => $f['classFour']->id,
            'section_id' => $f['sectionA']->id,
            'is_class_teacher' => true,
        ]);
        // No separate legacy class_teacher_assignments row was created.
        $this->assertDatabaseCount('class_teacher_assignments', 0);
    }

    public function test_bulk_store_rejects_class_teacher_selection_not_among_the_submitted_rows(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.teacher-subject-assignments.bulk-create'), [
            'teacher_id' => $f['teacher']->id,
            'assignments' => [
                ['class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id, 'subject_id' => $f['english']->id, 'periods_per_week' => 4],
            ],
            'make_class_teacher' => true,
            'class_teacher_class_id' => $f['classFive']->id,
            'class_teacher_section_id' => $f['sectionC']->id,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['teacher_id' => $f['teacher']->id]);
    }

    public function test_unauthorized_role_gets_403_on_bulk_store(): void
    {
        $f = $this->fixtures();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.teacher-subject-assignments.bulk-create'), [
            'teacher_id' => $f['teacher']->id,
            'assignments' => [
                ['class_id' => $f['classFour']->id, 'subject_id' => $f['english']->id],
            ],
        ]);

        $response->assertForbidden();
    }
}
