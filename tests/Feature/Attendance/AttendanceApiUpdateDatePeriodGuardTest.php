<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiUpdateDatePeriodGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->integer('teacher_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
            $table->string('period')->nullable();
            $table->string('subject')->nullable();
            $table->string('class')->nullable();
            $table->string('session')->nullable();
            $table->integer('marked_by')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_api_update_does_not_change_date_when_payload_contains_date()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $attendanceId = $this->seedAttendance([
            'date' => '2026-06-05',
            'period' => 'morning',
            'marked_by' => $authUser->id,
        ]);

        $response = (new AttendanceController())->update($this->apiRequest([
            'date' => '2026-06-06',
            'status' => 'absent',
        ], $authUser), $attendanceId);

        $attendance = DB::table('attendances')->where('id', $attendanceId)->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2026-06-05', $attendance->date);
        $this->assertSame('absent', $attendance->status);
    }

    public function test_api_update_does_not_change_period_when_payload_contains_period()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $attendanceId = $this->seedAttendance([
            'date' => '2026-06-05',
            'period' => 'morning',
            'marked_by' => $authUser->id,
        ]);

        $response = (new AttendanceController())->update($this->apiRequest([
            'period' => 'afternoon',
            'status' => 'late',
        ], $authUser), $attendanceId);

        $attendance = DB::table('attendances')->where('id', $attendanceId)->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('morning', $attendance->period);
        $this->assertSame('late', $attendance->status);
    }

    public function test_api_update_still_allows_status_remarks_subject_session_changes()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $attendanceId = $this->seedAttendance([
            'status' => 'present',
            'remarks' => 'Original remarks',
            'subject' => 'General',
            'session' => '2026-2027',
            'marked_by' => $authUser->id,
        ]);

        $response = (new AttendanceController())->update($this->apiRequest([
            'status' => 'half_day',
            'remarks' => 'Updated safely',
            'subject' => 'Math',
            'session' => '2027-2028',
        ], $authUser), $attendanceId);

        $attendance = DB::table('attendances')->where('id', $attendanceId)->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('half_day', $attendance->status);
        $this->assertSame('Updated safely', $attendance->remarks);
        $this->assertSame('Math', $attendance->subject);
        $this->assertSame('2027-2028', $attendance->session);
    }

    public function test_api_update_still_cannot_change_student_id_class_or_marked_by()
    {
        $originalMarker = $this->createUser('original-marker@example.test');
        $authUser = $this->createUser('auth-user@example.test');
        $spoofedUser = $this->createUser('spoofed-user@example.test');
        $studentId = $this->createStudent('Student A', '10A');
        $spoofedStudentId = $this->createStudent('Student B', '12Z');
        $attendanceId = $this->seedAttendance([
            'student_id' => $studentId,
            'class' => '10A',
            'marked_by' => $originalMarker->id,
        ]);

        $response = (new AttendanceController())->update($this->apiRequest([
            'student_id' => $spoofedStudentId,
            'class' => '12Z',
            'marked_by' => $spoofedUser->id,
            'status' => 'absent',
        ], $authUser), $attendanceId);

        $attendance = DB::table('attendances')->where('id', $attendanceId)->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($studentId, $attendance->student_id);
        $this->assertSame('10A', $attendance->class);
        $this->assertSame($originalMarker->id, $attendance->marked_by);
        $this->assertSame('absent', $attendance->status);
    }

    public function test_api_destroy_guard_still_returns_423()
    {
        $response = (new AttendanceController())->destroy(123);

        $this->assertSame(423, $response->getStatusCode());
        $this->assertSame(
            'API attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.',
            $response->getData(true)['message']
        );
    }

    public function test_api_bulk_mark_guard_still_returns_423()
    {
        $response = (new AttendanceController())->bulkMark(Request::create('/api/v1/attendance/bulk-mark', 'POST', []));

        $this->assertSame(423, $response->getStatusCode());
        $this->assertSame(
            'API bulk attendance marking is temporarily disabled. Use attendance preflight first. Safe API bulk apply is not enabled yet.',
            $response->getData(true)['message']
        );
    }

    private function apiRequest(array $payload, User $user): Request
    {
        $request = Request::create('/api/v1/attendance/1', 'PATCH', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secret',
        ]);
    }

    private function createStudent(string $name, string $class): int
    {
        return DB::table('students')->insertGetId([
            'name' => $name,
            'class' => $class,
        ]);
    }

    private function seedAttendance(array $overrides = []): int
    {
        return DB::table('attendances')->insertGetId(array_merge([
            'student_id' => 1,
            'date' => '2026-06-05',
            'status' => 'present',
            'remarks' => null,
            'period' => 'morning',
            'subject' => 'General',
            'class' => '10A',
            'session' => '2026-2027',
            'marked_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
