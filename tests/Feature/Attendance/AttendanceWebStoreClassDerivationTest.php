<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceWebStoreClassDerivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createSchema();
        $this->actingAs($this->createUser());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_web_store_ignores_request_class_and_uses_student_canonical_class(): void
    {
        $classId = $this->createSchoolClass('Class 10');
        $studentId = $this->createStudent([
            'class' => 'Legacy 10',
            'class_id' => $classId,
        ]);

        $response = $this->post('/admin/attendance', $this->storePayload([
            'class' => 'Spoofed Class',
            'student_ids' => [$studentId],
            'statuses' => ['present'],
        ]));

        $response->assertRedirect(route('attendance.index'));
        $this->assertSame('Class 10', DB::table('attendances')->value('class'));
    }

    public function test_web_store_uses_per_student_derived_class_for_each_row(): void
    {
        $classTenId = $this->createSchoolClass('Class 10');
        $classElevenId = $this->createSchoolClass('Class 11');
        $studentA = $this->createStudent(['class' => 'Legacy 10', 'class_id' => $classTenId]);
        $studentB = $this->createStudent(['class' => 'Legacy 11', 'class_id' => $classElevenId]);

        $response = $this->post('/admin/attendance', $this->storePayload([
            'class' => 'Spoofed Class',
            'student_ids' => [$studentA, $studentB],
            'statuses' => ['present', 'absent'],
        ]));

        $response->assertRedirect(route('attendance.index'));
        $classes = DB::table('attendances')
            ->orderBy('student_id')
            ->pluck('class')
            ->all();

        $this->assertSame(['Class 10', 'Class 11'], $classes);
    }

    public function test_web_store_falls_back_to_student_legacy_class_when_no_canonical_class(): void
    {
        $studentId = $this->createStudent([
            'class' => 'Legacy 9',
            'class_id' => null,
        ]);

        $response = $this->post('/admin/attendance', $this->storePayload([
            'class' => 'Spoofed Class',
            'student_ids' => [$studentId],
            'statuses' => ['late'],
        ]));

        $response->assertRedirect(route('attendance.index'));
        $this->assertSame('Legacy 9', DB::table('attendances')->value('class'));
    }

    public function test_web_store_aborts_without_insert_when_any_student_has_class_conflict(): void
    {
        $classA = $this->createSchoolClass('Class A');
        $classB = $this->createSchoolClass('Class B');
        $validStudent = $this->createStudent(['class' => 'Legacy A', 'class_id' => $classA]);
        $conflictStudent = $this->createStudent([
            'class' => 'Legacy Conflict',
            'class_id' => $classA,
            'school_class_id' => $classB,
        ]);

        $response = $this->from('/admin/attendance/create')->post('/admin/attendance', $this->storePayload([
            'class' => 'Class A',
            'student_ids' => [$validStudent, $conflictStudent],
            'statuses' => ['present', 'present'],
        ]));

        $response->assertRedirect('/admin/attendance/create');
        $response->assertSessionHas('error', 'Student class data has a conflict. Attendance cannot be marked until class data is resolved.');
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_web_store_aborts_without_insert_when_any_student_class_unresolved(): void
    {
        $classA = $this->createSchoolClass('Class A');
        $validStudent = $this->createStudent(['class' => 'Legacy A', 'class_id' => $classA]);
        $unresolvedStudent = $this->createStudent(['class' => null, 'class_id' => null]);

        $response = $this->from('/admin/attendance/create')->post('/admin/attendance', $this->storePayload([
            'class' => 'Class A',
            'student_ids' => [$validStudent, $unresolvedStudent],
            'statuses' => ['present', 'present'],
        ]));

        $response->assertRedirect('/admin/attendance/create');
        $response->assertSessionHas('error', 'Student class could not be resolved. Attendance cannot be marked.');
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_web_store_keeps_bulk_direct_write_guard_unchanged(): void
    {
        $this->createStudent(['class' => 'Class A']);

        $response = $this->from('/attendance/bulk-mark')->post('/admin/attendance', [
            'date' => '2026-06-06',
            'subject' => 'General',
            'period' => null,
            'classes' => ['Class A'],
            'default_status' => 'present',
        ]);

        $response->assertRedirect('/attendance/bulk-mark');
        $response->assertSessionHas(
            'warning',
            'Direct bulk attendance marking is temporarily disabled. Please use Preview first. Safe bulk apply is not enabled yet.'
        );
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_web_store_does_not_change_create_read_only_behavior(): void
    {
        $this->createStudent(['class' => 'Class A']);

        $response = $this->get('/admin/attendance/create?class=Class A');

        $response->assertStatus(200);
        $response->assertSee('Mark Daily Attendance');
        $this->assertSame(0, DB::table('attendances')->count());
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

        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->integer('class_id')->nullable();
            $table->integer('school_class_id')->nullable();
            $table->string('class')->nullable();
            $table->integer('section_id')->nullable();
            $table->string('section')->nullable();
            $table->string('roll_number')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->string('status');
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

        Schema::create('teachers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('subject_specialization')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'class' => 'Class A',
            'date' => '2026-06-06',
            'subject' => 'General',
            'period' => null,
            'student_ids' => [],
            'statuses' => [],
            'remarks' => [],
        ], $overrides);
    }

    private function createUser(): User
    {
        $user = User::create([
            'name' => 'Web Store Tester',
            'email' => 'web-store@test',
            'password' => 'secret',
        ]);

        $roleId = DB::table('roles')->insertGetId(['name' => 'admin']);
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);

        return $user;
    }

    private function createSchoolClass(string $name): int
    {
        return DB::table('school_classes')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStudent(array $overrides = []): int
    {
        return DB::table('students')->insertGetId(array_merge([
            'name' => 'Student A',
            'father_name' => 'Father A',
            'class' => 'Class A',
            'class_id' => null,
            'school_class_id' => null,
            'section_id' => 1,
            'section' => 'A',
            'roll_number' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
