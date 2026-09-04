<?php

namespace Tests\Feature;

use App\Models\AdmitCard;
use App\Models\AdmitCardFormat;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Category A1 integration coverage: a student seeded with school_class_id
 * set to "Class 3" but a deliberately mismatched legacy class string
 * ("III") must show "Class 3" -- never "III" -- on every page that used
 * to read the raw string directly. Covers the three page types the fix
 * loop asked for at minimum: an official document (admit card), a
 * parent-facing self-service page, and a report.
 */
class StudentDisplayClassNameIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeMismatchedStudent(): Student
    {
        $class = SchoolClass::create(['name' => 'Class 3', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        return Student::create([
            'name' => 'Mismatch Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'school_class_id' => $class->id, 'class_id' => $class->id,
            // Deliberately mismatched legacy string -- this is exactly the
            // "Class 1 in one place, III in another" symptom being fixed.
            'class' => 'III',
        ]);
    }

    public function test_admin_admit_card_index_shows_the_correct_class_not_the_legacy_string(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeMismatchedStudent();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'DCN' . uniqid()]);
        $exam = Exam::create([
            'name' => 'Display Class Exam', 'exam_type' => 'term', 'class_id' => $student->school_class_id,
            'class_name' => 'Class 3', 'subject_id' => $subject->id, 'subject' => 'Maths', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);
        $format = AdmitCardFormat::create(['name' => 'Default Format', 'is_active' => true]);
        AdmitCard::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'admit_card_format_id' => $format->id,
            'academic_session' => '2026-27', 'status' => 'published',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.admit-cards.index'));

        $response->assertOk();
        $response->assertSee('Class 3');
        $response->assertDontSee('III');
    }

    public function test_parent_dashboard_shows_the_correct_class_not_the_legacy_string(): void
    {
        $student = $this->makeMismatchedStudent();
        $parent = ParentModel::create([
            'name' => 'Mismatch Parent', 'email' => 'mismatchparent' . uniqid() . '@example.com',
            'password' => bcrypt('password123'), 'student_id' => $student->id,
        ]);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.dashboard'));

        $response->assertOk();
        $response->assertSee('Class 3');
        $response->assertDontSee('III');
    }

    public function test_attendance_student_report_shows_the_correct_class_not_the_legacy_string(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeMismatchedStudent();
        Attendance::create(['student_id' => $student->id, 'date' => now()->toDateString(), 'status' => 'present']);

        $response = $this->actingAs($admin)->get(route('attendance.student.report', $student->id));

        $response->assertOk();
        $response->assertSee('Class 3');
        $response->assertDontSee('III');
    }
}
