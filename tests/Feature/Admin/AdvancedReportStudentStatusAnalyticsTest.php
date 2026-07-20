<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AdvancedReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class AdvancedReportStudentStatusAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetMinimalSchema();
        $this->seedStudentAnalyticsData();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_student_analytics_does_not_require_students_status_column(): void
    {
        $this->assertFalse(Schema::hasColumn('students', 'status'));

        $analytics = $this->studentAnalytics();

        $this->assertSame(6, $analytics['total_students']);
    }

    public function test_student_analytics_counts_passed_out_from_student_statuses(): void
    {
        $analytics = $this->studentAnalytics();

        $this->assertSame(1, $analytics['passed_out']);
    }

    public function test_student_analytics_counts_left_school_from_student_statuses(): void
    {
        $analytics = $this->studentAnalytics();

        $this->assertSame(1, $analytics['left_school']);
    }

    public function test_student_analytics_counts_active_students_using_latest_status(): void
    {
        $analytics = $this->studentAnalytics();

        $this->assertSame(2, $analytics['active_students']);
    }

    public function test_student_analytics_uses_independent_queries_for_metrics(): void
    {
        $analytics = $this->studentAnalytics();

        $this->assertSame(6, $analytics['total_students']);
        $this->assertSame(4, $analytics['new_admissions']);
        $this->assertSame(1, $analytics['passed_out']);
        $this->assertSame(1, $analytics['left_school']);
        $this->assertSame(2, $analytics['active_students']);
    }

    private function studentAnalytics(): array
    {
        $method = new ReflectionMethod(AdvancedReportController::class, 'getStudentAnalytics');
        $method->setAccessible(true);

        return $method->invoke(
            new AdvancedReportController(),
            null,
            null,
            null,
            [Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31')]
        );
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
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

    private function seedStudentAnalyticsData(): void
    {
        DB::table('students')->insert([
            $this->studentRow(1, 'No Status Student', '2026-01-10 08:00:00'),
            $this->studentRow(2, 'Passed Out Student', '2025-12-10 08:00:00'),
            $this->studentRow(3, 'Left School Student', '2025-12-15 08:00:00'),
            $this->studentRow(4, 'Latest Active Student', '2026-01-12 08:00:00'),
            $this->studentRow(5, 'Inactive Student', '2026-01-13 08:00:00'),
            $this->studentRow(6, 'TC Issued Student', '2026-01-14 08:00:00'),
        ]);

        DB::table('student_statuses')->insert([
            $this->statusRow(1, 2, 'passed_out', '2026-02-01'),
            $this->statusRow(2, 3, 'left_school', '2026-02-01'),
            $this->statusRow(3, 4, 'passed_out', '2026-02-01'),
            $this->statusRow(4, 4, 'active', '2026-02-02'),
            $this->statusRow(5, 5, 'inactive', '2026-02-01'),
            $this->statusRow(6, 6, 'tc_issued', '2026-02-01'),
        ]);
    }

    private function studentRow(int $id, string $name, string $createdAt): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'academic_session_id' => 1,
            'class_id' => 10,
            'section_id' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ];
    }

    private function statusRow(int $id, int $studentId, string $status, string $statusDate): array
    {
        return [
            'id' => $id,
            'student_id' => $studentId,
            'status' => $status,
            'status_date' => $statusDate,
            'created_at' => $statusDate . ' 08:00:00',
            'updated_at' => $statusDate . ' 08:00:00',
        ];
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('student_statuses');
        Schema::dropIfExists('students');
    }
}
