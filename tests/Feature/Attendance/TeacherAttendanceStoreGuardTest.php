<?php

namespace Tests\Feature\Attendance;

use App\Services\AttendanceNotificationService;
use App\Services\AttendanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TeacherAttendanceStoreGuardTest extends TestCase
{
    private AttendanceService $attendanceService;
    private AttendanceNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();

        $this->attendanceService = Mockery::mock(AttendanceService::class);
        $this->attendanceService->shouldNotReceive('markAttendance');
        $this->app->instance(AttendanceService::class, $this->attendanceService);

        $this->notificationService = Mockery::mock(AttendanceNotificationService::class);
        $this->notificationService->shouldNotReceive('sendBulkAttendanceNotifications');
        $this->app->instance(AttendanceNotificationService::class, $this->notificationService);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');

        Mockery::close();

        parent::tearDown();
    }

    public function test_teacher_attendance_store_returns_warning(): void
    {
        $response = $this->from('/teacher/attendance/mark/1')
            ->post('/teacher/attendance/store', []);

        $response->assertRedirect('/teacher/attendance/mark/1');
        $response->assertSessionHas(
            'warning',
            'Teacher attendance marking is temporarily disabled until class/status/schema policy is aligned.'
        );
        $response->assertSessionMissing('errors');
    }

    public function test_teacher_attendance_store_does_not_create_attendance_rows(): void
    {
        $this->post('/teacher/attendance/store', $this->storePayload());

        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_teacher_attendance_store_does_not_call_attendance_service(): void
    {
        $this->post('/teacher/attendance/store', $this->storePayload());

        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_teacher_attendance_store_route_remains_registered(): void
    {
        $route = Route::getRoutes()->getByName('teacher.attendance.store');

        $this->assertNotNull($route);
        $this->assertSame('POST', implode('|', $route->methods()));
        $this->assertSame('TeacherAttendanceController@storeAttendance', class_basename($route->getAction('controller')));
    }

    public function test_attendance_service_lint_still_passes_by_remaining_unchanged(): void
    {
        $this->assertTrue(method_exists(AttendanceService::class, 'markAttendance'));
    }

    private function createSchema(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('class_id')->nullable();
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

    private function storePayload(): array
    {
        return [
            'class_id' => 1,
            'date' => '2026-06-06',
            'attendance' => [
                [
                    'student_id' => 1,
                    'status' => 'present',
                    'remarks' => 'Present',
                ],
            ],
        ];
    }
}
