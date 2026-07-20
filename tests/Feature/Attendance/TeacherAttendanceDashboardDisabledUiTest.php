<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;

class TeacherAttendanceDashboardDisabledUiTest extends TestCase
{
    public function test_dashboard_shows_teacher_attendance_temporarily_disabled_message(): void
    {
        $html = $this->renderDashboard();

        $this->assertStringContainsString(
            'Teacher attendance marking, updates, reports, and export are temporarily disabled until class/status/schema policy is aligned.',
            $html
        );
    }

    public function test_dashboard_does_not_render_active_mark_attendance_links(): void
    {
        $html = $this->renderDashboard();

        $this->assertStringNotContainsString('/teacher/attendance/mark/', $html);
        $this->assertStringNotContainsString('markAttendance(', $html);
        $this->assertStringContainsString('Mark Attendance Disabled', $html);
    }

    public function test_dashboard_does_not_render_active_reports_link(): void
    {
        $html = $this->renderDashboard();

        $this->assertStringNotContainsString('teacher/attendance/reports', $html);
        $this->assertStringContainsString('Reports Disabled', $html);
    }

    public function test_dashboard_does_not_render_active_export_link(): void
    {
        $html = $this->renderDashboard();

        $this->assertStringNotContainsString('teacher/attendance/export', $html);
        $this->assertStringContainsString('Export Disabled', $html);
    }

    public function test_dashboard_does_not_promise_excel_csv_export(): void
    {
        $html = $this->renderDashboard();

        $this->assertStringNotContainsString('Excel/CSV', $html);
        $this->assertStringNotContainsString('Export attendance records', $html);
        $this->assertStringContainsString('Teacher attendance export is not enabled yet.', $html);
    }

    public function test_dashboard_does_not_render_active_student_detail_links(): void
    {
        $html = $this->renderDashboard();

        $this->assertStringNotContainsString('teacher/attendance/student/', $html);
        $this->assertStringContainsString('Details Disabled', $html);
    }

    private function renderDashboard(): string
    {
        $this->withViewErrors([]);

        return view('teacher.attendance.dashboard', [
            'todaySummary' => [
                'total_students' => 2,
                'present' => 1,
                'absent' => 1,
                'attendance_rate' => 50,
            ],
            'classData' => [
                [
                    'class' => (object) [
                        'id' => 10,
                        'class_name' => 'Class 10',
                    ],
                    'subject' => (object) [
                        'name' => 'Mathematics',
                    ],
                    'summary' => [
                        'attendance_rate' => 50,
                        'present' => 1,
                        'absent' => 1,
                    ],
                ],
            ],
            'lowAttendanceAlerts' => [
                [
                    'student' => (object) [
                        'id' => 1,
                        'name' => 'Student One',
                        'schoolClass' => (object) [
                            'class_name' => 'Class 10',
                        ],
                    ],
                    'attendance_rate' => 55,
                    'absent_days' => 4,
                ],
            ],
        ])->render();
    }
}
