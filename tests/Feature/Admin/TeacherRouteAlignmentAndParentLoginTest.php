<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Teacher;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;

class TeacherRouteAlignmentAndParentLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['session.driver' => 'array']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        $schema->create('users', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('roles', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->timestamps();
        });

        $schema->create('role_user', function ($table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        $schema->create('students', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('admission_no')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('roll_number')->nullable();
            $table->string('gender')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('parents', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('admission_number')->nullable();
            $table->string('password')->nullable();
            $table->boolean('must_reset_password')->default(true);
            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('status')->default('active');
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('teacher_logins', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $schema->create('audit_logs', function ($table) {
            $table->bigIncrements('id');
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

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('audit_logs');
        Schema::connection('sqlite')->dropIfExists('teacher_logins');
        Schema::connection('sqlite')->dropIfExists('parents');
        Schema::connection('sqlite')->dropIfExists('students');
        Schema::connection('sqlite')->dropIfExists('role_user');
        Schema::connection('sqlite')->dropIfExists('roles');
        Schema::connection('sqlite')->dropIfExists('users');

        parent::tearDown();
    }

    /** @test */
    public function test_admin_facing_teacher_links_use_admin_namespace(): void
    {
        $indexView = file_get_contents(resource_path('views/teachers/index.blade.php'));
        $this->assertStringContainsString("route('admin.teachers.show'", $indexView);
        $this->assertStringContainsString("route('admin.teachers.edit'", $indexView);
        $this->assertStringContainsString("route('admin.teachers.destroy'", $indexView);
        $this->assertStringContainsString("route('admin.teachers.create'", $indexView);
        $this->assertStringNotContainsString("route('teachers.show'", $indexView);

        $editView = file_get_contents(resource_path('views/teachers/edit.blade.php'));
        $this->assertStringContainsString("route('admin.teachers.update'", $editView);
        $this->assertStringContainsString("route('admin.teachers.destroy'", $editView);
        $this->assertStringContainsString("route(\"admin.teachers.toggle-exam-head\"", $editView);
        $this->assertStringNotContainsString("route('teachers.update'", $editView);

        $createView = file_get_contents(resource_path('views/teachers/create.blade.php'));
        $this->assertStringContainsString("route('admin.teachers.store'", $createView);
        $this->assertStringNotContainsString("route('teachers.store'", $createView);
    }

    /** @test */
    public function test_parent_can_login_via_phone_and_password(): void
    {
        $this->withoutExceptionHandling();
        $parent = ParentModel::create([
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'phone' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/parent/login', [
            'login' => '1234567890',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/parent/dashboard');
        $this->assertAuthenticatedAs($parent, 'parent');
    }

    /** @test */
    public function test_parent_can_login_via_student_admission_number(): void
    {
        $student = Student::create([
            'name' => 'Student Name',
            'email' => 'student@example.com',
            'admission_no' => 'ADM-101',
            'class_id' => 1,
            'roll_number' => '1',
            'gender' => 'male',
        ]);

        $parent = ParentModel::where('student_id', $student->id)->first();
        if ($parent) {
            $parent->update([
                'phone' => '1234567890',
                'password' => Hash::make('password123'),
            ]);
        } else {
            $parent = ParentModel::create([
                'name' => 'Parent Name',
                'email' => 'parent@example.com',
                'phone' => '1234567890',
                'password' => Hash::make('password123'),
                'student_id' => $student->id,
            ]);
        }

        $response = $this->post('/parent/login', [
            'login' => 'ADM-101',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/parent/dashboard');
        $this->assertAuthenticatedAs($parent, 'parent');
    }

    /** @test */
    public function test_central_login_allows_parent_via_phone(): void
    {
        $parent = ParentModel::create([
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'phone' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => '1234567890',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/parent/dashboard');
        $this->assertAuthenticatedAs($parent, 'parent');
    }

    /** @test */
    public function test_parent_can_login_via_student_mobile(): void
    {
        $student = Student::create([
            'name' => 'Student Name',
            'email' => 'student@example.com',
            'mobile' => '9876543210',
            'class_id' => 1,
            'roll_number' => '1',
            'gender' => 'male',
        ]);

        $parent = ParentModel::where('student_id', $student->id)->first();
        if ($parent) {
            $parent->update([
                'password' => Hash::make('password123'),
            ]);
        } else {
            $parent = ParentModel::create([
                'name' => 'Parent Name',
                'email' => 'parent@example.com',
                'password' => Hash::make('password123'),
                'student_id' => $student->id,
            ]);
        }

        $response = $this->post('/parent/login', [
            'login' => '9876543210',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/parent/dashboard');
        $this->assertAuthenticatedAs($parent, 'parent');
    }

    /** @test */
    public function test_parent_can_login_via_student_phone(): void
    {
        $student = Student::create([
            'name' => 'Student Name',
            'email' => 'student@example.com',
            'phone' => '9876543210',
            'class_id' => 1,
            'roll_number' => '1',
            'gender' => 'male',
        ]);

        $parent = ParentModel::where('student_id', $student->id)->first();
        if ($parent) {
            $parent->update([
                'password' => Hash::make('password123'),
            ]);
        } else {
            $parent = ParentModel::create([
                'name' => 'Parent Name',
                'email' => 'parent@example.com',
                'password' => Hash::make('password123'),
                'student_id' => $student->id,
            ]);
        }

        $response = $this->post('/parent/login', [
            'login' => '9876543210',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/parent/dashboard');
        $this->assertAuthenticatedAs($parent, 'parent');
    }

    /** @test */
    public function test_parent_login_fails_with_invalid_mobile(): void
    {
        $response = $this->post('/parent/login', [
            'login' => '9999999999',
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error', 'Invalid mobile/admission');
        $this->assertGuest('parent');
    }

    /** @test */
    public function test_parent_login_fails_with_wrong_password(): void
    {
        $parent = ParentModel::create([
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'phone' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/parent/login', [
            'login' => '1234567890',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHas('error', 'Wrong password');
        $this->assertGuest('parent');
    }

    /** @test */
    public function test_central_login_parent_fails_with_wrong_password(): void
    {
        $parent = ParentModel::create([
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'phone' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => '1234567890',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest('parent');
    }
}
