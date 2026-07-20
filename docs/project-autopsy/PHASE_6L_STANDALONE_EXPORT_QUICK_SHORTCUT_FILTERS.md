# Phase 6L - Standalone Export Quick Shortcut Filters

## Scope

Phase 6L updated the standalone attendance export page quick shortcut links so they preserve selected class and allowlisted status filters when present.

This phase did not change `AttendanceController@export()`, routes, CSV headers, CSV row format, CSV period behavior, attendance write behavior, API behavior, migrations, schema, or real/local MySQL data.

## Files Inspected

- `resources/views/attendance/export.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceStandaloneExportStatusDropdownTest.php`
- `tests/Feature/Attendance/AttendanceExportStatusFilterTest.php`
- `docs/project-autopsy/PHASE_6K_STANDALONE_EXPORT_QUICK_SHORTCUT_AUDIT.md`
- `docs/project-autopsy/PHASE_6J_STANDALONE_EXPORT_STATUS_DROPDOWN.md`
- `docs/project-autopsy/PHASE_6H_ATTENDANCE_EXPORT_STATUS_FILTER_SUPPORT.md`

## Files Changed

- `resources/views/attendance/export.blade.php`
- `tests/Feature/Attendance/AttendanceStandaloneExportQuickShortcutTest.php`
- `docs/project-autopsy/PHASE_6L_STANDALONE_EXPORT_QUICK_SHORTCUT_FILTERS.md`

## Quick Shortcut Filter Behavior

The standalone export page now builds shared quick-export filter parameters before rendering the quick links.

Shared filter behavior:

- includes `class` when `request()->filled('class')`
- includes `status` only when it is allowlisted
- omits unsupported status values

The three quick shortcuts still provide their own date ranges:

- `Last 7 Days (CSV)`
- `Last 30 Days (CSV)`
- `This Month (CSV)`

Each shortcut still includes:

- `format=csv`
- its own `from_date`
- its own `to_date`

## Class Preservation Behavior

When the standalone export page is loaded with a selected class, each quick shortcut now includes:

- `class=<selected class>`

Example:

```text
/attendance/export?class=Class%201
```

Quick shortcut links include:

```text
class=Class 1
```

## Status Preservation Behavior

When the standalone export page is loaded with an allowlisted status, each quick shortcut now includes:

- `status=<selected status>`

Allowlisted statuses:

- `present`
- `absent`
- `late`
- `half_day`

Example:

```text
/attendance/export?status=late
```

Quick shortcut links include:

```text
status=late
```

## Unsupported Status Behavior

Unsupported status values are not preserved in quick shortcut links.

Example:

```text
/attendance/export?class=Class%201&status=unexpected
```

Quick shortcut links preserve:

- `class=Class 1`

Quick shortcut links omit:

- `status`

This matches the controller's Phase 6H behavior, where unsupported status values are ignored safely.

## Date Range Behavior Confirmation

The quick shortcuts keep their original date range behavior.

`Last 7 Days (CSV)`:

- `from_date=now()->subDays(7)->toDateString()`
- `to_date=now()->toDateString()`

`Last 30 Days (CSV)`:

- `from_date=now()->subDays(30)->toDateString()`
- `to_date=now()->toDateString()`

`This Month (CSV)`:

- `from_date=now()->startOfMonth()->toDateString()`
- `to_date=now()->endOfMonth()->toDateString()`

The new tests pin `now()` to `2026-06-06` and confirm:

- Last 7 Days: `2026-05-30` to `2026-06-06`
- Last 30 Days: `2026-05-07` to `2026-06-06`
- This Month: `2026-06-01` to `2026-06-30`

## CSV-Only UI Confirmation

The standalone export page remains CSV-only.

Still active:

- `Export CSV`
- `name="format" value="csv"`

Still disabled:

- Excel button
- PDF button

Helper text remains:

```text
Excel and PDF export are not enabled yet.
```

A concise quick shortcut helper was added:

```text
Quick exports use the selected class/status filters when available.
```

## Controller / Export Behavior Unchanged

`AttendanceController@export()` was not changed.

Unchanged:

- export route URI/name
- controller filters
- status allowlist
- unsupported status behavior
- CSV headers
- CSV row order
- raw `Period`
- `Period Display`
- filename
- response type
- CSV BOM behavior

No export route was executed against real/local MySQL.

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceStandaloneExportQuickShortcutTest.php`

Coverage:

- quick shortcuts preserve selected class
- quick shortcuts preserve allowlisted status
- quick shortcuts do not preserve unsupported status
- quick shortcuts keep `format=csv`
- quick shortcuts keep expected date ranges
- quick shortcuts keep CSV-only UI

## Commands Run

- `Get-Content resources/views/attendance/export.blade.php`
- `Get-Content tests/Feature/Attendance/AttendanceStandaloneExportStatusDropdownTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceExportStatusFilterTest.php`
- `Get-Content docs/project-autopsy/PHASE_6K_STANDALONE_EXPORT_QUICK_SHORTCUT_AUDIT.md`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l tests/Feature/Attendance/AttendanceStandaloneExportQuickShortcutTest.php`
- `php artisan test --filter=AttendanceStandaloneExportQuickShortcutTest --env=testing`
- `php artisan test --filter=AttendanceStandaloneExportStatusDropdownTest --env=testing`
- `php artisan test --filter=AttendanceExportStatusFilterTest --env=testing`
- `php artisan test --filter=AttendanceExportUiContractTest --env=testing`

## Test Result Summary

Passed:

- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l tests/Feature/Attendance/AttendanceStandaloneExportQuickShortcutTest.php`
- `php artisan test --filter=AttendanceStandaloneExportQuickShortcutTest --env=testing`
  - 6 tests passed
- `php artisan test --filter=AttendanceStandaloneExportStatusDropdownTest --env=testing`
  - 7 tests passed
- `php artisan test --filter=AttendanceExportStatusFilterTest --env=testing`
  - 7 tests passed
- `php artisan test --filter=AttendanceExportUiContractTest --env=testing`
  - 7 tests passed

PHPUnit emitted unrelated deprecation warnings about metadata in doc-comments in existing tests. These warnings were not introduced by Phase 6L and did not fail the targeted tests.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No real/local MySQL data was touched.
- No export route was executed against real/local MySQL.
- No CSV/Excel/PDF was generated from real/local MySQL.
- No attendance write behavior was changed.
- No API behavior was changed.
- No controller export behavior was changed.
- No export route URI/name was changed.
- No CSV headers or row format were changed.
- No biometric sync or device command was run.

## Remaining Risks

- Unsupported status values are still silently omitted/ignored rather than shown as validation feedback.
- Export filtering still lives directly in the controller rather than a dedicated request/filter object.
- Reports export remains date/class-only because reports has no visible status filter.
- Quick shortcuts now preserve selected class/status, but they still override only the date range by design.

## Recommended Phase 6M Next Step

Phase 6M should perform a read-only audit of the remaining attendance export contract:

- whether unsupported status should remain ignored or become a validation warning
- whether export filter logic should be extracted to a small shared helper/FormRequest later
- whether reports should ever gain status filtering or remain date/class-only
