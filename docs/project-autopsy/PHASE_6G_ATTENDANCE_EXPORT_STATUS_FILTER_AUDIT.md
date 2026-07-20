# Phase 6G - Attendance Export Status Filter Audit

## Scope

This was a read-only audit of attendance CSV export status filtering. No controller, route, view, model, test, migration, database data, export output, or biometric/device behavior was changed.

## Files Inspected

- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `resources/views/attendance/export.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Models/Attendance.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`
- `tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `docs/project-autopsy/PHASE_6F_ATTENDANCE_EXPORT_FILTER_PRESERVATION.md`
- `docs/project-autopsy/PHASE_6E_ATTENDANCE_EXPORT_UI_FORMAT_LABELS.md`
- `docs/project-autopsy/PHASE_6C_ATTENDANCE_CSV_PERIOD_DISPLAY_COLUMN.md`

## Commands Run

- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Models/Attendance.php`
- `Get-Content tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `Get-Content docs/project-autopsy/PHASE_6F_ATTENDANCE_EXPORT_FILTER_PRESERVATION.md`
- `Get-Content docs/project-autopsy/PHASE_6E_ATTENDANCE_EXPORT_UI_FORMAT_LABELS.md`
- `Get-Content docs/project-autopsy/PHASE_6C_ATTENDANCE_CSV_PERIOD_DISPLAY_COLUMN.md`
- `Get-Content routes/web.php`
- `rg -n "status|attendance\.export|from_date|to_date|format|exportToCsv|function export" resources/views/attendance/index.blade.php resources/views/attendance/reports.blade.php resources/views/attendance/export.blade.php app/Http/Controllers/AttendanceController.php tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `rg -n "attendance/export|attendance\.export|Route::.*export|export\(" routes/web.php app/Http/Controllers/AttendanceController.php`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l app/Models/Attendance.php`
- `php artisan route | Select-String "attendance/export"`

Notes:

- `php -l app/Http/Controllers/AttendanceController.php` passed.
- `php -l app/Models/Attendance.php` passed.
- The exact allowed route command `php artisan route | Select-String "attendance/export"` did not list route rows. Laravel treated `route` as a namespace and printed route command help with available commands such as `route:list`. No substitute route command was run.

## Index Status Filter Findings

The attendance index page has a visible status filter.

Field name:

- `status`

Visible values:

- empty value: `All Status`
- `present`: `Present`
- `absent`: `Absent`
- `late`: `Late`
- `half_day`: `Half Day`

The index controller query uses the status filter:

- `AttendanceController@index()` checks `$request->filled('status')`.
- When present, it applies `where('status', $request->status)`.

The index export link currently preserves:

- `date` as `from_date` and `to_date`
- `class` as `class`
- `format=csv`

The index export link intentionally omits:

- `status`

Current test coverage confirms this is intentional for now:

- `AttendanceExportFilterPreservationTest::test_index_export_link_does_not_preserve_status_until_supported`

Impact:

- If the user filters the index table by status, the CSV export can include a broader dataset than the visible table because export does not receive or apply the same status filter.

## Export Controller Status Findings

The unprefixed attendance export route is:

- URI: `GET /attendance/export`
- name: `attendance.export`
- controller: `AttendanceController@export`

`AttendanceController@export()` currently supports:

- `from_date`
- `to_date`
- `class`

It does not currently support:

- `status`

There is no status validation in `export()`.

The export method:

- creates an `Attendance::query()`
- applies date lower bound when `from_date` is filled
- applies date upper bound when `to_date` is filled
- applies class filter when `class` is filled
- loads `student` and `markedBy`
- always calls `exportToCsv($attendances)`

The `format` request parameter may be sent by the UI as `format=csv`, but the controller does not branch by format. It always returns CSV.

Adding optional status support appears small and low-risk if it is constrained to the same allowed values shown on the index page.

Recommended allowlist:

- `present`
- `absent`
- `late`
- `half_day`

## CSV Contract Findings

CSV already includes a `Status` column.

Current CSV headers include:

- `Date`
- `Class`
- `Student Name`
- `Roll Number`
- `Status`
- `Subject`
- `Period`
- `Period Display`
- `Remarks`
- `Marked By`
- `IP Address`

Adding a status filter would not change:

- CSV filename
- CSV content type
- CSV headers
- CSV row column order
- raw `Period`
- `Period Display`
- export format

It would only reduce exported rows to match the visible status-filtered index table when a status filter is active.

This is a backward-compatible behavior change for the filtered index export link. It is also the behavior a staff user would reasonably expect when exporting from a filtered list.

Contract caution:

- Existing direct consumers of `/attendance/export?status=...` do not currently get filtered results because status is ignored. Supporting `status` would make that parameter meaningful.
- To avoid accidental broad or malformed filtering, the controller should validate or allowlist accepted status values instead of applying arbitrary request input.

## Reports And Export Page Findings

`resources/views/attendance/reports.blade.php` uses:

- `date`
- `class`

It does not expose a visible status filter.

The reports export link preserves:

- report `date` as `from_date` and `to_date`
- report `class` as `class`
- `format=csv`

Because reports has no visible status filter, there is no status value to preserve there.

`resources/views/attendance/export.blade.php` exposes:

- `from_date`
- `to_date`
- `class`
- CSV submit button

It does not expose a status dropdown.

Recommendation:

- Phase 6H should not change the reports export link.
- Phase 6H can leave the standalone export page unchanged, or defer adding a status dropdown until after index export status support is safely implemented and tested.

## Risk Classification

### RED

- None found that require emergency write-path intervention in this phase. This is an export/read contract mismatch, not an attendance data mutation path.

### YELLOW

- Index page has a visible status filter, but export omits status, so exported rows can be broader than the visible table.
- `AttendanceController@export()` does not validate status because it does not support status today.
- If status support is added without an allowlist, arbitrary request values could silently produce empty or misleading exports.
- The standalone export page has no status dropdown, so status filtering may remain discoverable only from the index export link unless handled in a later UI phase.

### GREEN

- CSV already includes a `Status` column, so adding a status filter does not require changing CSV columns.
- Phase 6F already preserved date and class filters in index/reports export links.
- CSV is now the only active export format in the UI.
- Adding a constrained optional status filter would only narrow rows when the user has already chosen a visible index status filter.

## Recommended Phase 6H First Code Task

Phase 6H should add optional status support to attendance CSV export in the smallest safe way:

1. Update `AttendanceController@export()` to accept `status` only when it is one of:
   - `present`
   - `absent`
   - `late`
   - `half_day`
2. Apply `where('status', $request->status)` only for allowed values.
3. Update the index export link to preserve `status` when present.
4. Do not change the reports export link because reports has no status filter.
5. Do not change CSV headers, row format, filename, content type, period behavior, API behavior, or attendance write behavior.
6. Add focused isolated tests proving:
   - index export link includes status after support exists
   - export query filters by status
   - unsupported status is rejected or ignored in a controlled, tested way
   - reports export link remains unchanged
   - CSV columns remain unchanged

The standalone export page status dropdown can be deferred unless Phase 6H deliberately includes it with equally focused tests.

## Confirmation

- No application code was modified.
- No views were modified.
- No controllers were modified.
- No routes were modified.
- No models were modified.
- No tests were modified.
- No migrations or schema were touched.
- No database reads beyond file/code inspection were performed.
- No database writes were performed.
- No export route was executed.
- No CSV/Excel/PDF was generated from real/local MySQL.
- No attendance write route was executed.
- No API attendance write route was executed.
- No biometric sync or device command was run.
- No full test suite was run.
