<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceWebUpdateIdentityGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->actingAs($this->createAdminUser());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_web_update_does_not_mutate_class_from_crafted_request(): void
    {
        $attendance = $this->createAttendance(['class' => 'Class 10']);

        $this->put(route('attendance.update', $attendance), $this->updatePayload([
            'class' => 'Spoofed Class',
        ]));

        $this->assertSame('Class 10', $attendance->fresh()->class);
    }

    public function test_web_update_does_not_mutate_date_from_crafted_request(): void
    {
        $attendance = $this->createAttendance(['date' => '2026-06-06']);

        $this->put(route('attendance.update', $attendance), $this->updatePayload([
            'date' => '2026-07-07',
        ]));

        $this->assertSame('2026-06-06', $attendance->fresh()->date->format('Y-m-d'));
    }

    public function test_web_update_does_not_mutate_period_from_crafted_request(): void
    {
        $attendance = $this->createAttendance(['period' => 'Period 1']);

        $this->put(route('attendance.update', $attendance), $this->updatePayload([
            'period' => 'Period 9',
        ]));

        $this->assertSame('Period 1', $attendance->fresh()->period);
    }

    public function test_web_update_still_updates_status_subject_and_remarks(): void
    {
        $attendance = $this->createAttendance([
            'status' => 'present',
            'subject' => 'Math',
            'remarks' => 'Original',
        ]);

        $response = $this->put(route('attendance.update', $attendance), $this->updatePayload([
            'status' => 'late',
            'subject' => 'Science',
            'remarks' => 'Updated safely',
        ]));

        $response->assertRedirect(route('attendance.show', $attendance));

        $fresh = $attendance->fresh();

        $this->assertSame('late', $fresh->status);
        $this->assertSame('Science', $fresh->subject);
        $this->assertSame('Updated safely', $fresh->remarks);
    }

    public function test_web_update_does_not_mutate_marked_by(): void
    {
        $attendance = $this->createAttendance(['marked_by' => 1]);

        $this->put(route('attendance.update', $attendance), $this->updatePayload([
            'marked_by' => 2,
        ]));

        $this->assertSame(1, $attendance->fresh()->marked_by);
    }

    public function test_web_update_does_not_mutate_student_id_even_if_payload_contains_it(): void
    {
        $attendance = $this->createAttendance(['student_id' => 1]);

        $this->put(route('attendance.update', $attendance), $this->updatePayload([
            'student_id' => 2,
        ]));

        $this->assertSame(1, $attendance->fresh()->student_id);
    }

    public function test_edit_form_does_not_submit_class_date_period_as_editable_fields(): void
    {
        $attendance = $this->createAttendance([
            'date' => '2026-06-06',
            'class' => 'Class 10',
            'period' => 'Period 1',
        ])->load('student');

        $response = $this->withViewErrors([])->view('attendance.edit', [
            'attendance' => $attendance,
            'subjects' => collect(['Math', 'Science']),
        ]);

        $response->assertDontSee('name="class"', false);
        $response->assertDontSee('name="date"', false);
        $response->assertDontSee('name="period"', false);
        $response->assertSee('name="status"', false);
        $response->assertSee('name="subject"', false);
        $response->assertSee('name="remarks"', false);
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

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('roll_number')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
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

        DB::table('students')->insert([
            ['id' => 1, 'name' => 'Student One', 'roll_number' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Student Two', 'roll_number' => '2', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createAdminUser(): User
    {
        $user = User::create([
            'name' => 'Web Update Tester',
            'email' => 'web-update@test',
            'password' => 'secret',
        ]);

        $roleId = DB::table('roles')->insertGetId(['name' => 'admin']);
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

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'status' => 'absent',
            'subject' => 'English',
            'remarks' => 'Updated',
        ], $overrides);
    }
}
