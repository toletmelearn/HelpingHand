<?php

namespace Tests\Unit\Services;

use App\Services\AttendanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AttendanceServiceMarkAttendanceGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
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

    public function test_mark_attendance_throws_disabled_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'AttendanceService::markAttendance is temporarily disabled until class/status/schema policy is aligned.'
        );

        $this->service()->markAttendance($this->payload(), 1);
    }

    public function test_mark_attendance_does_not_create_attendance_rows(): void
    {
        try {
            $this->service()->markAttendance($this->payload(), 1);
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'AttendanceService::markAttendance is temporarily disabled until class/status/schema policy is aligned.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_mark_attendance_does_not_start_write_path(): void
    {
        $this->assertSame(0, DB::transactionLevel());

        try {
            $this->service()->markAttendance($this->payload(), 1);
        } catch (RuntimeException) {
            // Expected: the guard fires before the transaction/write path.
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('attendances')->count());
    }

    private function service(): AttendanceService
    {
        return new AttendanceService();
    }

    private function payload(): array
    {
        return [
            [
                'student_id' => 1,
                'date' => '2026-06-07',
                'status' => 'present',
                'remarks' => 'Present',
                'class_id' => 10,
            ],
        ];
    }
}
