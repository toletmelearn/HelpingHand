<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Result;
use App\Models\ReportCardTemplate;
use App\Models\PromotionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReportCardDesignerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Student $student;
    protected Exam $exam;
    protected Result $result;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->student = Student::create([
            'name' => 'Hermione Granger',
            'father_name' => 'Mr. Granger',
            'mother_name' => 'Mrs. Granger',
            'date_of_birth' => '2016-09-19',
            'aadhaar_number' => '111122223330',
            'address' => 'London',
            'phone' => '9876543210',
            'gender' => 'female',
            'admission_no' => 'ADM-9902',
            'class' => 'Grade 10',
        ]);

        $this->exam = Exam::create([
            'name' => 'Term 1 Geometry Exam',
            'exam_type' => 'mid_term',
            'class_name' => 'Grade 10',
            'subject' => 'Mathematics',
            'exam_date' => '2026-07-15',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 100.00,
            'passing_marks' => 33.00,
            'academic_year' => '2026',
        ]);

        $this->result = Result::create([
            'student_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'subject' => 'Mathematics',
            'marks_obtained' => 98.00,
            'total_marks' => 100.00,
            'percentage' => 98.00,
            'grade' => 'A1',
            'result_status' => 'pass',
            'academic_year' => '2026',
        ]);
    }

    public function test_report_designer_page_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.exams.reports.designer'));

        $response->assertStatus(200);
        $response->assertSee('Report Card Designer');
    }

    public function test_can_create_report_card_template()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.exams.reports.store-template'), [
                'name' => 'Standard CBSE Grade 10 Template',
                'show_logo' => 1,
                'show_attendance' => 1,
                'show_grades' => 1,
                'remarks_box' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('report_card_templates', [
            'name' => 'Standard CBSE Grade 10 Template',
        ]);
    }

    public function test_can_create_promotion_rule()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.exams.reports.store-rule'), [
                'class_name' => 'Grade 10',
                'min_overall_percentage' => 40.00,
                'max_failed_subjects' => 2,
                'min_attendance_percentage' => 75.00,
                'academic_year' => '2026',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('promotion_rules', [
            'class_name' => 'Grade 10',
            'min_overall_percentage' => 40.00,
            'max_failed_subjects' => 2,
            'min_attendance_percentage' => 75.00,
            'academic_year' => '2026',
        ]);
    }

    public function test_can_evaluate_student_promotion_eligibility()
    {
        // Set a promotion rule
        PromotionRule::create([
            'class_name' => 'Grade 10',
            'min_overall_percentage' => 35.00,
            'max_failed_subjects' => 1,
            'min_attendance_percentage' => 60.00,
            'academic_year' => '2026',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.exams.reports.check-promotion'), [
                'student_id' => $this->student->id,
                'academic_year' => '2026',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('promotion_result');
        $resultData = session('promotion_result');

        $this->assertEquals('Hermione Granger', $resultData['student']);
        $this->assertTrue($resultData['promoted']);
        $this->assertEquals(98.00, $resultData['stats']['percentage']);
    }
}
