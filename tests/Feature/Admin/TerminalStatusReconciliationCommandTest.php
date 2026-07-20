<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TerminalStatusReconciliationCommandTest extends TestCase
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

    public function test_command_requires_dry_run_option(): void
    {
        $this->artisan('helpinghand:reconcile-terminal-statuses')
            ->expectsOutput('This command is dry-run only in Phase 3U. Use --dry-run to inspect issues.')
            ->assertExitCode(1);
    }

    public function test_dry_run_detects_terminal_status_with_class_section_drift(): void
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

        $this->artisan('helpinghand:reconcile-terminal-statuses --dry-run')
            ->expectsOutputToContain('Latest terminal statuses with class/section drift: 1')
            ->expectsOutputToContain('Terminal Drift Student')
            ->assertExitCode(0);
    }

    public function test_dry_run_detects_passed_out_without_promotion_log(): void
    {
        $this->seedStudent(6, ['name' => 'Missing Log Student']);
        $this->seedStatus(6, 'passed_out');

        $this->artisan('helpinghand:reconcile-terminal-statuses --dry-run')
            ->expectsOutputToContain('Latest passed_out statuses without Passed Out promotion log: 1')
            ->expectsOutputToContain('Missing Log Student')
            ->assertExitCode(0);
    }

    public function test_dry_run_detects_class_id_school_class_id_conflict(): void
    {
        $this->seedStudent(301, [
            'name' => 'Class Conflict Student',
            'class_id' => 11,
            'school_class_id' => 8,
            'class' => 'Class 8',
        ]);

        $this->artisan('helpinghand:reconcile-terminal-statuses --dry-run')
            ->expectsOutputToContain('class_id / school_class_id conflicts: 1')
            ->expectsOutputToContain('Class Conflict Student')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_modify_database(): void
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

        $this->artisan('helpinghand:reconcile-terminal-statuses --dry-run')->assertExitCode(0);

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

    public function test_dry_run_reports_clean_state_when_no_issues(): void
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

        $this->artisan('helpinghand:reconcile-terminal-statuses --dry-run')
            ->expectsOutputToContain('Latest terminal statuses with class/section drift: 0')
            ->expectsOutputToContain('Latest passed_out statuses without Passed Out promotion log: 0')
            ->expectsOutputToContain('class_id / school_class_id conflicts: 0')
            ->expectsOutputToContain('Dry-run complete. No data was changed and no apply mode exists in Phase 3U.')
            ->assertExitCode(0);
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
