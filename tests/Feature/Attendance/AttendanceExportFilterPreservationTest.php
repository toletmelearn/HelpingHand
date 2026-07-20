<?php

namespace Tests\Feature\Attendance;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AttendanceExportFilterPreservationTest extends TestCase
{
    public function test_index_export_link_preserves_date_as_from_and_to_date(): void
    {
        $html = $this->renderIndexView('/attendance?date=2026-06-06');
        $query = $this->exportLinkQuery($html);

        $this->assertSame('2026-06-06', $query['from_date'] ?? null);
        $this->assertSame('2026-06-06', $query['to_date'] ?? null);
        $this->assertSame('csv', $query['format'] ?? null);
    }

    public function test_index_export_link_preserves_class(): void
    {
        $html = $this->renderIndexView('/attendance?class=Class%201');
        $query = $this->exportLinkQuery($html);

        $this->assertSame('Class 1', $query['class'] ?? null);
    }

    public function test_index_export_link_preserves_supported_status(): void
    {
        $html = $this->renderIndexView('/attendance?date=2026-06-06&class=Class%201&status=absent');
        $query = $this->exportLinkQuery($html);

        $this->assertSame('absent', $query['status'] ?? null);
        $this->assertSame('2026-06-06', $query['from_date'] ?? null);
        $this->assertSame('Class 1', $query['class'] ?? null);
    }

    public function test_index_export_link_does_not_preserve_unsupported_status(): void
    {
        $html = $this->renderIndexView('/attendance?date=2026-06-06&class=Class%201&status=unexpected');
        $query = $this->exportLinkQuery($html);

        $this->assertArrayNotHasKey('status', $query);
        $this->assertSame('2026-06-06', $query['from_date'] ?? null);
        $this->assertSame('Class 1', $query['class'] ?? null);
    }

    public function test_reports_export_link_preserves_date_as_from_and_to_date(): void
    {
        $html = $this->renderReportsView('/attendance/reports?date=2026-06-07');
        $query = $this->exportLinkQuery($html);

        $this->assertSame('2026-06-07', $query['from_date'] ?? null);
        $this->assertSame('2026-06-07', $query['to_date'] ?? null);
        $this->assertSame('csv', $query['format'] ?? null);
    }

    public function test_reports_export_link_preserves_class(): void
    {
        $html = $this->renderReportsView('/attendance/reports?class=Class%202');
        $query = $this->exportLinkQuery($html);

        $this->assertSame('Class 2', $query['class'] ?? null);
    }

    public function test_export_page_form_still_uses_supported_filters(): void
    {
        $html = $this->renderExportView();

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('action="' . route('attendance.export') . '"', $html);
        $this->assertStringContainsString('name="from_date"', $html);
        $this->assertStringContainsString('name="to_date"', $html);
        $this->assertStringContainsString('name="class"', $html);
        $this->assertStringContainsString('name="format"', $html);
    }

    private function renderIndexView(string $uri): string
    {
        $this->setCurrentRequest($uri);

        return view('attendance.index', [
            'attendances' => $this->paginator(),
            'classes' => collect(['Class 1', 'Class 2']),
            'stats' => [],
        ])->render();
    }

    private function renderReportsView(string $uri): string
    {
        $this->setCurrentRequest($uri);

        return view('attendance.reports', [
            'attendances' => $this->paginator(),
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

    private function renderExportView(): string
    {
        $this->setCurrentRequest('/attendance/export');

        return view('attendance.export', [
            'classes' => collect(['Class 1', 'Class 2']),
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
