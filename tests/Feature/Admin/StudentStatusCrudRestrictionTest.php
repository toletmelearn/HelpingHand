<?php

namespace Tests\Feature\Admin;

use App\Models\StudentStatus;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentStatusCrudRestrictionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();
        $this->actingAs($this->createUser());
        $this->seedStudent();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_generic_status_store_accepts_active(): void
    {
        $this->post('/admin/student-statuses', $this->statusPayload('active'))
            ->assertRedirect(route('admin.student-statuses.index'));

        $this->assertDatabaseHas('student_statuses', [
            'student_id' => 1,
            'status' => 'active',
        ]);
    }

    public function test_generic_status_store_accepts_inactive(): void
    {
        $this->post('/admin/student-statuses', $this->statusPayload('inactive'))
            ->assertRedirect(route('admin.student-statuses.index'));

        $this->assertDatabaseHas('student_statuses', [
            'student_id' => 1,
            'status' => 'inactive',
        ]);
    }

    public function test_generic_status_store_rejects_passed_out(): void
    {
        $this->post('/admin/student-statuses', $this->statusPayload('passed_out'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('student_statuses', [
            'student_id' => 1,
            'status' => 'passed_out',
        ]);
    }

    public function test_generic_status_store_rejects_left_school(): void
    {
        $this->post('/admin/student-statuses', $this->statusPayload('left_school'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('student_statuses', [
            'student_id' => 1,
            'status' => 'left_school',
        ]);
    }

    public function test_generic_status_store_rejects_tc_issued(): void
    {
        $this->post('/admin/student-statuses', $this->statusPayload('tc_issued'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('student_statuses', [
            'student_id' => 1,
            'status' => 'tc_issued',
        ]);
    }

    public function test_generic_status_update_rejects_terminal_status(): void
    {
        DB::table('student_statuses')->insert([
            'id' => 1,
            'student_id' => 1,
            'status' => 'active',
            'status_date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->put('/admin/student-statuses/1', $this->statusPayload('passed_out'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('student_statuses', [
            'id' => 1,
            'status' => 'active',
        ]);
    }

    public function test_create_form_does_not_show_terminal_status_options(): void
    {
        $view = file_get_contents(resource_path('views/admin/student-statuses/create.blade.php'));

        $this->assertStringContainsString('value="active"', $view);
        $this->assertStringContainsString('value="inactive"', $view);
        $this->assertStringNotContainsString('value="passed_out"', $view);
        $this->assertStringNotContainsString('value="left_school"', $view);
        $this->assertStringNotContainsString('value="tc_issued"', $view);
        $this->assertStringContainsString('require dedicated workflows', $view);
    }

    public function test_edit_form_does_not_show_terminal_status_options(): void
    {
        $view = file_get_contents(resource_path('views/admin/student-statuses/edit.blade.php'));

        $this->assertStringContainsString('value="active"', $view);
        $this->assertStringContainsString('value="inactive"', $view);
        $this->assertStringNotContainsString('value="passed_out"', $view);
        $this->assertStringNotContainsString('value="left_school"', $view);
        $this->assertStringNotContainsString('value="tc_issued"', $view);
        $this->assertStringContainsString('require dedicated workflows', $view);
    }

    public function test_show_view_can_still_display_existing_terminal_status(): void
    {
        DB::table('student_statuses')->insert([
            'id' => 1,
            'student_id' => 1,
            'status' => 'passed_out',
            'status_date' => '2026-02-01',
            'reason' => 'Passed out',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentStatus = StudentStatus::with(['student.schoolClass', 'student.section'])->findOrFail(1);
        $html = view('admin.student-statuses.show', compact('studentStatus'))->render();

        $this->assertStringContainsString('Passed out', $html);
        $this->assertStringContainsString('Status Student', $html);
    }

    private function statusPayload(string $status): array
    {
        return [
            'student_id' => 1,
            'status' => $status,
            'status_date' => '2026-02-01',
            'reason' => 'Manual status update',
            'remarks' => 'Test status',
        ];
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    private function seedStudent(): void
    {
        DB::table('students')->insert([
            'id' => 1,
            'name' => 'Status Student',
            'roll_number' => 'R-1',
            'class' => 'Class 8',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            $table->string('roll_number')->nullable();
            $table->string('class')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->string('section')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('status');
            $table->date('status_date');
            $table->string('reason')->nullable();
            $table->string('remarks')->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_issue_date')->nullable();
            $table->string('issued_by')->nullable();
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
