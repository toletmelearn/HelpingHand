<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\API\AttendanceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceApiStoreClassDerivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('academic_events');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_api_store_ignores_spoofed_client_class_when_student_has_canonical_class(): void
    {
        $user = $this->createUser();
        $classId = $this->createSchoolClass('Class 10');
        $studentId = $this->createStudent([
            'class' => 'Legacy 10',
            'class_id' => $classId,
            'school_class_id' => $classId,
        ]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'class' => 'Spoofed Class',
        ], $user));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Class 10', DB::table('attendances')->value('class'));
        $this->assertSame('Class 10', $response->getData(true)['data']['class']);
    }

    public function test_api_store_derives_class_from_canonical_school_class(): void
    {
        $user = $this->createUser();
        $classId = $this->createSchoolClass('Class 11');
        $studentId = $this->createStudent([
            'class' => 'Old Class Name',
            'class_id' => $classId,
        ]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
        ], $user));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Class 11', DB::table('attendances')->value('class'));
    }

    public function test_api_store_falls_back_to_student_legacy_class_when_no_canonical_class_exists(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent(['class' => 'Legacy 9']);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'class' => 'Spoofed Class',
        ], $user));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Legacy 9', DB::table('attendances')->value('class'));
    }

    public function test_api_store_returns_conflict_when_student_class_ids_conflict(): void
    {
        $user = $this->createUser();
        $classId = $this->createSchoolClass('Class 8');
        $schoolClassId = $this->createSchoolClass('Class 9');
        $studentId = $this->createStudent([
            'class' => 'Legacy 8',
            'class_id' => $classId,
            'school_class_id' => $schoolClassId,
        ]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'class' => 'Class 8',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Student class data has a conflict. Attendance cannot be marked until class data is resolved.',
            $response->getData(true)['message']
        );
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_api_store_returns_controlled_error_when_student_class_cannot_be_resolved(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent(['class' => null]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'class' => 'Spoofed Class',
        ], $user));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'Student class could not be resolved. Attendance cannot be marked.',
            $response->getData(true)['message']
        );
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_api_store_still_accepts_old_payload_with_class_but_does_not_trust_it(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent(['class' => 'Student Class']);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'class' => 'Client Class',
        ], $user));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Student Class', DB::table('attendances')->value('class'));
    }

    public function test_api_store_marked_by_guard_still_uses_authenticated_user(): void
    {
        $authUser = $this->createUser('auth-user@example.test');
        $spoofedUser = $this->createUser('spoofed-user@example.test');
        $studentId = $this->createStudent(['class' => 'Class A']);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
            'marked_by' => $spoofedUser->id,
        ], $authUser));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($authUser->id, DB::table('attendances')->value('marked_by'));
    }

    public function test_api_store_terminal_status_guard_still_blocks_before_create(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent(['class' => 'Class A']);
        $this->createStudentStatus($studentId, 'inactive');

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Attendance cannot be marked for terminal or inactive student.',
            $response->getData(true)['message']
        );
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_api_store_duplicate_handling_still_returns_409(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent(['class' => 'Class A']);
        $this->seedAttendance([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'period' => 'morning',
            'class' => 'Class A',
        ]);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'period' => 'morning',
            'status' => 'present',
            'class' => 'Spoofed Class',
        ], $user));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Attendance already marked for this student on this date and period.',
            $response->getData(true)['message']
        );
        $this->assertSame(1, DB::table('attendances')->count());
    }

    public function test_api_store_response_still_includes_period_display(): void
    {
        $user = $this->createUser();
        $studentId = $this->createStudent(['class' => 'Class A']);

        $response = (new AttendanceController())->store($this->storeRequest([
            'student_id' => $studentId,
            'date' => '2026-06-06',
            'status' => 'present',
        ], $user));

        $data = $response->getData(true)['data'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('period_display', $data);
        $this->assertSame('Full Day', $data['period_display']);
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

        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('class_order')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->integer('class_id')->nullable();
            $table->integer('school_class_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->string('status');
            $table->date('status_date')->nullable();
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

        Schema::create('academic_events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('academic_session_id')->nullable();
            $table->string('title');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function storeRequest(array $payload, User $user): Request
    {
        $request = Request::create('/api/v1/attendance', 'POST', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function createUser(string $email = 'auth-user@example.test'): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secret',
        ]);
    }

    private function createSchoolClass(string $name): int
    {
        return DB::table('school_classes')->insertGetId([
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStudent(array $overrides = []): int
    {
        return DB::table('students')->insertGetId(array_merge([
            'name' => 'Student A',
            'class' => 'Class A',
            'class_id' => null,
            'school_class_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createStudentStatus(int $studentId, string $status): int
    {
        return DB::table('student_statuses')->insertGetId([
            'student_id' => $studentId,
            'status' => $status,
            'status_date' => '2026-06-06',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAttendance(array $overrides = []): int
    {
        return DB::table('attendances')->insertGetId(array_merge([
            'student_id' => 1,
            'date' => '2026-06-06',
            'status' => 'present',
            'remarks' => null,
            'period' => null,
            'subject' => null,
            'class' => 'Class A',
            'session' => null,
            'marked_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
