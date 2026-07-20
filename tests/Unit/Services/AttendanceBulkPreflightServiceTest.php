<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Services\Attendance\AttendanceBulkPreflightService;

class AttendanceBulkPreflightServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory sqlite
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        // Create minimal tables
        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('class_id')->nullable();
            $table->integer('school_class_id')->nullable();
            $table->string('class')->nullable();
            $table->integer('section_id')->nullable();
            $table->string('section')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->string('status');
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('period')->nullable();
            $table->string('status')->nullable();
            $table->string('class')->nullable();
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('sections');
        parent::tearDown();
    }

    protected function seedStudent(array $attrs = [])
    {
        $id = DB::table('students')->insertGetId(array_merge(['name' => 'Student A','class_id' => 1,'school_class_id' => null,'class' => '10A','section_id'=>1,'section'=>'A'], $attrs));
        return DB::table('students')->where('id', $id)->first();
    }

    protected function seedSchoolClass(string $name): int
    {
        return DB::table('school_classes')->insertGetId(['name' => $name]);
    }

    public function test_preflight_reports_missing_date()
    {
        $s = $this->seedStudent();
        $service = new AttendanceBulkPreflightService();
        $payload = ['attendance_rows' => [['student_id' => $s->id, 'status' => 'present']]];
        $res = $service->preflight($payload);
        $this->assertFalse($res['is_valid']);
        $this->assertNotEmpty($res['errors']);
    }

    public function test_preflight_reports_invalid_student_id()
    {
        $service = new AttendanceBulkPreflightService();
        $payload = ['date' => '2026-06-05','attendance_rows' => [['student_id' => 9999, 'status' => 'present']]];
        $res = $service->preflight($payload);
        $this->assertFalse($res['is_valid']);
        $flat = collect($res['errors'])->flatten()->toArray();
        $this->assertStringContainsString('invalid_student_id', implode(',', $flat) ?: '');
    }

    public function test_preflight_accepts_valid_present_absent_late_half_day_statuses()
    {
        $s = $this->seedStudent();
        $service = new AttendanceBulkPreflightService();
        $payload = ['date' => '2026-06-05','attendance_rows' => [
            ['student_id' => $s->id, 'status' => 'present'],
            ['student_id' => $s->id, 'status' => 'absent', 'student_id' => $s->id],
        ]];
        // duplicate rows will cause duplicate_in_payload, so craft unique student
        $s2 = $this->seedStudent(['name'=>'Student B','class'=>'10A']);
        $payload['attendance_rows'] = [
            ['student_id' => $s->id, 'status' => 'present'],
            ['student_id' => $s2->id, 'status' => 'late'],
            ['student_id' => $s2->id, 'status' => 'half_day','student_id'=>$s2->id.'x']
        ];
        // fix third to valid numeric id
        $payload['attendance_rows'][2]['student_id'] = $s2->id;
        $res = $service->preflight($payload);
        $this->assertArrayHasKey('summary', $res);
        $this->assertIsArray($res['normalized']);
    }

    public function test_preflight_detects_duplicate_rows_in_payload()
    {
        $s = $this->seedStudent();
        $service = new AttendanceBulkPreflightService();
        $payload = ['date' => '2026-06-05','period' => 'morning','attendance_rows' => [
            ['student_id' => $s->id, 'status' => 'present'],
            ['student_id' => $s->id, 'status' => 'present'],
        ]];
        $res = $service->preflight($payload);
        $this->assertFalse($res['is_valid']);
        $this->assertNotEmpty($res['errors']);
    }

    public function test_preflight_detects_existing_attendance_for_student_date_period()
    {
        $s = $this->seedStudent();
        DB::table('attendances')->insert(['student_id' => $s->id, 'date' => '2026-06-05', 'period' => 'morning', 'status' => 'present','class'=>'10A']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date' => '2026-06-05','period'=>'morning','attendance_rows' => [['student_id' => $s->id, 'status' => 'absent']]];
        $res = $service->preflight($payload);
        $this->assertTrue($res['normalized'][0]['action'] === 'update' || $res['normalized'][0]['action'] === 'skip');
    }

    public function test_preflight_excludes_passed_out_student()
    {
        $s = $this->seedStudent();
        DB::table('student_statuses')->insert(['student_id'=>$s->id,'status'=>'passed_out']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date' => '2026-06-05','attendance_rows' => [['student_id' => $s->id, 'status' => 'present']]];
        $res = $service->preflight($payload);
        $this->assertEquals('skip', $res['normalized'][0]['action']);
    }

    public function test_preflight_excludes_inactive_student()
    {
        $s = $this->seedStudent();
        DB::table('student_statuses')->insert(['student_id'=>$s->id,'status'=>'inactive']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date' => '2026-06-05','attendance_rows' => [['student_id' => $s->id, 'status' => 'present']]];
        $res = $service->preflight($payload);
        $this->assertEquals('skip', $res['normalized'][0]['action']);
    }

    public function test_preflight_detects_student_class_id_conflict()
    {
        $s = $this->seedStudent(['class_id'=>2,'class'=>'10B']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','class_id'=>1,'attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $res = $service->preflight($payload);
        $this->assertNotEmpty($res['warnings']);
    }

    public function test_preflight_includes_derived_class_from_canonical_class()
    {
        $classId = $this->seedSchoolClass('Class 10');
        $s = $this->seedStudent(['class_id' => $classId, 'class' => 'Legacy 10']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertSame('Class 10', $res['normalized'][0]['derived_class']);
        $this->assertTrue($res['normalized'][0]['class_resolution_ok']);
    }

    public function test_preflight_includes_derived_class_source()
    {
        $classId = $this->seedSchoolClass('Class 11');
        $s = $this->seedStudent(['class_id' => $classId, 'class' => 'Legacy 11']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertSame('canonical', $res['normalized'][0]['derived_class_source']);
        $this->assertSame(200, $res['normalized'][0]['class_resolution_status']);
        $this->assertNull($res['normalized'][0]['class_resolution_message']);
    }

    public function test_preflight_falls_back_to_legacy_class_when_no_canonical_class()
    {
        $s = $this->seedStudent(['class_id' => null, 'class' => 'Legacy 9']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertSame('Legacy 9', $res['normalized'][0]['derived_class']);
        $this->assertSame('legacy', $res['normalized'][0]['derived_class_source']);
    }

    public function test_preflight_marks_class_id_conflict_as_error_or_blocker()
    {
        $classId = $this->seedSchoolClass('Class 8');
        $schoolClassId = $this->seedSchoolClass('Class 9');
        $s = $this->seedStudent([
            'class_id' => $classId,
            'school_class_id' => $schoolClassId,
            'class' => 'Legacy 8',
        ]);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertFalse($res['is_valid']);
        $this->assertSame('error', $res['normalized'][0]['action']);
        $this->assertContains('student_class_conflict', $res['errors'][1]);
        $this->assertSame('conflict', $res['normalized'][0]['derived_class_source']);
    }

    public function test_preflight_marks_unresolved_class_as_error_or_blocker()
    {
        $s = $this->seedStudent(['class_id' => null, 'school_class_id' => null, 'class' => null]);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertFalse($res['is_valid']);
        $this->assertSame('error', $res['normalized'][0]['action']);
        $this->assertContains('student_class_unresolved', $res['errors'][1]);
        $this->assertSame('unresolved', $res['normalized'][0]['derived_class_source']);
    }

    public function test_preflight_warns_when_payload_class_differs_from_derived_class()
    {
        $classId = $this->seedSchoolClass('Class 10');
        $s = $this->seedStudent(['class_id' => $classId, 'class' => 'Legacy 10']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','class'=>'Spoofed Class','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertContains('payload_derived_class_mismatch', $res['warnings'][1]);
    }

    public function test_preflight_keeps_existing_legacy_mismatch_warnings()
    {
        $classId = $this->seedSchoolClass('Class 10');
        $s = $this->seedStudent(['class_id' => $classId, 'class' => 'Legacy 10']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','class'=>'Spoofed Class','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertContains('payload_legacy_class_mismatch', $res['warnings'][1]);
    }

    public function test_preflight_reports_actual_school_class_id()
    {
        $s = $this->seedStudent(['class_id' => 1, 'school_class_id' => 2]);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];

        $res = $service->preflight($payload);

        $this->assertSame(2, $res['normalized'][0]['school_class_id']);
    }

    public function test_preflight_detects_class_mismatch_from_payload()
    {
        $s = $this->seedStudent(['class'=>'10B']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','class'=>'10A','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $res = $service->preflight($payload);
        $this->assertNotEmpty($res['warnings']);
    }

    public function test_preflight_detects_section_mismatch_from_payload()
    {
        $s = $this->seedStudent(['section_id'=>2,'section'=>'B']);
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','section_id'=>1,'attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $res = $service->preflight($payload);
        $this->assertNotEmpty($res['warnings']);
    }

    public function test_preflight_returns_create_action_for_clean_new_row()
    {
        $s = $this->seedStudent();
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $res = $service->preflight($payload);
        $this->assertEquals('create', $res['normalized'][0]['action']);
    }

    public function test_preflight_does_not_modify_database()
    {
        $s = $this->seedStudent();
        $service = new AttendanceBulkPreflightService();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $countBefore = DB::table('attendances')->count();
        $service->preflight($payload);
        $countAfter = DB::table('attendances')->count();
        $this->assertEquals($countBefore, $countAfter);
    }
}
