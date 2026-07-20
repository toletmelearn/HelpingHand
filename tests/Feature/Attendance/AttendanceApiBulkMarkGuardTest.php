<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiBulkMarkGuardTest extends TestCase
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
            $table->string('period')->nullable();
            $table->string('status')->nullable();
            $table->string('class')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');

        parent::tearDown();
    }

    public function test_api_bulk_mark_route_still_exists()
    {
        $this->assertTrue(Route::has('api.v1.attendance.bulk-mark'));
    }

    public function test_api_bulk_mark_route_still_dispatches_to_bulkMark()
    {
        $request = Request::create('/api/v1/attendance/bulk-mark', 'POST');
        $route = Route::getRoutes()->match($request);

        $this->assertSame(AttendanceController::class . '@bulkMark', $route->getAction('controller'));
    }

    public function test_api_bulk_mark_is_guarded_and_does_not_insert()
    {
        $response = (new AttendanceController())->bulkMark(Request::create('/api/v1/attendance/bulk-mark', 'POST', [
            'date' => '2026-06-05',
            'class' => '10A',
            'subject' => 'General',
            'period' => 'morning',
            'student_ids' => [1, 2],
            'statuses' => ['present', 'absent'],
            'marked_by' => 1,
        ]));

        $this->assertSame(423, $response->getStatusCode());
        $this->assertSame(0, DB::table('attendances')->count());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame(
            'API bulk attendance marking is temporarily disabled. Use attendance preflight first. Safe API bulk apply is not enabled yet.',
            $response->getData(true)['message']
        );
    }

    public function test_api_bulk_mark_guard_returns_before_validation()
    {
        $response = (new AttendanceController())->bulkMark(Request::create('/api/v1/attendance/bulk-mark', 'POST', []));

        $this->assertSame(423, $response->getStatusCode());
        $this->assertSame(0, DB::table('attendances')->count());
        $this->assertSame(
            'API bulk attendance marking is temporarily disabled. Use attendance preflight first. Safe API bulk apply is not enabled yet.',
            $response->getData(true)['message']
        );
    }
}
