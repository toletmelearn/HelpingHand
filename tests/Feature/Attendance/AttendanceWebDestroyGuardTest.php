<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceWebDestroyGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->actingAs($this->createUser('admin'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_web_destroy_does_not_delete_attendance_record(): void
    {
        $attendance = $this->createAttendance();

        $this->delete('/attendance/' . $attendance->id);

        $this->assertSame(1, DB::table('attendances')->where('id', $attendance->id)->count());
    }

    public function test_web_destroy_returns_warning_message(): void
    {
        $attendance = $this->createAttendance();

        $response = $this->from('/attendance/' . $attendance->id)
            ->delete('/attendance/' . $attendance->id);

        $response->assertRedirect();
        $response->assertSessionHas(
            'warning',
            'Web attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.'
        );
    }

    public function test_web_destroy_route_remains_registered(): void
    {
        $route = collect(Route::getRoutes())->first(function ($route) {
            return in_array('DELETE', $route->methods(), true)
                && $route->uri() === 'attendance/{attendance}'
                && $route->getAction('controller') === 'App\Http\Controllers\AttendanceController@destroy';
        });

        $this->assertNotNull($route);
        $this->assertSame('DELETE', implode('|', $route->methods()));
        $this->assertSame('AttendanceController@destroy', class_basename($route->getAction('controller')));
    }

    public function test_web_destroy_authorization_still_runs(): void
    {
        $attendance = $this->createAttendance();

        $this->actingAs($this->createUser('teacher'));

        $response = $this->delete('/attendance/' . $attendance->id);

        $response->assertForbidden();
        $this->assertSame(1, DB::table('attendances')->where('id', $attendance->id)->count());
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('user_id');
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->integer('teacher_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
            $table->string('period')->nullable();
            $table->string('subject')->nullable();
            $table->string('class')->nullable();
            $table->string('session')->nullable();
            $table->integer('marked_by')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(string $roleName): User
    {
        $user = User::create([
            'name' => ucfirst($roleName) . ' User',
            'email' => $roleName . uniqid() . '@test',
            'password' => 'secret',
        ]);

        $roleId = DB::table('roles')->insertGetId(['name' => $roleName]);
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);

        return $user;
    }

    private function createAttendance(array $overrides = []): Attendance
    {
        return Attendance::create(array_merge([
            'student_id' => 1,
            'date' => '2026-06-06',
            'status' => 'present',
            'remarks' => 'Original',
            'period' => 'Period 1',
            'subject' => 'Math',
            'class' => 'Class 10',
            'session' => '2026-2027',
            'marked_by' => 1,
            'ip_address' => '127.0.0.1',
            'device_info' => 'test',
        ], $overrides));
    }
}
