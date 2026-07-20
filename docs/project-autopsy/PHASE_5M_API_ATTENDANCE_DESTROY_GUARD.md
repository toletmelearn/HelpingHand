# Phase 5M - API Attendance Destroy Guard

Date: 2026-06-05

Scope: Guard API attendance deletion so `destroy()` no longer hard-deletes attendance records.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`
- `docs/project-autopsy/PHASE_5L_API_ATTENDANCE_MARKED_BY_GUARD.md`
- `docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_5J_API_ATTENDANCE_BULK_MARK_GUARD.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php`
- `docs/project-autopsy/PHASE_5M_API_ATTENDANCE_DESTROY_GUARD.md`

## Previous API Destroy Hard-Delete Risk Summary

Phase 5K found:

- API `destroy()` called `Attendance::findOrFail($id)` and then `$attendance->delete()`.
- `Attendance` does not use `SoftDeletes`.
- Deleting through the API therefore removed attendance rows from the table.
- No audit-preserving correction, void, or reversal workflow exists yet.
- No delete reason, `deleted_by`, or correction trail is captured.

## New Disabled Response Behavior

`API\AttendanceController@destroy()` now returns immediately before `findOrFail()` and before any delete call:

```php
// Phase 5M: hard delete disabled until audit-preserving attendance correction workflow exists.
return $this->error(
    'API attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.',
    423
);
```

This means:

- `$attendance->delete()` is not called.
- `Attendance::destroy()` is not called.
- `forceDelete()` is not called.
- no transaction is started.
- no attendance row is deleted by this method.

## Status Code Used

HTTP status: `423 Locked`

JSON response shape uses the existing API error helper:

- `success: false`
- `message: API attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.`
- `errors: null`
- `timestamp: ...`

## Route / URI / Name Preserved

The route remains registered through `Route::apiResource('attendance', AttendanceController::class)`.

Preserved route behavior:

- Method: `DELETE`
- URI: `/api/v1/attendance/{attendance}`
- Route name: `attendance.destroy`
- Controller action: `API\AttendanceController@destroy`

No route was removed, renamed, or reordered in this phase.

## API Store / Update Confirmation

No API `store()` or `update()` behavior was changed in Phase 5M.

The Phase 5L marked-by guard remains:

- `store()` derives `marked_by` from the authenticated API user.
- `update()` cannot mutate `marked_by`.

## API BulkMark Guard Confirmation

The Phase 5J `bulkMark()` guard remains intact.

`bulkMark()` still returns HTTP `423` before validation or insert with:

```text
API bulk attendance marking is temporarily disabled. Use attendance preflight first. Safe API bulk apply is not enabled yet.
```

## Tests Created

Created:

- `tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php`

Test coverage:

- `api_destroy_route_remains_registered`
- `api_destroy_returns_controlled_disabled_response`
- `api_destroy_does_not_delete_attendance_record`
- `api_destroy_response_mentions_audit_preserving_correction_or_disabled`
- `api_bulk_mark_guard_still_returns_423`

The tests use an isolated SQLite-memory `attendances` table and direct controller calls. They do not use project migrations and do not touch real/local MySQL.

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content routes/api.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content app/Models/Attendance.php
Get-Content tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php
php -l routes/api.php
php artisan route --path=api/v1/attendance
php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing
php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing
git diff -- app/Http/Controllers/API/AttendanceController.php tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is only an Artisan namespace.
- `php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing`: PASS, 5 tests / 10 assertions
- `php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing`: PASS, 6 tests / 13 assertions
- `php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing`: PASS, 4 tests / 9 assertions
- `php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing`: PASS, 12 tests / 16 assertions

PHPUnit emitted unrelated existing doc-comment metadata deprecation warnings during targeted test runs. No targeted test failed.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No database schema was changed.
- No SoftDeletes were added.
- No void/reversal tables were created.
- No real/local MySQL data was touched.
- No attendance records were deleted from real data.
- No attendance records were written to real data.
- No biometric sync was changed or triggered.
- No biometric device command was run.
- No web attendance behavior was changed.
- No API store/update behavior was changed in this phase.
- No API bulkMark behavior was changed in this phase.
- No API preflight/apply behavior was added.

## Remaining Risks

1. API `update()` still allows `student_id` mutation.
2. API `update()` still allows legacy `class` mutation.
3. API `store()` and `update()` still do not exclude terminal/inactive students.
4. API `store()` duplicate check remains non-atomic.
5. API `update()` can still create duplicate `student_id,date,period` conflicts.
6. API single writes still use legacy `class` string instead of canonical class identity.
7. API resource route names remain generic (`attendance.store`, `attendance.update`, `attendance.destroy`).
8. Safe API bulk apply contract is still not implemented.
9. Audit-preserving attendance correction workflow is still not implemented.
10. Existing legacy attendance rows may still require reconciliation.

## Recommended Phase 5N Next Step

Phase 5N should address API `update()` over-mutation by preventing client mutation of identity/class fields until a normalized correction policy exists.

Recommended first 5N scope:

- remove `student_id` from API `update()` validation
- remove legacy `class` from API `update()` validation
- keep status/remarks/period/subject/session behavior unchanged unless explicitly scoped
- add isolated tests proving `student_id`, `class`, and `marked_by` cannot be changed by API update
- do not add terminal/inactive policy yet unless Phase 5N is explicitly expanded
