<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController as ApiAttendanceController;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AttendanceMarked;
use App\Services\AttendanceNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Remediation Task 5: sendAttendanceMarkedNotification() was a disabled
 * no-op with zero call sites anywhere. Re-enabled for real: invoked from
 * both the web and API attendance-marking paths, absent-students-only,
 * queued (AttendanceMarked now implements ShouldQueue), guarded against
 * duplicate sends for the same student+date.
 */
class AttendanceMarkedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * students.parent_id is NOT mass-assignable -- Student::saved()
     * auto-provisions (or dedup-matches by phone number) a ParentModel
     * for every student. Each test student needs its own unique phone so
     * the hook doesn't merge them onto the same auto-created parent.
     */
    private function makeStudentWithParent(): Student
    {
        $student = Student::create([
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2015-01-01',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => (string) random_int(6000000000, 9999999999),
            'class' => 'Class A',
        ]);

        return $student->fresh();
    }

    public function test_absent_student_queues_a_notification(): void
    {
        Notification::fake();
        $student = $this->makeStudentWithParent();

        $result = (new AttendanceNotificationService())->sendAttendanceMarkedNotification($student->id, '2026-06-01', 'absent');

        $this->assertTrue($result['sent']);
        Notification::assertSentTo($student->parent, AttendanceMarked::class);
    }

    public function test_present_student_sends_no_notification(): void
    {
        Notification::fake();
        $student = $this->makeStudentWithParent();

        $result = (new AttendanceNotificationService())->sendAttendanceMarkedNotification($student->id, '2026-06-01', 'present');

        $this->assertFalse($result['sent']);
        $this->assertSame('not_absent', $result['reason']);
        Notification::assertNothingSent();
    }

    public function test_remarking_the_same_student_the_same_day_does_not_duplicate(): void
    {
        Notification::fake();
        $student = $this->makeStudentWithParent();
        $service = new AttendanceNotificationService();

        $first = $service->sendAttendanceMarkedNotification($student->id, '2026-06-01', 'absent');
        $this->assertTrue($first['sent']);

        // Notification::fake() intercepts sends, so nothing actually lands
        // in the notifications table for the duplicate check to see --
        // write the record directly to simulate the first send having
        // really gone out, then verify the guard sees it.
        $student->parent->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => AttendanceMarked::class,
            'data' => ['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'absent'],
        ]);

        $second = $service->sendAttendanceMarkedNotification($student->id, '2026-06-01', 'absent');

        $this->assertFalse($second['sent']);
        $this->assertSame('already_sent_for_this_date', $second['reason']);
    }

    public function test_web_attendance_marking_queues_notifications_for_absent_students(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();
        $absentStudent = $this->makeStudentWithParent();
        $presentStudent = $this->makeStudentWithParent();

        $this->actingAs($admin)->post(route('admin.attendance.store'), [
            'class' => 'Class A',
            'date' => '2026-06-02',
            'subject' => 'Math',
            'student_ids' => [$absentStudent->id, $presentStudent->id],
            'statuses' => ['absent', 'present'],
        ]);

        Notification::assertSentTo($absentStudent->parent, AttendanceMarked::class);
        Notification::assertNotSentTo($presentStudent->parent, AttendanceMarked::class);
    }

    public function test_api_attendance_marking_queues_a_notification_for_an_absent_student(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();
        $student = $this->makeStudentWithParent();

        $request = \Illuminate\Http\Request::create('/api/v1/attendance', 'POST', [
            'student_id' => $student->id,
            'date' => '2026-06-03',
            'status' => 'absent',
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = (new ApiAttendanceController())->store($request);

        $this->assertSame(201, $response->getStatusCode());
        Notification::assertSentTo($student->parent, AttendanceMarked::class);
    }
}
