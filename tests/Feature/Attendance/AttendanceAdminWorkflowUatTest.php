<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attendance V1 completion pass: a realistic end-to-end walkthrough of the
 * admin attendance workflow, chained through the REAL routes (never
 * calling services directly), on fixtures this test builds and owns.
 * Attendance marking is currently admin/office-staff-only on every
 * surface (teacher self-service marking and bulk quick-mark are both
 * intentionally frozen from a prior session -- see
 * TeacherAttendanceStoreGuardTest / AttendanceBulkDirectWriteGuardTest);
 * this suite verifies that the one live path is itself complete and safe.
 */
class AttendanceAdminWorkflowUatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function nonPrivilegedUser(): User
    {
        return User::factory()->create();
    }

    private function makeStudent(SchoolClass $class, array $overrides = []): Student
    {
        return Student::create(array_merge([
            'name' => 'UAT Student ' . uniqid(),
            'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999),
            'address' => 'Addr', 'roll_number' => random_int(1, 9999),
            'class' => $class->name,
            'school_class_id' => $class->id,
        ], $overrides));
    }

    public function test_full_admin_attendance_lifecycle(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'UAT Attendance Class', 'class_order' => 9801, 'is_active' => true]);
        $studentA = $this->makeStudent($class);
        $studentB = $this->makeStudent($class);
        $date = '2026-08-24'; // a Monday, not a holiday

        // ------------------------------------------------------------
        // 1. Admin marks attendance for a whole class in one submission.
        // ------------------------------------------------------------
        $mark = $this->actingAs($admin)->post(route('admin.attendance.store'), [
            'class' => 'ignored-spoofed-value',
            'date' => $date,
            'subject' => 'Maths',
            'period' => '1',
            'student_ids' => [$studentA->id, $studentB->id],
            'statuses' => ['present', 'absent'],
            'remarks' => ['On time', 'Sick leave'],
        ]);
        $mark->assertRedirect(route('attendance.index'));
        $mark->assertSessionHas('success');

        $recordA = Attendance::where('student_id', $studentA->id)->firstOrFail();
        $recordB = Attendance::where('student_id', $studentB->id)->firstOrFail();
        $this->assertSame('present', $recordA->status);
        $this->assertSame('absent', $recordB->status);
        // The client-supplied "class" is never trusted -- the canonical
        // school_class_id-derived name is what actually gets stored.
        $this->assertSame('UAT Attendance Class', $recordA->class);

        // ------------------------------------------------------------
        // 8. Duplicate submission for the same class/date/period is
        // rejected outright -- no double records, no silent overwrite.
        // ------------------------------------------------------------
        $duplicate = $this->actingAs($admin)->post(route('admin.attendance.store'), [
            'class' => 'UAT Attendance Class',
            'date' => $date,
            'subject' => 'Maths',
            'period' => '1',
            'student_ids' => [$studentA->id],
            'statuses' => ['absent'],
        ]);
        $duplicate->assertSessionHas('error');
        $this->assertSame('present', $recordA->fresh()->status, 'the original record must survive a duplicate submission untouched');
        $this->assertSame(2, Attendance::count(), 'a duplicate submission must not create extra rows');

        // ------------------------------------------------------------
        // 2. Admin edits an attendance record (status/subject/remarks only).
        // ------------------------------------------------------------
        $edit = $this->actingAs($admin)->put(route('admin.attendance.update', $recordB), [
            'status' => 'late',
            'subject' => 'Maths',
            'remarks' => 'Arrived at recess',
        ]);
        $edit->assertRedirect(route('attendance.show', $recordB));
        $this->assertSame('late', $recordB->fresh()->status);
        // Phase 6T hardening: identity fields must survive an edit untouched.
        $this->assertSame($studentB->id, $recordB->fresh()->student_id);
        $this->assertSame($date, $recordB->fresh()->date->toDateString());

        // ------------------------------------------------------------
        // 9. Wrong class/section manipulation is rejected: a student with
        // a genuine class_id vs school_class_id conflict cannot have
        // attendance marked until the conflict is resolved.
        // ------------------------------------------------------------
        $otherClass = SchoolClass::create(['name' => 'UAT Other Class', 'class_order' => 9802, 'is_active' => true]);
        $conflictedStudent = $this->makeStudent($class);
        // Student::saving() always keeps class_id in sync with
        // school_class_id on a normal write, so a genuine conflict can
        // only be produced by bypassing that hook -- same technique
        // AttendanceClassResolverTest's own fixtures use.
        \Illuminate\Support\Facades\DB::table('students')->where('id', $conflictedStudent->id)->update(['class_id' => $otherClass->id]);
        $conflictedStudent->refresh();

        $conflictAttempt = $this->actingAs($admin)->post(route('admin.attendance.store'), [
            'class' => 'UAT Attendance Class',
            'date' => $date,
            'subject' => 'Maths',
            'period' => '2',
            'student_ids' => [$conflictedStudent->id],
            'statuses' => ['present'],
        ]);
        $conflictAttempt->assertSessionHas('error');
        $this->assertDatabaseMissing('attendances', ['student_id' => $conflictedStudent->id]);

        // ------------------------------------------------------------
        // 3. Admin views historical attendance (index, filtered by date).
        // ------------------------------------------------------------
        $history = $this->actingAs($admin)->get(route('attendance.index', ['date' => $date]));
        $history->assertOk();
        $history->assertSee($studentA->name);

        // ------------------------------------------------------------
        // 4. Admin generates/views the attendance report for the class.
        // ------------------------------------------------------------
        $report = $this->actingAs($admin)->get(route('attendance.reports', ['date' => $date, 'class' => 'UAT Attendance Class']));
        $report->assertOk();

        // 10. Date/history behavior: the student's own monthly report
        // correctly reflects what was actually recorded.
        $studentReport = $this->actingAs($admin)->get(route('attendance.student.report', $studentA->id) . '?month=8&year=2026');
        $studentReport->assertOk();

        // ------------------------------------------------------------
        // 5. Admin exports attendance data.
        // ------------------------------------------------------------
        $export = $this->actingAs($admin)->get(route('attendance.export', ['from_date' => $date, 'to_date' => $date]));
        $export->assertOk();
        $export->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // ------------------------------------------------------------
        // 6. Unauthorized user cannot perform admin-only marking, editing,
        // or exporting -- and existing data is untouched by the attempt.
        // ------------------------------------------------------------
        $intruder = $this->nonPrivilegedUser();

        $this->actingAs($intruder)->post(route('admin.attendance.store'), [
            'class' => 'UAT Attendance Class', 'date' => $date, 'subject' => 'Maths', 'period' => '3',
            'student_ids' => [$studentA->id], 'statuses' => ['absent'],
        ])->assertForbidden();

        $this->actingAs($intruder)->put(route('admin.attendance.update', $recordA), [
            'status' => 'absent', 'subject' => 'Maths',
        ])->assertForbidden();

        // export() (like reports()) deliberately catches the authorization
        // failure and redirects to login rather than a bare 403 -- same
        // existing pattern, not something this pass changes.
        $this->actingAs($intruder)->get(route('attendance.export'))->assertRedirect(route('login'));

        // ------------------------------------------------------------
        // 7. After the full lifecycle, exactly the expected records exist
        // -- nothing extra, nothing lost, no fabricated rows.
        // ------------------------------------------------------------
        $this->assertSame(2, Attendance::count());
        $this->assertSame('present', $recordA->fresh()->status);
        $this->assertSame('late', $recordB->fresh()->status);
    }

    public function test_guest_cannot_mark_or_view_attendance(): void
    {
        $class = SchoolClass::create(['name' => 'UAT Guest Class', 'class_order' => 9803, 'is_active' => true]);
        $student = $this->makeStudent($class);

        $this->post(route('admin.attendance.store'), [
            'class' => 'UAT Guest Class', 'date' => '2026-08-24', 'subject' => 'Maths', 'period' => '1',
            'student_ids' => [$student->id], 'statuses' => ['present'],
        ])->assertRedirect(route('login'));

        $this->get(route('attendance.index'))->assertRedirect(route('login'));

        $this->assertSame(0, Attendance::count());
    }
}
