<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiMarkedByGuardTest extends TestCase
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

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('user_id');
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->timestamp('deleted_at')->nullable();
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

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_api_store_uses_authenticated_user_as_marked_by()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->apiRequest([
            'student_id' => $studentId,
            'date' => '2026-06-05',
            'status' => 'present',
            'class' => '10A',
        ], $authUser));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($authUser->id, DB::table('attendances')->value('marked_by'));
    }

    public function test_api_store_ignores_spoofed_marked_by()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $spoofedUser = $this->createUser('spoofed-user@example.test');
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->apiRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'class' => '10A',
            'marked_by' => $spoofedUser->id,
        ], $authUser));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($authUser->id, DB::table('attendances')->value('marked_by'));
        $this->assertNotSame($spoofedUser->id, DB::table('attendances')->value('marked_by'));
    }

    public function test_api_store_no_longer_requires_marked_by_in_payload()
    {
        $authUser = $this->createUser('auth-user@example.test');
        $studentId = $this->createStudent();

        $response = (new AttendanceController())->store($this->apiRequest([
            'student_id' => $studentId,
            'date' => '2026-06-07',
            'status' => 'late',
            'class' => '10A',
        ], $authUser));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($authUser->id, DB::table('attendances')->value('marked_by'));
    }

    public function test_api_update_does_not_change_marked_by_when_payload_contains_marked_by()
    {
        $originalMarker = $this->createUser('original-marker@example.test');
        $authUser = $this->createUser('auth-user@example.test');
        $spoofedUser = $this->createUser('spoofed-user@example.test');
        $studentId = $this->createStudent();

        $attendanceId = DB::table('attendances')->insertGetId([
            'student_id' => $studentId,
            'date' => '2026-06-08',
            'status' => 'present',
            'class' => '10A',
            'marked_by' => $originalMarker->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = (new AttendanceController())->update($this->apiRequest([
            'status' => 'absent',
            'marked_by' => $spoofedUser->id,
        ], $authUser), $attendanceId);

        $attendance = DB::table('attendances')->where('id', $attendanceId)->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('absent', $attendance->status);
        $this->assertSame($originalMarker->id, $attendance->marked_by);
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

    public function test_api_destroy_route_action_is_not_changed_in_this_phase()
    {
        $request = Request::create('/api/v1/attendance/123', 'DELETE');
        $route = Route::getRoutes()->match($request);

        $this->assertSame(AttendanceController::class . '@destroy', $route->getAction('controller'));
    }

    private function apiRequest(array $payload, User $user): Request
    {
        $request = Request::create('/api/v1/attendance', 'POST', $payload);
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

    private function createStudent(): int
    {
        return DB::table('students')->insertGetId([
            'name' => 'Student A',
            'class' => '10A',
        ]);
    }
}
