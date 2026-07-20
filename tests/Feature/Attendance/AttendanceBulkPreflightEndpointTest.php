<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use App\Models\User;

class AttendanceBulkPreflightEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(); // bypass global middleware for testing the endpoint

        // configure in-memory sqlite
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });

        // create and authenticate a simple user so authorization passes
        $userId = DB::table('users')->insertGetId(['name' => 'Test User', 'email' => 'test@example.com']);

        // create minimal roles schema used by app authorization helpers and seed an admin role
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('user_id');
        });
        $roleId = DB::table('roles')->insertGetId(['name' => 'admin']);
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $userId]);

        $this->actingAs(User::find($userId));

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

        Schema::create('teachers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
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
        Schema::dropIfExists('users');
        Schema::dropIfExists('students');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('sections');
        parent::tearDown();
    }

    protected function seedStudent(array $attrs = [])
    {
        $id = DB::table('students')->insertGetId(array_merge(['name' => 'Student A','class_id' => 1,'school_class_id'=>null,'class' => '10A','section_id'=>1,'section'=>'A'], $attrs));
        return DB::table('students')->where('id', $id)->first();
    }

    public function test_preflight_route_is_registered()
    {
        $this->assertTrue(RouteFacade::has('attendance.preflight') || RouteFacade::has('admin.attendance.preflight'));
    }

    public function test_preflight_endpoint_returns_structured_summary()
    {
        $s = $this->seedStudent();
        $payload = ['date' => '2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $res = $this->postJson('/admin/attendance/preflight', $payload);
        $res->assertStatus(200)->assertJsonStructure(['success','data' => ['summary','normalized','errors','warnings','is_valid']]);
    }

    public function test_preflight_endpoint_detects_existing_attendance_without_writing()
    {
        $s = $this->seedStudent();
        DB::table('attendances')->insert(['student_id'=>$s->id,'date'=>'2026-06-05','period'=>'morning','status'=>'present','class'=>'10A']);
        $countBefore = DB::table('attendances')->count();
        $payload = ['date'=>'2026-06-05','period'=>'morning','attendance_rows'=>[['student_id'=>$s->id,'status'=>'absent']]];
        $res = $this->postJson('/admin/attendance/preflight', $payload);
        $res->assertStatus(200);
        $json = $res->json();
        $this->assertEquals('update', $json['data']['normalized'][0]['action']);
        $this->assertEquals($countBefore, DB::table('attendances')->count());
    }

    public function test_preflight_endpoint_detects_terminal_student_without_writing()
    {
        $s = $this->seedStudent();
        DB::table('student_statuses')->insert(['student_id'=>$s->id,'status'=>'passed_out']);
        $countBefore = DB::table('attendances')->count();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $res = $this->postJson('/admin/attendance/preflight', $payload);
        $res->assertStatus(200);
        $json = $res->json();
        $this->assertEquals('skip', $json['data']['normalized'][0]['action']);
        $this->assertEquals($countBefore, DB::table('attendances')->count());
    }

    public function test_preflight_endpoint_does_not_modify_attendance_table()
    {
        $s = $this->seedStudent();
        $countBefore = DB::table('attendances')->count();
        $payload = ['date'=>'2026-06-05','attendance_rows'=>[['student_id'=>$s->id,'status'=>'present']]];
        $this->postJson('/admin/attendance/preflight', $payload)->assertStatus(200);
        $this->assertEquals($countBefore, DB::table('attendances')->count());
    }

    public function test_existing_store_route_behavior_not_changed_by_preflight_phase()
    {
        $this->assertTrue(RouteFacade::has('admin.attendance.store') || RouteFacade::has('attendance.store'));
    }
}
