<?php

namespace Tests\Feature\Attendance;

use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attendance V1 completion pass: Admin\TeacherAttendanceController (staff
 * attendance, distinct from the student-facing AttendanceController and
 * from Teacher\TeacherAttendanceController's teacher-portal self-service
 * screens) had two live, untested defects.
 *
 * 1. Authorization: its constructor only registered 'auth' -- no
 *    'role:admin' middleware, no $this->authorize() call anywhere in the
 *    controller, and no dedicated test file existed. Any authenticated
 *    account of any role could view, mark, edit, and export every
 *    teacher's attendance, which feeds payroll's
 *    AttendanceDeductionCalculator.
 *
 * 2. Read-only violation: index() (a GET) unconditionally called
 *    ensureAllTeachersPresent($date) on every page view, silently
 *    inserting a fabricated "present" TeacherAttendance row for every
 *    teacher for WHATEVER date was browsed to -- including past dates
 *    and Sundays -- before the admin had ever actually taken attendance.
 *    The exact same anti-pattern AttendanceController::create() was
 *    already hardened against for student attendance (see that
 *    controller's ensureAllStudentsPresent() docblock); this sibling
 *    controller had never received the equivalent fix.
 */
class TeacherAttendanceControllerHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function nonAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    // ------------------------------------------------------------
    // 1. Authorization
    // ------------------------------------------------------------

    public function test_non_admin_cannot_view_teacher_attendance_index(): void
    {
        $response = $this->actingAs($this->nonAdmin())->get(route('admin.teacher-attendance.index'));

        $response->assertForbidden();
    }

    public function test_guest_cannot_view_teacher_attendance_index(): void
    {
        $response = $this->get(route('admin.teacher-attendance.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_view_teacher_attendance_index(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.teacher-attendance.index'));

        $response->assertOk();
    }

    public function test_non_admin_cannot_mark_all_teachers_present(): void
    {
        $teacher = Teacher::create(['name' => 'Hardening Test Teacher', 'status' => 'active']);

        $response = $this->actingAs($this->nonAdmin())
            ->post(route('admin.teacher-attendance.mark-all-present'), ['date' => '2026-08-24']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('teacher_attendances', ['teacher_id' => $teacher->id]);
    }

    public function test_non_admin_cannot_export_teacher_attendance(): void
    {
        $response = $this->actingAs($this->nonAdmin())->get(route('admin.teacher-attendance.export'));

        $response->assertForbidden();
    }

    // ------------------------------------------------------------
    // 2. Read-only listing (no fabricated attendance on page view)
    // ------------------------------------------------------------

    public function test_viewing_the_index_does_not_create_any_attendance_records(): void
    {
        Teacher::create(['name' => 'Read Only Teacher A', 'status' => 'active']);
        Teacher::create(['name' => 'Read Only Teacher B', 'status' => 'active']);

        $this->actingAs($this->admin())->get(route('admin.teacher-attendance.index', ['date' => '2026-08-20']));

        $this->assertSame(0, TeacherAttendance::count());
    }

    public function test_viewing_the_index_for_a_past_date_does_not_create_records_either(): void
    {
        Teacher::create(['name' => 'Read Only Teacher C', 'status' => 'active']);

        $this->actingAs($this->admin())->get(route('admin.teacher-attendance.index', ['date' => '2026-01-01']));

        $this->assertSame(0, TeacherAttendance::count());
    }

    public function test_viewing_the_index_repeatedly_stays_at_zero_records(): void
    {
        Teacher::create(['name' => 'Read Only Teacher D', 'status' => 'active']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.teacher-attendance.index', ['date' => '2026-08-20']));
        $this->actingAs($admin)->get(route('admin.teacher-attendance.index', ['date' => '2026-08-20']));
        $this->actingAs($admin)->get(route('admin.teacher-attendance.index', ['date' => '2026-08-20']));

        $this->assertSame(0, TeacherAttendance::count());
    }

    public function test_mark_all_present_still_actually_writes_when_explicitly_requested(): void
    {
        $teacher = Teacher::create(['name' => 'Explicit Mark Teacher', 'status' => 'active']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.teacher-attendance.mark-all-present'), ['date' => '2026-08-24']);

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'date' => '2026-08-24',
            'status' => 'present',
        ]);
    }
}
