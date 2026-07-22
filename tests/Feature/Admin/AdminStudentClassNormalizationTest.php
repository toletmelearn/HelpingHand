<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminStudentClassNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();

        Gate::before(function () {
            return true;
        });

        $this->actingAs($this->createUser());
        $this->seedClassesAndSections();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_admin_store_with_class_id_sets_class_id_school_class_id_and_class_name(): void
    {
        $this->post('/admin/students', $this->studentPayload([
            'class_id' => 11,
            'class' => null,
        ]))->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('students', [
            'name' => 'Test Student',
            'class_id' => 11,
            'school_class_id' => 11,
            'class' => 'Class 8',
        ]);
    }

    public function test_admin_store_with_class_string_resolves_school_class(): void
    {
        $this->post('/admin/students', $this->studentPayload([
            'class' => 'Class 5',
        ]))->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('students', [
            'name' => 'Test Student',
            'class_id' => 8,
            'school_class_id' => 8,
            'class' => 'Class 5',
        ]);
    }

    public function test_admin_store_with_section_id_sets_section_id_and_section_name(): void
    {
        $this->post('/admin/students', $this->studentPayload([
            'class' => 'Class 5',
            'section_id' => 2,
            'section' => null,
        ]))->assertRedirect(route('admin.students.index'));

        // section must be the section's *name* ("B"), not its numeric id --
        // every view that prints the raw `section` column expects a letter,
        // not "2".
        $this->assertDatabaseHas('students', [
            'name' => 'Test Student',
            'section_id' => 2,
            'section' => 'B',
        ]);
    }

    public function test_admin_update_keeps_class_fields_consistent(): void
    {
        $studentId = $this->insertStudent([
            'name' => 'Existing Student',
            'class' => 'Class 5',
            'class_id' => 8,
            'school_class_id' => 8,
            'section' => '1',
            'section_id' => 1,
            'aadhaar_number' => '111111111111',
            'roll_number' => 10,
        ]);

        $this->put("/admin/students/{$studentId}", $this->studentPayload([
            'name' => 'Existing Student',
            'aadhaar_number' => '111111111111',
            'roll_number' => 10,
            'class_id' => 11,
            'class' => null,
            'section_id' => 3,
            'section' => null,
        ]))->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'class_id' => 11,
            'school_class_id' => 11,
            'class' => 'Class 8',
            'section_id' => 3,
            'section' => 'C',
        ]);
    }

    public function test_unresolved_legacy_class_string_does_not_crash_and_preserves_string(): void
    {
        $this->post('/admin/students', $this->studentPayload([
            'class' => 'Legacy Class X',
        ]))->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('students', [
            'name' => 'Test Student',
            'class' => 'Legacy Class X',
            'class_id' => null,
            'school_class_id' => null,
        ]);
    }

    public function test_unresolved_legacy_section_string_does_not_crash_and_preserves_string(): void
    {
        $this->post('/admin/students', $this->studentPayload([
            'class' => 'Class 5',
            'section' => 'Legacy Z',
        ]))->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('students', [
            'name' => 'Test Student',
            'section' => 'Legacy Z',
            'section_id' => null,
        ]);
    }

    public function test_student_relationships_resolve_without_conflict_with_string_attributes(): void
    {
        $studentId = $this->insertStudent([
            'name' => 'Relation Test Student',
            'class' => 'Class 5',
            'class_id' => 8,
            'school_class_id' => 8,
            'section' => 'A',
            'section_id' => 1,
        ]);

        $student = \App\Models\Student::find($studentId);

        // Verify that the string attributes return strings
        $this->assertEquals('Class 5', $student->class);
        $this->assertEquals('A', $student->section);

        // Verify that the conflict-free relation methods resolve model properties correctly
        $this->assertNotNull($student->schoolClass);
        $this->assertEquals('Class 5', $student->schoolClass->name);

        $this->assertNotNull($student->schoolSection);
        $this->assertEquals('A', $student->schoolSection->name);
    }


    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '123456789012',
            'address' => 'Test Address',
            'mobile' => '9876543210',
            'gender' => 'male',
            'category' => 'General',
            'class' => 'Class 5',
            'section' => '1',
            'roll_number' => 1,
            'religion' => 'Test',
            'caste' => 'Test',
            'blood_group' => 'O+',
        ], $overrides);
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function seedClassesAndSections(): void
    {
        $now = now();

        \DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Class 5', 'class_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Class 8', 'class_order' => 11, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        \DB::table('sections')->insert([
            ['id' => 1, 'name' => 'A', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'B', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'C', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertStudent(array $attributes): int
    {
        $now = now();

        return \DB::table('students')->insertGetId(array_merge([
            'father_name' => 'Existing Father',
            'mother_name' => 'Existing Mother',
            'date_of_birth' => '2010-01-01',
            'address' => 'Existing Address',
            'mobile' => '9876543210',
            'gender' => 'male',
            'category' => 'General',
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
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
            $table->string('aadhaar_number')->nullable();
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
            $table->string('religion')->nullable();
            $table->string('caste')->nullable();
            $table->string('blood_group')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
