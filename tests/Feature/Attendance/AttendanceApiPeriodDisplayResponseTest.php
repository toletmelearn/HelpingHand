<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiPeriodDisplayResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_api_index_includes_period_display_and_preserves_raw_period(): void
    {
        $studentId = $this->createStudent();
        $this->seedAttendance([
            'student_id' => $studentId,
            'period' => null,
        ]);

        $response = (new AttendanceController())->index();
        $data = $response->getData(true)['data'][0];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('period', $data);
        $this->assertNull($data['period']);
        $this->assertSame('Full Day', $data['period_display']);
    }

    public function test_api_show_includes_period_display_and_preserves_raw_period(): void
    {
        $attendanceId = $this->seedAttendance(['period' => 'Period 1']);

        $response = (new AttendanceController())->show($attendanceId);
        $data = $response->getData(true)['data'];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Period 1', $data['period']);
        $this->assertSame('Period 1', $data['period_display']);
    }

    public function test_api_store_response_includes_period_display_for_null_period(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $data = $response->getData(true)['data'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('period', $data);
        $this->assertNull($data['period']);
        $this->assertSame('Full Day', $data['period_display']);
    }

    public function test_api_store_response_preserves_literal_full_day_raw_period(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'period' => 'Full Day',
            'class' => '10A',
        ], $user));

        $data = $response->getData(true)['data'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Full Day', $data['period']);
        $this->assertSame('Full Day', $data['period_display']);
    }

    public function test_api_update_response_includes_period_display(): void
    {
        $attendanceId = $this->seedAttendance([
            'period' => null,
            'status' => 'present',
        ]);

        $response = (new AttendanceController())->update(
            Request::create('/api/v1/attendance/' . $attendanceId, 'PATCH', ['status' => 'late']),
            $attendanceId
        );
        $data = $response->getData(true)['data'];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($data['period']);
        $this->assertSame('Full Day', $data['period_display']);
        $this->assertSame('late', $data['status']);
    }

    public function test_api_daily_report_includes_period_display(): void
    {
        $this->seedAttendance([
            'class' => '10A',
            'date' => '2026-06-06',
            'period' => ' Morning ',
        ]);

        $response = (new AttendanceController())->dailyReport('10A', '2026-06-06');
        $data = $response->getData(true)['data'][0];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(' Morning ', $data['period']);
        $this->assertSame('Morning', $data['period_display']);
    }

    public function test_attendance_model_does_not_globally_append_period_display(): void
    {
        $attendance = new Attendance(['period' => null]);

        $this->assertArrayNotHasKey('period_display', $attendance->toArray());
    }

    private function createSchema(): void
    {
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
    }

    private function createUser(string $email = 'auth-user@example.test'): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secret',
        ]);
    }

    private function createStudent(string $class = '10A'): int
    {
        return DB::table('students')->insertGetId([
            'name' => 'Student A',
            'class' => $class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAttendance(array $overrides = []): int
    {
        return DB::table('attendances')->insertGetId(array_merge([
            'student_id' => $this->createStudent(),
            'date' => '2026-06-06',
            'status' => 'present',
            'remarks' => null,
            'period' => null,
            'subject' => 'General',
            'class' => '10A',
            'session' => '2026-2027',
            'marked_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function storeRequest(array $payload, User $user): Request
    {
        $request = Request::create('/api/v1/attendance', 'POST', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
