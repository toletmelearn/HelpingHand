<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;

use Illuminate\Support\ViewErrorBag;

class AttendanceCreditPolicyDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        view()->share('errors', new ViewErrorBag());
    }
    /** @test */
    public function teacher_dashboard_shows_attendance_credit_policy_label()
    {
        $data = [
            'todaySummary' => ['attendance_rate' => 95, 'total_students' => 20, 'present' => 19, 'absent' => 1],
            'classData' => [],
            'lowAttendanceAlerts' => [],
        ];

        $html = view('teacher.attendance.dashboard', $data)->render();

        $this->assertStringContainsString('Attendance credit policy', $html);
    }

    /** @test */
    public function teacher_dashboard_shows_late_half_day_and_credit_when_present()
    {
        $data = [
            'todaySummary' => ['attendance_rate' => 88],
            'classData' => [
                [
                    'class' => (object)['class_name' => 'Class 1'],
                    'subject' => (object)['name' => 'Math'],
                    'summary' => [
                        'attendance_rate' => 88,
                        'present' => 22,
                        'absent' => 3,
                        'late_days' => 2,
                        'half_days' => 1,
                        'attendance_credit' => 22.5,
                        'leave_days' => 0,
                    ],
                ],
            ],
            'lowAttendanceAlerts' => [],
        ];

        $html = view('teacher.attendance.dashboard', $data)->render();

        $this->assertStringContainsString('Late Days: 2', $html);
        $this->assertStringContainsString('Half Days: 1', $html);
        $this->assertStringContainsString('Attendance Credit: 22.5', $html);
    }

    /** @test */
    public function teacher_dashboard_does_not_break_when_new_credit_keys_are_missing()
    {
        $data = [
            'todaySummary' => ['attendance_rate' => 50],
            'classData' => [
                [
                    'class' => (object)['class_name' => 'Class 2'],
                    'subject' => (object)['name' => 'Science'],
                    'summary' => [
                        'attendance_rate' => 50,
                        'present' => 5,
                        'absent' => 5,
                        // no late/half/credit keys
                    ],
                ],
            ],
            'lowAttendanceAlerts' => [],
        ];

        $html = view('teacher.attendance.dashboard', $data)->render();

        $this->assertStringContainsString('Late Days: 0', $html);
        $this->assertStringContainsString('Half Days: 0', $html);
        $this->assertStringContainsString('Attendance Credit: N/A', $html);
    }

    /** @test */
    public function teacher_dashboard_disabled_message_still_visible()
    {
        $data = [
            'todaySummary' => [],
            'classData' => [
                [
                    'class' => (object)['class_name' => 'Class A'],
                    'subject' => (object)['name' => 'Math'],
                    'summary' => ['present' => 0, 'absent' => 0, 'attendance_rate' => 0],
                ],
            ],
            'lowAttendanceAlerts' => [],
        ];

        $html = view('teacher.attendance.dashboard', $data)->render();

        $this->assertStringContainsString('Teacher attendance marking, updates, reports, and export are temporarily disabled', $html);
        $this->assertStringContainsString('Mark Attendance Disabled', $html);
        $this->assertStringContainsString('Reports Disabled', $html);
    }
}
