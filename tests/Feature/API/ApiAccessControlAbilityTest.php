<?php

namespace Tests\Feature\API;

use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiAccessControlAbilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('App\Models\Notification')) {
            class_alias(\Illuminate\Notifications\DatabaseNotification::class, 'App\Models\Notification');
        }

        $this->resetMinimalSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_token_without_mobile_user_cannot_access_me(): void
    {
        $user = $this->userWithRole();
        $token = $user->createToken('test-token', [])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson('/api/v1/me')
            ->assertForbidden();
    }

    public function test_token_with_mobile_user_can_access_me(): void
    {
        $user = $this->userWithRole();
        $token = $user->createToken('test-token', ['mobile:user'])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_student_token_with_mobile_student_can_access_own_student_dashboard(): void
    {
        $user = $this->userWithRole('student');
        $this->createStudentFor($user);
        $token = $user->createToken('test-token', ['mobile:user', 'mobile:student'])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/student')
            ->assertOk();
    }

    public function test_student_token_without_mobile_student_cannot_access_student_dashboard(): void
    {
        $user = $this->userWithRole('student');
        $this->createStudentFor($user);
        $token = $user->createToken('test-token', ['mobile:user'])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/student')
            ->assertForbidden();
    }

    public function test_teacher_token_with_mobile_teacher_can_access_own_teacher_route(): void
    {
        $user = $this->userWithRole('teacher');
        $teacher = $this->createTeacherFor($user);
        $token = $user->createToken('test-token', ['mobile:user', 'mobile:teacher'])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson("/api/v1/teachers/{$teacher->id}/classes")
            ->assertOk();
    }

    public function test_teacher_token_without_mobile_teacher_cannot_access_teacher_route(): void
    {
        $user = $this->userWithRole('teacher');
        $teacher = $this->createTeacherFor($user);
        $token = $user->createToken('test-token', ['mobile:user'])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson("/api/v1/teachers/{$teacher->id}/classes")
            ->assertForbidden();
    }

    public function test_admin_role_without_mobile_admin_cannot_bypass(): void
    {
        $user = $this->userWithRole('admin');
        $token = $user->createToken('test-token', ['mobile:user'])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson('/api/v1/students')
            ->assertForbidden();
    }

    public function test_admin_role_with_mobile_admin_can_reach_admin_allowed_route(): void
    {
        $user = $this->userWithRole('admin');
        $token = $user->createToken('test-token', ['mobile:user', 'mobile:admin'])->plainTextToken;

        $this->withBearerToken($token)
            ->getJson('/api/v1/students')
            ->assertOk();
    }

    public function test_old_unscoped_token_can_call_refresh_token_for_transition(): void
    {
        $user = $this->userWithRole();
        $token = $user->createToken('old-token', [])->plainTextToken;

        $this->withBearerToken($token)
            ->postJson('/api/v1/refresh-token')
            ->assertOk();
    }

    public function test_old_unscoped_token_can_call_logout_for_transition(): void
    {
        $user = $this->userWithRole();
        $token = $user->createToken('old-token', [])->plainTextToken;

        $this->withBearerToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk();
    }

    private function userWithRole(?string $roleName = null): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => uniqid('user_', true).'@example.test',
            'password' => Hash::make('password123'),
        ]);

        if (!$roleName) {
            return $user;
        }

        $role = Role::firstOrCreate(
            ['name' => $roleName],
            [
                'display_name' => ucfirst(str_replace(['-', '_'], ' ', $roleName)),
                'description' => 'Test '.$roleName.' role',
            ]
        );

        $user->roles()->attach($role->id);

        return $user;
    }

    private function createStudentFor(User $user): Student
    {
        return Student::create([
            'name' => 'Test Student',
            'user_id' => $user->id,
            'roll_number' => 1,
        ]);
    }

    private function createTeacherFor(User $user): Teacher
    {
        $teacher = new Teacher();
        $teacher->forceFill([
            'name' => 'Test Teacher',
            'email' => uniqid('teacher_', true).'@example.test',
            'password' => 'password123',
            'user_id' => $user->id,
            'designation' => 'Teacher',
        ]);
        $teacher->save();

        return $teacher;
    }

    private function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('preferred_language')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->integer('roll_number')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('designation')->nullable();
            $table->string('wing')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('name')->nullable();
            $table->date('date')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->integer('total_marks')->nullable();
            $table->timestamps();
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->integer('marks_obtained')->nullable();
            $table->integer('total_marks')->nullable();
            $table->string('grade')->nullable();
            $table->timestamps();
        });

        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('topic')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->nullable();
            $table->morphs('notifiable');
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('class_management', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('class_teacher', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('class_id');
        });

        Schema::create('exam_papers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('exam_papers');
        Schema::dropIfExists('class_teacher');
        Schema::dropIfExists('class_management');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('lesson_plans');
        Schema::dropIfExists('results');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('fees');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('students');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
