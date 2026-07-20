# Phase 5T - API Attendance Store Duplicate Exception Guard

Date: 2026-06-05

Scope: Add controlled duplicate-key `QueryException` handling to API `AttendanceController@store()` while preserving existing store behavior.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `docs/project-autopsy/PHASE_5S_API_ATTENDANCE_STORE_DUPLICATE_RACE_AUDIT.md`
- `docs/project-autopsy/PHASE_5R_API_ATTENDANCE_STORE_TERMINAL_STATUS_GUARD.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`
- `docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md`

## Previous Duplicate / Race Risk Summary

Phase 5S found:

- API `store()` duplicate pre-check is non-atomic.
- Existing app-level duplicate pre-check already returns controlled HTTP `409`.
- Race-condition duplicate DB exceptions were caught by generic exception handling.
- DB duplicate exceptions were not returned as controlled HTTP `409`.
- Nullable `period` remains a MySQL risk, but null-period policy was explicitly out of scope for Phase 5T.

## New Duplicate-Key Exception Behavior

`API\AttendanceController@store()` now catches `Illuminate\Database\QueryException` before the generic `\Exception` catch.

If the exception is detected as a duplicate attendance conflict, API `store()` returns:

- HTTP status: `409`
- Message: `Attendance already marked for this student on this date and period.`

If the `QueryException` is not a duplicate attendance conflict, the previous generic failure response shape remains:

```text
Failed to mark attendance: ...
```

## Duplicate Detection Logic

Added private helper:

```php
private function isDuplicateAttendanceException(QueryException $exception): bool
```

Detection is conservative. It requires:

- a duplicate/unique signal, such as SQLSTATE `23000`, MySQL driver code `1062`, `unique constraint failed`, or `duplicate entry`
- and an attendance identity hint, such as `attendances_student_id_date_period_unique`, `attendances.student_id`, `attendances.date`, or `student_id_date_period`

This avoids converting unrelated DB errors into duplicate attendance conflicts.

## Behavior Confirmations

App-level duplicate pre-check remains:

- The existing `student_id/date/period` `exists()` check is still in place.
- Its controlled HTTP `409` response is unchanged.

Terminal/inactive guard remains:

- Phase 5R student lookup and latest `student_statuses.id` check remain before duplicate pre-check and create.
- `passed_out`, `left_school`, `tc_issued`, and `inactive` remain blocked.

Nullable period policy unchanged:

- `period` remains nullable.
- No null-period sentinel was added.
- No schema/data policy was changed.

Legacy class behavior unchanged:

- `class` remains required in API `store()`.
- `class` remains client-supplied.
- No class mismatch validation or canonical class normalization was added.

Other API guards unchanged:

- API `update()` was not changed.
- API `destroy()` still returns HTTP `423`.
- API `bulkMark()` still returns HTTP `423`.
- No API preflight/apply behavior was added.
- `updateOrCreate()` and `firstOrCreate()` were not used.

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`

Coverage:

- `api_store_existing_duplicate_precheck_still_returns_409`
- `api_store_duplicate_database_exception_returns_controlled_409`
- `api_store_non_duplicate_query_exception_still_returns_generic_failure`
- `api_store_terminal_status_guard_still_blocks_before_duplicate_check`
- `api_store_marked_by_guard_still_uses_authenticated_user`

Test strategy:

- Uses isolated SQLite-memory schema only.
- Does not use project migrations.
- Uses direct controller invocation.
- Simulates a DB-level duplicate exception with a SQLite trigger that raises a duplicate-shaped unique constraint error after the app-level pre-check has passed.
- Keeps non-duplicate `QueryException` coverage by recreating an intentionally incomplete isolated `attendances` table and confirming the generic failure response remains.

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content routes/api.php
Get-Content tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php
Get-Content docs/project-autopsy/PHASE_5S_API_ATTENDANCE_STORE_DUPLICATE_RACE_AUDIT.md
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php
php -l routes/api.php
php artisan route --path=api/v1/attendance
php artisan test --filter=AttendanceApiStoreDuplicateHandlingTest --env=testing
php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
php artisan test --filter=AttendanceApiUpdateDatePeriodGuardTest --env=testing
php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is only an Artisan namespace.
- `php artisan test --filter=AttendanceApiStoreDuplicateHandlingTest --env=testing`: PASS, 5 tests / 13 assertions
- `php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing`: PASS, 10 tests / 26 assertions
- `php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing`: PASS, 6 tests / 13 assertions
- `php artisan test --filter=AttendanceApiUpdateDatePeriodGuardTest --env=testing`: PASS, 6 tests / 20 assertions
- `php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing`: PASS, 4 tests / 9 assertions

PHPUnit emitted unrelated existing doc-comment metadata deprecation warnings during targeted test runs. No targeted test failed.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No database schema was changed.
- No real/local MySQL data was touched.
- No attendance records were written in real data.
- No attendance records were deleted in real data.
- No biometric sync was changed or triggered.
- No biometric device command was run.
- No web attendance behavior was changed.
- No API `update()` behavior was changed.
- No API `destroy()` guard behavior was changed.
- No API `bulkMark()` guard behavior was changed.
- No API preflight/apply behavior was added.
- Nullable period policy was not changed.
- Legacy class behavior was not changed.

## Remaining Risks

1. App-level duplicate pre-check remains non-atomic.
2. Nullable `period` still weakens MySQL unique protection for full-day/no-period attendance.
3. Null-period duplicate races may still succeed silently in MySQL.
4. API `store()` still accepts client-supplied legacy `class`.
5. API `store()` still does not validate class against the student's class.
6. API `store()` still does not derive canonical class data.
7. No dedicated attendance correction workflow exists.
8. Safe API bulk apply contract does not exist.
9. API `store()` still uses inline validation rather than a FormRequest.
10. Existing historical attendance for terminal/inactive students remains editable through currently allowed update fields.

## Recommended Phase 5U Next Step

Phase 5U should perform a read-only audit of null-period attendance policy before changing it.

Recommended audit focus:

- whether omitted `period` means full-day attendance
- whether full-day attendance should be represented by `NULL`, a sentinel such as `full_day`, or a dedicated column
- how existing null-period rows should be counted and protected
- whether MySQL duplicate protection needs a generated column, functional unique index, sentinel migration, or application-level lock
- how any null-period policy would affect web, API, reports, and preflight flows
