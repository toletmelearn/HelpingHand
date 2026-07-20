# Phase 5N - API Attendance Update Identity Guard

Date: 2026-06-05

Scope: Prevent API attendance `update()` from mutating attendance identity/class fields while keeping normal editable fields working.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`
- `docs/project-autopsy/PHASE_5M_API_ATTENDANCE_DESTROY_GUARD.md`
- `docs/project-autopsy/PHASE_5L_API_ATTENDANCE_MARKED_BY_GUARD.md`
- `docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php`
- `docs/project-autopsy/PHASE_5N_API_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`

## Previous API Update Over-Mutation Risk Summary

Phase 5K and Phase 5M identified that API `update()` could mutate too many fields:

- `student_id` could be changed, reassigning an attendance row to another student.
- legacy `class` could be changed, creating class attribution drift.
- `marked_by` was already blocked in Phase 5L.
- `date` and `period` can still be changed and may create duplicate conflicts; that risk is intentionally deferred to a later phase.

## New Update Behavior

`API\AttendanceController@update()` now removes `student_id` and `class` from update validation.

It also explicitly unsets blocked identity/class fields before mass assignment:

```php
// Phase 5N: update cannot mutate attendance identity/class fields.
unset($validated['student_id'], $validated['class']);
// Phase 5L: marked_by is derived from authenticated API user and cannot be supplied by client.
unset($validated['marked_by']);
```

## Fields Blocked From Update

Blocked client mutation:

- `student_id`
- `class`
- `marked_by`

`marked_by` was already blocked by Phase 5L and remains blocked.

## Fields Still Allowed

Still allowed in API `update()`:

- `date`
- `period`
- `status`
- `remarks`
- `subject`
- `session`

This phase intentionally did not change `date` or `period` handling. Duplicate conflict checks for date/period mutation remain a later-phase risk.

## Guard Confirmations

Marked-by guard remains:

- API `store()` derives `marked_by` from authenticated API user.
- API `update()` cannot mutate `marked_by`.

Destroy guard remains:

- API `destroy()` returns HTTP `423`.
- `$attendance->delete()` is bypassed.

BulkMark guard remains:

- API `bulkMark()` returns HTTP `423`.
- bulk insert remains unreachable.

## Tests Created

Created:

- `tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php`

Test coverage:

- `api_update_does_not_change_student_id_when_payload_contains_student_id`
- `api_update_does_not_change_legacy_class_when_payload_contains_class`
- `api_update_still_allows_status_remarks_subject_session_changes`
- `api_update_still_cannot_change_marked_by`
- `api_destroy_guard_still_returns_423`
- `api_bulk_mark_guard_still_returns_423`

The tests use isolated SQLite-memory tables and direct controller invocation. They do not use project migrations and do not touch real/local MySQL.

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content routes/api.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content app/Models/Attendance.php
Get-Content tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php
php -l routes/api.php
php artisan route --path=api/v1/attendance
php artisan test --filter=AttendanceApiUpdateIdentityGuardTest --env=testing
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing
php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing
git diff -- app/Http/Controllers/API/AttendanceController.php tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is only an Artisan namespace.
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

1. API `update()` still allows `date` mutation.
2. API `update()` still allows `period` mutation.
3. Date/period mutation can still create duplicate `student_id,date,period` conflicts.
4. API `store()` duplicate check remains non-atomic.
5. API `store()` and `update()` still do not exclude terminal/inactive students.
6. API single writes still use legacy `class` string in `store()`.
7. API resource route names remain generic (`attendance.store`, `attendance.update`, `attendance.destroy`).
8. Safe API bulk apply contract is still not implemented.
9. Audit-preserving attendance correction workflow is still not implemented.
10. Existing legacy attendance rows may still require reconciliation.

## Recommended Phase 5O Next Step

Phase 5O should perform a read-only audit of API `update()` date/period mutation and duplicate-conflict behavior.

Recommended audit focus:

- whether `date` and `period` should be mutable through API update
- whether update can collide with an existing attendance row for the same `student_id,date,period`
- whether to guard date/period mutation entirely before designing a normalized correction workflow
- whether API single writes should move toward preflight-backed validation before further writes are allowed
