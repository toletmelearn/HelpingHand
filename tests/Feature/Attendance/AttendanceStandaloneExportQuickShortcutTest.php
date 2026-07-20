<?php

namespace Tests\Feature\Attendance;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendanceStandaloneExportQuickShortcutTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_quick_shortcuts_preserve_selected_class(): void
    {
        $queries = $this->quickShortcutQueries('/attendance/export?class=Class%201');

        foreach ($queries as $query) {
            $this->assertSame('Class 1', $query['class'] ?? null);
        }
    }

    public function test_quick_shortcuts_preserve_allowlisted_status(): void
    {
        $queries = $this->quickShortcutQueries('/attendance/export?status=late');

        foreach ($queries as $query) {
            $this->assertSame('late', $query['status'] ?? null);
        }
    }

    public function test_quick_shortcuts_do_not_preserve_unsupported_status(): void
    {
        $queries = $this->quickShortcutQueries('/attendance/export?class=Class%201&status=unexpected');

        foreach ($queries as $query) {
            $this->assertSame('Class 1', $query['class'] ?? null);
            $this->assertArrayNotHasKey('status', $query);
        }
    }

    public function test_quick_shortcuts_keep_format_csv(): void
    {
        $queries = $this->quickShortcutQueries('/attendance/export?class=Class%201&status=present');

        foreach ($queries as $query) {
            $this->assertSame('csv', $query['format'] ?? null);
        }
    }

    public function test_quick_shortcuts_keep_expected_date_ranges(): void
    {
        $queries = $this->quickShortcutQueries();

        $this->assertSame('2026-05-30', $queries['Last 7 Days (CSV)']['from_date'] ?? null);
        $this->assertSame('2026-06-06', $queries['Last 7 Days (CSV)']['to_date'] ?? null);

        $this->assertSame('2026-05-07', $queries['Last 30 Days (CSV)']['from_date'] ?? null);
        $this->assertSame('2026-06-06', $queries['Last 30 Days (CSV)']['to_date'] ?? null);

        $this->assertSame('2026-06-01', $queries['This Month (CSV)']['from_date'] ?? null);
        $this->assertSame('2026-06-30', $queries['This Month (CSV)']['to_date'] ?? null);
    }

    public function test_quick_shortcuts_keep_csv_only_ui(): void
    {
        $html = $this->renderExportView('/attendance/export?class=Class%201&status=absent');

        $this->assertStringContainsString('Quick exports use the selected class/status filters when available.', $html);
        $this->assertStringContainsString('Export CSV', $html);
        $this->assertStringContainsString('Excel not enabled', $html);
        $this->assertStringContainsString('PDF not enabled', $html);
        $this->assertStringNotContainsString('name="format" value="excel"', $html);
        $this->assertStringNotContainsString('name="format" value="pdf"', $html);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function quickShortcutQueries(string $uri = '/attendance/export'): array
    {
        $html = $this->renderExportView($uri);
        $labels = [
            'Last 7 Days (CSV)',
            'Last 30 Days (CSV)',
            'This Month (CSV)',
        ];

        $queries = [];

        foreach ($labels as $label) {
            preg_match('/<a href="([^"]+)"\s+class="btn btn-outline-[^"]+ w-100">\s*<i class="bi [^"]+"><\/i>\s*' . preg_quote($label, '/') . '\s*<\/a>/s', $html, $matches);

            $this->assertNotEmpty($matches, $label . ' quick export link was not found.');

            $href = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $query);

            $queries[$label] = $query;
        }

        return $queries;
    }

    private function renderExportView(string $uri): string
    {
        Carbon::setTestNow(Carbon::parse('2026-06-06 09:00:00'));

        $request = Request::create($uri, 'GET');
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);

        return view('attendance.export', [
            'classes' => collect(['Class 1', 'Class 2']),
        ])->render();
    }
}
