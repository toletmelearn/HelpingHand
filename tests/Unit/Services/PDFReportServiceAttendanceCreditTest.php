<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PDFReportService;
use App\Models\Attendance;
use App\Support\Attendance\AttendanceCreditCalculator;

class PDFReportServiceAttendanceCreditTest extends TestCase
{
    private function callPrivateCalculateStats($attendances)
    {
        $service = new PDFReportService();
        $reflector = new \ReflectionClass(PDFReportService::class);
        $method = $reflector->getMethod('calculateAttendanceStats');
        $method->setAccessible(true);
        return $method->invokeArgs($service, [$attendances]);
    }

    public function test_pdf_attendance_stats_use_credit_policy_for_late_and_half_day()
    {
        // Create a collection of fake attendance objects
        $attendances = collect([
            (object) ['status' => 'present'],
            (object) ['status' => 'late'],
            (object) ['status' => 'half_day'],
        ]);

        $stats = $this->callPrivateCalculateStats($attendances);

        // present (1.0) + late (1.0) + half_day (0.5) = 2.5 credit
        // total = 3 => rate = 83.33%
        $this->assertEquals(83.33, $stats['attendance_percentage']);
        $this->assertEquals(83.33, $stats['attendance_rate']);
    }

    public function test_pdf_attendance_stats_preserve_existing_keys()
    {
        $attendances = collect([
            (object) ['status' => 'present'],
        ]);

        $stats = $this->callPrivateCalculateStats($attendances);

        $this->assertArrayHasKey('total_days', $stats);
        $this->assertArrayHasKey('present_days', $stats);
        $this->assertArrayHasKey('absent_days', $stats);
        $this->assertArrayHasKey('late_days', $stats);
        $this->assertArrayHasKey('attendance_percentage', $stats);
    }

    public function test_pdf_attendance_stats_keep_leave_zero_credit()
    {
        $attendances = collect([
            (object) ['status' => 'present'],
            (object) ['status' => 'leave'],
        ]);

        $stats = $this->callPrivateCalculateStats($attendances);

        // present (1.0) + leave (0.0) = 1.0 credit
        // total = 2 => rate = 50.0%
        $this->assertEquals(50.0, $stats['attendance_percentage']);
    }

    public function test_pdf_attendance_stats_add_credit_fields_if_added()
    {
        $attendances = collect([
            (object) ['status' => 'present'],
        ]);

        $stats = $this->callPrivateCalculateStats($attendances);

        $this->assertArrayHasKey('attendance_credit', $stats);
        $this->assertArrayHasKey('half_days', $stats);
        $this->assertArrayHasKey('leave_days', $stats);
        $this->assertArrayHasKey('attendance_rate', $stats);
    }

    public function test_attendance_credit_calculator_tests_still_pass()
    {
        // Simple assertion calling calculator directly to verify sanity
        $statuses = ['present', 'late', 'half_day', 'absent', 'leave'];
        $summary = AttendanceCreditCalculator::summarize($statuses);

        $this->assertEquals(5, $summary['total_days']);
        $this->assertEquals(2.5, $summary['attendance_credit']);
        $this->assertEquals(50.0, $summary['attendance_rate']);
    }
}
