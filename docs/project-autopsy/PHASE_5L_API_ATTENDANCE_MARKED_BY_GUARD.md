# Phase 5L - API Attendance Marked By Guard

Date: 2026-06-05

Scope: Fix only API single attendance `marked_by` spoofing in `store()` and `update()`.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Http/Middleware/ApiAccessControl.php`
- `app/Models/Attendance.php`
- `app/Models/User.php`
- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`
- `docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_5J_API_ATTENDANCE_BULK_MARK_GUARD.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `docs/project-autopsy/PHASE_5L_API_ATTENDANCE_MARKED_BY_GUARD.md`

## Previous Marked By Spoofing Risk Summary

Phase 5K found that API single attendance writes trusted caller-supplied marker identity:

- `store()` required `marked_by` in the request payload.
- `store()` only checked that `marked_by` existed in `users`.
- `store()` saved the client-provided `marked_by`.
- `update()` accepted `marked_by` as an editable field.
- A caller with API access could attribute a new or existing attendance record to another user.

## API Store New Marked By Behavior

`API\AttendanceController@store()` now:

- gets the authenticated API user from `$request->user()`
- returns HTTP `401` if no authenticated user is available
- no longer validates `marked_by` from the client payload
- no longer requires `marked_by` in the payload
- sets `$validated['marked_by'] = $request->user()->id`
- keeps all other validation rules unchanged
- keeps the existing duplicate check unchanged
- keeps legacy `class` behavior unchanged
- does not add terminal/inactive student checks in this phase

Added code comment:

```php
// Phase 5L: marked_by is derived from authenticated API user and cannot be supplied by client.
```

## API Update New Marked By Behavior

`API\AttendanceController@update()` now:

- removes `marked_by` from validation rules
- does not allow a client-provided `marked_by` value into `$validated`
- leaves the existing attendance row's original `marked_by` unchanged
- keeps all other update behavior unchanged
- still allows `student_id` and legacy `class` mutation, because those are explicitly deferred to later phases
- does not add `updated_by`

Added code comment:

```php
// Phase 5L: marked_by is derived from authenticated API user and cannot be supplied by client.
```

## API BulkMark Guard Confirmation

The Phase 5J `bulkMark()` guard remains intact.

`bulkMark()` still returns HTTP `423` before validation or insert:

```text
API bulk attendance marking is temporarily disabled. Use attendance preflight first. Safe API bulk apply is not enabled yet.
```

No API bulk apply or preflight token behavior was added.

## API Destroy Confirmation

API `destroy()` was not changed in this phase.

The route action remains:

- `DELETE /api/v1/attendance/{attendance}`
- `API\AttendanceController@destroy`

The hard-delete risk remains for Phase 5M.

## Tests Created

Created:

- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`

Test coverage:

- `api_store_uses_authenticated_user_as_marked_by`
- `api_store_ignores_spoofed_marked_by`
- `api_store_no_longer_requires_marked_by_in_payload`
- `api_update_does_not_change_marked_by_when_payload_contains_marked_by`
- `api_bulk_mark_guard_still_returns_423`
- `api_destroy_route_action_is_not_changed_in_this_phase`

The tests use isolated SQLite-memory tables and direct controller invocation with a request user resolver. They do not use project migrations and do not touch real/local MySQL.

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content routes/api.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php
Get-Content docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md | Select-Object -First 120
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
php -l routes/api.php
php artisan route --path=api/v1/attendance
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing
php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing
git diff -- app/Http/Controllers/API/AttendanceController.php tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is only an Artisan namespace.
- `php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing`: PASS, 6 tests / 13 assertions
- `php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing`: PASS, 4 tests / 9 assertions
- `php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing`: PASS, 12 tests / 16 assertions

PHPUnit emitted unrelated existing doc-comment metadata deprecation warnings during targeted test runs. No targeted test failed.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No database schema was changed.
- No real/local MySQL data was touched.
- No biometric sync was changed or triggered.
- No biometric device command was run.
- No web attendance behavior was changed.
- No API bulkMark behavior was changed in this phase.
- No API destroy behavior was changed.
- No API preflight/apply behavior was added.

## Remaining Risks

1. API `destroy()` still hard-deletes attendance records.
2. API `update()` still allows `student_id` mutation.
3. API `update()` still allows legacy `class` mutation.
4. API `store()` and `update()` still do not exclude terminal/inactive students.
5. API `store()` duplicate check remains non-atomic.
6. API `update()` can still create duplicate `student_id,date,period` conflicts.
7. API single writes still use legacy `class` string instead of canonical class identity.
8. API resource route names remain generic (`attendance.store`, `attendance.update`, `attendance.destroy`).
9. Safe API bulk apply contract is still not implemented.
10. Existing legacy attendance rows may still require reconciliation.

## Recommended Phase 5M Next Step

Phase 5M should guard API `destroy()` with a controlled disabled response until an audit-preserving delete, void, or reversal policy exists.

Recommended behavior:

- keep the `DELETE /api/v1/attendance/{attendance}` route registered
- return a controlled `423 Locked` or `409 Conflict`
- do not call `$attendance->delete()`
- do not implement soft deletes or schema changes in the guard phase
- add an isolated test proving the route no longer deletes from SQLite-memory attendance data
