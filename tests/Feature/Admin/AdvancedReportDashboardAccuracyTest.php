<?php

namespace Tests\Feature\Admin;

use App\Models\AdvancedReport;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\Result;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reports V1 completion pass: Admin\AdvancedReportController::dashboard()
 * -- the one cross-module report spanning Students/Fees/Attendance/Exams/
 * Library/Biometric -- crashed with a QueryException on every single
 * request, unconditionally, before any of these fixes:
 *
 *   - getExamAnalytics(): exams has no `date` column (it's exam_date) and
 *     no `results_published` column at all; that concept doesn't exist
 *     anywhere in the schema. Also mutated the same query builder across
 *     four counts instead of cloning per metric, so the counts were
 *     cumulative AND-ed together, not four independent totals.
 *   - getAttendanceAnalytics(): attendances has no class_id/section_id
 *     columns (only a free-text `class` string) -- crashed the moment a
 *     class/section filter was applied.
 *   - getLibraryAnalytics(): Book has no status/issued_at/due_date
 *     columns -- crashed unconditionally (that state lives on BookIssue).
 *   - getBiometricAnalytics(): teacher_biometric_records has no single
 *     `status` column (arrival_status/departure_status are separate) --
 *     crashed unconditionally, plus the same cumulative-mutation bug.
 *
 * This suite locks in that the dashboard now renders in both the
 * no-filter and filtered cases, and that the in-scope (Attendance/Exam/
 * Fee) figures are actually correct against known fixture data.
 */
class AdvancedReportDashboardAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_dashboard_loads_without_a_fatal_error(): void
    {
        $this->actingAs($this->admin())->get(route('admin.advanced-reports.dashboard'))->assertOk();
    }

    public function test_dashboard_loads_with_class_and_section_filters_applied(): void
    {
        $class = SchoolClass::create(['name' => 'AR Class', 'class_order' => 991, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.advanced-reports.dashboard', [
            'class_id' => $class->id, 'section_id' => $section->id,
        ]));

        $response->assertOk();
    }

    public function test_attendance_analytics_are_scoped_by_class_and_ignore_other_classes(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-15');

        $class = SchoolClass::create(['name' => 'AR Attend Class', 'class_order' => 992, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'AR Other Class', 'class_order' => 993, 'is_active' => true]);

        $inClassStudent = Student::create([
            'name' => 'AR In Class', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id,
        ]);
        $outOfClassStudent = Student::create([
            'name' => 'AR Out Of Class', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $otherClass->id, 'school_class_id' => $otherClass->id,
        ]);

        Attendance::create(['student_id' => $inClassStudent->id, 'date' => '2026-08-15', 'status' => 'present']);
        Attendance::create(['student_id' => $inClassStudent->id, 'date' => '2026-08-14', 'status' => 'absent']);
        Attendance::create(['student_id' => $outOfClassStudent->id, 'date' => '2026-08-15', 'status' => 'present']);

        $response = $this->actingAs($this->admin())->get(route('admin.advanced-reports.dashboard', [
            'class_id' => $class->id, 'date_range' => 'this_month',
        ]));

        $response->assertOk();
        // Only the 2 records belonging to $class's student -- the 3rd
        // record (a different class) must not be counted.
        $response->assertViewHas('attendanceStats', function ($stats) {
            return $stats['total_attendance'] === 2;
        });
    }

    public function test_exam_analytics_count_independently_not_cumulatively(): void
    {
        $class = SchoolClass::create(['name' => 'AR Exam Class', 'class_order' => 994, 'is_active' => true]);

        $mathSubject = \App\Models\Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true]);
        $scienceSubject = \App\Models\Subject::firstOrCreate(['name' => 'Science'], ['code' => 'Science', 'is_active' => true]);

        $completedExam = Exam::create([
            'name' => 'AR Completed Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $mathSubject->id, 'subject' => 'Math', 'exam_date' => today()->subDays(5),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $upcomingExam = Exam::create([
            'name' => 'AR Upcoming Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $scienceSubject->id, 'subject' => 'Science', 'exam_date' => today()->addDays(10),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'scheduled',
        ]);

        $student = Student::create([
            'name' => 'AR Exam Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
        ]);
        Result::create([
            'student_id' => $student->id, 'exam_id' => $completedExam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A', 'academic_year' => '2026-27',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.advanced-reports.dashboard', [
            'class_id' => $class->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('examStats', function ($stats) {
            // Both exams are "completed_exams=1" AND "upcoming_exams=1"
            // independently -- neither count should be 0 from bleeding
            // into the other's filter (the pre-fix cumulative-AND bug).
            return $stats['completed_exams'] === 1
                && $stats['upcoming_exams'] === 1
                && $stats['results_published'] === 1; // only the completed exam has a Result
        });
    }

    public function test_fee_analytics_total_collected_matches_stored_records(): void
    {
        $class = SchoolClass::create(['name' => 'AR Fee Class', 'class_order' => 995, 'is_active' => true]);
        $student = Student::create([
            'name' => 'AR Fee Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id,
        ]);
        $structure = FeeStructure::create([
            'class_name' => $class->name, 'academic_year' => '2026-2027', 'frequency' => 'yearly',
            'status' => 'active', 'is_active' => true,
        ]);
        $admin = $this->admin();
        FeeCollection::create([
            'receipt_no' => 'AR-FEE-1', 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 700, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 700,
            'payment_date' => today(), 'payment_mode' => 'cash', 'collected_by' => $admin->id,
        ]);
        FeeCollection::create([
            'receipt_no' => 'AR-FEE-2', 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 300, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 300,
            'payment_date' => today(), 'payment_mode' => 'cash', 'collected_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.advanced-reports.dashboard', [
            'class_id' => $class->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('feeStats', function ($stats) {
            return (float) $stats['total_fees_collected'] === 1000.0;
        });
    }

    public function test_pdf_export_does_not_crash_with_no_class_filter_applied(): void
    {
        $admin = $this->admin();
        $report = AdvancedReport::create([
            'name' => 'AR PDF Export', 'type' => 'summary', 'module' => 'students',
            'is_active' => true, 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.advanced-reports.export', [$report->id, 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_does_not_crash_with_a_class_filter_applied(): void
    {
        $class = SchoolClass::create(['name' => 'AR Export Class', 'class_order' => 996, 'is_active' => true]);
        $admin = $this->admin();
        $report = AdvancedReport::create([
            'name' => 'AR PDF Export Filtered', 'type' => 'summary', 'module' => 'students',
            'is_active' => true, 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.advanced-reports.export', [$report->id, 'pdf', 'class_id' => $class->id]));

        $response->assertOk();
    }

    public function test_non_admin_cannot_access_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.advanced-reports.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_the_dashboard(): void
    {
        $this->get(route('admin.advanced-reports.dashboard'))->assertRedirect(route('login'));
    }
}
