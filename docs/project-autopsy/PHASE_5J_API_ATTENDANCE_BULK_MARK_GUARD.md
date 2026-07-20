# Phase 5J - API Attendance Bulk-Mark Guard

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `app/Models/Attendance.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `docs/project-autopsy/PHASE_5H_ATTENDANCE_ROUTE_API_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_5I_ATTENDANCE_ROUTE_ORDER_FIX.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`
- `docs/project-autopsy/PHASE_5J_API_ATTENDANCE_BULK_MARK_GUARD.md`

## Bulk-Mark Guard Behavior

`App\Http\Controllers\API\AttendanceController::bulkMark()` now returns immediately at the start of the method:

```php
return $this->error(
    'API bulk attendance marking is temporarily disabled. Use attendance preflight first. Safe API bulk apply is not enabled yet.',
    423
);
```

This uses the existing API controller error helper shape:

- `success: false`
- `message: ...`
- `errors: null`
- `timestamp: ...`

## Preserved API Route

The route was not removed or renamed:

- Method: `POST`
- URI: `api/v1/attendance/bulk-mark`
- Name: `api.attendance.bulk-mark`
- Controller method: `API\AttendanceController@bulkMark`

## Write Behavior Disabled

For the API bulk-mark route, the guard returns before:

- request validation
- student row expansion
- status row expansion
- timestamp creation
- `Attendance::insert()`
- `Attendance::create()`
- `DB::transaction()`

The old unsafe implementation remains behind the early return for deliberate future replacement, but it is no longer reachable during this phase.

## Unchanged Behavior

This phase did not change:

- web attendance routes
- web direct bulk guard
- web preflight JSON endpoint
- web preflight UI
- API `store()`
- API `update()`
- API `destroy()`
- API attendance report routes
- biometric sync or device commands

No API apply token, approval token, or write approval flow was added.

## Tests Created

Created:

- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`

Assertions added:

- `api.attendance.bulk-mark` route still exists.
- `POST /api/v1/attendance/bulk-mark` still dispatches to `API\AttendanceController@bulkMark`.
- `bulkMark()` returns HTTP `423`.
- guarded bulk-mark does not insert into an isolated SQLite-memory `attendances` table.
- empty/malformed payloads also return `423`, proving the guard returns before validation.

The test calls the controller method directly for write-safety and uses a minimal in-memory SQLite `attendances` table. It does not authenticate through the API, run migrations, or touch real/local MySQL.

## Commands Run

```powershell
rg -n "bulkMark|AttendanceController|attendance" routes/api.php app/Http/Controllers/API/AttendanceController.php
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content routes/api.php
Get-ChildItem tests/Feature/Attendance | Select-Object Name
Get-Content app/Http/Controllers/API/BaseApiController.php
rg -n "Sanctum|actingAs|api.attendance.bulk-mark|bulk-mark|assertStatus\(423|assertStatus\(409" tests app routes
Get-Content phpunit.xml
Get-Content tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php
Get-Content tests/Feature/API/SanctumTokenAbilityTest.php
Get-ChildItem tests/Unit/Services | Select-Object Name
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php
php artisan route:list --path=api/v1/attendance
php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing
php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing
php -l routes/api.php
git diff -- app/Http/Controllers/API/AttendanceController.php tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php
Get-Content docs/project-autopsy/PHASE_5H_ATTENDANCE_ROUTE_API_WRITE_AUDIT.md | Select-Object -First 80
Get-Content docs/project-autopsy/PHASE_5I_ATTENDANCE_ROUTE_ORDER_FIX.md | Select-Object -First 80
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route:list --path=api/v1/attendance`: PASS, showed `POST api/v1/attendance/bulk-mark` registered as `api.attendance.bulk-mark`
- `php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing`: PASS, 4 tests / 9 assertions
- `php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing`: PASS, 12 tests / 16 assertions

PHPUnit emitted unrelated existing doc-comment metadata deprecation warnings during targeted test runs. No targeted test failed.

## Safety Confirmations

- No attendance records were marked.
- No attendance records were created, updated, deleted, seeded, imported, exported, or synced.
- No full test suite was run.
- No migrations were run.
- No database schema was changed.
- No real/local MySQL data was touched.
- No biometric sync was changed or triggered.
- No biometric device command was run.
- No web attendance write behavior was changed.
- No API single-record attendance behavior was changed.

## Remaining Risks

1. API `store()` still permits single attendance writes.
2. API `store()` still accepts caller-supplied `marked_by`.
3. API `store()` still accepts caller-supplied legacy `class`.
4. API `store()` duplicate protection remains a pre-write exists check, which can race.
5. API `update()` still permits changing `marked_by`.
6. API `update()` still permits changing legacy `class`.
7. Terminal/inactive student exclusion is not yet enforced across API single-record writes.
8. Safe API bulk preflight/apply contract does not exist yet.
9. Safe transactional attendance upsert policy is not yet implemented.
10. Existing legacy attendance rows and duplicate-risk data may still require reconciliation.

## Recommended Phase 5K Next Step

Phase 5K should perform a read-only audit of API single-record attendance writes, especially `store()` and `update()`, focusing on:

- trusted `marked_by` derivation from authenticated user instead of request body
- terminal/inactive student exclusion
- duplicate/race handling
- legacy `class` versus canonical `class_id`
- whether API single writes should be guarded before a transactional normalized write policy is introduced
