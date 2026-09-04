<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\TimetablePdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Priority 1.4: self-service PDF export, one download route each for
 * Student\StudentTimetableController, Teacher\TeacherTimetableController,
 * and Parent\TimetableController -- same server-resolved own-identity
 * security as those controllers' existing today()/weekly()/index() routes
 * (no route parameter names a student/teacher/class, so there is nothing
 * to tamper with).
 */
class TimetablePdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedTimetable(string $label): array
    {
        $class = SchoolClass::create(['name' => "{$label} Class", 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => "{$label} Subject", 'code' => strtoupper($label) . uniqid()]);
        $teacher = Teacher::create(['name' => "{$label} Teacher", 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        $slot = TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'room_number' => "{$label}-Room", 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        return compact('class', 'subject', 'teacher', 'timing', 'slot');
    }

    private function studentUser(SchoolClass $class): array
    {
        $student = Student::create([
            'name' => 'PDF Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student'])->id);
        $user->student()->save($student);

        return [$user, $student];
    }

    public function test_student_can_download_timetable_pdf(): void
    {
        $data = $this->seedTimetable('Alpha');
        [$user, $student] = $this->studentUser($data['class']);

        $response = $this->actingAs($user)->get(route('student.timetable.download-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_contains_student_timetable_grid(): void
    {
        $data = $this->seedTimetable('Beta');
        [$user, $student] = $this->studentUser($data['class']);

        $html = (new TimetablePdfGenerator())->generateTimetableView([
            'title' => $student->name,
            'subtitle' => 'Student Timetable',
            'days' => collect(['Monday']),
            'periods' => collect(['P1']),
            'grid' => ['P1' => ['Monday' => ['Beta Subject', 'Beta Teacher', 'Room: Beta-Room']]],
        ]);

        $this->assertStringContainsString('Beta Subject', $html);
        $this->assertStringContainsString('Beta Teacher', $html);
        $this->assertStringContainsString('Beta-Room', $html);
        $this->assertStringContainsString($student->name, $html);
    }

    public function test_parent_can_download_child_pdf(): void
    {
        $data = $this->seedTimetable('Gamma');
        $student = Student::create([
            'name' => 'PDF Child', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2015-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-PDF-' . uniqid(), 'phone' => (string) random_int(6000000000, 9999999999),
            'address' => 'Addr', 'school_class_id' => $data['class']->id,
        ]);
        $parent = ParentModel::create([
            'name' => 'PDF Parent', 'email' => 'pdfparent' . uniqid() . '@example.com',
            'password' => bcrypt('password123'), 'student_id' => $student->id,
        ]);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.timetable.download-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_teacher_can_download_class_pdf(): void
    {
        $data = $this->seedTimetable('Delta');
        $login = TeacherLogin::create([
            'teacher_id' => $data['teacher']->id,
            'username' => 'pdfteacher' . uniqid(),
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable.download-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_file_readable_and_valid(): void
    {
        $data = $this->seedTimetable('Epsilon');
        [$user, $student] = $this->studentUser($data['class']);

        $response = $this->actingAs($user)->get(route('student.timetable.download-pdf'));

        $response->assertOk();
        // A real PDF file always starts with this magic header.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
