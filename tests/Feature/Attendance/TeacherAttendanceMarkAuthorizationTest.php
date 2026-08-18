<?php

namespace Tests\Feature\Attendance;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UAT Step 5, Scenario 31: the teacher-portal attendance-marking screen
 * (teacher/attendance/mark/{classId}) was unreachable for every real
 * teacher. Root cause: AttendancePolicy::markAttendance() resolved the
 * acting teacher via Teacher::where('user_id', Auth::user()->id) against
 * the default 'web' guard, but this route is only ever reached through the
 * separate 'teacher' guard (TeacherAuth middleware) -- so Auth::user() was
 * always null and the policy denied unconditionally, for every teacher,
 * independent of the UAT fixture.
 *
 * The fix replaces that Gate-based check with an inline ownership check
 * against TeacherClassSubjectAssignment (the same canonical
 * teacher-assigned-to-class mechanism TimetableSlotPolicy already uses),
 * resolved via the already-authenticated teacher-guard identity -- no new
 * authentication mechanism, real authorization preserved (not
 * authentication-only).
 */
class TeacherAttendanceMarkAuthorizationTest extends TestCase
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

    public function test_teacher_assigned_to_the_class_can_open_the_mark_attendance_screen(): void
    {
        $class = SchoolClass::create(['name' => 'Attend Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Attend Subject', 'code' => 'AT' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Assigned Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026-2027',
        ]);

        $login = $this->login($teacher);

        $response = $this->actingAs($login, 'teacher')->get('/teacher/attendance/mark/' . $class->id);

        $response->assertOk();
        $response->assertSee('Attend Class');
    }

    public function test_teacher_not_assigned_to_the_class_is_rejected(): void
    {
        $class = SchoolClass::create(['name' => 'Other Attend Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Unassigned Teacher', 'status' => 'active']);
        // Deliberately no TeacherClassSubjectAssignment for this teacher/class pair.

        $login = $this->login($teacher);

        $response = $this->actingAs($login, 'teacher')->get('/teacher/attendance/mark/' . $class->id);

        $response->assertForbidden();
    }

    public function test_teacher_assigned_to_a_different_class_cannot_mark_attendance_for_this_one(): void
    {
        $ownClass = SchoolClass::create(['name' => 'My Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'Someone Elses Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Cross Subject', 'code' => 'CR' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Cross Class Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $ownClass->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026-2027',
        ]);

        $login = $this->login($teacher);

        $response = $this->actingAs($login, 'teacher')->get('/teacher/attendance/mark/' . $otherClass->id);

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_is_denied_not_shown(): void
    {
        $class = SchoolClass::create(['name' => 'Guest Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        $response = $this->get('/teacher/attendance/mark/' . $class->id);

        $response->assertRedirect(route('teacher.login'));
    }

    public function test_existing_admin_attendance_screen_is_unaffected(): void
    {
        $admin = User::factory()->create();
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'web')->get('/admin/attendance/create');

        $response->assertOk();
    }
}
