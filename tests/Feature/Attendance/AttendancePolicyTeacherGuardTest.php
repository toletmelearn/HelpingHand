<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Follow-up defect fix (post Step-5-UAT audit): AttendancePolicy::view()/
 * update()/export() resolved the acting teacher via
 * Teacher::where('user_id', $user->id) against the default 'web' guard --
 * but TeacherAttendanceController::editAttendance()/exportAttendance() are
 * reached exclusively through the 'teacher' guard (TeacherAuth
 * middleware), so $user (web) is always null there and these abilities
 * denied every real teacher unconditionally. view()'s teacher branch also
 * compared against the nonexistent teachers.class_id column.
 *
 * Fix: each policy method now resolves the teacher-guard identity itself
 * (AttendancePolicy::resolveTeacher(), mirroring the exact pattern
 * TeacherAttendanceController already uses), and the class-ownership
 * check uses TeacherClassSubjectAssignment instead of teacher->class_id.
 * Admin/web-guard behavior (the $user branches) is untouched.
 */
class AttendancePolicyTeacherGuardTest extends TestCase
{
    use RefreshDatabase;

    private function login(Teacher $teacher): TeacherLogin
    {
        return TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => 'teacher' . uniqid(),
            'password' => Hash::make('password123'),
        ]);
    }

    /**
     * Sets the 'teacher' guard's session user WITHOUT reassigning the
     * default guard. actingAs()/be() always call Auth::shouldUse($guard),
     * which -- for a non-default guard like 'teacher' -- makes the
     * unguarded Auth::user() resolve to the TeacherLogin instead of null
     * for the rest of the test. That's a PHPUnit-only artifact (a real
     * HTTP request never touches Auth::shouldUse()) which triggers an
     * unrelated, already-classified-out-of-scope crash in
     * AuthServiceProvider's Gate::before callback whenever a Gate-mediated
     * authorize() call is made. Setting the guard's user directly, the
     * same way the real login controllers do (Auth::guard('teacher')
     * ->login(...)), leaves the default 'web' guard genuinely null --
     * exactly matching real production request state.
     */
    private function actingAsTeacherOnly(TeacherLogin $login): void
    {
        $this->app['auth']->guard('teacher')->setUser($login);
    }

    /**
     * attendances.marked_by has a real FK constraint against users.id
     * (not teachers.id) -- a separate, pre-existing data-shape
     * inconsistency in the original marked_by == $teacher->id ownership
     * comparison, out of this task's scope (only teacher->class_id was
     * named). Left untouched in the policy; here we just satisfy the FK
     * by minting a User row sharing the teacher's id, so the existing
     * comparison has something valid to match against.
     */
    private function markedByIdFor(Teacher $teacher): int
    {
        User::factory()->create(['id' => $teacher->id]);

        return $teacher->id;
    }

    private function makeStudent(SchoolClass $class): Student
    {
        return Student::create([
            'name' => 'Student ' . uniqid(),
            'father_name' => 'Father ' . uniqid(),
            'mother_name' => 'Mother ' . uniqid(),
            'date_of_birth' => '2015-01-01',
            'address' => 'Test Address',
            'phone' => '9000000000',
            'admission_no' => 'ADM' . uniqid(),
            'school_class_id' => $class->id,
        ]);
    }

    public function test_teacher_can_edit_their_own_recently_marked_attendance(): void
    {
        $class = SchoolClass::create(['name' => 'Policy Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Marking Teacher', 'status' => 'active']);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $this->markedByIdFor($teacher),
        ]);

        $login = $this->login($teacher);
        $this->actingAsTeacherOnly($login);

        $response = $this->get('/teacher/attendance/' . $attendance->id . '/edit');

        $response->assertOk();
    }

    public function test_teacher_can_reach_the_export_path(): void
    {
        $teacher = Teacher::create(['name' => 'Export Teacher', 'status' => 'active']);
        $login = $this->login($teacher);
        $this->actingAsTeacherOnly($login);

        $response = $this->get('/teacher/attendance/export');

        $response->assertOk();
    }

    public function test_teacher_cannot_edit_another_teachers_marked_attendance(): void
    {
        $class = SchoolClass::create(['name' => 'Cross Policy Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $otherTeacher = Teacher::create(['name' => 'Other Marking Teacher', 'status' => 'active']);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $this->markedByIdFor($otherTeacher),
        ]);

        $me = Teacher::create(['name' => 'Bystander Marking Teacher', 'status' => 'active']);
        $login = $this->login($me);
        $this->actingAsTeacherOnly($login);

        $response = $this->get('/teacher/attendance/' . $attendance->id . '/edit');

        $response->assertForbidden();
    }

    public function test_teacher_guard_identity_is_used_and_a_bare_web_session_is_ignored(): void
    {
        // No web-guard user is ever authenticated in this test at all --
        // proves the ability is granted purely from the teacher guard,
        // not from any web-guard fallback/assumption.
        $class = SchoolClass::create(['name' => 'Guard Identity Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Guard Identity Teacher', 'status' => 'active']);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $this->markedByIdFor($teacher),
        ]);

        $this->assertGuest('web');

        $login = $this->login($teacher);
        $this->actingAsTeacherOnly($login);

        $response = $this->get('/teacher/attendance/' . $attendance->id . '/edit');

        $response->assertOk();
    }

    public function test_attendance_never_marked_by_any_teacher_is_denied(): void
    {
        $class = SchoolClass::create(['name' => 'Unowned Policy Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => null,
        ]);

        $teacher = Teacher::create(['name' => 'Nonexistent Owner Teacher', 'status' => 'active']);
        $login = $this->login($teacher);
        $this->actingAsTeacherOnly($login);

        $response = $this->get('/teacher/attendance/' . $attendance->id . '/edit');

        $response->assertForbidden();
    }

    public function test_attendance_marked_more_than_a_day_ago_can_no_longer_be_edited(): void
    {
        $class = SchoolClass::create(['name' => 'Stale Policy Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Stale Marking Teacher', 'status' => 'active']);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => 'present',
            'marked_by' => $this->markedByIdFor($teacher),
        ]);
        $attendance->forceFill(['created_at' => now()->subDays(3)])->save();

        $login = $this->login($teacher);
        $this->actingAsTeacherOnly($login);

        $response = $this->get('/teacher/attendance/' . $attendance->id . '/edit');

        $response->assertForbidden();
    }

    public function test_admin_web_guard_view_and_update_behavior_is_unchanged(): void
    {
        $class = SchoolClass::create(['name' => 'Admin Policy Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Admin-Viewed Teacher', 'status' => 'active']);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $this->markedByIdFor($teacher),
        ]);

        $admin = User::factory()->create();
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'web')->get(route('attendance.edit', $attendance));

        $response->assertOk();
    }

    public function test_non_admin_non_owning_web_user_still_cannot_edit(): void
    {
        $class = SchoolClass::create(['name' => 'Web Denied Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Web Denied Teacher', 'status' => 'active']);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $this->markedByIdFor($teacher),
        ]);

        $plainUser = User::factory()->create();

        // AttendanceController::edit() (pre-existing, unmodified by this
        // fix) catches AuthorizationException itself and redirects with a
        // flash error rather than letting a raw 403 propagate.
        $response = $this->actingAs($plainUser, 'web')->get(route('attendance.edit', $attendance));

        $response->assertRedirect(route('attendance.index'));
        $response->assertSessionHas('error', 'You do not have permission to edit this attendance record.');
    }

    public function test_edit_and_export_paths_perform_no_database_writes(): void
    {
        $class = SchoolClass::create(['name' => 'Readonly Policy Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Readonly Teacher', 'status' => 'active']);
        $student = $this->makeStudent($class);

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $this->markedByIdFor($teacher),
        ]);

        $countBefore = Attendance::count();
        $login = $this->login($teacher);
        $this->actingAsTeacherOnly($login);

        $this->get('/teacher/attendance/' . $attendance->id . '/edit');
        $this->get('/teacher/attendance/export');

        $this->assertSame($countBefore, Attendance::count());
        $this->assertSame('present', $attendance->fresh()->status);
    }
}
