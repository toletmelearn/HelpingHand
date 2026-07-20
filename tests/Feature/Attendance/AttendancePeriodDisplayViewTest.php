<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AttendancePeriodDisplayViewTest extends TestCase
{
    public function test_show_view_displays_null_period_as_full_day(): void
    {
        $html = $this->renderShowView($this->attendanceWithPeriod(null));

        $this->assertStringContainsString('<strong>Period:</strong> Full Day', $html);
    }

    public function test_show_view_displays_literal_full_day_as_full_day(): void
    {
        $html = $this->renderShowView($this->attendanceWithPeriod('Full Day'));

        $this->assertStringContainsString('<strong>Period:</strong> Full Day', $html);
    }

    public function test_show_view_displays_named_period_trimmed(): void
    {
        $html = $this->renderShowView($this->attendanceWithPeriod(' Morning '));

        $this->assertStringContainsString('<strong>Period:</strong> Morning', $html);
    }

    public function test_index_view_displays_null_period_as_full_day(): void
    {
        $html = view('attendance.index', [
            'attendances' => $this->paginator([$this->attendanceWithPeriod(null)]),
            'classes' => collect(['Class 1']),
            'stats' => [],
        ])->render();

        $this->assertStringContainsString('Full Day', $html);
        $this->assertStringNotContainsString('<span class="text-muted">N/A</span>', $html);
    }

    public function test_reports_view_displays_null_period_as_full_day(): void
    {
        $html = view('attendance.reports', [
            'attendances' => $this->paginator([$this->attendanceWithPeriod(null)]),
            'classes' => collect(['Class 1']),
            'stats' => [
                'total' => 1,
                'present' => 1,
                'absent' => 0,
                'late' => 0,
                'percentage' => 100,
            ],
        ])->render();

        $this->assertStringContainsString('Full Day', $html);
        $this->assertStringNotContainsString('<span class="text-muted">-</span>', $html);
    }

    public function test_read_views_do_not_change_stored_period_values(): void
    {
        $attendance = $this->attendanceWithPeriod(' Full Day ');

        $this->renderShowView($attendance);

        $this->assertSame(' Full Day ', $attendance->period);
    }

    public function test_edit_form_period_input_value_is_not_normalized(): void
    {
        $attendance = $this->attendanceWithPeriod(' Full Day ');

        $html = view('attendance.edit', [
            'attendance' => $attendance,
            'subjects' => ['General'],
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('value=" Full Day "', $html);
    }

    private function renderShowView(Attendance $attendance): string
    {
        return view('attendance.show', ['attendance' => $attendance])->render();
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
        ]);

        $attendance->id = 1;
        $attendance->exists = true;
        $attendance->setRelation('student', new Student([
            'name' => 'Demo Student',
            'father_name' => 'Demo Father',
            'mother_name' => 'Demo Mother',
            'roll_number' => '1',
            'aadhar_number' => '123456789012',
            'phone' => '9999999999',
        ]));
        $attendance->setRelation('markedBy', null);

        $attendance->date = Carbon::parse('2026-06-06');
        $attendance->created_at = Carbon::parse('2026-06-06 09:00:00');
        $attendance->updated_at = Carbon::parse('2026-06-06 09:00:00');

        return $attendance;
    }

    private function paginator(array $items): LengthAwarePaginator
    {
        $collection = new Collection($items);

        return new LengthAwarePaginator(
            $collection,
            $collection->count(),
            15,
            1,
            ['path' => '/attendance']
        );
    }
}
