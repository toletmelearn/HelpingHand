# Phase 6I - Standalone Attendance Export Status Filter Audit

## Scope

This was a read-only audit of whether the standalone attendance export page should expose a status dropdown now that `AttendanceController@export()` supports allowlisted status filtering.

No application code, views, controllers, routes, services, models, tests, migrations, database data, export output, attendance writes, or biometric/device behavior were modified or executed.

## Files Inspected

- `resources/views/attendance/export.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceExportStatusFilterTest.php`
- `tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`
- `docs/project-autopsy/PHASE_6H_ATTENDANCE_EXPORT_STATUS_FILTER_SUPPORT.md`
- `docs/project-autopsy/PHASE_6G_ATTENDANCE_EXPORT_STATUS_FILTER_AUDIT.md`
- `docs/project-autopsy/PHASE_6E_ATTENDANCE_EXPORT_UI_FORMAT_LABELS.md`

## Commands Run

- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content tests/Feature/Attendance/AttendanceExportStatusFilterTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`
- `Get-Content docs/project-autopsy/PHASE_6H_ATTENDANCE_EXPORT_STATUS_FILTER_SUPPORT.md`
- `Get-Content docs/project-autopsy/PHASE_6G_ATTENDANCE_EXPORT_STATUS_FILTER_AUDIT.md`
- `Get-Content docs/project-autopsy/PHASE_6E_ATTENDANCE_EXPORT_UI_FORMAT_LABELS.md`
- `rg -n "status|from_date|to_date|attendance\.export|format|Excel|PDF|EXPORT_STATUS_FILTERS|function export" resources/views/attendance/export.blade.php resources/views/attendance/index.blade.php resources/views/attendance/reports.blade.php app/Http/Controllers/AttendanceController.php tests/Feature/Attendance/AttendanceExportStatusFilterTest.php tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php routes/web.php`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php artisan route | Select-String "attendance/export"`

Notes:

- `php -l app/Http/Controllers/AttendanceController.php` passed.
- The exact allowed route command `php artisan route | Select-String "attendance/export"` did not list route rows. Laravel treated `route` as a namespace and printed route command help with available commands such as `route:list`. No substitute route command was run.

## Standalone Export Page Findings

`resources/views/attendance/export.blade.php` currently exposes these visible filters:

- `from_date`
- `to_date`
- `class`

It also exposes:

- one active CSV submit button: `name="format" value="csv"`
- disabled Excel button
- disabled PDF button

It does not currently expose:

- `status`

The lead text says:

- `Choose a date range and optional class filter for attendance CSV export.`

That text is accurate for the current UI, but now incomplete relative to controller support because the controller can also filter by status.

The export page remains CSV-only:

- active button is `Export CSV`
- Excel and PDF buttons are disabled `type="button"` controls
- helper text says Excel and PDF export are not enabled yet
- quick export links use `format=csv`

The page's "About Export" section says exported data includes attendance status, dates, and remarks. That describes a CSV output column, not a filter, so it is not misleading. However, once export supports status in the controller and index link, a staff user may reasonably expect the standalone export page to offer the same status narrowing.

Adding a status dropdown would match current controller support and would be a request-input/UI change only.

## Controller Support Alignment Findings

`AttendanceController@export()` currently supports:

- `from_date`
- `to_date`
- `class`
- `status`

The status allowlist is:

- `present`
- `absent`
- `late`
- `half_day`

When `status` is present and allowlisted, export applies:

- `where('status', $request->status)`

When `status` is missing, export behavior remains unchanged.

When `status` is unsupported, it is ignored safely and no arbitrary status filter is applied.

The standalone export page could safely submit the same allowlisted values because:

- the controller already handles allowlisted status
- tests already cover allowlisted status filtering
- tests already cover unsupported status being ignored safely
- adding the dropdown would not require changing CSV headers, CSV rows, filename, response type, export route, or export query semantics beyond passing an already-supported request parameter

Existing relevant tests:

- `AttendanceExportStatusFilterTest::test_export_controller_applies_allowlisted_status_filter`
- `AttendanceExportStatusFilterTest::test_export_controller_ignores_unsupported_status_safely`
- `AttendanceExportStatusFilterTest::test_export_without_status_keeps_previous_behavior`
- `AttendanceExportStatusFilterTest::test_index_export_link_preserves_status_when_present`
- `AttendanceExportStatusFilterTest::test_reports_export_link_does_not_add_status`
- `AttendanceExportFilterPreservationTest::test_export_page_form_still_uses_supported_filters`

The export page form test should be extended in Phase 6J if a dropdown is added.

## User Expectation Findings

The attendance index page has a visible status filter:

- `All Status`
- `Present`
- `Absent`
- `Late`
- `Half Day`

The index export link now preserves the status filter when the selected value is allowlisted.

The standalone export page lacks a status filter, even though it is the dedicated export UI. This creates a mild consistency gap:

- the index page can export by status
- the standalone export page cannot visibly select status
- manually adding `status` to the URL would work, but the UI does not disclose that capability

Leaving the standalone page without status is safe, because it exports a broader dataset rather than mutating data or corrupting output. It is incomplete from a usability standpoint because staff who use the dedicated export page cannot narrow by status without knowing the URL parameter.

Adding a dropdown improves usability and makes the standalone page consistent with the index export behavior.

Reports remain different by design:

- reports page has no visible status filter
- reports export link should remain date/class-only unless the reports UI later gains a visible status filter

## Risk Classification

### RED

- None. This is a read/export UI consistency issue, not an attendance write path.

### YELLOW

- Standalone export page does not disclose a status filter that the controller now supports.
- The export page lead text only mentions date range and class, so it would need a small wording update if status is added.
- Quick export links remain date-range-only; adding status to the form would not automatically affect quick links.

### GREEN

- Controller already has an allowlist for status.
- Unsupported status values are ignored safely.
- CSV columns and order do not need to change.
- CSV-only export UI remains intact.
- Excel/PDF buttons remain disabled and non-submit.
- Adding the dropdown can be tested with view rendering only and does not require executing export against real/local MySQL.

## Recommended Phase 6J First Code Task

Phase 6J should add a status dropdown to `resources/views/attendance/export.blade.php`.

Recommended behavior:

1. Add a `status` select to the standalone export form.
2. Options should match the export allowlist:
   - `present`
   - `absent`
   - `late`
   - `half_day`
3. Include an empty option such as `All Status`.
4. Preserve selected value from `request('status')`.
5. Update the lead/helper text to mention optional status filtering.
6. Keep CSV as the only active export format.
7. Keep Excel/PDF disabled.
8. Do not change `AttendanceController@export()` because status support already exists.
9. Do not change reports export link.
10. Add view-focused tests only:
    - export page renders a status dropdown
    - dropdown options match the allowlist
    - selected status is preserved
    - form still uses GET and `attendance.export`
    - CSV-only UI remains intact
    - reports export still does not add status

## Confirmation

- No application code was modified.
- No views were modified.
- No controllers were modified.
- No routes were modified.
- No services were modified.
- No models were modified.
- No tests were modified.
- No migrations or schema were touched.
- No database checks were run.
- No database data was read or modified.
- No export route was executed.
- No CSV/Excel/PDF was generated from real/local MySQL.
- No attendance write route was executed.
- No API attendance write route was executed.
- No biometric sync or device command was run.
- No full test suite was run.
