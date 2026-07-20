<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\AttendanceService;

class AttendanceServiceClassSummaryCreditTest extends TestCase
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

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('class_id')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');

        parent::tearDown();
    }

    private function service(): AttendanceService
    {
        return new AttendanceService();
    }

    public function test_class_summary_uses_credit_policy_for_late_and_half_day()
    {
        // Setup students and attendance records
        DB::table('students')->insert([
            ['id' => 1, 'name' => 'Student 1', 'class_id' => 10],
            ['id' => 2, 'name' => 'Student 2', 'class_id' => 10],
            ['id' => 3, 'name' => 'Student 3', 'class_id' => 10],
        ]);

        DB::table('attendances')->insert([
            ['student_id' => 1, 'date' => '2026-06-08', 'status' => 'present'],
            ['student_id' => 2, 'date' => '2026-06-08', 'status' => 'late'],
            ['student_id' => 3, 'date' => '2026-06-08', 'status' => 'half_day'],
        ]);

        $summary = $this->service()->getClassAttendanceSummary(10, '2026-06-08');

        // present = 1.0 credit, late = 1.0 credit, half_day = 0.5 credit => credit = 2.5
        // total = 3 => rate = (2.5 / 3) * 100 = 83.33%
        $this->assertEquals(83.33, $summary['attendance_rate']);
    }

    public function test_class_summary_preserves_existing_summary_keys()
    {
        DB::table('students')->insert([
            ['id' => 1, 'name' => 'Student 1', 'class_id' => 10],
        ]);

        DB::table('attendances')->insert([
            ['student_id' => 1, 'date' => '2026-06-08', 'status' => 'present'],
        ]);

        $summary = $this->service()->getClassAttendanceSummary(10, '2026-06-08');

        $this->assertArrayHasKey('total_students', $summary);
        $this->assertArrayHasKey('present', $summary);
        $this->assertArrayHasKey('absent', $summary);
        $this->assertArrayHasKey('leave', $summary);
        $this->assertArrayHasKey('attendance_rate', $summary);
    }

    public function test_class_summary_keeps_leave_zero_credit()
    {
        DB::table('students')->insert([
            ['id' => 1, 'name' => 'Student 1', 'class_id' => 10],
            ['id' => 2, 'name' => 'Student 2', 'class_id' => 10],
        ]);

        DB::table('attendances')->insert([
            ['student_id' => 1, 'date' => '2026-06-08', 'status' => 'present'],
            ['student_id' => 2, 'date' => '2026-06-08', 'status' => 'leave'],
        ]);

        $summary = $this->service()->getClassAttendanceSummary(10, '2026-06-08');

        // present = 1.0, leave = 0.0 => credit = 1.0; total = 2 => rate = 50%
        $this->assertEquals(50.0, $summary['attendance_rate']);
    }

    public function test_class_summary_adds_credit_fields_if_added()
    {
        DB::table('students')->insert([
            ['id' => 1, 'name' => 'Student 1', 'class_id' => 10],
        ]);

        DB::table('attendances')->insert([
            ['student_id' => 1, 'date' => '2026-06-08', 'status' => 'present'],
        ]);

        $summary = $this->service()->getClassAttendanceSummary(10, '2026-06-08');

        $this->assertArrayHasKey('attendance_credit', $summary);
        $this->assertArrayHasKey('late_days', $summary);
        $this->assertArrayHasKey('half_days', $summary);
        $this->assertArrayHasKey('leave_days', $summary);
    }

    public function test_teacher_dashboard_disabled_ui_still_passes()
    {
        $this->withViewErrors([]);
        $html = view('teacher.attendance.dashboard', [
            'todaySummary' => [
                'total_students' => 2,
                'present' => 1,
                'absent' => 1,
                'attendance_rate' => 50,
            ],
            'classData' => [
                [
                    'class' => (object) [
                        'id' => 10,
                        'class_name' => 'Class 10',
                    ],
                    'subject' => (object) [
                        'name' => 'Mathematics',
                    ],
                    'summary' => [
                        'attendance_rate' => 50,
                        'present' => 1,
                        'absent' => 1,
                    ],
                ],
            ],
            'lowAttendanceAlerts' => [
                [
                    'student' => (object) [
                        'id' => 1,
                        'name' => 'Student One',
                        'schoolClass' => (object) [
                            'class_name' => 'Class 10',
                        ],
                    ],
                    'attendance_rate' => 55,
                    'absent_days' => 4,
                ],
            ],
        ])->render();

        $this->assertStringContainsString(
            'Teacher attendance marking, updates, reports, and export are temporarily disabled until class/status/schema policy is aligned.',
            $html
        );
    }
}
