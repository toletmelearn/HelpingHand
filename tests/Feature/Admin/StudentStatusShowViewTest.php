<?php

namespace Tests\Feature\Admin;

use App\Models\StudentStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentStatusShowViewTest extends TestCase
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

    public function test_student_status_show_does_not_reference_missing_current_class(): void
    {
        $view = file_get_contents(resource_path('views/admin/student-statuses/show.blade.php'));

        $this->assertStringNotContainsString('currentClass', $view);
    }

    public function test_student_status_show_displays_canonical_school_class_name(): void
    {
        $this->seedStudentStatus([
            'class' => 'Legacy Class',
            'class_id' => 11,
            'school_class_id' => 8,
            'section_id' => 3,
            'section' => '3',
        ]);

        $html = $this->renderStatusShow();

        $this->assertStringContainsString('Class 8', $html);
        $this->assertStringContainsString('C', $html);
        $this->assertStringNotContainsString('Legacy Class', $html);
    }

    public function test_student_status_show_falls_back_to_legacy_class_string_when_class_fk_missing(): void
    {
        $this->seedStudentStatus([
            'class' => 'Legacy Class Only',
            'class_id' => null,
            'school_class_id' => null,
            'section_id' => null,
            'section' => 'Legacy Section',
        ]);

        $html = $this->renderStatusShow();

        $this->assertStringContainsString('Legacy Class Only', $html);
        $this->assertStringContainsString('Legacy Section', $html);
    }

    public function test_student_status_show_handles_missing_student_or_class_safely_if_feasible(): void
    {
        DB::table('student_statuses')->insert([
            'id' => 1,
            'student_id' => 999,
            'status' => 'passed_out',
            'status_date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->renderStatusShow();

        $this->assertStringContainsString('N/A', $html);
    }

    private function renderStatusShow(): string
    {
        $studentStatus = StudentStatus::with(['student.schoolClass', 'student.section'])->findOrFail(1);

        return view('admin.student-statuses.show', compact('studentStatus'))->render();
    }

    private function seedStudentStatus(array $studentOverrides = []): void
    {
        DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Class 5', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'Class 8', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sections')->insert([
            'id' => 3,
            'name' => 'C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('students')->insert(array_merge([
            'id' => 1,
            'name' => 'Status Student',
            'roll_number' => 'R-1',
            'class' => 'Legacy Class',
            'class_id' => 11,
            'school_class_id' => 11,
            'section_id' => 3,
            'section' => '3',
            'created_at' => now(),
            'updated_at' => now(),
        ], $studentOverrides));

        DB::table('student_statuses')->insert([
            'id' => 1,
            'student_id' => 1,
            'status' => 'passed_out',
            'status_date' => '2026-02-01',
            'reason' => 'Passed out',
            'remarks' => 'Completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

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
    }
}
