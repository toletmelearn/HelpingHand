<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\AttendanceController;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AttendanceCsvPeriodDisplayExportTest extends TestCase
{
    public function test_csv_export_keeps_raw_period_column(): void
    {
        $rows = $this->csvRowsFor([
            $this->attendanceWithPeriod(null),
        ]);

        $this->assertSame('Period', $rows[0][6]);
        $this->assertSame('', $rows[1][6]);
    }

    public function test_csv_export_adds_period_display_column_after_period(): void
    {
        $rows = $this->csvRowsFor([
            $this->attendanceWithPeriod('Period 1'),
        ]);

        $this->assertSame('Period', $rows[0][6]);
        $this->assertSame('Period Display', $rows[0][7]);
        $this->assertSame('Remarks', $rows[0][8]);
    }

    public function test_csv_export_displays_null_period_as_full_day_in_display_column(): void
    {
        $rows = $this->csvRowsFor([
            $this->attendanceWithPeriod(null),
        ]);

        $this->assertSame('', $rows[1][6]);
        $this->assertSame('Full Day', $rows[1][7]);
    }

    public function test_csv_export_preserves_literal_full_day_raw_period(): void
    {
        $rows = $this->csvRowsFor([
            $this->attendanceWithPeriod('Full Day'),
        ]);

        $this->assertSame('Full Day', $rows[1][6]);
        $this->assertSame('Full Day', $rows[1][7]);
    }

    public function test_csv_export_displays_named_period_trimmed(): void
    {
        $rows = $this->csvRowsFor([
            $this->attendanceWithPeriod(' Period 1 '),
        ]);

        $this->assertSame(' Period 1 ', $rows[1][6]);
        $this->assertSame('Period 1', $rows[1][7]);
    }

    public function test_csv_export_does_not_change_existing_route_or_filters(): void
    {
        $route = Route::getRoutes()->getByName('attendance.export');

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertSame('attendance/export', $route->uri());
        $this->assertSame(AttendanceController::class . '@export', $route->getActionName());
    }

    /**
     * @param array<int, Attendance> $attendances
     * @return array<int, array<int, string|null>>
     */
    private function csvRowsFor(array $attendances): array
    {
        $response = $this->invokeExportToCsv(new Collection($attendances));
        $output = $this->streamedContent($response);
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $output)));

        return array_map(function (string $line, int $index): array {
            if ($index === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }

            return str_getcsv($line);
        }, $lines, array_keys($lines));
    }

    private function invokeExportToCsv(Collection $attendances): StreamedResponse
    {
        $controller = new AttendanceController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('exportToCsv');
        $method->setAccessible(true);

        return $method->invoke($controller, $attendances);
    }

    private function streamedContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    private function attendanceWithPeriod(?string $period): Attendance
    {
        $attendance = new Attendance([
            'student_id' => 1,
            'date' => '2026-06-06',
            'status' => 'present',
            'remarks' => 'Regular attendance',
            'period' => $period,
            'subject' => 'General',
            'class' => 'Class 1',
            'session' => '2026-2027',
            'marked_by' => 1,
            'ip_address' => '127.0.0.1',
        ]);

        $attendance->id = 1;
        $attendance->exists = true;
        $attendance->date = Carbon::parse('2026-06-06');
        $attendance->setRelation('student', new Student([
            'name' => 'Demo Student',
            'roll_number' => '7',
        ]));
        $attendance->setRelation('markedBy', new User([
            'name' => 'Demo User',
        ]));

        return $attendance;
    }
}
