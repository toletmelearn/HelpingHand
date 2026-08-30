<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarksModerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Exam $exam;
    protected Student $student;
    protected Result $result;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $class = SchoolClass::firstOrCreate(['name' => 'Grade 10'], ['class_order' => 10, 'is_active' => true]);
        $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['code' => 'Mathematics', 'is_active' => true]);

        $this->exam = Exam::create([
            'name' => 'Term 1 Geometry Exam',
            'exam_type' => 'mid_term',
            'class_id' => $class->id,
            'class_name' => $class->name,
            'subject_id' => $subject->id,
            'subject' => $subject->name,
            'exam_date' => '2026-07-15',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 100.00,
            'passing_marks' => 33.00,
            'academic_year' => '2026',
        ]);

        $this->student = Student::create([
            'name' => 'Harry Potter',
            'father_name' => 'James Potter',
            'mother_name' => 'Lily Potter',
            'date_of_birth' => '2016-07-31',
            'aadhaar_number' => '111122223339',
            'address' => '4 Privet Drive',
            'phone' => '9876543219',
            'gender' => 'male',
            'admission_no' => 'ADM-9901',
        ]);

        $this->result = Result::create([
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'subject' => 'Mathematics',
            'marks_obtained' => 30.00, // Failing score (passing is 33)
            'total_marks' => 100.00,
            'percentage' => 30.00,
            'grade' => 'D',
            'result_status' => 'fail',
            'academic_year' => '2026',
        ]);
    }

    public function test_moderation_page_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.exams.moderation.index'));

        $response->assertStatus(200);
        $response->assertSee('Marks Moderation');
    }

    public function test_can_moderate_marks_flat_percentage()
    {
        // Add 10% scaling to the results
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.exams.moderation.moderate'), [
                'exam_id' => $this->exam->id,
                'subject' => 'Mathematics',
                'adjustment_percentage' => 10.00,
                'reason' => 'Scale standard difficulty',
            ]);

        $response->assertRedirect();
        
        $this->result->refresh();
        // 30 marks + 10% = 33 marks (which is now pass)
        $this->assertEquals(33.00, $this->result->marks_obtained);
        $this->assertEquals(30.00, $this->result->original_marks_obtained);
        $this->assertEquals('pass', $this->result->result_status);
    }

    public function test_can_apply_grace_marks_to_failing_student()
    {
        // 30 marks obtained, needs 3 grace marks to pass (passing threshold is 33)
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.exams.moderation.grace'), [
                'student_id' => $this->student->id,
                'academic_year' => '2026',
                'max_grace_marks' => 5,
            ]);

        $response->assertRedirect();

        $this->result->refresh();
        $this->assertEquals(33.00, $this->result->marks_obtained);
        $this->assertEquals(3.00, $this->result->grace_marks_applied);
        $this->assertEquals('pass', $this->result->result_status);
    }
}
