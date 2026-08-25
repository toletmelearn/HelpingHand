<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\BellTiming;
use App\Models\Exam;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\ParentModel;
use App\Models\Result;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HelpingHand V1 integration + production-readiness pass.
 *
 * Each module already has its own dedicated, real-route UAT proving that
 * module works in isolation: ExamV1UatTest (exams/papers), FeesV1UatTest
 * (fee lifecycle), MarksResultsV1CompletionTest (marks/verify/lock),
 * DashboardV1CompletionTest (all four dashboards), AdvancedReportDashboard-
 * AccuracyTest (reports). This suite does NOT repeat that coverage. It
 * targets the SEAMS between modules -- the things no single module's own
 * test can prove: does one continuous school day (attendance -> exam ->
 * marks -> report card -> fees) work end to end through real routes on
 * ONE set of fixtures, and do Dashboard/Reports independently agree with
 * the database once several modules have all written to it in the same
 * request cycle.
 */
class V1IntegrationReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        // Also super-admin: PermissionMiddleware-gated routes (e.g. fee
        // collection) only bypass via User::isSuperAdmin(), not the
        // Gate::before('admin') shortcut Policy-based checks use -- and
        // this codebase's permission catalog is seeded by
        // database/seeders/PermissionSeeder, which RefreshDatabase does
        // not run. Same reasoning FeesV1UatTest documents.
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin'], ['display_name' => 'Super Admin']);
        $user->roles()->attach([$adminRole->id, $superAdminRole->id]);

        return $user;
    }

    private function teacherLogin(string $suffix): array
    {
        $teacher = Teacher::create(['name' => "V1 Teacher $suffix", 'status' => 'active']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'v1' . $suffix . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        return [$teacher, $login];
    }

    private function makeStudent(SchoolClass $class, Section $section, string $name): Student
    {
        return Student::create([
            'name' => $name, 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2012-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'section_id' => $section->id,
            'class' => $class->name,
        ]);
    }

    /**
     * The realistic-school-day narrative (STEP 11): a small school with
     * 2 classes, 2 sections, 2 teachers, 10 students, 3 subjects, chained
     * through attendance -> timetable -> exam -> marks -> results ->
     * report card -> fees -> reports -> dashboard, on real routes.
     */
    public function test_a_full_school_day_holds_together_end_to_end(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-24 08:00:00'); // a Monday
        $dayOfWeek = now()->format('l');
        $admin = $this->admin();

        // -- School setup: 2 classes, 2 sections each, 3 subjects, 2 teachers, 10 students --
        $class8 = SchoolClass::create(['name' => 'V1 Class 8', 'class_order' => 951, 'is_active' => true]);
        $class9 = SchoolClass::create(['name' => 'V1 Class 9', 'class_order' => 952, 'is_active' => true]);
        $sectionA = Section::create(['name' => 'A', 'class_id' => $class8->id]);
        $sectionB = Section::create(['name' => 'B', 'class_id' => $class9->id]);

        $english = Subject::create(['name' => 'V1 English', 'code' => 'V1EN' . uniqid(), 'is_active' => true]);
        $maths = Subject::create(['name' => 'V1 Maths', 'code' => 'V1MA' . uniqid(), 'is_active' => true]);
        Subject::create(['name' => 'V1 Science', 'code' => 'V1SC' . uniqid(), 'is_active' => true]);

        [$teacherA, $loginA] = $this->teacherLogin('A');
        [$teacherB, $loginB] = $this->teacherLogin('B');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacherA->id, 'class_id' => $class8->id, 'subject_id' => $maths->id,
            'academic_year' => '2026-27',
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacherB->id, 'class_id' => $class9->id, 'subject_id' => $english->id,
            'academic_year' => '2026-27',
        ]);

        $class8Students = collect(range(1, 5))->map(fn ($i) => $this->makeStudent($class8, $sectionA, "V1 Student 8-$i"));
        $class9Students = collect(range(1, 5))->map(fn ($i) => $this->makeStudent($class9, $sectionB, "V1 Student 9-$i"));
        $this->assertCount(5, $class8Students);
        $this->assertCount(5, $class9Students);

        // ============================================================
        // Timetable + Bell Timing: today's period for teacherA/Class 8.
        // ============================================================
        $bellTiming = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class8->id, 'section_id' => $sectionA->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $maths->id, 'teacher_id' => $teacherA->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        // Teacher's own dashboard shows today's period -- the timetable/
        // dashboard seam.
        $teacherDash = $this->actingAs($loginA, 'teacher')->get(route('teacher.dashboard'));
        $teacherDash->assertOk();
        $teacherDash->assertSee('V1 Maths');
        $teacherDash->assertSee('V1 Class 8');

        // ============================================================
        // Attendance: admin marks today's attendance for Class 8 via the
        // real route (Teacher\TeacherAttendanceController::storeAttendance
        // is deliberately disabled -- "Phase 6Y" -- so the admin path is
        // the only live attendance-write path; see FINAL REPORT).
        // ============================================================
        $today = today()->toDateString();
        $statuses = ['present', 'present', 'present', 'absent', 'present'];
        $attendanceResponse = $this->actingAs($admin)->post(route('attendance.store'), [
            'class' => $class8->name, 'date' => $today, 'subject' => $maths->name, 'period' => 'P1',
            'student_ids' => $class8Students->pluck('id')->all(),
            'statuses' => $statuses,
        ]);
        $attendanceResponse->assertSessionHas('success');
        $this->assertSame(4, Attendance::where('date', $today)->where('status', 'present')->count());
        $this->assertSame(1, Attendance::where('date', $today)->where('status', 'absent')->count());

        // Re-marking the same class/date/period is blocked (no silent
        // duplicate submission).
        $duplicate = $this->actingAs($admin)->post(route('attendance.store'), [
            'class' => $class8->name, 'date' => $today, 'subject' => $maths->name, 'period' => 'P1',
            'student_ids' => [$class8Students->first()->id], 'statuses' => ['present'],
        ]);
        $duplicate->assertSessionHas('error');
        $this->assertSame(5, Attendance::where('date', $today)->count(), 'duplicate submission must not add rows');

        // Class 9 (untouched by this submission) has no attendance rows --
        // wrong-class isolation.
        $this->assertSame(0, Attendance::whereIn('student_id', $class9Students->pluck('id'))->count());

        // Dashboard's today_attendance figure agrees with the database.
        $dashResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertViewHas('stats', fn ($stats) => $stats['today_attendance'] === 5);

        // ============================================================
        // Exam -> Marks -> Results -> Report Card.
        // ============================================================
        $examCreate = $this->actingAs($admin)->post(route('admin.exams.store'), [
            'name' => 'V1 Integration Mid Term', 'exam_type' => 'term', 'class_id' => $class8->id,
            'subject' => $maths->name, 'exam_date' => today()->toDateString(),
            'start_time' => '09:00', 'end_time' => '11:00', 'total_marks' => 100, 'passing_marks' => 40,
            'academic_year' => '2026-27', 'term' => 'Term 1', 'status' => 'active',
        ]);
        $examCreate->assertSessionHas('success');
        $exam = Exam::where('name', 'V1 Integration Mid Term')->firstOrFail();

        // Teacher A enters marks for their own class via the real route.
        // One pass, one fail, one attempt to exceed max marks (rejected).
        $target = $class8Students->take(2);
        $marksResponse = $this->actingAs($loginA, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $target[0]->id, 'marks_obtained' => 85],
                ['student_id' => $target[1]->id, 'marks_obtained' => 20],
            ],
        ]);
        $marksResponse->assertRedirect();
        $this->assertDatabaseHas('results', ['student_id' => $target[0]->id, 'exam_id' => $exam->id, 'result_status' => 'pass']);
        $this->assertDatabaseHas('results', ['student_id' => $target[1]->id, 'exam_id' => $exam->id, 'result_status' => 'fail']);

        $overMax = $this->actingAs($loginA, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [['student_id' => $target[2]->id ?? $class8Students[2]->id, 'marks_obtained' => 999]],
        ]);
        $overMax->assertSessionHas('error');
        $this->assertDatabaseMissing('results', ['student_id' => $class8Students[2]->id, 'exam_id' => $exam->id]);

        // Teacher B (assigned to Class 9, not Class 8) cannot submit marks
        // for this Class-8 exam -- cross-teacher isolation.
        $wrongTeacher = $this->actingAs($loginB, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [['student_id' => $class8Students[3]->id, 'marks_obtained' => 50]],
        ]);
        $wrongTeacher->assertSessionHas('error');
        $this->assertDatabaseMissing('results', ['student_id' => $class8Students[3]->id, 'exam_id' => $exam->id]);

        // Report card renders and matches the marks actually entered.
        $passResult = Result::where('student_id', $target[0]->id)->where('exam_id', $exam->id)->firstOrFail();
        $reportCard = $this->actingAs($admin)->get(route('admin.results.report-card', [$target[0]->id, $exam->id]));
        $reportCard->assertOk();
        $reportCard->assertSee('85');

        // A student cannot view another student's result (IDOR check on
        // the student-facing results route).
        $studentUserSelf = User::factory()->create(['role' => 'student']);
        $studentUserSelf->roles()->attach(Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student'])->id);
        $studentUserSelf->student()->save($target[1]);
        $ownResult = Result::where('student_id', $target[1]->id)->where('exam_id', $exam->id)->firstOrFail();

        $this->actingAs($studentUserSelf)->get(route('student.results.show', $ownResult))->assertOk();
        $this->actingAs($studentUserSelf)->get(route('student.results.show', $passResult))->assertForbidden();
        $this->actingAs($studentUserSelf)->get(route('student.results.generate-pdf', $ownResult))->assertOk();

        // ============================================================
        // Fees: structure -> assignment -> partial payment -> balance.
        // (Full lifecycle already proven end-to-end by FeesV1UatTest --
        // this only checks the fee->dashboard/report seam.)
        // ============================================================
        $feeType = FeeType::create(['name' => 'V1 Integration Tuition', 'status' => 'active']);
        $structure = FeeStructure::create([
            'class_name' => $class8->name, 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active', 'is_active' => true,
        ]);
        FeeStructureItem::create(['fee_structure_id' => $structure->id, 'fee_type_id' => $feeType->id, 'amount' => 2000]);
        StudentFeeAssignment::create([
            'student_id' => $target[0]->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027',
        ]);
        $this->assertSame(2000.0, LedgerService::getOutstandingBalance($target[0]->id));

        $payment = $this->actingAs($admin)->post(route('admin.fees.process.collection'), [
            'student_id' => $target[0]->id, 'total_amount' => 800, 'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ]);
        $payment->assertSessionHas('success');
        $this->assertSame(1200.0, LedgerService::getOutstandingBalance($target[0]->id));

        // ============================================================
        // Reports + Dashboard agree with the ground truth after several
        // modules have all written to the database in this one test.
        // ============================================================
        $reportsResponse = $this->actingAs($admin)->get(route('admin.advanced-reports.dashboard', [
            'class_id' => $class8->id, 'date_range' => 'this_month',
        ]));
        $reportsResponse->assertOk();
        $reportsResponse->assertViewHas('feeStats', fn ($stats) => (float) $stats['total_fees_collected'] === 800.0);
        $reportsResponse->assertViewHas('attendanceStats', fn ($stats) => $stats['total_attendance'] === 5);

        $finalDash = $this->actingAs($admin)->get(route('admin.dashboard'));
        $finalDash->assertOk();
        $finalDash->assertViewHas('stats', function ($stats) {
            return $stats['total_students'] === 10 && $stats['total_teachers'] === 2;
        });
    }

    /**
     * Parent IDOR check across the fee/result surface reachable from the
     * parent dashboard, exercised here because it needs both a fee record
     * AND a second, unrelated parent -- narrower than a full school day.
     */
    public function test_parent_cannot_reach_another_parents_fee_receipt_via_id_manipulation(): void
    {
        $class = SchoolClass::create(['name' => 'V1 IDOR Class', 'class_order' => 953, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $ownChild = $this->makeStudent($class, $section, 'V1 Own Child');
        $otherChild = $this->makeStudent($class, $section, 'V1 Other Child');

        $structure = FeeStructure::create([
            'class_name' => $class->name, 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active', 'is_active' => true,
        ]);
        $admin = $this->admin();
        $otherCollection = \App\Models\FeeCollection::create([
            'receipt_no' => 'V1-IDOR-' . uniqid(), 'student_id' => $otherChild->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 500, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 500,
            'payment_date' => today(), 'payment_mode' => 'cash', 'collected_by' => $admin->id,
        ]);

        $parent = ParentModel::create([
            'name' => 'V1 IDOR Parent', 'email' => 'v1idor' . uniqid() . '@example.com',
            'password' => Hash::make('password123'), 'student_id' => $ownChild->id,
        ]);

        $this->actingAs($parent, 'parent')->get(route('parent.receipt.download', $otherCollection->id))
            ->assertStatus(403);
    }
}
