# Phase 6C - Attendance CSV Period Display Column

Date: 2026-06-06

Scope: CSV presentation-only update. Added a separate `Period Display` column to attendance CSV export while preserving the existing raw `Period` column and all export route/query/filter behavior. No migrations, schema changes, database writes, attendance writes, API response changes, biometric sync, device commands, or full test suite were run.

## Files Inspected

- `app/Support/Attendance/AttendancePeriodPresenter.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Models/Attendance.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `routes/web.php`
- `docs/project-autopsy/PHASE_6B_ATTENDANCE_CSV_EXPORT_PERIOD_PRESENTATION_AUDIT.md`
- `docs/project-autopsy/PHASE_6A_API_ATTENDANCE_PERIOD_DISPLAY_FIELD.md`
- `docs/project-autopsy/PHASE_5Y_ATTENDANCE_PERIOD_DISPLAY_ALIGNMENT.md`

## Files Changed

- `app/Http/Controllers/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `docs/project-autopsy/PHASE_6C_ATTENDANCE_CSV_PERIOD_DISPLAY_COLUMN.md`

## CSV Header Change

`AttendanceController::exportToCsv()` now writes:

```text
Date, Class, Student Name, Roll Number, Status, Subject, Period, Period Display, Remarks, Marked By, IP Address
```

The new `Period Display` column is immediately after the existing raw `Period` column.

## CSV Row Behavior

The existing raw `Period` value remains:

```php
$attendance->period
```

The new `Period Display` value is:

```php
AttendancePeriodPresenter::display($attendance->period)
```

Expected examples:

| Raw Stored Period | CSV `Period` | CSV `Period Display` |
| --- | --- | --- |
| `NULL` | blank cell | `Full Day` |
| `Full Day` | `Full Day` | `Full Day` |
| ` Period 1 ` | ` Period 1 ` | `Period 1` |

## Raw Period Preserved

The raw `Period` column was not renamed, removed, normalized, or replaced. It still writes the stored `$attendance->period` value.

## Period Display Added

`Period Display` was added as a separate presentation column using the Phase 5X read-only helper.

The controller now imports:

```php
use App\Support\Attendance\AttendancePeriodPresenter;
```

## Export Route / Query / Filter Confirmation

Unchanged:

- route URI
- route name
- authorization
- request filters
- export query
- eager-loaded relationships
- filename
- streamed CSV response type
- UTF-8 BOM behavior

`AttendanceController@export()` still filters only by the existing optional `from_date`, `to_date`, and `class` inputs, then calls `exportToCsv($attendances)`.

## API Response Confirmation

No API controller code was changed in this phase.

Unchanged:

- API `period`
- API `period_display`
- API store/update validation
- API store/update response transformation
- API bulkMark and destroy guards

## Tests Created / Updated

Added `tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`.

The test uses in-memory Eloquent model instances and reflection to invoke the private `exportToCsv()` helper directly. It does not execute the export route and does not touch real/local MySQL.

Tests cover:

- CSV keeps raw `Period` column.
- CSV adds `Period Display` immediately after `Period`.
- Null raw period remains blank while display shows `Full Day`.
- Literal `Full Day` raw period remains `Full Day`.
- Named period raw value is preserved while display is trimmed.
- Existing `attendance.export` route URI/action remain unchanged.

## Commands Run

- `Get-Content app/Support/Attendance/AttendancePeriodPresenter.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Models/Attendance.php`
- `Get-Content docs/project-autopsy/PHASE_6B_ATTENDANCE_CSV_EXPORT_PERIOD_PRESENTATION_AUDIT.md`
- `Get-ChildItem tests/Feature/Attendance`
- `Get-Content tests/Feature/Attendance/AttendancePeriodDisplayViewTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceApiPeriodDisplayResponseTest.php`
- `Get-Content tests/TestCase.php`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l app/Support/Attendance/AttendancePeriodPresenter.php`
- `php -l tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `php artisan test --filter=AttendanceCsvPeriodDisplayExportTest --env=testing`
- `php artisan test --filter=AttendancePeriodDisplayViewTest --env=testing`
- `php artisan test --filter=AttendanceApiPeriodDisplayResponseTest --env=testing`
- `php artisan test --filter=AttendancePeriodPresenterTest --env=testing`
- `git diff -- app/Http/Controllers/AttendanceController.php tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`
- `Get-Content resources/views/attendance/index.blade.php | Select-String "attendance.export"`
- `Get-Content resources/views/attendance/reports.blade.php | Select-String "attendance.export"`
- `Get-Content routes/web.php | Select-String "attendance/export"`

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php`: passed.
- `php -l app/Support/Attendance/AttendancePeriodPresenter.php`: passed.
- `php -l tests/Feature/Attendance/AttendanceCsvPeriodDisplayExportTest.php`: passed.
- `php artisan test --filter=AttendanceCsvPeriodDisplayExportTest --env=testing`: passed, 6 tests and 15 assertions.
- `php artisan test --filter=AttendancePeriodDisplayViewTest --env=testing`: passed, 7 tests and 10 assertions.
- `php artisan test --filter=AttendanceApiPeriodDisplayResponseTest --env=testing`: passed, 7 tests and 22 assertions.
- `php artisan test --filter=AttendancePeriodPresenterTest --env=testing`: passed, 10 tests and 36 assertions.

The targeted test runs emitted unrelated PHPUnit metadata deprecation warnings from older tests during discovery. No Phase 6C test failed.

## Constraint Confirmation

- No full test suite was run.
- No migrations were run.
- No migrations or schema files were changed.
- No real/local MySQL writes were performed.
- No attendance data was normalized, repaired, inserted, updated, or deleted.
- No attendance write route was executed.
- No export route was executed.
- No CSV was generated from real/local MySQL.
- No API store/update behavior was changed.
- No API response shape was changed in this phase.
- No web store/update behavior was changed.
- No biometric sync or device command was run.

## Remaining Risks

- The export UI still offers Excel/PDF buttons, but the controller still returns CSV for all formats.
- Index and reports export links still do not pass current visible filters.
- CSV consumers now receive an additional column, which is non-destructive but still a public output change.
- Stored period values remain mixed: mostly `NULL`, plus literal `Full Day` from Phase 5W diagnostics.
- Null-period duplicate policy and legacy period write normalization remain unresolved.

## Recommended Phase 6D Next Step

Phase 6D should perform a read-only audit of the attendance export UI contract:

- decide whether Excel/PDF buttons should be hidden, disabled, or implemented
- decide whether index/reports export links should preserve current filters
- confirm whether the CSV column addition is acceptable for downstream consumers

Do this before changing export formats or route behavior.
