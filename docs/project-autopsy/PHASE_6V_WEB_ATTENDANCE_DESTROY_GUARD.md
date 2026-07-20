# Phase 6V - Web Attendance Destroy Guard

Date: 2026-06-06

Scope: Guard ordinary web attendance destroy/delete so attendance records cannot be hard-deleted through web routes.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Models/Attendance.php`
- `app/Policies/AttendancePolicy.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceWebUpdateIdentityGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php`
- `docs/project-autopsy/PHASE_6U_WEB_ATTENDANCE_DESTROY_DELETE_AUDIT.md`
- `docs/project-autopsy/PHASE_5M_API_ATTENDANCE_DESTROY_GUARD.md`
- `docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`

## Files Changed

- `app/Http/Controllers/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php`
- `docs/project-autopsy/PHASE_6V_WEB_ATTENDANCE_DESTROY_GUARD.md`

## Previous Hard-Delete Risk

Phase 6U found that web `AttendanceController@destroy()` called:

```php
$this->authorize('delete', $attendance);
$attendance->delete();
```

`Attendance` does not use `SoftDeletes`, so this was a hard-delete path. There was no audit-preserving correction, void, or reversal workflow; no delete reason; no delete audit metadata; and no transaction/logging around the delete.

## New Web Destroy Guard Behavior

`AttendanceController@destroy()` now keeps authorization, then returns before any delete call:

```php
$this->authorize('delete', $attendance);

// Phase 6V: hard delete disabled until audit-preserving attendance correction workflow exists.
return redirect()->route('attendance.index')
    ->with(
        'warning',
        'Web attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.'
    );
```

Behavior:

- Authorized users receive a warning response.
- The route remains registered.
- The controller does not delete the attendance row.
- The message aligns with the API destroy guard posture.

## Delete Call Confirmation

`$attendance->delete()` is no longer reachable in web `destroy()`.

The method also does not call:

- `Attendance::destroy()`
- `forceDelete()`
- a transaction containing a delete
- any alternate delete helper

## Route Confirmation

The web destroy route remains registered through the existing resource routes.

Focused test coverage checks that a `DELETE attendance/{attendance}` route still resolves to:

```text
App\Http\Controllers\AttendanceController@destroy
```

No web route URI, route name, route method, or route registration was changed.

## Delete UI Confirmation

Delete UI was intentionally not changed in this phase.

Still unchanged:

- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`

These views can still render delete forms/buttons. The server-side guard now prevents those forms from deleting attendance records.

## API Destroy Guard Confirmation

`app/Http/Controllers/API/AttendanceController.php` was not changed.

The API destroy guard remains:

- returns HTTP `423`
- does not delete attendance
- keeps the Phase 5M audit-preserving correction workflow message

Targeted `AttendanceApiDestroyGuardTest` passed after this phase.

## Store / Update / Preflight / Teacher Confirmation

This phase did not change:

- web `store()`
- web `update()`
- API controller behavior
- teacher attendance behavior
- attendance preflight behavior
- routes
- policies
- migrations/schema
- CSV/export behavior

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php`

Coverage:

- `web_destroy_does_not_delete_attendance_record`
- `web_destroy_returns_warning_message`
- `web_destroy_route_remains_registered`
- `web_destroy_authorization_still_runs`

The test uses isolated SQLite-memory tables only and does not use project migrations or real/local MySQL.

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/AttendanceController.php
Get-Content -Path app/Http/Controllers/API/AttendanceController.php
Get-Content -Path app/Models/Attendance.php
Get-Content -Path app/Policies/AttendancePolicy.php
Get-Content -Path routes/web.php
Get-Content -Path docs/project-autopsy/PHASE_6U_WEB_ATTENDANCE_DESTROY_DELETE_AUDIT.md
Get-Content -Path tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php
rg -n "assertSessionHas\('warning'|with\('warning'|AttendanceWebDestroy|attendance.destroy|withoutMiddleware\(\)" tests/Feature app/Http/Controllers/AttendanceController.php
Get-Content -Path app/Models/User.php
Get-Content -Path app/Models/Role.php
Get-Content -Path tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php
php -l app/Http/Controllers/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php
php -l app/Models/Attendance.php
php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing
php artisan test --filter=AttendanceWebUpdateIdentityGuardTest --env=testing
php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing
```

Notes:

- The first `AttendanceWebDestroyGuardTest` run failed because the test initially used `route('attendance.destroy')`, which resolved to the API route in this app's duplicate route-name setup and returned the API `423` response. The test harness was corrected to use explicit web paths (`/attendance/{id}`) so it verifies the web destroy surface.
- Targeted PHPUnit runs emitted existing doc-comment metadata deprecation warnings from unrelated tests. No final targeted test failed.

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php`: PASS
- `php -l app/Models/Attendance.php`: PASS
- `php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing`: PASS, 4 tests / 8 assertions
- `php artisan test --filter=AttendanceWebUpdateIdentityGuardTest --env=testing`: PASS, 7 tests / 14 assertions
- `php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing`: PASS, 5 tests / 10 assertions

## Full Suite / Data Safety Confirmation

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No SoftDeletes were added.
- No correction/void workflow was implemented.
- No real/local MySQL data was touched.
- No attendance deletes were performed against real/local MySQL.
- No attendance writes were performed against real/local MySQL.
- No export route was executed.
- No biometric sync or device command was run.

All delete/write assertions used isolated SQLite in-memory test schemas only.

## Remaining Risks

- Delete UI still appears in index/show views and should be hidden or disabled in a follow-up.
- Delete UI still is not visibly `@can('delete', $attendance)` gated.
- There is still no audit-preserving correction/void workflow.
- `Attendance` still does not use `SoftDeletes`.
- Route-name ambiguity remains between API and web attendance resources using generic names like `attendance.destroy`.
- Historical attendance rows may still need reconciliation/audit reports.

## Recommended Phase 6W Next Step

Phase 6W should clean up web delete UI now that the server-side guard is in place.

Recommended scope:

- Hide or disable delete buttons/forms in `attendance.index` and `attendance.show`.
- Keep the server-side destroy guard unchanged.
- Add focused view tests proving delete controls are no longer active or no longer visible.
- Do not add SoftDeletes or correction workflow yet.
