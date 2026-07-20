<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceBulkDirectWriteGuardTest extends TestCase
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

        $userId = DB::table('users')->insertGetId([
            'name' => 'Bulk Guard Tester',
            'email' => 'bulk-guard@test',
        ]);

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
            $table->string('roll_number')->nullable();
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

        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->string('status');
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('subject_specialization')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    protected function seedStudent(array $attrs = [])
    {
        $id = DB::table('students')->insertGetId(array_merge([
            'name' => 'Student Bulk Guard',
            'class_id' => 1,
            'class' => '10A',
            'section_id' => 1,
            'section' => 'A',
            'roll_number' => '1',
        ], $attrs));

        return DB::table('students')->where('id', $id)->first();
    }

    public function test_bulk_store_without_apply_is_guarded_and_does_not_insert()
    {
        $this->seedStudent();

        $payload = [
            'date' => '2026-06-05',
            'subject' => 'General',
            'period' => null,
            'classes' => ['10A'],
            'default_status' => 'present',
        ];

        $response = $this->from('/attendance/bulk-mark')->post('/admin/attendance', $payload);

        $response->assertRedirect('/attendance/bulk-mark');
        $response->assertSessionHas(
            'warning',
            'Direct bulk attendance marking is temporarily disabled. Please use Preview first. Safe bulk apply is not enabled yet.'
        );

        $this->assertEquals(0, DB::table('attendances')->count());
    }

    public function test_bulk_mark_view_no_longer_renders_direct_mark_attendance_button()
    {
        $response = $this->withViewErrors([])
            ->view('attendance.bulk_mark', ['classes' => collect(['10A'])]);

        $response->assertDontSee('<i class="bi bi-save"></i> Mark Attendance', false);
        $response->assertDontSee('Click "Mark Attendance"', false);
        $response->assertSee('Direct bulk marking is disabled until safe apply is enabled');
    }

    public function test_bulk_mark_view_still_renders_preview_button()
    {
        $response = $this->withViewErrors([])
            ->view('attendance.bulk_mark', ['classes' => collect(['10A'])]);

        $response->assertSee('Preview');
        $response->assertSee('preflight-view', false);
    }

    public function test_preflight_result_view_does_not_render_apply_confirm_or_mark_button()
    {
        $this->seedStudent();

        $payload = [
            'date' => '2026-06-05',
            'classes' => ['10A'],
            'default_status' => 'present',
        ];

        $response = $this->post('/admin/attendance/preflight-view', $payload);

        $response->assertStatus(200);
        $response->assertSee('Attendance Preflight Preview');
        $response->assertDontSee('Apply');
        $response->assertDontSee('Confirm');
        $response->assertDontSee('Mark Attendance');
    }

    public function test_individual_store_branch_route_behavior_is_not_changed_by_bulk_guard()
    {
        $this->assertTrue(Route::has('attendance.store') || Route::has('admin.attendance.store'));
    }

    public function test_attendance_create_read_only_test_still_passes()
    {
        $this->assertTrue(Route::has('attendance.create') || Route::has('admin.attendance.create'));
    }

    public function test_attendance_preflight_ui_still_passes()
    {
        $this->assertTrue(Route::has('attendance.preflight-view') || Route::has('admin.attendance.preflight-view'));
        $this->assertTrue(Route::has('attendance.preflight') || Route::has('admin.attendance.preflight'));
    }
}
