<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk delete: TeacherSubjectAssignmentController::bulkDelete() lets an
 * admin remove multiple assignment rows (checkbox multi-select) in one
 * request instead of one destroy() submit per row. Authorization is
 * enforced per fetched row via the same policy destroy() uses -- a
 * mismatched/missing id must not let an unauthorized bulk request slip
 * through just because the check is batched.
 */
class TeacherSubjectAssignmentBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeAssignment(string $suffix = ''): TeacherClassSubjectAssignment
    {
        $class = SchoolClass::create(['name' => 'BD Class' . $suffix, 'class_order' => random_int(970950, 970999), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'BD Teacher' . $suffix, 'status' => 'active']);
        $subject = Subject::create(['name' => 'BD Subject' . $suffix, 'code' => 'BD-' . uniqid(), 'is_active' => true]);

        return TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026-2027',
        ]);
    }

    public function test_bulk_delete_removes_multiple_assignments(): void
    {
        $admin = $this->admin();
        $a1 = $this->makeAssignment('1');
        $a2 = $this->makeAssignment('2');
        $a3 = $this->makeAssignment('3');

        $response = $this->actingAs($admin)->deleteJson(route('admin.teacher-subject-assignments.bulk-delete'), [
            'ids' => [$a1->id, $a2->id],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'deleted_count' => 2]);
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['id' => $a1->id]);
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['id' => $a2->id]);
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $a3->id]);
    }

    public function test_bulk_delete_rejects_empty_ids(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->deleteJson(route('admin.teacher-subject-assignments.bulk-delete'), [
            'ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_delete_rejects_a_nonexistent_id(): void
    {
        $admin = $this->admin();
        $a1 = $this->makeAssignment('1');

        $response = $this->actingAs($admin)->deleteJson(route('admin.teacher-subject-assignments.bulk-delete'), [
            'ids' => [$a1->id, 999999],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $a1->id]);
    }

    public function test_unauthorized_role_gets_403_on_bulk_delete(): void
    {
        $user = User::factory()->create();
        $a1 = $this->makeAssignment('1');

        $response = $this->actingAs($user)->deleteJson(route('admin.teacher-subject-assignments.bulk-delete'), [
            'ids' => [$a1->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $a1->id]);
    }
}
