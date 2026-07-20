<?php

namespace Tests\Feature\Attendance;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AttendanceExportUiContractTest extends TestCase
{
    public function test_export_page_has_csv_button(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('Export CSV', $html);
        $this->assertStringContainsString('name="format"', $html);
        $this->assertStringContainsString('value="csv"', $html);
    }

    public function test_export_page_does_not_have_active_excel_submit(): void
    {
        $html = $this->renderExportView();

        $this->assertStringNotContainsString('type="submit" class="btn btn-primary flex-fill" name="format" value="excel"', $html);
        $this->assertStringNotContainsString('Export as Excel', $html);
        $this->assertStringContainsString('type="button" class="btn btn-outline-secondary flex-fill" disabled', $html);
        $this->assertStringContainsString('Excel not enabled', $html);
    }

    public function test_export_page_does_not_have_active_pdf_submit(): void
    {
        $html = $this->renderExportView();

        $this->assertStringNotContainsString('type="submit" class="btn btn-info flex-fill" name="format" value="pdf"', $html);
        $this->assertStringNotContainsString('Export as PDF', $html);
        $this->assertStringContainsString('PDF not enabled', $html);
    }

    public function test_export_page_does_not_promise_excel_pdf_as_available(): void
    {
        $html = $this->renderExportView();

        $this->assertStringNotContainsString('Choose your preferred export format', $html);
        $this->assertStringNotContainsString('Excel files preserve formatting and formulas', $html);
        $this->assertStringNotContainsString('PDF exports are ideal for printing and sharing', $html);
        $this->assertStringContainsString('Excel and PDF export are not enabled yet', $html);
    }

    public function test_index_export_link_label_mentions_csv(): void
    {
        $html = view('attendance.index', [
            'attendances' => $this->paginator(),
            'classes' => collect(['Class 1']),
            'stats' => [],
        ])->render();

        $this->assertStringContainsString('Export CSV', $html);
        $this->assertStringContainsString(route('attendance.export'), $html);
    }

    public function test_reports_export_link_label_mentions_csv(): void
    {
        $html = view('attendance.reports', [
            'attendances' => $this->paginator(),
            'classes' => collect(['Class 1']),
            'stats' => [
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'percentage' => 0,
            ],
        ])->render();

        $this->assertStringContainsString('Export CSV', $html);
        $this->assertStringContainsString(route('attendance.export'), $html);
    }

    public function test_export_form_still_uses_get_and_attendance_export_route(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('action="' . route('attendance.export') . '"', $html);
        $this->assertStringContainsString('name="from_date"', $html);
        $this->assertStringContainsString('name="to_date"', $html);
        $this->assertStringContainsString('name="class"', $html);
    }

    private function renderExportView(): string
    {
        return view('attendance.export', [
            'classes' => collect(['Class 1', 'Class 2']),
        ])->render();
    }

    private function paginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            new Collection(),
            0,
            15,
            1,
            ['path' => '/attendance']
        );
    }
}
