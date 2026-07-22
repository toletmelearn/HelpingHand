<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController as ApiAttendanceController;
use App\Models\AcademicEvent;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendanceHolidayBlockTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2015-01-01',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => '9999999999',
            'class' => 'Class A',
        ]);
    }

    public function test_web_attendance_marking_is_blocked_on_a_holiday(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        AcademicEvent::create([
            'title' => 'Diwali Break',
            'type' => 'holiday',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.attendance.store'), [
            'class' => 'Class A',
            'date' => '2026-11-02',
            'subject' => 'Math',
            'student_ids' => [$student->id],
            'statuses' => ['present'],
        ]);

        $response->assertSessionHas('error', 'Attendance cannot be marked on a holiday: Diwali Break.');
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }

    public function test_web_attendance_marking_still_works_on_a_non_holiday(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        AcademicEvent::create([
            'title' => 'Diwali Break',
            'type' => 'holiday',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.attendance.store'), [
            'class' => 'Class A',
            'date' => '2026-11-10',
            'subject' => 'Math',
            'student_ids' => [$student->id],
            'statuses' => ['present'],
        ]);

        $response->assertRedirect(route('attendance.index'));
        $this->assertDatabaseHas('attendances', ['student_id' => $student->id, 'date' => '2026-11-10']);
    }

    public function test_api_attendance_marking_is_blocked_on_a_holiday(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        AcademicEvent::create([
            'title' => 'Diwali Break',
            'type' => 'holiday',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'is_active' => true,
        ]);

        $request = Request::create('/api/v1/attendance', 'POST', [
            'student_id' => $student->id,
            'date' => '2026-11-03',
            'status' => 'present',
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = (new ApiAttendanceController())->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'Attendance cannot be marked on a holiday: Diwali Break.',
            $response->getData(true)['message']
        );
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }
}
