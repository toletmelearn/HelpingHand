<?php

namespace Tests\Feature\Teacher;

use App\Models\AdmitCard;
use App\Models\AdmitCardFormat;
use App\Models\Exam;
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
 * TeacherAdmitCardController: mirrors TeacherDatesheetController's
 * ownership scoping (real TeacherClassSubjectAssignment rows only) and
 * Parent/StudentAdmitCardController's published/locked-only,
 * revoked-is-never-visible visibility rule -- see
 * ATC-1: "Completes admit card feature for all 4 roles" commit.
 */
class TeacherAdmitCardTest extends TestCase
{
    use RefreshDatabase;

    private function teacherLogin(SchoolClass $class, ?Section $section, Subject $subject): array
    {
        $teacher = Teacher::create(['name' => 'ATC Teacher', 'status' => 'active']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'atc' . uniqid(), 'password' => Hash::make('secret123'),
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'section_id' => $section?->id,
            'subject_id' => $subject->id, 'academic_year' => '2026-2027', 'is_class_teacher' => false,
        ]);

        return [$login, $teacher];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function studentIn(SchoolClass $class, ?Section $section): Student
    {
        return Student::create([
            'name' => 'ATC Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'section_id' => $section?->id,
        ]);
    }

    private function fixtures(): array
    {
        $class = SchoolClass::create(['name' => 'ATC Class A', 'class_order' => 993001, 'is_active' => true]);
        $section = Section::create(['name' => 'ATC-A']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'ATC Subject', 'code' => 'ATC-' . uniqid(), 'is_active' => true]);

        $student = $this->studentIn($class, $section);

        $exam = Exam::create([
            'name' => 'ATC Exam', 'exam_type' => 'term', 'class_id' => $class->id, 'class_name' => $class->name,
            'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '11:00',
            'total_marks' => 100, 'passing_marks' => 33, 'academic_year' => '2026-2027', 'status' => 'scheduled',
        ]);

        $format = AdmitCardFormat::create(['name' => 'ATC Format', 'is_active' => true]);
        $admin = $this->admin();
        $admitCard = AdmitCard::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'admit_card_format_id' => $format->id,
            'academic_session' => '2026-2027', 'status' => 'published', 'published_at' => now(), 'published_by' => $admin->id,
        ]);

        return compact('class', 'section', 'subject', 'student', 'exam', 'admitCard', 'admin', 'format');
    }

    public function test_assigned_teacher_sees_published_admit_card_in_index_and_show(): void
    {
        $f = $this->fixtures();
        [$login] = $this->teacherLogin($f['class'], $f['section'], $f['subject']);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.index'))
            ->assertOk()->assertSee($f['exam']->name);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.show', $f['admitCard']))
            ->assertOk();
    }

    public function test_unassigned_teacher_cannot_view_the_admit_card(): void
    {
        $f = $this->fixtures();

        $otherClass = SchoolClass::create(['name' => 'ATC Class B', 'class_order' => 993002, 'is_active' => true]);
        $otherSection = Section::create(['name' => 'ATC-B']);
        $this->bridgeSectionToClass($otherClass, $otherSection);
        $otherSubject = Subject::create(['name' => 'ATC Subject B', 'code' => 'ATC-' . uniqid(), 'is_active' => true]);
        [$login] = $this->teacherLogin($otherClass, $otherSection, $otherSubject);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.index'))
            ->assertOk()->assertDontSee($f['exam']->name);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.show', $f['admitCard']))
            ->assertForbidden();
    }

    public function test_teacher_assigned_to_a_different_section_of_the_same_class_cannot_view_it(): void
    {
        $f = $this->fixtures();
        $otherSection = Section::create(['name' => 'ATC-C']);
        $this->bridgeSectionToClass($f['class'], $otherSection);
        [$login] = $this->teacherLogin($f['class'], $otherSection, $f['subject']);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.show', $f['admitCard']))
            ->assertForbidden();
    }

    public function test_teacher_assigned_to_the_whole_class_sees_every_section(): void
    {
        $f = $this->fixtures();
        // section_id null on the assignment = whole-class, mirrors
        // TeacherDatesheetController's own null-section-means-all rule.
        [$login] = $this->teacherLogin($f['class'], null, $f['subject']);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.show', $f['admitCard']))
            ->assertOk();
    }

    public function test_draft_admit_card_is_not_visible_even_to_an_assigned_teacher(): void
    {
        $f = $this->fixtures();
        $f['admitCard']->update(['status' => 'draft', 'published_at' => null, 'published_by' => null]);
        [$login] = $this->teacherLogin($f['class'], $f['section'], $f['subject']);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.index'))
            ->assertOk()->assertDontSee($f['exam']->name);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.show', $f['admitCard']))
            ->assertForbidden();
    }

    public function test_revoked_admit_card_is_not_visible_even_to_an_assigned_teacher(): void
    {
        $f = $this->fixtures();
        $f['admitCard']->update(['status' => 'revoked']);
        [$login] = $this->teacherLogin($f['class'], $f['section'], $f['subject']);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.show', $f['admitCard']))
            ->assertForbidden();
    }

    public function test_assigned_teacher_can_download_the_pdf(): void
    {
        $f = $this->fixtures();
        [$login] = $this->teacherLogin($f['class'], $f['section'], $f['subject']);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.download-pdf', $f['admitCard']))
            ->assertOk();
    }

    public function test_unassigned_teacher_cannot_download_the_pdf(): void
    {
        $f = $this->fixtures();
        $otherClass = SchoolClass::create(['name' => 'ATC Class D', 'class_order' => 993004, 'is_active' => true]);
        [$login] = $this->teacherLogin($otherClass, null, $f['subject']);

        $this->actingAs($login, 'teacher')->get(route('teacher.admit-cards.download-pdf', $f['admitCard']))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_teacher_login(): void
    {
        $f = $this->fixtures();

        $this->get(route('teacher.admit-cards.index'))->assertRedirect(route('teacher.login'));
        $this->get(route('teacher.admit-cards.show', $f['admitCard']))->assertRedirect(route('teacher.login'));
    }
}
