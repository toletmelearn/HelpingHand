<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentPromotionNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();
        $this->actingAs($this->createUser());
        $this->seedPromotionData();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_promotion_sets_class_id_school_class_id_and_class_name(): void
    {
        $this->post('/admin/student-promotions', $this->promotionPayload())
            ->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseHas('students', [
            'id' => 1,
            'class_id' => 11,
            'school_class_id' => 11,
            'class' => 'Class 8',
        ]);
    }

    public function test_promotion_preserves_section_id_and_legacy_section(): void
    {
        $this->post('/admin/student-promotions', $this->promotionPayload())
            ->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseHas('students', [
            'id' => 1,
            'section_id' => 3,
            'section' => '3',
        ]);
    }

    public function test_promotion_creates_promotion_log(): void
    {
        $this->post('/admin/student-promotions', $this->promotionPayload([
            'remarks' => 'Promoted after annual exam',
        ]))->assertRedirect(route('admin.student-promotions.index'));

        $this->assertDatabaseHas('student_promotion_logs', [
            'student_id' => 1,
            'academic_session_id' => 1,
            'from_class' => 'Class 5',
            'to_class' => 'Class 8',
            'remarks' => 'Promoted after annual exam',
        ]);
    }

    public function test_promotion_rejects_destination_class_not_higher_than_source(): void
    {
        $this->post('/admin/student-promotions', $this->promotionPayload([
            'from_class' => 11,
            'to_class' => 8,
        ]))->assertSessionHasErrors('to_class');

        $this->assertDatabaseHas('students', [
            'id' => 1,
            'class_id' => 8,
            'school_class_id' => 8,
            'class' => 'Class 5',
        ]);

        $this->assertDatabaseCount('student_promotion_logs', 0);
    }

    public function test_promotion_does_not_touch_passed_out_flow(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/StudentPromotionController.php'));

        $this->assertStringContainsString('function store', $controller);
        $this->assertStringContainsString('function markAsPassedOut', $controller);
        $this->assertStringContainsString('StudentStatus::create', $controller);
        $this->assertStringNotContainsString("'status' => 'passed_out',\n            'class_id' => null", $controller);
    }

    private function promotionPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => 1,
            'from_class' => 8,
            'to_class' => 11,
            'students' => [1],
            'remarks' => null,
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

    private function seedPromotionData(): void
    {
        $now = now();

        DB::table('academic_sessions')->insert([
            'id' => 1,
            'name' => '2026-2027',
            'code' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Class 5', 'class_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Class 8', 'class_order' => 11, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('students')->insert([
            'id' => 1,
            'name' => 'Promotion Student',
            'class' => 'Class 5',
            'class_id' => 8,
            'school_class_id' => 8,
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
            $table->string('code')->nullable();
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
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('academic_sessions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
