<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Datesheet;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Teacher/Student/Parent Datesheet visibility -- IDOR and scoping checks.
 * Reuses the exact fixture patterns already established for
 * TimetableSlotPolicy/StudentResultController/ParentExamPaperController
 * visibility tests this session.
 */
class DatesheetVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function publishedDatesheetWithEntry(SchoolClass $class, ?Section $section, Subject $subject): Datesheet
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        $session = AcademicSession::create(['name' => '2026-2027', 'code' => 'DV-' . uniqid(), 'is_current' => true, 'is_active' => true, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);

        $datesheet = Datesheet::create([
            'name' => 'DV Datesheet', 'exam_type' => 'Term 1', 'academic_session_id' => $session->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-15', 'status' => 'draft', 'created_by' => $admin->id,
        ]);
        $datesheet->classes()->create(['school_class_id' => $class->id, 'section_id' => $section?->id]);
        $datesheet->entries()->create([
            'school_class_id' => $class->id, 'section_id' => $section?->id, 'subject_id' => $subject->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);

        $this->actingAs($admin)->post(route('admin.datesheets.submit', $datesheet));
        $this->actingAs($admin)->post(route('admin.datesheets.approve', $datesheet));
        $this->actingAs($admin)->post(route('admin.datesheets.publish', $datesheet));

        return $datesheet->fresh();
    }

    // --- Teacher visibility -------------------------------------------------

    public function test_teacher_sees_published_datesheet_for_their_assigned_class(): void
    {
        $class = SchoolClass::create(['name' => 'DV Class A', 'class_order' => 991001, 'is_active' => true]);
        $section = Section::create(['name' => 'DV-A']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'DV Subject A', 'code' => 'DV-' . uniqid(), 'is_active' => true]);
        $this->publishedDatesheetWithEntry($class, $section, $subject);

        $teacher = Teacher::create(['name' => 'DV Teacher A', 'status' => 'active']);
        $login = TeacherLogin::create(['teacher_id' => $teacher->id, 'username' => 'dva' . uniqid(), 'password' => Hash::make('secret123')]);
        TeacherClassSubjectAssignment::create(['teacher_id' => $teacher->id, 'class_id' => $class->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'academic_year' => '2026-2027']);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.datesheets.index'));

        $response->assertOk();
        $response->assertSee('DV Subject A');
    }

    public function test_teacher_does_not_see_datesheet_for_an_unassigned_class(): void
    {
        $class = SchoolClass::create(['name' => 'DV Class B', 'class_order' => 991002, 'is_active' => true]);
        $section = Section::create(['name' => 'DV-B']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'DV Subject B', 'code' => 'DV-' . uniqid(), 'is_active' => true]);
        $this->publishedDatesheetWithEntry($class, $section, $subject);

        // A teacher with NO assignment at all.
        $teacher = Teacher::create(['name' => 'DV Teacher B', 'status' => 'active']);
        $login = TeacherLogin::create(['teacher_id' => $teacher->id, 'username' => 'dvb' . uniqid(), 'password' => Hash::make('secret123')]);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.datesheets.index'));

        $response->assertOk();
        $response->assertDontSee('DV Subject B');
    }

    // --- Student visibility --------------------------------------------------

    public function test_student_sees_published_datesheet_for_their_own_class(): void
    {
        $class = SchoolClass::create(['name' => 'DV Class C', 'class_order' => 991003, 'is_active' => true]);
        $section = Section::create(['name' => 'DV-C']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'DV Subject C', 'code' => 'DV-' . uniqid(), 'is_active' => true]);
        $this->publishedDatesheetWithEntry($class, $section, $subject);

        $student = Student::create([
            'name' => 'DV Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);
        $studentUser = User::factory()->create();
        $studentUser->roles()->attach(Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student'])->id);
        $studentUser->student()->save($student);

        $response = $this->actingAs($studentUser)->get(route('student.datesheets.index'));

        $response->assertOk();
        $response->assertSee('DV Subject C');
    }

    public function test_student_does_not_see_datesheet_for_a_different_class(): void
    {
        $class = SchoolClass::create(['name' => 'DV Class D', 'class_order' => 991004, 'is_active' => true]);
        $section = Section::create(['name' => 'DV-D']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'DV Subject D', 'code' => 'DV-' . uniqid(), 'is_active' => true]);
        $this->publishedDatesheetWithEntry($class, $section, $subject);

        $otherClass = SchoolClass::create(['name' => 'DV Class D2', 'class_order' => 991005, 'is_active' => true]);
        $student = Student::create([
            'name' => 'DV Student D2', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $otherClass->id, 'school_class_id' => $otherClass->id,
        ]);
        $studentUser = User::factory()->create();
        $studentUser->roles()->attach(Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student'])->id);
        $studentUser->student()->save($student);

        $response = $this->actingAs($studentUser)->get(route('student.datesheets.index'));

        $response->assertOk();
        $response->assertDontSee('DV Subject D');
    }

    // --- Parent visibility ----------------------------------------------------

    public function test_parent_sees_published_datesheet_for_their_child_class(): void
    {
        $class = SchoolClass::create(['name' => 'DV Class E', 'class_order' => 991006, 'is_active' => true]);
        $section = Section::create(['name' => 'DV-E']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'DV Subject E', 'code' => 'DV-' . uniqid(), 'is_active' => true]);
        $this->publishedDatesheetWithEntry($class, $section, $subject);

        $student = Student::create([
            'name' => 'DV Student E', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);
        $parent = ParentModel::create([
            'name' => 'DV Parent E', 'email' => 'dvparente' . uniqid() . '@example.com', 'phone' => (string) random_int(6000000000, 9999999999),
            'password' => Hash::make('secret123'), 'student_id' => $student->id,
        ]);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.datesheets.index'));

        $response->assertOk();
        $response->assertSee('DV Subject E');
    }
}
