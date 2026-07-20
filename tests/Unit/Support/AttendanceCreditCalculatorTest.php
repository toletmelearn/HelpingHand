<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use App\Support\Attendance\AttendanceCreditCalculator;

class AttendanceCreditCalculatorTest extends TestCase
{
    public function test_credit_for_present_is_one()
    {
        $this->assertSame(1.0, AttendanceCreditCalculator::creditForStatus('present'));
    }

    public function test_credit_for_late_is_one()
    {
        $this->assertSame(1.0, AttendanceCreditCalculator::creditForStatus('late'));
    }

    public function test_credit_for_half_day_is_half()
    {
        $this->assertSame(0.5, AttendanceCreditCalculator::creditForStatus('half_day'));
    }

    public function test_credit_for_absent_is_zero()
    {
        $this->assertSame(0.0, AttendanceCreditCalculator::creditForStatus('absent'));
    }

    public function test_credit_for_legacy_leave_is_zero()
    {
        $this->assertSame(0.0, AttendanceCreditCalculator::creditForStatus('leave'));
    }

    public function test_unknown_status_is_zero_credit()
    {
        $this->assertSame(0.0, AttendanceCreditCalculator::creditForStatus('unknown'));
    }

    public function test_summarize_counts_all_status_buckets()
    {
        $statuses = ['present','absent','late','half_day','leave','unknown'];
        $sum = AttendanceCreditCalculator::summarize($statuses);

        $this->assertEquals(6, $sum['total_days']);
        $this->assertEquals(1, $sum['present_days']);
        $this->assertEquals(1, $sum['absent_days']);
        $this->assertEquals(1, $sum['late_days']);
        $this->assertEquals(1, $sum['half_days']);
        $this->assertEquals(1, $sum['leave_days']);
    }

    public function test_summarize_calculates_mixed_credit_and_rate()
    {
        $statuses = ['present','late','half_day','absent'];
        $sum = AttendanceCreditCalculator::summarize($statuses);

        // credit = 1 + 1 + 0.5 + 0 = 2.5 ; total = 4 ; rate = (2.5/4)*100 = 62.5
        $this->assertEquals(2.5, $sum['attendance_credit']);
        $this->assertEquals(62.5, $sum['attendance_rate']);
    }

    public function test_summarize_keeps_leave_in_denominator_with_zero_credit()
    {
        $statuses = ['present','leave'];
        $sum = AttendanceCreditCalculator::summarize($statuses);

        // credit = 1 ; total = 2 ; rate = 50
        $this->assertEquals(1.0, $sum['attendance_credit']);
        $this->assertEquals(50.0, $sum['attendance_rate']);
    }

    public function test_summarize_handles_empty_input()
    {
        $sum = AttendanceCreditCalculator::summarize([]);
        $this->assertEquals(0, $sum['total_days']);
        $this->assertEquals(0.0, $sum['attendance_credit']);
        $this->assertEquals(0.0, $sum['attendance_rate']);
    }

    public function test_summarize_records_accepts_array_records()
    {
        $records = [
            ['status' => 'present'],
            ['status' => 'half_day']
        ];

        $sum = AttendanceCreditCalculator::summarizeRecords($records);
        $this->assertEquals(2, $sum['total_days']);
        $this->assertEquals(1.5, $sum['attendance_credit']);
    }

    public function test_summarize_records_accepts_object_records()
    {
        $records = [
            (object)['status' => 'present'],
            (object)['status' => 'late']
        ];

        $sum = AttendanceCreditCalculator::summarizeRecords($records);
        $this->assertEquals(2, $sum['total_days']);
        $this->assertEquals(2.0, $sum['attendance_credit']);
    }
}
