<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Result;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exams V1 completion pass: results/cbse_results/exam_papers/
 * exam_blueprints/admit_cards/exam_seating_arrangements all cascade-delete
 * on exams.id, but neither Admin\ExamController::destroy() nor
 * Teacher\TeacherExamController::destroy() ran any dependency check before
 * calling Exam::delete() -- a single click could silently wipe every
 * student's recorded marks for an exam. Also covers the two other gaps
 * found in the same controller: no duplicate-exam prevention, and no
 * protection against changing class/subject/marks after results exist.
 */
class ExamDependencyProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Dep Test Student ' . uniqid(), 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2012-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
        ]);
    }

    private function makeExam(SchoolClass $class, array $overrides = []): Exam
    {
        return Exam::create(array_merge([
            'name' => 'Dependency Test Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math',
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'term' => 'Term 1', 'status' => 'active',
        ], $overrides));
    }

    // ------------------------------------------------------------
    // destroy() dependency protection (Admin)
    // ------------------------------------------------------------

    public function test_admin_cannot_delete_an_exam_with_recorded_results(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Dep Class A', 'class_order' => 951, 'is_active' => true]);
        $exam = $this->makeExam($class);
        $student = $this->makeStudent();
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A', 'academic_year' => '2026-27',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.exams.destroy', $exam));

        $response->assertSessionHas('error');
        $this->assertNotNull(Exam::find($exam->id), 'exam must survive a blocked delete');
        $this->assertDatabaseHas('results', ['exam_id' => $exam->id]);
    }

    public function test_admin_cannot_delete_an_exam_with_exam_papers(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Dep Class B', 'class_order' => 952, 'is_active' => true]);
        $exam = $this->makeExam($class);
        \App\Models\ExamPaper::create([
            'title' => 'Paper 1', 'subject' => 'Math', 'exam_type' => 'term',
            'academic_year' => '2026-27', 'exam_id' => $exam->id, 'class_id' => $class->id,
            'is_published' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.exams.destroy', $exam));

        $response->assertSessionHas('error');
        $this->assertNotNull(Exam::find($exam->id));
    }

    public function test_admin_can_delete_an_exam_with_no_dependencies(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Dep Class C', 'class_order' => 953, 'is_active' => true]);
        $exam = $this->makeExam($class);

        $response = $this->actingAs($admin)->delete(route('admin.exams.destroy', $exam));

        $response->assertSessionHas('success');
        $this->assertNull(Exam::find($exam->id));
    }

    // ------------------------------------------------------------
    // destroy() dependency protection (Teacher)
    // ------------------------------------------------------------

    private function teacherLogin(): array
    {
        $teacher = Teacher::create(['name' => 'Dep Test Teacher', 'status' => 'active', 'is_exam_head' => true]);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'deptest' . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        return [$teacher, $login];
    }

    public function test_teacher_cannot_delete_an_exam_with_recorded_results(): void
    {
        [$teacher, $login] = $this->teacherLogin();
        $class = SchoolClass::create(['name' => 'Dep Class D', 'class_order' => 954, 'is_active' => true]);
        $exam = $this->makeExam($class, ['created_by' => $teacher->id]);
        $student = $this->makeStudent();
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A', 'academic_year' => '2026-27',
        ]);

        $response = $this->actingAs($login, 'teacher')->delete(route('teacher.exams.destroy', $exam));

        $response->assertSessionHas('error');
        $this->assertNotNull(Exam::find($exam->id));
    }

    // ------------------------------------------------------------
    // Duplicate prevention (store)
    // ------------------------------------------------------------

    public function test_duplicate_exam_for_same_class_subject_term_year_is_rejected(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Dup Class', 'class_order' => 955, 'is_active' => true]);
        $this->makeExam($class);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            'name' => 'Second Attempt', 'exam_type' => 'term', 'class_id' => $class->id,
            'subject' => 'Math', 'exam_date' => today()->addDays(6)->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'term' => 'Term 1', 'status' => 'active',
        ]);

        $response->assertSessionHasErrors('subject');
        $this->assertSame(1, Exam::where('class_id', $class->id)->count());
    }

    public function test_same_class_different_subject_is_not_a_duplicate(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Non Dup Class', 'class_order' => 956, 'is_active' => true]);
        $this->makeExam($class);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            'name' => 'Science Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'subject' => 'Science', 'exam_date' => today()->addDays(6)->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'term' => 'Term 1', 'status' => 'active',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(2, Exam::where('class_id', $class->id)->count());
    }

    // ------------------------------------------------------------
    // update() protection once results exist
    // ------------------------------------------------------------

    public function test_total_marks_cannot_change_once_results_are_recorded(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Lock Class', 'class_order' => 957, 'is_active' => true]);
        $exam = $this->makeExam($class);
        $student = $this->makeStudent();
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A', 'academic_year' => '2026-27',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.exams.update', $exam), [
            'name' => $exam->name, 'exam_type' => $exam->exam_type, 'class_id' => $class->id,
            'subject' => $exam->subject, 'exam_date' => $exam->exam_date->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 150, 'passing_marks' => 33,
            'academic_year' => $exam->academic_year, 'term' => $exam->term, 'status' => 'active',
        ]);

        $response->assertSessionHasErrors('total_marks');
        $this->assertSame(100.0, $exam->fresh()->total_marks);
    }

    public function test_name_and_description_can_still_change_once_results_are_recorded(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Lock Class 2', 'class_order' => 958, 'is_active' => true]);
        $exam = $this->makeExam($class);
        $student = $this->makeStudent();
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A', 'academic_year' => '2026-27',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.exams.update', $exam), [
            'name' => 'Renamed Exam', 'exam_type' => $exam->exam_type, 'class_id' => $class->id,
            'subject' => $exam->subject, 'exam_date' => $exam->exam_date->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => $exam->academic_year, 'term' => $exam->term, 'status' => 'completed',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('Renamed Exam', $exam->fresh()->name);
        $this->assertSame('completed', $exam->fresh()->status);
    }

    public function test_total_marks_can_still_change_freely_before_any_results_exist(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'Free Class', 'class_order' => 959, 'is_active' => true]);
        $exam = $this->makeExam($class);

        $response = $this->actingAs($admin)->put(route('admin.exams.update', $exam), [
            'name' => $exam->name, 'exam_type' => $exam->exam_type, 'class_id' => $class->id,
            'subject' => $exam->subject, 'exam_date' => $exam->exam_date->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 150, 'passing_marks' => 33,
            'academic_year' => $exam->academic_year, 'term' => $exam->term, 'status' => 'active',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(150.0, $exam->fresh()->total_marks);
    }

    // ------------------------------------------------------------
    // Authorization / parameter tampering
    // ------------------------------------------------------------

    public function test_non_admin_cannot_delete_an_exam(): void
    {
        $user = User::factory()->create();
        $class = SchoolClass::create(['name' => 'Authz Class', 'class_order' => 960, 'is_active' => true]);
        $exam = $this->makeExam($class);

        $response = $this->actingAs($user)->delete(route('admin.exams.destroy', $exam));

        $response->assertForbidden();
        $this->assertNotNull(Exam::find($exam->id));
    }

    public function test_guest_cannot_delete_an_exam(): void
    {
        $class = SchoolClass::create(['name' => 'Guest Class', 'class_order' => 961, 'is_active' => true]);
        $exam = $this->makeExam($class);

        $response = $this->delete(route('admin.exams.destroy', $exam));

        $response->assertRedirect(route('login'));
        $this->assertNotNull(Exam::find($exam->id));
    }
}
