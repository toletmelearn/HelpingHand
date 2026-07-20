<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TerminalStatusReconciliationReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_admin_reconciliation_report_route_is_get_only(): void
    {
        $route = Route::getRoutes()->getByName('admin.reports.terminal-status-reconciliation');

        $this->assertNotNull($route);
        $this->assertSame(
            ['GET', 'HEAD'],
            array_values(array_intersect($route->methods(), ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE']))
        );
    }

    public function test_admin_reconciliation_report_page_renders_summary_counts(): void
    {
        $this->seedStudent(6, [
            'class_id' => 1,
            'school_class_id' => 1,
            'class' => 'Nursery',
            'section_id' => 1,
            'section' => '1',
        ]);
        $this->seedStatus(6, 'passed_out');
        $this->seedStudent(301, [
            'class_id' => 11,
            'school_class_id' => 8,
            'class' => 'Class 8',
        ]);

        $this->get(route('admin.reports.terminal-status-reconciliation'))
            ->assertOk()
            ->assertSee('Terminal Status Reconciliation')
            ->assertSee('Terminal Drift')
            ->assertSee('Passed Out Missing Log')
            ->assertSee('Class FK Conflicts')
            ->assertSee('Read-only report.');
    }

    public function test_admin_reconciliation_report_displays_detected_student_ids(): void
    {
        $this->seedStudent(6, [
            'name' => 'Terminal Drift Student',
            'class_id' => 1,
            'school_class_id' => 1,
            'class' => 'Nursery',
            'section_id' => 1,
            'section' => '1',
        ]);
        $this->seedStatus(6, 'passed_out');
        $this->seedStudent(301, [
            'name' => 'Class Conflict Student',
            'class_id' => 11,
            'school_class_id' => 8,
            'class' => 'Class 8',
        ]);

        $this->get(route('admin.reports.terminal-status-reconciliation'))
            ->assertOk()
            ->assertSee('Terminal Drift Student')
            ->assertSee('Class Conflict Student')
            ->assertSee('passed_out')
            ->assertSee('class_fk_conflict');
    }

    public function test_admin_reconciliation_report_does_not_render_repair_or_apply_controls(): void
    {
        $this->seedStudent(6, [
            'class_id' => 1,
            'school_class_id' => 1,
            'class' => 'Nursery',
            'section_id' => 1,
            'section' => '1',
        ]);
        $this->seedStatus(6, 'passed_out');

        $html = $this->get(route('admin.reports.terminal-status-reconciliation'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('method="POST"', $html);
        $this->assertStringNotContainsString('Apply', $html);
        $this->assertStringNotContainsString('Delete', $html);
    }

    public function test_admin_reconciliation_report_uses_detector_service_data(): void
    {
        $this->seedStudent(6, [
            'name' => 'Detector Student',
            'class_id' => 1,
            'school_class_id' => 1,
            'class' => 'Nursery',
            'section_id' => 1,
            'section' => '1',
        ]);
        $this->seedStatus(6, 'passed_out');

        $this->get(route('admin.reports.terminal-status-reconciliation'))
            ->assertOk()
            ->assertSee('Detector Student')
            ->assertSee('Review terminal status cleanup; possible Phase 3M-style cleanup needed.')
            ->assertSee('Review missing Passed Out promotion log; do not auto-create without admin confirmation.');
    }

    private function seedStudent(int $id, array $overrides = []): void
    {
        DB::table('students')->insert(array_merge([
            'id' => $id,
            'name' => 'Student ' . $id,
            'class_id' => null,
            'school_class_id' => null,
            'class' => null,
            'section_id' => null,
            'section' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function seedStatus(int $studentId, string $status): void
    {
        DB::table('student_statuses')->insert([
            'student_id' => $studentId,
            'status' => $status,
            'status_date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->string('class')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('section')->nullable();
            $table->timestamps();
        });

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('status');
            $table->date('status_date')->nullable();
            $table->timestamps();
        });

        Schema::create('student_promotion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('from_class')->nullable();
            $table->string('to_class')->nullable();
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('student_promotion_logs');
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
    }
}
