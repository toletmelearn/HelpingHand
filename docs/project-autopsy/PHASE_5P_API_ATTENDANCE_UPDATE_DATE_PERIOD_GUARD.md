# Phase 5P - API Attendance Update Date/Period Guard

Date: 2026-06-05

Scope: Prevent API attendance `update()` from mutating `date` and `period` until a dedicated correction workflow exists.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`
- `docs/project-autopsy/PHASE_5O_API_ATTENDANCE_DATE_PERIOD_DUPLICATE_AUDIT.md`
- `docs/project-autopsy/PHASE_5N_API_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php`
- `docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md`

## Previous API Update Date/Period Duplicate Risk Summary

Phase 5O found:

- API `update()` still allowed `date` mutation.
- API `update()` still allowed `period` mutation.
- No duplicate conflict check existed before update.
- Updating `date` or `period` could collide with another row having the same `student_id,date,period`.
- `period` is nullable, weakening reliance on DB uniqueness for full-day/no-period records in MySQL.
- Date/period changes are correction-like operations and should not remain ordinary update fields without a dedicated workflow.

## New Update Behavior

`API\AttendanceController@update()` no longer validates `date` or `period` for update.

It also explicitly unsets blocked identity/date/period fields before mass assignment:

```php
// Phase 5P: update cannot mutate attendance identity/date/period fields; corrections need a dedicated workflow.
unset($validated['student_id'], $validated['class'], $validated['date'], $validated['period']);
// Phase 5L: marked_by is derived from authenticated API user and cannot be supplied by client.
unset($validated['marked_by']);
```

## Fields Blocked From Update

Blocked client mutation:

- `student_id`
- `class`
- `date`
- `period`
- `marked_by`

## Fields Still Allowed

Still allowed in API `update()`:

- `status`
- `remarks`
- `subject`
- `session`

## Guard Confirmations

Identity/class/marked_by guards remain:

- API `update()` cannot mutate `student_id`.
- API `update()` cannot mutate legacy `class`.
- API `update()` cannot mutate `marked_by`.
- API `store()` still derives `marked_by` from authenticated API user.

Destroy guard remains:

- API `destroy()` returns HTTP `423`.
- `$attendance->delete()` remains unreachable.

BulkMark guard remains:

- API `bulkMark()` returns HTTP `423`.
- bulk insert remains unreachable.

## Tests Created

Created:

- `tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php`

Test coverage:

- `api_update_does_not_change_date_when_payload_contains_date`
- `api_update_does_not_change_period_when_payload_contains_period`
- `api_update_still_allows_status_remarks_subject_session_changes`
- `api_update_still_cannot_change_student_id_class_or_marked_by`
- `api_destroy_guard_still_returns_423`
- `api_bulk_mark_guard_still_returns_423`

The tests use isolated SQLite-memory tables and direct controller invocation. They do not use project migrations and do not touch real/local MySQL.

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content routes/api.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content app/Models/Attendance.php
Get-Content tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php
php -l routes/api.php
php artisan route --path=api/v1/attendance
php artisan test --filter=AttendanceApiUpdateDatePeriodGuardTest --env=testing
php artisan test --filter=AttendanceApiUpdateIdentityGuardTest --env=testing
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing
php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing
git diff -- app/Http/Controllers/API/AttendanceController.php tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is only an Artisan namespace.
- `php artisan test --filter=AttendanceApiUpdateDatePeriodGuardTest --env=testing`: PASS, 6 tests / 20 assertions
- `php artisan test --filter=AttendanceApiUpdateIdentityGuardTest --env=testing`: PASS, 6 tests / 18 assertions
- `php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing`: PASS, 6 tests / 13 assertions
- `php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing`: PASS, 5 tests / 10 assertions
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
- No API store behavior was changed in this phase.
- No API destroy guard behavior was changed.
- No API bulkMark guard behavior was changed.
- No API preflight/apply behavior was added.
- No terminal/inactive policy was added.

## Remaining Risks

1. API `store()` duplicate check remains non-atomic.
2. API `store()` still does not exclude terminal/inactive students.
3. API `update()` can still edit existing attendance rows for terminal/inactive students.
4. API single writes still use legacy `class` string in `store()`.
5. API resource route names remain generic (`attendance.store`, `attendance.update`, `attendance.destroy`).
6. Safe API bulk apply contract is still not implemented.
7. Audit-preserving attendance correction workflow is still not implemented.
8. Date/period correction is now blocked through `update()` but has no replacement workflow yet.
9. Existing legacy attendance rows may still require reconciliation.
10. `subject` remains editable but is not part of attendance uniqueness policy.

## Recommended Phase 5Q Next Step

Phase 5Q should perform a read-only audit of API `store()` terminal/inactive exclusion and non-atomic duplicate behavior.

Recommended audit focus:

- whether API `store()` should reject terminal/inactive students before create
- whether store should move from pre-check-plus-create to transactional create/upsert with controlled duplicate handling
- whether nullable `period` requires normalized sentinel handling or explicit null-period duplicate policy
- whether `store()` should continue to accept legacy `class` before canonical class normalization
