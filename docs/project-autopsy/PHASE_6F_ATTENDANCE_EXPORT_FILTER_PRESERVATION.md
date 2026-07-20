# Phase 6F - Attendance Export Filter Preservation

Date: 2026-06-06

Scope: Preserve supported visible attendance filters in export links. The export controller, export route, export page form, CSV output, API behavior, attendance write behavior, migrations, schema, and real/local MySQL data were not changed.

## Files Inspected

- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `resources/views/attendance/export.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceExportUiContractTest.php`
- `tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `docs/project-autopsy/PHASE_6E_ATTENDANCE_EXPORT_UI_FORMAT_LABELS.md`
- `docs/project-autopsy/PHASE_6D_ATTENDANCE_EXPORT_UI_CONTRACT_AUDIT.md`

## Files Changed

- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`
- `docs/project-autopsy/PHASE_6F_ATTENDANCE_EXPORT_FILTER_PRESERVATION.md`

Note: `resources/views/attendance/export.blade.php` remains unchanged in Phase 6F. It still contains the CSV-only UI and supported `from_date`, `to_date`, and `class` form fields from Phase 6E.

## Index Export Link Behavior

`resources/views/attendance/index.blade.php` now builds export route parameters from supported visible filters:

- always includes `format=csv`
- if `date` is present, maps it to both:
  - `from_date`
  - `to_date`
- if `class` is present, maps it to:
  - `class`

Example:

```text
/attendance?date=2026-06-06&class=Class%201
```

Export link now points to:

```text
/attendance/export?format=csv&from_date=2026-06-06&to_date=2026-06-06&class=Class%201
```

## Reports Export Link Behavior

`resources/views/attendance/reports.blade.php` now builds export route parameters from supported visible filters:

- always includes `format=csv`
- if `date` is present, maps it to both:
  - `from_date`
  - `to_date`
- if `class` is present, maps it to:
  - `class`

Example:

```text
/attendance/reports?date=2026-06-07&class=Class%202
```

Export link now points to:

```text
/attendance/export?format=csv&from_date=2026-06-07&to_date=2026-06-07&class=Class%202
```

## Status Filter Decision

The index page has a `status` filter, but `AttendanceController@export()` does not currently support `status`.

Phase 6F intentionally does not preserve `status`.

Reason:

- preserving `status` in the link would imply export behavior that does not exist
- adding controller-side `status` filtering would be a controller/export behavior change
- the phase goal was to preserve supported filters while keeping export controller behavior unchanged

Recommended later task:

- add `status` support to `AttendanceController@export()` with focused tests
- then update the index export link to include `status`

## Export Page Form Compatibility

`resources/views/attendance/export.blade.php` was inspected and left unchanged.

It still uses:

- `method="GET"`
- `route('attendance.export')`
- `from_date`
- `to_date`
- `class`
- active CSV `format=csv`

Excel/PDF remain disabled non-submit UI controls from Phase 6E.

## Controller / Export Behavior Confirmation

`AttendanceController@export()` was inspected and not changed.

Unchanged:

- authorization
- route URI/name
- `from_date` query behavior
- `to_date` query behavior
- `class` query behavior
- no `status` query behavior
- no Excel/PDF behavior
- CSV response type
- filename
- UTF-8 BOM
- raw `Period` plus `Period Display` CSV columns from Phase 6C

## Tests Created / Updated

Added `tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`.

The tests render Blade views directly and parse the `Export CSV` link query string. They do not execute the export route and do not touch real/local MySQL.

Tests cover:

- index export link preserves `date` as `from_date` and `to_date`
- index export link preserves `class`
- index export link does not preserve `status` until controller support exists
- reports export link preserves `date` as `from_date` and `to_date`
- reports export link preserves `class`
- export page form still uses supported filters

## Commands Run

- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content routes/web.php`
- `Get-Content tests/Feature/Attendance/AttendanceExportUiContractTest.php`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`
- `php artisan test --filter=AttendanceExportFilterPreservationTest --env=testing`
- `php artisan test --filter=AttendanceExportUiContractTest --env=testing`
- `php artisan test --filter=AttendanceCsvPeriodDisplayExportTest --env=testing`
- `git diff -- resources/views/attendance/index.blade.php resources/views/attendance/reports.blade.php resources/views/attendance/export.blade.php tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`
- `Get-Content docs/project-autopsy/PHASE_6E_ATTENDANCE_EXPORT_UI_FORMAT_LABELS.md`
- `Get-Content docs/project-autopsy/PHASE_6D_ATTENDANCE_EXPORT_UI_CONTRACT_AUDIT.md`

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php`: passed.
- `php -l tests/Feature/Attendance/AttendanceExportFilterPreservationTest.php`: passed.
- `php artisan test --filter=AttendanceExportFilterPreservationTest --env=testing`: passed, 6 tests and 22 assertions.
- `php artisan test --filter=AttendanceExportUiContractTest --env=testing`: passed, 7 tests and 23 assertions.
- `php artisan test --filter=AttendanceCsvPeriodDisplayExportTest --env=testing`: passed, 6 tests and 15 assertions.

The targeted test runs emitted unrelated PHPUnit metadata deprecation warnings from older tests during discovery. No Phase 6F test failed.

## Constraint Confirmation

- No full test suite was run.
- No migrations were run.
- No migrations or schema files were changed.
- No real/local MySQL writes were performed.
- No real/local MySQL data was touched.
- No export route was executed.
- No CSV was generated from real/local MySQL.
- No attendance write route was executed.
- No API behavior was changed.
- No controller export behavior was changed.
- No export route/query/filter behavior was changed beyond UI link parameters.
- No biometric sync or device command was run.

## Remaining Risks

- Index `status` filter is still not preserved because export does not support it.
- Export controller still ignores `format`.
- Excel/PDF remain disabled and unimplemented.
- Export links now preserve supported date/class filters, but users with a status-filtered index view may still export a broader dataset than expected.
- Export page quick links still use predefined date ranges and do not include class unless selected in the form.

## Recommended Phase 6G Next Step

Phase 6G should audit whether attendance CSV export should support `status` filtering.

Likely safe next implementation:

- add optional `status` filtering to `AttendanceController@export()`
- update index export link to preserve `status`
- add focused controller/helper tests using isolated SQLite or query inspection
- do not change export format, attendance writes, API behavior, or real/local data
