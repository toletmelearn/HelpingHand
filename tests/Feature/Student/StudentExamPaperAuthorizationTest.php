<?php

namespace Tests\Feature\Student;

use App\Models\ExamPaper;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exams V1 completion pass: Student\StudentExamPaperController previously
 * called Auth::guard('student')->user() in all three actions -- but
 * config/auth.php never defines a 'student' guard (only web/parent/
 * teacher; every sibling student-facing controller, e.g.
 * StudentResultController, correctly resolves the student via
 * Auth::user()->student instead). That call threw
 * InvalidArgumentException("Auth guard [student] is not defined.")
 * unconditionally, so every request to /student/exam-papers/* 500'd for
 * every real student before this fix. Separately, download() (and the
 * missing show()) had no class-ownership check at all, unlike its
 * correctly-guarded Parent\ParentExamPaperController sibling.
 */
class StudentExamPaperAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function studentUser(SchoolClass $class): array
    {
        $user = User::factory()->create();
        $student = Student::create([
            'name' => 'Exam Paper Test Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2012-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'school_class_id' => $class->id, 'class' => $class->name, 'user_id' => $user->id,
        ]);

        return [$user, $student];
    }

    private function makePaper(SchoolClass $class, array $overrides = []): ExamPaper
    {
        return ExamPaper::create(array_merge([
            'title' => 'Sample Paper', 'subject' => 'Math', 'exam_type' => 'term',
            'academic_year' => '2026-27', 'class_id' => $class->id, 'is_published' => true,
        ], $overrides));
    }

    public function test_student_can_view_the_exam_papers_index_without_a_fatal_error(): void
    {
        $class = SchoolClass::create(['name' => 'SEP Class A', 'class_order' => 971, 'is_active' => true]);
        [$user] = $this->studentUser($class);
        $this->makePaper($class);

        $response = $this->actingAs($user)->get(route('student.exam-papers.index'));

        $response->assertOk();
    }

    public function test_student_can_view_a_paper_belonging_to_their_own_class(): void
    {
        $class = SchoolClass::create(['name' => 'SEP Class B', 'class_order' => 972, 'is_active' => true]);
        [$user] = $this->studentUser($class);
        $paper = $this->makePaper($class);

        $response = $this->actingAs($user)->get(route('student.exam-papers.show', $paper->id));

        $response->assertOk();
    }

    public function test_student_cannot_view_a_paper_belonging_to_another_class(): void
    {
        $ownClass = SchoolClass::create(['name' => 'SEP Class C', 'class_order' => 973, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'SEP Class D', 'class_order' => 974, 'is_active' => true]);
        [$user] = $this->studentUser($ownClass);
        $paper = $this->makePaper($otherClass);

        $response = $this->actingAs($user)->get(route('student.exam-papers.show', $paper->id));

        $response->assertForbidden();
    }

    public function test_student_cannot_download_a_paper_belonging_to_another_class(): void
    {
        $ownClass = SchoolClass::create(['name' => 'SEP Class E', 'class_order' => 975, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'SEP Class F', 'class_order' => 976, 'is_active' => true]);
        [$user] = $this->studentUser($ownClass);
        $paper = $this->makePaper($otherClass, ['file_path' => 'exam_papers/other-class.pdf']);

        $response = $this->actingAs($user)->get(route('student.exam-papers.download', $paper->id));

        $response->assertForbidden();
    }

    public function test_unpublished_paper_is_not_visible_even_for_the_owning_class(): void
    {
        $class = SchoolClass::create(['name' => 'SEP Class G', 'class_order' => 977, 'is_active' => true]);
        [$user] = $this->studentUser($class);
        $paper = $this->makePaper($class, ['is_published' => false]);

        $response = $this->actingAs($user)->get(route('student.exam-papers.show', $paper->id));

        $response->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $class = SchoolClass::create(['name' => 'SEP Class H', 'class_order' => 978, 'is_active' => true]);
        $paper = $this->makePaper($class);

        $response = $this->get(route('student.exam-papers.show', $paper->id));

        $response->assertRedirect(route('login'));
    }
}
