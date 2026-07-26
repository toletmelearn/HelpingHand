<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentPassedOutStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();
        $this->actingAs($this->createUser());
        $this->seedPassedOutData();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_passed_out_creates_student_status_record(): void
    {
        $this->post('/admin/student-promotions/student/1/passed-out', [
            'remarks' => 'Completed final class',
        ])->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseHas('student_statuses', [
            'student_id' => 1,
            'status' => 'passed_out',
            'reason' => 'Passed out',
            'remarks' => 'Completed final class',
            'issued_by' => '1',
        ]);
    }

    public function test_passed_out_does_not_require_students_status_column(): void
    {
        $this->assertFalse(Schema::hasColumn('students', 'status'));

        $this->post('/admin/student-promotions/student/1/passed-out')
            ->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseHas('student_statuses', [
            'student_id' => 1,
            'status' => 'passed_out',
        ]);
    }

    public function test_passed_out_clears_class_and_section_compatibility_fields(): void
    {
        $this->post('/admin/student-promotions/student/1/passed-out')
            ->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseHas('students', [
            'id' => 1,
            'class_id' => null,
            'school_class_id' => null,
            'class' => 'Passed Out',
            'section_id' => null,
            'section' => null,
        ]);
    }

    public function test_passed_out_promotion_log_uses_original_class_label(): void
    {
        $this->post('/admin/student-promotions/student/1/passed-out', [
            'remarks' => 'Graduated',
        ])->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseHas('student_promotion_logs', [
            'student_id' => 1,
            'academic_session_id' => 1,
            'from_class' => 'Class 8',
            'to_class' => 'Passed Out',
            'promoted_by' => 1,
            'remarks' => 'Graduated',
        ]);
    }

    public function test_passed_out_operation_is_transaction_wrapped_for_happy_path(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/StudentPromotionController.php'));

        $this->assertStringContainsString('function markAsPassedOut', $controller);
        $this->assertStringContainsString('DB::transaction(function ()', $controller);

        $this->post('/admin/student-promotions/student/1/passed-out')
            ->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseCount('student_statuses', 1);
        $this->assertDatabaseCount('student_promotion_logs', 1);
    }

    private function createUser(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
        ]);

        // StudentPromotionController now authorizes every action via
        // StudentPromotionPolicy (admin-only), so this test's acting user
        // needs the admin role to exercise the pass-out logic itself.
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $roleId]);

        return $user;
    }

    private function seedPassedOutData(): void
    {
        $now = now();

        DB::table('academic_sessions')->insert([
            'id' => 1,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('school_classes')->insert([
            'id' => 11,
            'name' => 'Class 8',
            'class_order' => 11,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('students')->insert([
            'id' => 1,
            'name' => 'Passed Out Student',
            'class' => 'Class 8',
            'class_id' => 11,
            'school_class_id' => 11,
            'section' => '3',
            'section_id' => 3,
            'roll_number' => 12,
            'created_at' => $now,
            'updated_at' => $now,
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

        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('class_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->string('section')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->integer('roll_number')->nullable();
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

        Schema::create('student_promotion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('from_class');
            $table->string('to_class');
            $table->unsignedBigInteger('promoted_by')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->text('remarks')->nullable();
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
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('student_promotion_logs');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('academic_sessions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
