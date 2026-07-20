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

class TeacherAttendanceUpdateGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();

        $attendanceService = Mockery::mock(AttendanceService::class);
        $attendanceService->shouldNotReceive('markAttendance');
        $this->app->instance(AttendanceService::class, $attendanceService);

        $notificationService = Mockery::mock(AttendanceNotificationService::class);
        $notificationService->shouldNotReceive('sendBulkAttendanceNotifications');
        $this->app->instance(AttendanceNotificationService::class, $notificationService);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');

        Mockery::close();

        parent::tearDown();
    }

    public function test_teacher_attendance_update_returns_warning(): void
    {
        $attendanceId = $this->createAttendance();

        $response = $this->from('/teacher/attendance/' . $attendanceId . '/edit')
            ->put('/teacher/attendance/' . $attendanceId, []);

        $response->assertRedirect('/teacher/attendance/' . $attendanceId . '/edit');
        $response->assertSessionHas(
            'warning',
            'Teacher attendance updates are temporarily disabled until class/status/schema policy is aligned.'
        );
        $response->assertSessionMissing('errors');
    }

    public function test_teacher_attendance_update_does_not_change_status(): void
    {
        $attendanceId = $this->createAttendance(['status' => 'present']);

        $this->put('/teacher/attendance/' . $attendanceId, [
            'status' => 'leave',
            'remarks' => 'Changed',
        ]);

        $this->assertSame('present', DB::table('attendances')->where('id', $attendanceId)->value('status'));
    }

    public function test_teacher_attendance_update_does_not_change_remarks(): void
    {
        $attendanceId = $this->createAttendance(['remarks' => 'Original remarks']);

        $this->put('/teacher/attendance/' . $attendanceId, [
            'status' => 'absent',
            'remarks' => 'Changed remarks',
        ]);

        $this->assertSame('Original remarks', DB::table('attendances')->where('id', $attendanceId)->value('remarks'));
    }

    public function test_teacher_attendance_update_does_not_write_updated_by(): void
    {
        $attendanceId = $this->createAttendance(['updated_by' => null]);

        $this->put('/teacher/attendance/' . $attendanceId, [
            'status' => 'absent',
            'remarks' => 'Changed',
            'updated_by' => 99,
        ]);

        $this->assertNull(DB::table('attendances')->where('id', $attendanceId)->value('updated_by'));
    }

    public function test_teacher_attendance_update_route_remains_registered(): void
    {
        $route = Route::getRoutes()->getByName('teacher.attendance.update');

        $this->assertNotNull($route);
        $this->assertSame('PUT', implode('|', $route->methods()));
        $this->assertSame('TeacherAttendanceController@updateAttendance', class_basename($route->getAction('controller')));
    }

    private function createSchema(): void
    {
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
            $table->integer('updated_by')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();
        });
    }

    private function createAttendance(array $overrides = []): int
    {
        return DB::table('attendances')->insertGetId(array_merge([
            'student_id' => 1,
            'teacher_id' => 1,
            'date' => '2026-06-06',
            'status' => 'present',
            'remarks' => 'Original remarks',
            'period' => null,
            'subject' => 'General',
            'class' => 'Class 10',
            'session' => '2026-2027',
            'marked_by' => 1,
            'updated_by' => null,
            'ip_address' => '127.0.0.1',
            'device_info' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
