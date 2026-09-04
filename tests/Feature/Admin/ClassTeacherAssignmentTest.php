<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Academic setup completion: Class Teacher assignment. Canonical source of
 * truth is teacher_class_subject_assignments.is_class_teacher (see
 * ClassTeacherAssignmentService docblock) -- ClassTeacherAssignment
 * (free-text class, no section), TeacherClassAssignment (class FK but no
 * section), and the bare class_teacher table (backs the previously-broken
 * ClassTeacherPolicy/CBSEResultPolicy) are all confirmed dead: 0 real rows,
 * consumed by nothing that actually runs. This test covers the new
 * dedicated Admin\ClassTeacherManagementController screen and the two
 * policy fixes that now read the canonical table instead.
 */
class ClassTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function fixtures(): array
    {
        $classFour = SchoolClass::create(['name' => 'CT Class 4', 'class_order' => 970911, 'is_active' => true]);
        $classFive = SchoolClass::create(['name' => 'CT Class 5', 'class_order' => 970912, 'is_active' => true]);

        $sectionA = Section::create(['name' => 'CT-A']);
        $this->bridgeSectionToClass($classFour, $sectionA);
        $sectionB = Section::create(['name' => 'CT-B']);
        $this->bridgeSectionToClass($classFour, $sectionB);

        // Valid for Class 5 only -- not Class 4.
        $sectionC = Section::create(['name' => 'CT-C']);
        $this->bridgeSectionToClass($classFive, $sectionC);

        $teacher = Teacher::create(['name' => 'CT Teacher', 'status' => 'active']);
        $otherTeacher = Teacher::create(['name' => 'CT Teacher Two', 'status' => 'active']);
        $subject = Subject::create(['name' => 'CT Subject', 'code' => 'CT-'.uniqid(), 'is_active' => true]);
        $subject2 = Subject::create(['name' => 'CT Subject Two', 'code' => 'CT-'.uniqid(), 'is_active' => true]);

        return compact('classFour', 'classFive', 'sectionA', 'sectionB', 'sectionC', 'teacher', 'otherTeacher', 'subject', 'subject2');
    }

    // 1. Valid Class + Section + Teacher assignment.
    public function test_assign_creates_a_class_teacher_for_a_valid_class_and_section(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.class-teachers.assign', $f['classFour']), [
            'section_id' => $f['sectionA']->id,
            'teacher_id' => $f['teacher']->id,
            'subject_id' => $f['subject']->id,
            'academic_year' => '2026-2027',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'teacher_id' => $f['teacher']->id,
            'class_id' => $f['classFour']->id,
            'section_id' => $f['sectionA']->id,
            'is_class_teacher' => 1,
        ]);
    }

    // 2. Invalid section belonging to another class is rejected.
    public function test_assign_rejects_a_section_belonging_to_a_different_class(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.class-teachers.assign', $f['classFour']), [
            'section_id' => $f['sectionC']->id, // belongs to Class 5, not Class 4
            'teacher_id' => $f['teacher']->id,
            'subject_id' => $f['subject']->id,
            'academic_year' => '2026-2027',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('teacher_class_subject_assignments', [
            'teacher_id' => $f['teacher']->id,
            'class_id' => $f['classFour']->id,
        ]);
    }

    // 3. Duplicate/conflicting Class Teacher assignment is prevented.
    // 4. Changing Class Teacher works.
    public function test_assigning_a_new_class_teacher_safely_displaces_the_previous_one(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.class-teachers.assign', $f['classFour']), [
            'section_id' => $f['sectionA']->id,
            'teacher_id' => $f['teacher']->id,
            'subject_id' => $f['subject']->id,
            'academic_year' => '2026-2027',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('admin.class-teachers.assign', $f['classFour']), [
            'section_id' => $f['sectionA']->id,
            'teacher_id' => $f['otherTeacher']->id,
            'subject_id' => $f['subject2']->id,
            'academic_year' => '2026-2027',
        ])->assertSessionHas('success');

        // Exactly one class teacher for Class 4 / Section A: the new one.
        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'teacher_id' => $f['otherTeacher']->id, 'class_id' => $f['classFour']->id,
            'section_id' => $f['sectionA']->id, 'is_class_teacher' => 1,
        ]);
        $stillFlagged = TeacherClassSubjectAssignment::where('class_id', $f['classFour']->id)
            ->where('section_id', $f['sectionA']->id)
            ->where('is_class_teacher', true)
            ->count();
        $this->assertSame(1, $stillFlagged);
    }

    public function test_assign_enforces_the_maximum_two_classes_per_teacher(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        $classSix = SchoolClass::create(['name' => 'CT Class 6', 'class_order' => 970913, 'is_active' => true]);
        $classSeven = SchoolClass::create(['name' => 'CT Class 7', 'class_order' => 970914, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.class-teachers.assign', $f['classFour']), [
            'teacher_id' => $f['teacher']->id, 'subject_id' => $f['subject']->id, 'academic_year' => '2026-2027',
        ])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.class-teachers.assign', $classSix), [
            'teacher_id' => $f['teacher']->id, 'subject_id' => $f['subject']->id, 'academic_year' => '2026-2027',
        ])->assertSessionHas('success');

        $response = $this->actingAs($admin)->post(route('admin.class-teachers.assign', $classSeven), [
            'teacher_id' => $f['teacher']->id, 'subject_id' => $f['subject']->id, 'academic_year' => '2026-2027',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('teacher_class_subject_assignments', [
            'teacher_id' => $f['teacher']->id, 'class_id' => $classSeven->id,
        ]);
    }

    // 5. Unauthorized teacher (role) cannot modify another class's assignment.
    public function test_non_admin_role_cannot_assign_or_remove_a_class_teacher(): void
    {
        $f = $this->fixtures();
        $teacherRoleUser = $this->makeUserWithRole('teacher');

        $existing = TeacherClassSubjectAssignment::create([
            'teacher_id' => $f['teacher']->id, 'class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => '2026-2027', 'is_class_teacher' => true,
        ]);

        $this->actingAs($teacherRoleUser)->post(route('admin.class-teachers.assign', $f['classFour']), [
            'section_id' => $f['sectionB']->id, 'teacher_id' => $f['otherTeacher']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => '2026-2027',
        ])->assertForbidden();

        $this->actingAs($teacherRoleUser)->post(route('admin.class-teachers.remove', $existing))->assertForbidden();

        // 6. Existing valid assignment remains intact.
        $this->assertTrue($existing->fresh()->is_class_teacher);
    }

    public function test_remove_clears_the_flag_without_deleting_the_subject_assignment(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        $assignment = TeacherClassSubjectAssignment::create([
            'teacher_id' => $f['teacher']->id, 'class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => '2026-2027', 'is_class_teacher' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.class-teachers.remove', $assignment))
            ->assertSessionHas('success');

        $assignment->refresh();
        $this->assertFalse((bool) $assignment->is_class_teacher);
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $assignment->id]); // row still exists
    }

    // 7. Existing (plain, non-class-teacher) Teacher-Subject Assignment remains unaffected.
    public function test_generic_subject_assignment_screen_is_unaffected_by_class_teacher_assignment(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $f['otherTeacher']->id, 'class_id' => $f['classFour']->id,
            'section_id' => $f['sectionB']->id, 'subject_id' => $f['subject2']->id,
        ])->assertRedirect(route('admin.teacher-subject-assignments.index'));

        $this->actingAs($admin)->post(route('admin.class-teachers.assign', $f['classFour']), [
            'section_id' => $f['sectionA']->id, 'teacher_id' => $f['teacher']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => date('Y').'-'.(date('Y') + 1),
        ])->assertSessionHas('success');

        // The plain subject assignment from the generic screen is untouched.
        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'teacher_id' => $f['otherTeacher']->id, 'class_id' => $f['classFour']->id,
            'section_id' => $f['sectionB']->id, 'is_class_teacher' => 0,
        ]);
    }

    public function test_index_and_show_pages_render_for_admin(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $f['teacher']->id, 'class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => date('Y').'-'.(date('Y') + 1), 'is_class_teacher' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.class-teachers.index'))
            ->assertOk()
            ->assertSee('CT Class 4');

        $this->actingAs($admin)->get(route('admin.class-teachers.show', $f['classFour']))
            ->assertOk()
            ->assertSee('CT Teacher');
    }

    /**
     * Item 3 audit gap: index() computes an "N of M sections have a class
     * teacher" summary per class (assigned distinct slots -- section rows
     * plus the always-present "whole class" slot -- intersected against
     * is_class_teacher=true rows for the current academic year), but
     * nothing asserted the actual numbers rendered, only that the page
     * loaded. Class 4 has 2 section slots (A, B) plus the whole-class
     * slot = 3 total; only Section A is assigned, so 1 of 3.
     */
    public function test_index_shows_class_teacher_coverage_summary(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $f['teacher']->id, 'class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => date('Y').'-'.(date('Y') + 1), 'is_class_teacher' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.class-teachers.index'));

        $response->assertOk();
        $response->assertSee('1 / 3 assigned');
    }

    // --- Dependency fixes: ClassTeacherPolicy / CBSEResultPolicy now read the canonical table ---

    /**
     * Exercises ClassTeacherPolicy directly (not via $user->can()/
     * $this->authorize()): Student::class is separately bound to
     * StudentPolicy in AuthServiceProvider's $policies array, which has no
     * viewClassStudent/updateClassStudent methods of its own -- Laravel's
     * Gate therefore resolves those two ability names to StudentPolicy and
     * denies (method-not-found), never reaching ClassTeacherPolicy at all.
     * That routing collision is a separate, pre-existing bug in
     * Admin\ClassTeacherController's authorization wiring, independent of
     * (and not fixed by) this pass's actual target: correcting
     * ClassTeacherPolicy's own logic to read the canonical
     * is_class_teacher table instead of the always-empty class_teacher
     * pivot. This test verifies that logic fix in isolation.
     */
    public function test_class_teacher_policy_recognizes_the_canonical_assignment(): void
    {
        $f = $this->fixtures();
        $teacherUser = User::factory()->create();
        Role::firstOrCreate(['name' => 'class-teacher'], ['display_name' => 'Class Teacher']);
        $teacherUser->roles()->attach(Role::where('name', 'class-teacher')->first()->id);
        $f['teacher']->update(['user_id' => $teacherUser->id]);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $f['teacher']->id, 'class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => date('Y').'-'.(date('Y') + 1), 'is_class_teacher' => true,
        ]);

        $student = Student::create([
            'name' => 'CT Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $f['classFour']->id, 'school_class_id' => $f['classFour']->id,
        ]);

        $policy = new \App\Policies\ClassTeacherPolicy;
        $this->assertTrue($policy->viewClassStudent($teacherUser, $student));
        $this->assertTrue($policy->updateClassStudent($teacherUser, $student));

        $unrelatedStudent = Student::create([
            'name' => 'CT Unrelated Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $f['classFive']->id, 'school_class_id' => $f['classFive']->id,
        ]);
        $this->assertFalse($policy->viewClassStudent($teacherUser, $unrelatedStudent));
    }

    public function test_cbse_result_policy_recognizes_the_canonical_class_teacher_assignment(): void
    {
        $f = $this->fixtures();
        $teacherUser = User::factory()->create();
        Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $teacherUser->roles()->attach(Role::where('name', 'teacher')->first()->id);
        $f['teacher']->update(['user_id' => $teacherUser->id]);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $f['teacher']->id, 'class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => date('Y').'-'.(date('Y') + 1), 'is_class_teacher' => true,
        ]);

        $student = Student::create([
            'name' => 'CT CBSE Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $f['classFour']->id, 'school_class_id' => $f['classFour']->id,
        ]);
        $exam = \App\Models\Exam::create([
            'name' => 'CT Exam', 'exam_type' => 'term', 'class_id' => $f['classFour']->id,
            'class_name' => $f['classFour']->name, 'subject_id' => $f['subject']->id, 'subject' => $f['subject']->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $cbse = \App\Models\CBSEResult::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject_id' => $f['subject']->id,
            'pt_marks' => 5, 'notebook_marks' => 2, 'sea_marks' => 2, 'exam_marks' => 20,
            'academic_year' => '2026-27', 'term' => 'Term 1',
        ]);

        $policy = new \App\Policies\CBSEResultPolicy;
        $this->assertTrue($policy->view($teacherUser, $cbse));
        $this->assertTrue($policy->update($teacherUser, $cbse));
    }

    public function test_teacher_class_teacher_school_class_helper_reflects_canonical_table(): void
    {
        $f = $this->fixtures();

        $this->assertFalse($f['teacher']->isClassTeacherOfSchoolClass($f['classFour']->id));

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $f['teacher']->id, 'class_id' => $f['classFour']->id, 'section_id' => $f['sectionA']->id,
            'subject_id' => $f['subject']->id, 'academic_year' => date('Y').'-'.(date('Y') + 1), 'is_class_teacher' => true,
        ]);

        $this->assertTrue($f['teacher']->fresh()->isClassTeacherOfSchoolClass($f['classFour']->id));
        $this->assertFalse($f['teacher']->fresh()->isClassTeacherOfSchoolClass($f['classFive']->id));
    }
}
