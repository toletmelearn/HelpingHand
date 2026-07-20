# Phase 6B - Attendance CSV / Export Period Presentation Audit

Date: 2026-06-06

Scope: Read-only audit of attendance CSV/export period presentation after Phase 6A added non-breaking `period_display` to API attendance responses. No application code, routes, controllers, services, models, tests, migrations, database data, export route execution, attendance write route, biometric sync, or device command was modified or run.

## Files Inspected

- `app/Support/Attendance/AttendancePeriodPresenter.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Models/Attendance.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `resources/views/attendance/export.blade.php`
- `routes/web.php`
- `docs/project-autopsy/PHASE_6A_API_ATTENDANCE_PERIOD_DISPLAY_FIELD.md`
- `docs/project-autopsy/PHASE_5Z_ATTENDANCE_EXPORT_API_PERIOD_PRESENTATION_AUDIT.md`
- `docs/project-autopsy/PHASE_5Y_ATTENDANCE_PERIOD_DISPLAY_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_5W_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_RESULT.md`

## Commands Run

- `Get-Content app/Support/Attendance/AttendancePeriodPresenter.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Http/Controllers/API/AttendanceController.php`
- `Get-Content app/Models/Attendance.php`
- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content routes/web.php`
- `Get-Content docs/project-autopsy/PHASE_6A_API_ATTENDANCE_PERIOD_DISPLAY_FIELD.md`
- `Get-Content docs/project-autopsy/PHASE_5Z_ATTENDANCE_EXPORT_API_PERIOD_PRESENTATION_AUDIT.md`
- `Get-Content docs/project-autopsy/PHASE_5Y_ATTENDANCE_PERIOD_DISPLAY_ALIGNMENT.md`
- `Get-Content docs/project-autopsy/PHASE_5W_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_RESULT.md`
- `rg -n "attendance.*export|export.*attendance|exportToCsv|attendance\.export|Period Display|fputcsv|AttendancePeriodPresenter" tests app resources routes docs/project-autopsy`
  - Result: timed out due broad search size.
- `rg -n "exportToCsv|function export|fputcsv|attendance\.export|Export Report|Export" app/Http/Controllers/AttendanceController.php resources/views/attendance routes/web.php tests`
- `rg -n "Attendance.*Export|exportToCsv|attendance\.export|fputcsv|Period Display" tests`
  - Result: no attendance export tests found.
- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content app/Http/Controllers/AttendanceController.php | Select-Object -Skip 380 -First 80`
- `Get-Content app/Http/Controllers/AttendanceController.php | Select-Object -Skip 460 -First 25`
- `php -l app/Http/Controllers/AttendanceController.php`
  - Result: passed.
- `php -l app/Support/Attendance/AttendancePeriodPresenter.php`
  - Result: passed.
- `php artisan route | Select-String "attendance/export"`
  - Result: failed because this app exposes `route:list`; `route` is an Artisan namespace, not a route-listing command. No alternate route command was run.

No optional database counts were run in this phase.

## Export Route / UI Findings

### Route

From `routes/web.php`:

- Method: `GET`
- URI: `/attendance/export`
- Name: `attendance.export`
- Controller: `AttendanceController@export`

The route is registered before the unprefixed `Route::resource('attendance', AttendanceController::class)` route, so it is not swallowed by the resource `show` route.

### UI Links

The following views link to `attendance.export`:

- `resources/views/attendance/index.blade.php`
  - Top-level `Export` button links directly to `route('attendance.export')`.
  - It does not pass the currently selected index filters.
- `resources/views/attendance/reports.blade.php`
  - Top-level `Export Report` button links directly to `route('attendance.export')`.
  - It does not pass the current report filters.
- `resources/views/attendance/export.blade.php`
  - Contains a filter form posting as `GET` to `attendance.export` with `from_date`, `to_date`, `class`, and `format`.
  - Contains quick CSV links for last 7 days, last 30 days, and current month.
  - Offers buttons labelled CSV, Excel, and PDF, but the controller currently always returns CSV.

### Export Purpose

The export is user-facing and likely used as a report/audit artifact for school staff. The presence of CSV compatibility wording and quick report ranges suggests manual review in Excel/Google Sheets is a primary use case.

Potential re-import usage was not found for attendance exports, but it cannot be ruled out. The project has separate student import/export flows, so downstream tooling or manual reconciliation remains a contract risk.

## CSV Header / Row Findings

`AttendanceController@export(Request $request)`:

- authorizes `viewAny` for `Attendance`
- filters by optional `from_date`
- filters by optional `to_date`
- filters by optional `class`
- eager loads `student` and `markedBy`
- calls private `exportToCsv($attendances)`

`exportToCsv()` streams a CSV response with:

- `Content-Type: text/csv`
- filename pattern `attendance-report-YYYY-MM-DD.csv`
- UTF-8 BOM

Current CSV headers:

```text
Date, Class, Student Name, Roll Number, Status, Subject, Period, Remarks, Marked By, IP Address
```

Current row behavior:

| CSV Column | Current Value Source |
| --- | --- |
| Date | `$attendance->date->format('Y-m-d')` |
| Class | `$attendance->class` |
| Student Name | `$attendance->student->name ?? 'N/A'` |
| Roll Number | `$attendance->student->roll_number ?? 'N/A'` |
| Status | `ucfirst($attendance->status)` |
| Subject | `$attendance->subject` |
| Period | `$attendance->period` |
| Remarks | `$attendance->remarks` |
| Marked By | `$attendance->markedBy->name ?? 'System'` |
| IP Address | `$attendance->ip_address` |

## Current Period Export Behavior

- The CSV currently exports raw `$attendance->period`.
- There is no `Period Display` column.
- `AttendancePeriodPresenter::display()` is not used by the CSV export.
- A database `NULL` period will stream as a blank CSV cell.
- Literal stored `Full Day` will stream as `Full Day`.
- Named periods such as `Period 1` will stream raw.
- Empty string periods, if present later, would stream as blank cells and be visually indistinguishable from `NULL` in common spreadsheet tools.

This differs from the Blade and API presentation work:

- Blade show/index/reports now display full-day-like periods as `Full Day`.
- API attendance responses now add `period_display` while preserving raw `period`.
- CSV still has only the raw `Period` column.

## Contract Risk Analysis

Changing the existing `Period` column directly from raw value to display value is risky.

Potential consumers:

- school staff using the CSV as a manual audit artifact
- spreadsheet workflows expecting blank period cells for full-day attendance
- downstream scripts that treat the `Period` column as the stored database value
- future re-import or reconciliation tooling that needs raw values

Important local data context from Phase 5W:

- total attendance rows: 104
- `period IS NULL`: 98
- `period = ''`: 0
- literal `Full Day`: 6
- duplicate exact groups: 0
- duplicate null-period groups: 0

Because the current local dataset mixes canonical `NULL` full-day storage with literal `Full Day`, preserving the raw column matters for auditability. Replacing raw `NULL` blanks with `Full Day` in the same column would make the export friendlier, but it would hide the distinction between stored `NULL` and stored literal `Full Day`.

Safest approach:

- preserve existing raw `Period`
- add a separate `Period Display` column
- use `AttendancePeriodPresenter::display($attendance->period)` for that new column

This mirrors the Phase 6A API approach: keep raw `period` unchanged and add a non-breaking display field.

## Existing Export Test Findings

Search found no attendance-specific CSV export test covering:

- route `attendance.export`
- `AttendanceController::exportToCsv()`
- CSV headers
- raw `Period`
- future `Period Display`

The test suite does have student export tests, but those are separate student import/export surfaces and do not cover attendance export.

Testing Phase 6C should be feasible without real/local MySQL:

- create isolated SQLite-memory tables if route execution is used
- or use reflection against the private `exportToCsv()` helper with in-memory `Attendance` models and loaded `student` / `markedBy` relations
- capture the streamed response callback
- assert headers and row values

Reflection on the helper may be the smallest targeted test because it avoids policy/auth/view setup and does not execute the real export route against local data.

## Risk Classification

### RED

- None requiring immediate data or write-path intervention in this display-only scope.

### YELLOW

- CSV `Period` currently exposes raw values only, so `NULL` appears blank while literal `Full Day` appears as text.
- Blank CSV cells cannot distinguish `NULL` from empty string in common spreadsheet tools.
- The export UI offers Excel/PDF buttons, but the controller currently returns CSV for all formats.
- Index/reports export links do not pass current filters, which can surprise users expecting the visible report to be exported.
- No attendance export tests currently lock the CSV contract.
- Replacing the raw `Period` column directly could break downstream users or hide stored-value differences.

### GREEN

- Export route is specific and registered before the attendance resource route.
- CSV export is centralized in `AttendanceController::exportToCsv()`.
- `AttendancePeriodPresenter` already provides a tested display method.
- Phase 6A established the compatibility pattern: keep raw value and add display value.
- No duplicate null-period groups were detected in Phase 5W local diagnostics.

## Recommended Phase 6C First Code Task

Phase 6C should add a separate `Period Display` column to attendance CSV export while preserving the existing raw `Period` column unchanged.

Suggested implementation:

- import/use `AttendancePeriodPresenter` in `AttendanceController`
- change CSV headers from:

```text
..., Subject, Period, Remarks, ...
```

to:

```text
..., Subject, Period, Period Display, Remarks, ...
```

- keep row `Period` as `$attendance->period`
- add row `Period Display` as `AttendancePeriodPresenter::display($attendance->period)`
- add isolated CSV export tests verifying:
  - raw `Period` remains raw
  - null raw period remains blank in the raw column
  - `Period Display` shows `Full Day` for null period
  - literal `Full Day` remains raw and displays `Full Day`
  - named periods such as `Period 1` remain raw and display trimmed

Do not change write behavior, database values, API response shape, or export route semantics in Phase 6C.

## Safety Confirmation

- No application code was modified.
- No routes were modified.
- No controllers, services, models, tests, or migrations were modified.
- Only this report was created.
- No export route was executed.
- No CSV was generated from real/local MySQL.
- No optional read-only database count was run.
- No attendance write route was executed.
- No API store/update/destroy/bulkMark route was executed.
- No attendance data was inserted, updated, deleted, normalized, repaired, seeded, synced, or imported.
- No biometric sync or device command was run.
- No migration command was run.
- No full test suite was run.
