<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AttendancePreflightUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });

        $userId = DB::table('users')->insertGetId(['name' => 'UI Tester', 'email' => 'ui@test']);

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
            Schema::create('teachers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });

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
        Schema::dropIfExists('roles');
        Schema::dropIfExists('role_user');
        parent::tearDown();
    }

    protected function seedStudent(array $attrs = [])
    {
        $id = DB::table('students')->insertGetId(array_merge(['name' => 'Student UI','class_id' => 1,'school_class_id'=>null,'class' => '10A','section_id'=>1,'section'=>'A'], $attrs));
        return DB::table('students')->where('id', $id)->first();
    }

    public function test_preflight_view_route_is_registered()
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('attendance.preflight-view') || \Illuminate\Support\Facades\Route::has('admin.attendance.preflight-view')
        );
    }

    public function test_preflight_view_renders_summary_without_writing_attendance()
    {
        $s = $this->seedStudent();
        $payload = ['date' => '2026-06-05', 'classes' => ['10A'], 'default_status' => 'present'];
        $res = $this->post('/admin/attendance/preflight-view', $payload);
        $res->assertStatus(200);
        $res->assertSee('Attendance Preflight Preview');
        $res->assertSee('This is a preflight preview only');
        $this->assertEquals(0, DB::table('attendances')->count());
    }

    public function test_preflight_view_displays_existing_attendance_warning()
    {
        $s = $this->seedStudent();
        DB::table('attendances')->insert(['student_id'=>$s->id,'date'=>'2026-06-05','period'=>null,'status'=>'present','class'=>'10A']);
        $payload = ['date' => '2026-06-05', 'classes' => ['10A'], 'default_status' => 'absent'];
        $res = $this->post('/admin/attendance/preflight-view', $payload);
        $res->assertStatus(200);
        $res->assertSee('Would update'); // not strict; ensure update list appears (summary shows would_update)
        $this->assertEquals(1, DB::table('attendances')->count());
    }

    public function test_preflight_view_displays_terminal_student_skip()
    {
        $s = $this->seedStudent();
        DB::table('student_statuses')->insert(['student_id'=>$s->id,'status'=>'passed_out']);
        $payload = ['date'=>'2026-06-05','classes'=>['10A'],'default_status'=>'present'];
        $res = $this->post('/admin/attendance/preflight-view', $payload);
        $res->assertStatus(200);
        $res->assertSee('Skipped');
    }

    public function test_preflight_view_does_not_render_apply_or_confirm_button()
    {
        $s = $this->seedStudent();
        $payload = ['date' => '2026-06-05', 'classes' => ['10A'], 'default_status' => 'present'];
        $res = $this->post('/admin/attendance/preflight-view', $payload);
        $res->assertStatus(200);
        $res->assertDontSee('Mark Attendance');
        $res->assertDontSee('Apply');
    }

    public function test_existing_preflight_json_endpoint_still_passes()
    {
        $this->postJson('/admin/attendance/preflight', ['date'=>'2026-06-05','attendance_rows'=>[]])->assertStatus(200);
    }

    public function test_existing_attendance_store_route_is_not_changed()
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.attendance.store') || \Illuminate\Support\Facades\Route::has('attendance.store'));
    }
}
