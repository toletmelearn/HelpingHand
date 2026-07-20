<?php

namespace Tests\Unit\Services;

use App\Services\StudentStatus\TerminalStatusReconciliationDetector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TerminalStatusReconciliationDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetMinimalSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_detector_finds_terminal_status_with_class_section_drift(): void
    {
        $this->seedStudent(6, [
            'name' => 'Terminal Drift Student',
            'class_id' => 1,
            'school_class_id' => 1,
            'class' => 'Nursery',
            'section_id' => 1,
            'section' => '1',
        ]);
        $this->seedStatus(6, 'passed_out');

        $result = $this->detect();

        $this->assertSame(1, $result['summary']['terminal_status_drift']);
        $this->assertSame(6, $result['terminal_status_drift']->first()->student_id);
        $this->assertSame('missing', $result['terminal_status_drift']->first()->matching_log_status);
    }

    public function test_detector_finds_passed_out_without_promotion_log(): void
    {
        $this->seedStudent(6, ['name' => 'Missing Log Student']);
        $this->seedStatus(6, 'passed_out');

        $result = $this->detect();

        $this->assertSame(1, $result['summary']['passed_out_without_log']);
        $this->assertSame(6, $result['passed_out_without_log']->first()->student_id);
    }

    public function test_detector_finds_class_id_school_class_id_conflict(): void
    {
        $this->seedStudent(301, [
            'name' => 'Class Conflict Student',
            'class_id' => 11,
            'school_class_id' => 8,
            'class' => 'Class 8',
        ]);

        $result = $this->detect();

        $this->assertSame(1, $result['summary']['class_fk_conflicts']);
        $this->assertSame(301, $result['class_fk_conflicts']->first()->student_id);
    }

    public function test_detector_reports_clean_state_when_no_issues(): void
    {
        $this->seedStudent(1, [
            'name' => 'Clean Student',
            'class_id' => 1,
            'school_class_id' => 1,
            'class' => 'Nursery',
            'section_id' => 1,
            'section' => '1',
        ]);
        $this->seedStatus(1, 'active');

        $result = $this->detect();

        $this->assertSame(0, $result['summary']['terminal_status_drift']);
        $this->assertSame(0, $result['summary']['passed_out_without_log']);
        $this->assertSame(0, $result['summary']['passed_out_logs_without_latest_status']);
        $this->assertSame(0, $result['summary']['suspicious_passed_out_logs']);
        $this->assertSame(0, $result['summary']['class_fk_conflicts']);
        $this->assertSame(0, $result['summary']['class_fk_null_mismatches']);
    }

    public function test_detector_does_not_modify_database(): void
    {
        $this->seedStudent(6, [
            'class_id' => 1,
            'school_class_id' => 1,
            'class' => 'Nursery',
            'section_id' => 1,
            'section' => '1',
        ]);
        $this->seedStatus(6, 'passed_out');

        $before = [
            'students' => DB::table('students')->count(),
            'student_statuses' => DB::table('student_statuses')->count(),
            'student_promotion_logs' => DB::table('student_promotion_logs')->count(),
            'student' => (array) DB::table('students')->where('id', 6)->first(),
        ];

        $this->detect();

        $after = [
            'students' => DB::table('students')->count(),
            'student_statuses' => DB::table('student_statuses')->count(),
            'student_promotion_logs' => DB::table('student_promotion_logs')->count(),
            'student' => (array) DB::table('students')->where('id', 6)->first(),
        ];

        $this->assertSame($before['students'], $after['students']);
        $this->assertSame($before['student_statuses'], $after['student_statuses']);
        $this->assertSame($before['student_promotion_logs'], $after['student_promotion_logs']);
        $this->assertSame($before['student']['class_id'], $after['student']['class_id']);
        $this->assertSame($before['student']['school_class_id'], $after['student']['school_class_id']);
        $this->assertSame($before['student']['class'], $after['student']['class']);
        $this->assertSame($before['student']['section_id'], $after['student']['section_id']);
        $this->assertSame($before['student']['section'], $after['student']['section']);
    }

    private function detect(): array
    {
        return app(TerminalStatusReconciliationDetector::class)->detect();
    }

    private function seedStudent(int $id, array $overrides = []): void
    {
        DB::table('students')->insert(array_merge([
            'id' => $id,
            'name' => 'Student ' . $id,
            'class_id' => null,
            'school_class_id' => null,
            'class' => null,
            'section_id' => null,
            'section' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function seedStatus(int $studentId, string $status): void
    {
        DB::table('student_statuses')->insert([
            'student_id' => $studentId,
            'status' => $status,
            'status_date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->string('class')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('section')->nullable();
            $table->timestamps();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('status');
            $table->date('status_date')->nullable();
            $table->timestamps();
        });

        Schema::create('student_promotion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('from_class')->nullable();
            $table->string('to_class')->nullable();
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('student_promotion_logs');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
    }
}
