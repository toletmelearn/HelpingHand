<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class AttendanceNullPeriodDiagnosticsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory sqlite
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->date('date')->nullable();
            $table->string('period')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        parent::tearDown();
    }

    public function test_command_reports_total_null_empty_and_distinct_period_counts()
    {
        DB::table('attendances')->insert(['student_id'=>1,'date'=>'2026-06-05','period'=>null]);
        DB::table('attendances')->insert(['student_id'=>2,'date'=>'2026-06-05','period'=>'']);
        DB::table('attendances')->insert(['student_id'=>3,'date'=>'2026-06-05','period'=>'Period 1']);

        $this->artisan('helpinghand:attendance-null-period-diagnostics')->assertExitCode(0)->run();
    }

    public function test_command_detects_duplicate_exact_groups()
    {
        DB::table('attendances')->insert(['student_id'=>1,'date'=>'2026-06-05','period'=>'morning']);
        DB::table('attendances')->insert(['student_id'=>1,'date'=>'2026-06-05','period'=>'morning']);

        $this->artisan('helpinghand:attendance-null-period-diagnostics')->assertExitCode(0)->run();
    }

    public function test_command_detects_duplicate_null_period_groups()
    {
        DB::table('attendances')->insert(['student_id'=>2,'date'=>'2026-06-05','period'=>null]);
        DB::table('attendances')->insert(['student_id'=>2,'date'=>'2026-06-05','period'=>null]);

        $this->artisan('helpinghand:attendance-null-period-diagnostics')->assertExitCode(0)->run();
    }

    public function test_command_detects_duplicate_empty_period_groups()
    {
        DB::table('attendances')->insert(['student_id'=>3,'date'=>'2026-06-05','period'=>'']);
        DB::table('attendances')->insert(['student_id'=>3,'date'=>'2026-06-05','period'=>'']);

        $this->artisan('helpinghand:attendance-null-period-diagnostics')->assertExitCode(0)->run();
    }

    public function test_command_detects_suspicious_sentinel_values()
    {
        DB::table('attendances')->insert(['student_id'=>4,'date'=>'2026-06-05','period'=>'full_day']);

        $this->artisan('helpinghand:attendance-null-period-diagnostics')->assertExitCode(0)->run();
    }

    public function test_command_respects_limit_for_samples()
    {
        for ($i=1;$i<=10;$i++) {
            DB::table('attendances')->insert(['student_id'=>$i,'date'=>'2026-06-05','period'=>null]);
        }

        $this->artisan('helpinghand:attendance-null-period-diagnostics --limit=5')->assertExitCode(0)->run();
    }

    public function test_command_outputs_valid_json_with_json_flag()
    {
        DB::table('attendances')->insert(['student_id'=>1,'date'=>'2026-06-05','period'=>null]);
        $this->artisan('helpinghand:attendance-null-period-diagnostics --json')->assertExitCode(0)->run();
    }

    public function test_command_has_no_apply_fix_or_repair_option()
    {
        $cmd = new \App\Console\Commands\AttendanceNullPeriodDiagnosticsCommand();
        $this->assertFalse($cmd->getDefinition()->hasOption('apply'));
        $this->assertFalse($cmd->getDefinition()->hasOption('fix'));
        $this->assertFalse($cmd->getDefinition()->hasOption('repair'));
    }

    public function test_command_does_not_modify_attendance_rows()
    {
        DB::table('attendances')->insert(['student_id'=>9,'date'=>'2026-06-05','period'=>null]);
        $countBefore = DB::table('attendances')->count();
        $this->artisan('helpinghand:attendance-null-period-diagnostics')->assertExitCode(0)->run();
        $this->assertEquals($countBefore, DB::table('attendances')->count());
    }
}
