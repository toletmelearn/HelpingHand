<?php

namespace Tests\Feature\Attendance;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AttendanceStandaloneExportStatusDropdownTest extends TestCase
{
    public function test_export_page_renders_status_dropdown(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('id="status"', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('Filter by Status (Optional)', $html);
    }

    public function test_export_page_status_dropdown_has_allowlisted_options(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('<option value="">All Status</option>', $html);
        $this->assertStringContainsString('value="present"', $html);
        $this->assertStringContainsString('>Present</option>', $html);
        $this->assertStringContainsString('value="absent"', $html);
        $this->assertStringContainsString('>Absent</option>', $html);
        $this->assertStringContainsString('value="late"', $html);
        $this->assertStringContainsString('>Late</option>', $html);
        $this->assertStringContainsString('value="half_day"', $html);
        $this->assertStringContainsString('>Half Day</option>', $html);
    }

    public function test_export_page_preserves_selected_status(): void
    {
        $html = $this->renderExportView('/attendance/export?status=late');

        $this->assertStringContainsString('value="late" selected', $html);
    }

    public function test_export_page_form_still_uses_get_and_attendance_export_route(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('action="' . route('attendance.export') . '"', $html);
        $this->assertStringContainsString('name="from_date"', $html);
        $this->assertStringContainsString('name="to_date"', $html);
        $this->assertStringContainsString('name="class"', $html);
        $this->assertStringContainsString('name="status"', $html);
    }

    public function test_export_page_still_has_csv_as_only_active_export_format(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('type="submit" class="btn btn-success flex-fill" name="format" value="csv"', $html);
        $this->assertStringContainsString('Export CSV', $html);
        $this->assertStringNotContainsString('name="format" value="excel"', $html);
        $this->assertStringNotContainsString('name="format" value="pdf"', $html);
    }

    public function test_export_page_excel_pdf_remain_disabled(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('type="button" class="btn btn-outline-secondary flex-fill" disabled', $html);
        $this->assertStringContainsString('Excel not enabled', $html);
        $this->assertStringContainsString('PDF not enabled', $html);
        $this->assertStringContainsString('Excel and PDF export are not enabled yet', $html);
    }

    public function test_reports_export_link_still_does_not_add_status(): void
    {
        $html = $this->renderReportsView('/attendance/reports?date=2026-06-06&class=Class%201&status=late');
        $query = $this->exportLinkQuery($html);

        $this->assertArrayNotHasKey('status', $query);
        $this->assertSame('2026-06-06', $query['from_date'] ?? null);
        $this->assertSame('Class 1', $query['class'] ?? null);
    }

    private function renderExportView(string $uri = '/attendance/export'): string
    {
        $this->setCurrentRequest($uri);

        return view('attendance.export', [
            'classes' => collect(['Class 1', 'Class 2']),
        ])->render();
    }

    private function renderReportsView(string $uri): string
    {
        $this->setCurrentRequest($uri);

        return view('attendance.reports', [
            'attendances' => $this->paginator('/attendance/reports'),
            'classes' => collect(['Class 1', 'Class 2']),
            'stats' => [
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'percentage' => 0,
            ],
        ])->render();
    }

    /**
     * @return array<string, string>
     */
    private function exportLinkQuery(string $html): array
    {
        preg_match('/<a href="([^"]+)" class="btn btn-primary">\s*<i class="bi bi-download"><\/i>\s*Export CSV/s', $html, $matches);

        $this->assertNotEmpty($matches, 'Export CSV link was not found.');

        $href = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $query);

        return $query;
    }

    private function setCurrentRequest(string $uri): void
    {
        $request = Request::create($uri, 'GET');

        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
    }

    private function paginator(string $path): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            new Collection(),
            0,
            15,
            1,
            ['path' => $path]
        );
    }
}
