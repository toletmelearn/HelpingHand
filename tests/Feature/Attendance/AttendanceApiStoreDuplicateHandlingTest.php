<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiStoreDuplicateHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createBaseSchema();
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

    public function test_api_store_existing_duplicate_precheck_still_returns_409()
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();
        $this->seedAttendance([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'morning',
        ]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'morning',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Attendance already marked for this student on this date and period.',
            $response->getData(true)['message']
        );
        $this->assertSame(1, DB::table('attendances')->count());
    }

    public function test_api_store_duplicate_database_exception_returns_controlled_409()
    {
        DB::unprepared("
            CREATE TRIGGER force_attendance_duplicate_for_test
            BEFORE INSERT ON attendances
            WHEN NEW.period = 'afternoon'
            BEGIN
                SELECT RAISE(ABORT, 'UNIQUE constraint failed: attendances.student_id, attendances.date, attendances.period');
            END
        ");

        $user = $this->createUser();
        $studentId = $this->createStudent();
        $this->seedAttendance([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'morning',
        ]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'afternoon',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Attendance already marked for this student on this date and period.',
            $response->getData(true)['message']
        );
        $this->assertSame(1, DB::table('attendances')->count());
    }

    public function test_api_store_non_duplicate_query_exception_still_returns_generic_failure()
    {
        Schema::dropIfExists('attendances');
        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->string('period')->nullable();
            $table->timestamps();
        });

        $user = $this->createUser();
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'morning',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringStartsWith('Failed to mark attendance:', $response->getData(true)['message']);
    }

    public function test_api_store_terminal_status_guard_still_blocks_before_duplicate_check()
    {
        $user = $this->createUser();
        $studentId = $this->createStudent();
        $this->createStudentStatus($studentId, 'inactive');
        $this->seedAttendance([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'morning',
        ]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'afternoon',
            'status' => 'present',
            'class' => '10A',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Attendance cannot be marked for terminal or inactive student.',
            $response->getData(true)['message']
        );
        $this->assertSame(1, DB::table('attendances')->count());
    }

    public function test_api_store_marked_by_guard_still_uses_authenticated_user()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $spoofedUser = $this->createUser('spoofed-user@example.test');
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'period' => 'morning',
            'status' => 'present',
            'class' => '10A',
            'marked_by' => $spoofedUser->id,
        ], $authUser));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($authUser->id, DB::table('attendances')->value('marked_by'));
    }

    private function createBaseSchema(): void
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

    private function storeRequest(array $payload, User $user): Request
    {
        $request = Request::create('/api/v1/attendance', 'POST', $payload);
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

    private function createStudentStatus(int $studentId, string $status): int
    {
        return DB::table('student_statuses')->insertGetId([
            'student_id' => $studentId,
            'status' => $status,
            'status_date' => '2026-06-05',
            'created_at' => now(),
            'updated_at' => now(),
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
            'subject' => null,
            'class' => '10A',
            'session' => null,
            'marked_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
