<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminStudentFormCanonicalIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();

        Gate::before(function () {
            return true;
        });

        $this->withViewErrors([]);
        $this->actingAs($this->createUser());
        $this->seedClassesAndSections();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_admin_create_form_contains_class_id_select(): void
    {
        $response = $this->get('/admin/students/create');

        $response->assertOk();
        $response->assertSee('name="class_id"', false);
        $response->assertSee('<option value="11"', false);
        $response->assertSee('Class 8');
    }

    public function test_admin_create_form_contains_section_id_select(): void
    {
        $response = $this->get('/admin/students/create');

        $response->assertOk();
        $response->assertSee('name="section_id"', false);
        $response->assertSee('<option value="3"', false);
        $response->assertSee('C');
    }

    public function test_admin_edit_form_selects_student_canonical_class_id(): void
    {
        // school_class_id is master (see Phase A closure) and wins over a
        // disagreeing legacy class_id -- id 8, not 11, should be selected.
        $studentId = $this->insertStudent([
            'class' => 'Class 8',
            'class_id' => 11,
            'school_class_id' => 8,
            'section' => '3',
            'section_id' => 3,
        ]);

        $content = $this->get("/admin/students/{$studentId}/edit")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="class_id"', $content);
        $this->assertStringContainsString('<option value="8" selected>', $content);
    }

    public function test_admin_edit_form_selects_student_section_id(): void
    {
        $studentId = $this->insertStudent([
            'class' => 'Class 5',
            'class_id' => 8,
            'school_class_id' => 8,
            'section' => '3',
            'section_id' => 3,
        ]);

        $content = $this->get("/admin/students/{$studentId}/edit")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="section_id"', $content);
        $this->assertStringContainsString('<option value="3" selected>', $content);
    }

    private function createUser(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
        ]);

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'admin',
            'display_name' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => 1,
        ]);

        return $user;
    }

    private function seedClassesAndSections(): void
    {
        $now = now();

        DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Class 5', 'class_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Class 8', 'class_order' => 11, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('sections')->insert([
            ['id' => 1, 'name' => 'A', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'C', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertStudent(array $overrides = []): int
    {
        $now = now();

        return DB::table('students')->insertGetId(array_merge([
            'name' => 'Existing Student',
            'father_name' => 'Existing Father',
            'mother_name' => 'Existing Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '111111111111',
            'address' => 'Existing Address',
            'mobile' => '9876543210',
            'gender' => 'male',
            'category' => 'General',
            'roll_number' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
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

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('class_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('aadhar_number')->nullable();
            $table->text('address')->nullable();
            $table->string('mobile')->nullable();
            $table->string('gender')->nullable();
            $table->string('category')->nullable();
            $table->string('class')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->string('section')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->integer('roll_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
