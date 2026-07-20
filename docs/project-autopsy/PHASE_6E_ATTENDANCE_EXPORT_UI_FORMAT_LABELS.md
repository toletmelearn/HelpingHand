# Phase 6E - Attendance Export UI Format Labels

Date: 2026-06-06

Scope: UI-label and button cleanup only. CSV is now the only active attendance export format promised by the UI. Controller export behavior, routes, filters, queries, CSV output, API behavior, attendance write behavior, migrations, schema, and real/local MySQL data were not changed.

## Files Inspected

- `resources/views/attendance/export.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `docs/project-autopsy/PHASE_6D_ATTENDANCE_EXPORT_UI_CONTRACT_AUDIT.md`
- `docs/project-autopsy/PHASE_6C_ATTENDANCE_CSV_PERIOD_DISPLAY_COLUMN.md`

## Files Changed

- `resources/views/attendance/export.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `tests/Feature/Attendance/AttendanceExportUiContractTest.php`
- `docs/project-autopsy/PHASE_6E_ATTENDANCE_EXPORT_UI_FORMAT_LABELS.md`

## Export Page UI Changes

`resources/views/attendance/export.blade.php` was updated so the active export path is clearly CSV-only.

Changed:

- Lead text now describes attendance CSV export instead of preferred export formats.
- Active submit button label changed from `Export as CSV` to `Export CSV`.
- Excel submit button changed to a disabled non-submit button:

```html
type="button"
disabled
```

- PDF submit button changed to a disabled non-submit button:

```html
type="button"
disabled
```

- Added helper text:

```text
Excel and PDF export are not enabled yet.
```

- Removed available-format promises about Excel preserving formatting/formulas.
- Removed available-format promises about PDF being ideal for printing/sharing.

Unchanged:

- form method remains `GET`
- form action remains `route('attendance.export')`
- `from_date` field name
- `to_date` field name
- `class` field name
- active CSV submit still sends `format=csv`
- quick CSV links remain CSV links

## Index / Reports Label Changes

`resources/views/attendance/index.blade.php`:

- `Export` changed to `Export CSV`

`resources/views/attendance/reports.blade.php`:

- `Export Report` changed to `Export CSV`

No route parameters were added. Filter preservation was intentionally left unchanged for Phase 6F.

## Excel / PDF Button Confirmation

Excel and PDF controls remain visible as disabled informational buttons, but they are no longer active submit buttons.

They no longer send:

- `format=excel`
- `format=pdf`

The UI no longer promises Excel/PDF as currently available export outputs.

## CSV Active Format Confirmation

CSV is the only active export format in the attendance export UI.

The active submit remains:

```html
name="format" value="csv"
```

## Controller / Export Behavior Confirmation

`AttendanceController@export()` was inspected but not changed.

Unchanged:

- route URI
- route name
- authorization
- export query
- supported filters
- filename
- response content type
- CSV BOM behavior
- CSV header/row behavior from Phase 6C

The controller still always returns CSV.

## Filter Preservation Confirmation

Filter preservation was not changed in this phase.

Still unchanged:

- index export link still uses `route('attendance.export')` without active `date`, `class`, or `status` filters
- reports export link still uses `route('attendance.export')` without active `date` or `class` filters
- export controller still supports `from_date`, `to_date`, and `class`
- export controller still does not support `status`

## Tests Created / Updated

Added `tests/Feature/Attendance/AttendanceExportUiContractTest.php`.

Tests render Blade views directly and do not execute the export route.

Coverage:

- export page has an active CSV button
- export page does not have an active Excel submit
- export page does not have an active PDF submit
- export page does not promise Excel/PDF as available
- index export link label mentions CSV
- reports export link label mentions CSV
- export form still uses GET and the `attendance.export` route

## Commands Run

- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content routes/web.php | Select-String "attendance.export"`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l tests/Feature/Attendance/AttendanceExportUiContractTest.php`
- `php artisan test --filter=AttendanceExportUiContractTest --env=testing`
- `php artisan test --filter=AttendanceCsvPeriodDisplayExportTest --env=testing`
- `php artisan test --filter=AttendancePeriodPresenterTest --env=testing`
- `git diff -- resources/views/attendance/export.blade.php resources/views/attendance/index.blade.php resources/views/attendance/reports.blade.php tests/Feature/Attendance/AttendanceExportUiContractTest.php`
- `Get-Content docs/project-autopsy/PHASE_6D_ATTENDANCE_EXPORT_UI_CONTRACT_AUDIT.md`
- `Get-Content docs/project-autopsy/PHASE_6C_ATTENDANCE_CSV_PERIOD_DISPLAY_COLUMN.md`

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php`: passed.
- `php -l tests/Feature/Attendance/AttendanceExportUiContractTest.php`: passed.
- `php artisan test --filter=AttendanceExportUiContractTest --env=testing`: passed, 7 tests and 23 assertions.
- `php artisan test --filter=AttendanceCsvPeriodDisplayExportTest --env=testing`: passed, 6 tests and 15 assertions.
- `php artisan test --filter=AttendancePeriodPresenterTest --env=testing`: passed, 10 tests and 36 assertions.

The targeted test runs emitted unrelated PHPUnit metadata deprecation warnings from older tests during discovery. No Phase 6E test failed.

## Constraint Confirmation

- No full test suite was run.
- No migrations were run.
- No migrations or schema files were changed.
- No real/local MySQL writes were performed.
- No real/local MySQL data was touched.
- No export route was executed.
- No CSV/Excel/PDF was generated from real/local MySQL.
- No attendance write route was executed.
- No API store/update/destroy/bulkMark route was executed.
- No API behavior was changed.
- No controller export behavior was changed.
- No export route/filter/query behavior was changed.
- No biometric sync or device command was run.

## Remaining Risks

- Index export link still does not preserve active `date`, `class`, or `status` filters.
- Reports export link still does not preserve active `date` or `class` filters.
- Export controller supports `from_date` / `to_date`, while reports UI uses a single `date`.
- Export controller does not support `status`, while index UI has a status filter.
- Disabled Excel/PDF buttons may still imply future availability and should be revisited when export roadmap is clear.

## Recommended Phase 6F Next Step

Phase 6F should handle export filter preservation:

- decide how index `date` maps to export `from_date` / `to_date`
- decide whether to add `status` support to export or omit status from preserved links
- update index/reports export links to preserve the visible dataset as closely as possible
- keep this as a route/link/filter change only, without changing attendance writes or real/local data
