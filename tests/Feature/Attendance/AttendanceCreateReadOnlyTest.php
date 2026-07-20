<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AttendanceCreateReadOnlyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });

        $userId = DB::table('users')->insertGetId(['name' => 'Create Tester', 'email' => 'create@test']);

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
            $table->string('class')->nullable();
            $table->integer('section_id')->nullable();
            $table->string('section')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('period')->nullable();
            $table->string('status')->nullable();
            $table->string('class')->nullable();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->string('status');
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

        Schema::create('teachers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('students');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('role_user');
        parent::tearDown();
    }

    protected function seedStudent(array $attrs = [])
    {
        $id = DB::table('students')->insertGetId(array_merge(['name' => 'Student Create','class_id' => 1,'class' => '10A','section_id'=>1,'section'=>'A'], $attrs));
        return DB::table('students')->where('id', $id)->first();
    }

    public function test_attendance_create_route_is_registered()
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('attendance.create') || \Illuminate\Support\Facades\Route::has('admin.attendance.create'));
    }

    public function test_attendance_create_page_does_not_insert_attendance_records()
    {
        $s = $this->seedStudent();
        $res = $this->get('/admin/attendance/create?class=10A');
        $res->assertStatus(200);
        $this->assertEquals(0, DB::table('attendances')->count());
    }

    public function test_attendance_create_page_still_renders_successfully()
    {
        $s = $this->seedStudent();
        $res = $this->get('/admin/attendance/create?class=10A');
        $res->assertStatus(200);
        $res->assertSee('Mark Daily Attendance');
    }

    public function test_attendance_preflight_ui_still_works_after_create_fix()
    {
        $s = $this->seedStudent();
        $payload = ['date' => '2026-06-05', 'classes' => ['10A'], 'default_status' => 'present'];
        $res = $this->post('/admin/attendance/preflight-view', $payload);
        $res->assertStatus(200);
    }

    public function test_attendance_store_route_still_exists()
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.attendance.store') || \Illuminate\Support\Facades\Route::has('attendance.store'));
    }
}
