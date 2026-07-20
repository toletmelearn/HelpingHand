<?php

namespace Tests\Feature\API;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SanctumTokenAbilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetMinimalSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_admin_login_receives_mobile_admin_ability(): void
    {
        $user = $this->userWithRole('admin');

        $this->login($user)->assertOk();

        $this->assertLatestTokenAbilities($user, ['mobile:user', 'mobile:admin']);
    }

    public function test_teacher_login_receives_mobile_teacher_ability(): void
    {
        $user = $this->userWithRole('teacher');

        $this->login($user)->assertOk();

        $this->assertLatestTokenAbilities($user, ['mobile:user', 'mobile:teacher']);
    }

    public function test_student_login_receives_mobile_student_ability(): void
    {
        $user = $this->userWithRole('student');

        $this->login($user)->assertOk();

        $this->assertLatestTokenAbilities($user, ['mobile:user', 'mobile:student']);
    }

    public function test_parent_login_receives_mobile_parent_ability(): void
    {
        $user = $this->userWithRole('parent');

        $this->login($user)->assertOk();

        $this->assertLatestTokenAbilities($user, ['mobile:user', 'mobile:parent']);
    }

    public function test_login_without_recognized_role_receives_mobile_user_only(): void
    {
        $user = $this->userWithRole();

        $this->login($user)->assertOk();

        $this->assertSame(['mobile:user'], $user->tokens()->latest('id')->first()->abilities);
    }

    public function test_refresh_token_recomputes_role_abilities(): void
    {
        $user = $this->userWithRole('teacher');

        $loginResponse = $this->login($user)->assertOk();
        $oldToken = $user->tokens()->latest('id')->first();

        $refreshResponse = $this->withHeader(
            'Authorization',
            'Bearer '.$loginResponse->json('data.token')
        )->postJson('/api/v1/refresh-token', [
            'device_name' => 'refresh-device',
        ]);

        $refreshResponse->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldToken->id]);
        $this->assertLatestTokenAbilities($user, ['mobile:user', 'mobile:teacher']);
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

    private function login(User $user)
    {
        return $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'test-device',
        ]);
    }

    private function assertLatestTokenAbilities(User $user, array $expectedAbilities): void
    {
        $abilities = $user->tokens()->latest('id')->first()->abilities;

        foreach ($expectedAbilities as $ability) {
            $this->assertContains($ability, $abilities);
        }
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
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
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
