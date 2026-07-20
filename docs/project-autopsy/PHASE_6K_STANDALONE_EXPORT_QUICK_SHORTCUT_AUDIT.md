# Phase 6K - Standalone Export Quick Shortcut Audit

## Scope

This was a read-only audit of the standalone attendance export page quick shortcut links after Phase 6J added the status dropdown.

No application code, views, controllers, routes, tests, migrations, database data, export output, attendance writes, or biometric/device behavior were modified or executed.

## Files Inspected

- `resources/views/attendance/export.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceStandaloneExportStatusDropdownTest.php`
- `tests/Feature/Attendance/AttendanceExportStatusFilterTest.php`
- `docs/project-autopsy/PHASE_6J_STANDALONE_EXPORT_STATUS_DROPDOWN.md`
- `docs/project-autopsy/PHASE_6H_ATTENDANCE_EXPORT_STATUS_FILTER_SUPPORT.md`
- `docs/project-autopsy/PHASE_6F_ATTENDANCE_EXPORT_FILTER_PRESERVATION.md`

## Commands Run

- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content tests/Feature/Attendance/AttendanceStandaloneExportStatusDropdownTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceExportStatusFilterTest.php`
- `Get-Content docs/project-autopsy/PHASE_6J_STANDALONE_EXPORT_STATUS_DROPDOWN.md`
- `Get-Content docs/project-autopsy/PHASE_6H_ATTENDANCE_EXPORT_STATUS_FILTER_SUPPORT.md`
- `Get-Content docs/project-autopsy/PHASE_6F_ATTENDANCE_EXPORT_FILTER_PRESERVATION.md`
- `rg -n "Quick Export|Last 7 Days|Last 30 Days|This Month|attendance\.export|from_date|to_date|class|status|EXPORT_STATUS_FILTERS|function export" resources/views/attendance/export.blade.php resources/views/attendance/index.blade.php resources/views/attendance/reports.blade.php app/Http/Controllers/AttendanceController.php routes/web.php tests/Feature/Attendance/AttendanceStandaloneExportStatusDropdownTest.php tests/Feature/Attendance/AttendanceExportStatusFilterTest.php`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php artisan route | Select-String "attendance/export"`

Notes:

- `php -l app/Http/Controllers/AttendanceController.php` passed.
- The exact allowed route command `php artisan route | Select-String "attendance/export"` did not list route rows. Laravel treated `route` as a namespace and printed route command help with available commands such as `route:list`. No substitute route command was run.

## Quick Shortcut Findings

`resources/views/attendance/export.blade.php` has three quick export shortcuts under `Quick Export Options`.

### Last 7 Days

Label:

- `Last 7 Days (CSV)`

Route:

- `attendance.export`

Query parameters:

- `format=csv`
- `from_date=now()->subDays(7)->toDateString()`
- `to_date=now()->toDateString()`

Does not pass:

- `class`
- `status`

### Last 30 Days

Label:

- `Last 30 Days (CSV)`

Route:

- `attendance.export`

Query parameters:

- `format=csv`
- `from_date=now()->subDays(30)->toDateString()`
- `to_date=now()->toDateString()`

Does not pass:

- `class`
- `status`

### This Month

Label:

- `This Month (CSV)`

Route:

- `attendance.export`

Query parameters:

- `format=csv`
- `from_date=now()->startOfMonth()->toDateString()`
- `to_date=now()->endOfMonth()->toDateString()`

Does not pass:

- `class`
- `status`

## Current Shortcut Contract

All three quick shortcuts are direct date-range CSV links.

They currently:

- pass `format=csv`
- pass `from_date`
- pass `to_date`
- do not preserve selected class
- do not preserve selected status

The labels are date-range-only and do not explicitly say whether class/status filters are included or ignored.

Because the main export form now exposes class and status, the quick shortcuts may be interpreted as operating within the currently selected filters. In reality, they export broad date-range data across all classes and statuses.

## Main Form vs Shortcut Contract Findings

Main export form supports:

- `from_date`
- `to_date`
- `class`
- `status`
- active CSV `format=csv`

Quick shortcut links support:

- `from_date`
- `to_date`
- active CSV `format=csv`

Quick shortcut links do not support:

- currently selected `class`
- currently selected `status`

Potential confusion:

- A user can select a class/status in the form, then click `Last 7 Days (CSV)` and receive a broader export than expected.
- The quick shortcut section does not currently explain that the shortcuts ignore selected class/status filters.

Safer user contract options:

- preserve selected class/status in quick shortcut URLs when valid request values are present
- or make shortcut labels explicit, such as `Last 7 Days - All Classes/Statuses (CSV)`

Preserving selected class/status better matches the current direction of prior phases, where export links try to preserve visible filters when supported.

## Controller Compatibility Findings

`AttendanceController@export()` already supports:

- `from_date`
- `to_date`
- `class`
- `status`

Status behavior:

- allowed values: `present`, `absent`, `late`, `half_day`
- allowlisted status applies `where('status', $request->status)`
- unsupported status values are ignored safely

Therefore quick links can safely pass `class` and allowlisted `status` without controller changes.

No CSV contract change is needed:

- CSV headers stay unchanged
- CSV row order stays unchanged
- raw `Period` and `Period Display` stay unchanged
- route URI/name stay unchanged
- response type stays CSV

## Risk Classification

### RED

- None. This is an export UI/link contract issue, not an attendance write path.

### YELLOW

- Quick shortcuts ignore selected class/status while the main form now includes those filters.
- Shortcut labels do not clarify that they are broad all-class/all-status date-range exports.
- Users may receive broader CSV output than expected after selecting class/status.

### GREEN

- Controller already supports class/status.
- Status filtering is already allowlisted.
- Unsupported status values are ignored safely.
- Updating quick links can be a view-only change.
- Tests can remain view-rendering only.
- No controller/export behavior change is required.

## Recommended Phase 6L First Code Task

Phase 6L should preserve selected class/status in standalone quick export shortcut links when request values are present and supported.

Recommended implementation:

1. In `resources/views/attendance/export.blade.php`, build shared quick-link filter params:
   - include `class` when `request()->filled('class')`
   - include `status` only when it is one of:
     - `present`
     - `absent`
     - `late`
     - `half_day`
2. Merge those params into each quick shortcut:
   - `Last 7 Days (CSV)`
   - `Last 30 Days (CSV)`
   - `This Month (CSV)`
3. Keep each shortcut's date range behavior unchanged.
4. Keep CSV-only UI unchanged.
5. Do not change `AttendanceController@export()`.
6. Add view-only tests proving:
   - quick shortcuts preserve selected class
   - quick shortcuts preserve allowlisted status
   - quick shortcuts do not preserve unsupported status
   - date ranges and `format=csv` remain present
   - controller/export behavior remains unchanged

Optional wording:

- Add concise helper text that quick shortcuts use selected class/status filters when available.

## Confirmation

- No application code was modified.
- No views were modified.
- No controllers were modified.
- No routes were modified.
- No tests were modified.
- No migrations or schema were touched.
- No database data was read or modified.
- No export route was executed.
- No CSV/Excel/PDF was generated from real/local MySQL.
- No attendance write route was executed.
- No API attendance write route was executed.
- No biometric sync or device command was run.
- No full test suite was run.
