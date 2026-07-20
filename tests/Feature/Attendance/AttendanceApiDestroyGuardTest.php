<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiDestroyGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->string('period')->nullable();
            $table->string('class')->nullable();
            $table->integer('marked_by')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');

        parent::tearDown();
    }

    public function test_api_destroy_route_remains_registered()
    {
        $request = Request::create('/api/v1/attendance/123', 'DELETE');
        $route = Route::getRoutes()->match($request);

        $this->assertSame(AttendanceController::class . '@destroy', $route->getAction('controller'));
    }

    public function test_api_destroy_returns_controlled_disabled_response()
    {
        $response = (new AttendanceController())->destroy(123);
        $json = $response->getData(true);

        $this->assertSame(423, $response->getStatusCode());
        $this->assertFalse($json['success']);
        $this->assertSame(
            'API attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.',
            $json['message']
        );
    }

    public function test_api_destroy_does_not_delete_attendance_record()
    {
        $attendanceId = $this->seedAttendance();

        $response = (new AttendanceController())->destroy($attendanceId);

        $this->assertSame(423, $response->getStatusCode());
        $this->assertSame(1, DB::table('attendances')->where('id', $attendanceId)->count());
    }

    public function test_api_destroy_response_mentions_audit_preserving_correction_or_disabled()
    {
        $message = (new AttendanceController())->destroy(123)->getData(true)['message'];

        $this->assertStringContainsString('temporarily disabled', $message);
        $this->assertStringContainsString('audit-preserving correction workflow', $message);
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

    private function seedAttendance(): int
    {
        return DB::table('attendances')->insertGetId([
            'student_id' => 1,
            'date' => '2026-06-05',
            'status' => 'present',
            'period' => 'morning',
            'class' => '10A',
            'marked_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
