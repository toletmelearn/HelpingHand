<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiStoreTerminalStatusGuardTest extends TestCase
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
            $table->timestamps();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->string('status');
            $table->date('status_date')->nullable();
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
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

        Schema::create('academic_events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('academic_session_id')->nullable();
            $table->string('title');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('academic_events');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_api_store_rejects_passed_out_student()
    {
        $this->assertTerminalStatusIsRejected('passed_out');
    }

    public function test_api_store_rejects_left_school_student()
    {
        $this->assertTerminalStatusIsRejected('left_school');
    }

    public function test_api_store_rejects_tc_issued_student()
    {
        $this->assertTerminalStatusIsRejected('tc_issued');
    }

    public function test_api_store_rejects_inactive_student()
    {
        $this->assertTerminalStatusIsRejected('inactive');
    }

    public function test_api_store_allows_student_with_no_status()
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(1, DB::table('attendances')->count());
    }

    public function test_api_store_allows_latest_active_status_after_old_inactive_status()
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();
        $this->createStudentStatus($studentId, 'inactive');
        $this->createStudentStatus($studentId, 'active');

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(1, DB::table('attendances')->count());
    }

    public function test_api_store_uses_highest_student_status_id_as_latest()
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();
        $this->createStudentStatus($studentId, 'active', '2026-06-05');
        $this->createStudentStatus($studentId, 'inactive', '2026-01-01');

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_api_store_marked_by_guard_still_uses_authenticated_user()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $spoofedUser = $this->createUser('spoofed-user@example.test');
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'status' => 'present',
            'class' => '10A',
            'marked_by' => $spoofedUser->id,
        ], $authUser));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($authUser->id, DB::table('attendances')->value('marked_by'));
    }

    public function test_api_update_date_period_guard_still_passes()
    {
        $user = $this->createUser();
        $attendanceId = DB::table('attendances')->insertGetId([
            'student_id' => $this->createStudent(),
            'date' => '2026-06-05',
            'period' => 'morning',
            'status' => 'present',
            'class' => '10A',
            'marked_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = (new AttendanceController())->update($this->updateRequest([
            'date' => '2026-06-06',
            'period' => 'afternoon',
            'status' => 'absent',
        ], $user), $attendanceId);

        $attendance = DB::table('attendances')->where('id', $attendanceId)->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2026-06-05', $attendance->date);
        $this->assertSame('morning', $attendance->period);
        $this->assertSame('absent', $attendance->status);
    }

    public function test_api_bulk_mark_and_destroy_guards_still_return_423()
    {
        $bulkResponse = (new AttendanceController())->bulkMark(Request::create('/api/v1/attendance/bulk-mark', 'POST', []));
        $destroyResponse = (new AttendanceController())->destroy(123);

        $this->assertSame(423, $bulkResponse->getStatusCode());
        $this->assertSame(423, $destroyResponse->getStatusCode());
    }

    private function assertTerminalStatusIsRejected(string $status): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();
        $this->createStudentStatus($studentId, $status);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Attendance cannot be marked for terminal or inactive student.',
            $response->getData(true)['message']
        );
        $this->assertSame(0, DB::table('attendances')->count());
    }

    private function storeRequest(array $payload, User $user): Request
    {
        $request = Request::create('/api/v1/attendance', 'POST', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function updateRequest(array $payload, User $user): Request
    {
        $request = Request::create('/api/v1/attendance/1', 'PATCH', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function createUser(string $email = 'auth-user@example.test'): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secret',
        ]);
    }

    private function createStudent(): int
    {
        return DB::table('students')->insertGetId([
            'name' => 'Student A',
            'class' => '10A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStudentStatus(int $studentId, string $status, string $statusDate = '2026-06-05'): int
    {
        return DB::table('student_statuses')->insertGetId([
            'student_id' => $studentId,
            'status' => $status,
            'status_date' => $statusDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
