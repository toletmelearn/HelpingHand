# Phase 6D - Attendance Export UI Contract Audit

Date: 2026-06-06

Scope: Read-only audit of the attendance export UI contract after Phase 6C added a `Period Display` CSV column. No application code, routes, controllers, views, services, models, tests, migrations, database data, export route, attendance write route, API write route, biometric sync, or device command was modified or executed.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Support/Attendance/AttendancePeriodPresenter.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `resources/views/attendance/export.blade.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `docs/project-autopsy/PHASE_6C_ATTENDANCE_CSV_PERIOD_DISPLAY_COLUMN.md`
- `docs/project-autopsy/PHASE_6B_ATTENDANCE_CSV_EXPORT_PERIOD_PRESENTATION_AUDIT.md`

## Commands Run

- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Support/Attendance/AttendancePeriodPresenter.php`
- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content routes/web.php`
- `Get-Content tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `Get-Content docs/project-autopsy/PHASE_6C_ATTENDANCE_CSV_PERIOD_DISPLAY_COLUMN.md`
- `Get-Content docs/project-autopsy/PHASE_6B_ATTENDANCE_CSV_EXPORT_PERIOD_PRESENTATION_AUDIT.md`
- `rg -n "attendance\.export|Export as|format|from_date|to_date|Filter by Class|Export Report|Export" resources/views/attendance app/Http/Controllers/AttendanceController.php routes/web.php tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `php -l app/Http/Controllers/AttendanceController.php`
  - Result: passed.
- `php -l app/Support/Attendance/AttendancePeriodPresenter.php`
  - Result: passed.
- `php artisan route:list | Select-String "attendance/export"`
  - Result: listed `attendance/export`, plus teacher/admin attendance export routes.

No optional database checks were run.

## Export UI Entry Point Findings

### `resources/views/attendance/index.blade.php`

Entry point:

```php
route('attendance.export')
```

Label:

```text
Export
```

Findings:

- The button is a GET link.
- It is safe in HTTP method terms.
- It does not pass active index filters.
- The index page filters by `date`, `class`, and `status`.
- The button label does not say CSV, even though the controller returns CSV.

### `resources/views/attendance/reports.blade.php`

Entry point:

```php
route('attendance.export')
```

Label:

```text
Export Report
```

Findings:

- The button is a GET link.
- It is safe in HTTP method terms.
- It does not pass active report filters.
- The reports page filters by `date` and `class`.
- The button label does not say CSV, even though the controller returns CSV.

### `resources/views/attendance/export.blade.php`

Entry points:

- Main form:

```php
method="GET" action="{{ route('attendance.export') }}"
```

- Quick links:

```php
route('attendance.export', ['format' => 'csv', 'from_date' => ..., 'to_date' => ...])
```

Labels:

- `Export as CSV`
- `Export as Excel`
- `Export as PDF`
- `Last 7 Days (CSV)`
- `Last 30 Days (CSV)`
- `This Month (CSV)`

Findings:

- The main form is GET-only and safe in HTTP method terms.
- The form passes supported date/class filters plus a `format` parameter.
- The quick links pass `format=csv`, `from_date`, and `to_date`.
- The main form exposes active Excel and PDF submit buttons.
- The page text says users can choose a preferred export format.
- The informational text says Excel files preserve formatting/formulas and PDF exports are ideal for printing/sharing.
- These Excel/PDF promises are not implemented in `AttendanceController@export()`.

## Controller Format Behavior Findings

`AttendanceController@export(Request $request)` currently:

- authorizes `viewAny`
- builds an `Attendance::query()`
- applies `from_date` if present
- applies `to_date` if present
- applies `class` if present
- eager loads `student` and `markedBy`
- always returns `$this->exportToCsv($attendances)`

Format behavior:

- The `format` request parameter is not read.
- No CSV/Excel/PDF switch exists.
- No Excel branch exists.
- No PDF branch exists.
- The controller always returns CSV.
- The response content type is always `text/csv`.
- The filename always ends in `.csv`.
- The CSV UTF-8 BOM behavior remains fixed.

Conclusion:

The Excel and PDF buttons are currently misleading because submitting them sends `format=excel` or `format=pdf`, but the server still returns a CSV file.

## Filter Preservation Findings

### Index Filters

`AttendanceController@index()` supports:

- `date`
- `class`
- `status`

`resources/views/attendance/index.blade.php` filter form submits those to `attendance.index`.

Index export button:

```php
route('attendance.export')
```

Findings:

- Active index filters are not passed.
- `status` is not supported by `AttendanceController@export()`.
- A user viewing filtered rows may export a broader dataset than the visible table.

### Reports Filters

`AttendanceController@reports()` supports:

- `date`
- `class`

`resources/views/attendance/reports.blade.php` filter form submits those to `attendance.reports`.

Reports export button:

```php
route('attendance.export')
```

Findings:

- Active report filters are not passed.
- Reports use a single `date` value, while export supports `from_date` and `to_date`.
- A user viewing a report for one date/class may export a broader dataset than the visible report.

### Export Controller Filters

`AttendanceController@export()` supports:

- `from_date`
- `to_date`
- `class`

It does not support:

- `date`
- `status`
- `format`

### Export Page Filters

`resources/views/attendance/export.blade.php` passes:

- `from_date`
- `to_date`
- `class`
- `format`

Findings:

- Date range and class map to controller-supported filters.
- `format` is ignored.
- The export page form is the closest match to the actual controller contract, except for Excel/PDF format promises.

## Contract Risk Analysis

### Option A: Hide or disable Excel/PDF buttons until implemented

Risk: low.

Benefits:

- Aligns UI with current controller behavior.
- Avoids users downloading CSV after choosing Excel/PDF.
- Does not alter export route, output, filters, query, or data.

This is the safest first fix.

### Option B: Keep Excel/PDF buttons but display "Coming soon"

Risk: low to moderate.

Benefits:

- Keeps future capability visible.
- Avoids false downloads if buttons are disabled.

Concern:

- Disabled controls can still clutter the export surface if implementation is not near-term.

### Option C: Implement Excel/PDF now

Risk: moderate to high.

Concerns:

- Requires dependency and formatting review.
- Needs new tests.
- Changes public export behavior.
- May interact with large dataset memory/performance concerns.

Not recommended as the next phase.

### Option D: Update index/reports export links to preserve filters

Risk: low to moderate.

Benefits:

- Better matches user expectations.

Concerns:

- Index has `status`, but export does not currently support `status`.
- Reports use `date`, while export expects `from_date`/`to_date`.
- Needs a small design decision about mapping single-date report filters to date ranges.

Good candidate after UI format contract is corrected.

### Option E: Add explicit CSV label everywhere

Risk: low.

Benefits:

- Makes current behavior clear.
- Pairs naturally with hiding/disabling Excel/PDF buttons.

Recommended as part of Phase 6E.

## Recommended Phase 6E First Code Task

Phase 6E should update attendance export UI labels/buttons only:

- make CSV the only active export format
- change generic `Export` / `Export Report` labels to make CSV explicit where practical
- hide or disable `Export as Excel` and `Export as PDF`
- remove or revise text that promises Excel/PDF behavior
- do not change `AttendanceController@export()`
- do not change routes
- do not change filters
- do not add new export formats
- do not execute the export route against real/local MySQL

Then Phase 6F should separately handle filter preservation for index/reports export links.

## Safety Confirmation

- No application code was modified.
- No routes were modified.
- No controllers were modified.
- No views were modified.
- No services, models, tests, or migrations were modified.
- Only this report was created.
- No export route was executed.
- No CSV/Excel/PDF was generated from real/local MySQL.
- No database checks were run.
- No attendance write route was executed.
- No API store/update/destroy/bulkMark route was executed.
- No data was normalized, repaired, inserted, updated, or deleted.
- No biometric sync or device command was run.
- No migration command was run.
- No full test suite was run.
