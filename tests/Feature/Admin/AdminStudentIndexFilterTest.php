<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminStudentIndexFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();
        $this->withViewErrors([]);
        $this->actingAs($this->createUser());
        $this->seedClassesSectionsAndStudents();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_admin_index_section_filter_uses_section_id(): void
    {
        $response = $this->get('/admin/students?section_id=3');

        $response->assertOk();
        $response->assertSee('Section C Student');
        $response->assertDontSee('Section A Student');
    }

    public function test_admin_index_legacy_numeric_section_filter_still_matches_section_id(): void
    {
        $response = $this->get('/admin/students?section=3');

        $response->assertOk();
        $response->assertSee('Section C Student');
        $response->assertDontSee('Section A Student');
    }

    public function test_admin_index_legacy_section_name_filter_resolves_to_section_id(): void
    {
        $response = $this->get('/admin/students?section=C');

        $response->assertOk();
        $response->assertSee('Section C Student');
        $response->assertDontSee('Section A Student');
    }

    public function test_admin_index_class_id_filter_still_works(): void
    {
        $response = $this->get('/admin/students?class_id=11');

        $response->assertOk();
        $response->assertSee('Class 8 Student');
        $response->assertDontSee('Section A Student');
    }

    public function test_admin_index_view_contains_section_id_filter_select(): void
    {
        $response = $this->get('/admin/students?class_id=8');

        $response->assertOk();
        $response->assertSee('name="section_id"', false);
        $response->assertSee('<option value="3"', false);
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function seedClassesSectionsAndStudents(): void
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

        DB::table('students')->insert([
            $this->studentRow('Section A Student', 8, 1, '1', '111111111111', 1),
            $this->studentRow('Section C Student', 8, 3, '3', '222222222222', 2),
            $this->studentRow('Class 8 Student', 11, 1, '1', '333333333333', 3),
        ]);
    }

    private function studentRow(string $name, int $classId, int $sectionId, string $section, string $aadhar, int $rollNumber): array
    {
        $now = now();

        return [
            'name' => $name,
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => $aadhar,
            'address' => 'Test Address',
            'mobile' => '9876543210',
            'gender' => 'male',
            'category' => 'General',
            'class' => $classId === 11 ? 'Class 8' : 'Class 5',
            'class_id' => $classId,
            'school_class_id' => $classId,
            'section' => $section,
            'section_id' => $sectionId,
            'roll_number' => $rollNumber,
            'created_at' => $now,
            'updated_at' => $now,
        ];
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
            $table->string('admission_no')->nullable();
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
            $table->string('photo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // The index view calls FieldPermissionHelper::canEditField(), which
        // loads the authenticated user's roles() relation -- needed for the
        // view to render at all, even though this test doesn't care about
        // photo permissions specifically.
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
        });

        Schema::create('field_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->string('field_name');
            $table->string('role');
            $table->string('permission_level')->default('editable');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('field_permissions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('users');
    }
}
