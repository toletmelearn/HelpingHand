# Phase 5G - Attendance Bulk Direct Write Guard

Date: 2026-06-05

Scope: Prevent direct web bulk `classes[] + default_status` attendance writes and keep the bulk flow preview-only until a safe apply path is designed.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `resources/views/attendance/bulk_mark.blade.php`
- `resources/views/attendance/preflight-result.blade.php`
- `resources/views/attendance/create.blade.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `docs/project-autopsy/PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT.md`
- `docs/project-autopsy/PHASE_5D_ATTENDANCE_PREFLIGHT_UI.md`
- `docs/project-autopsy/PHASE_5E_ATTENDANCE_CREATE_READ_ONLY_FIX.md`

## Files Changed

- `app/Http/Controllers/AttendanceController.php`
- `resources/views/attendance/bulk_mark.blade.php`
- `tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `docs/project-autopsy/PHASE_5G_ATTENDANCE_BULK_DIRECT_WRITE_GUARD.md`

## Bulk Store Guard Behavior

In `AttendanceController@store()`, the bulk branch is now guarded at the start of:

```php
if ($request->filled('classes') && $request->filled('default_status')) {
```

The guard returns immediately with a warning:

> Direct bulk attendance marking is temporarily disabled. Please use Preview first. Safe bulk apply is not enabled yet.

This happens before validation, student expansion, or any attendance write call.

The guarded branch does not call:

- `Attendance::insert()`
- `Attendance::create()`
- `Attendance::update()`
- `AttendanceService::markAttendance()`
- `DB::transaction()`

## Bulk UI Change Summary

`resources/views/attendance/bulk_mark.blade.php` now:

- Removes the direct bulk `Mark Attendance` submit button.
- Keeps the `Preview` button active.
- Adds helper text: "Bulk attendance must be previewed first. Direct bulk marking is disabled until safe apply is enabled."
- Updates instructions to tell users to click Preview rather than directly record bulk attendance.
- Does not add Apply, Confirm, Save, hidden token, or approval UI.
- Uses the available preflight route name, falling back to `admin.attendance.preflight-view` when the unprefixed route name is not registered.

## Preflight Result Read-Only Confirmation

`resources/views/attendance/preflight-result.blade.php` was not changed.

It remains read-only:

- No Apply button.
- No Confirm button.
- No Mark Attendance button.
- Only Back/Edit-style links.

## Individual Store Branch Confirmation

The individual/per-student branch in `AttendanceController@store()` was not changed in this phase.

The guard only affects requests that contain both:

- `classes`
- `default_status`

Per-student payloads using `student_ids` and `statuses` continue to fall through to the existing individual branch.

## Tests Created/Updated

Created:

- `tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`

Tests included:

- `bulk_store_without_apply_is_guarded_and_does_not_insert`
- `bulk_mark_view_no_longer_renders_direct_mark_attendance_button`
- `bulk_mark_view_still_renders_preview_button`
- `preflight_result_view_does_not_render_apply_confirm_or_mark_button`
- `individual_store_branch_route_behavior_is_not_changed_by_bulk_guard`
- `attendance_create_read_only_test_still_passes`
- `attendance_preflight_ui_still_passes`

The test uses isolated SQLite in-memory tables only and does not use project migrations.

## Commands Run

- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content resources/views/attendance/bulk_mark.blade.php`
- `Get-Content resources/views/attendance/preflight-result.blade.php`
- `Get-Content tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `Get-Content app/Policies/AttendancePolicy.php`
- `Get-Content app/Models/User.php`
- `rg -n "function hasRole|roles\(" app/Models app/Traits app/Policies`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `php artisan route | Select-String "attendance"` (timed out in this environment)
- `php artisan route:list | Select-String "attendance"`
- `php artisan test --filter=AttendanceBulkDirectWriteGuardTest --env=testing`
- `php artisan test --filter=AttendancePreflightUiTest --env=testing`
- `php artisan test --filter=AttendanceCreateReadOnlyTest --env=testing`
- `php artisan test --filter=AttendanceBulkPreflightEndpointTest --env=testing`
- `php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing`
- `git diff -- app/Http/Controllers/AttendanceController.php resources/views/attendance/bulk_mark.blade.php tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `git status --short`
- `rg -n "Mark Attendance|Apply|Confirm|Preview|Direct bulk marking|Bulk attendance must" resources/views/attendance/bulk_mark.blade.php resources/views/attendance/preflight-result.blade.php app/Http/Controllers/AttendanceController.php tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `Get-Content docs/project-autopsy/PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT.md`

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php`: passed, no syntax errors.
- `php -l tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`: passed, no syntax errors.
- `php artisan test --filter=AttendanceBulkDirectWriteGuardTest --env=testing`: 7 passed, 18 assertions.
- `php artisan test --filter=AttendancePreflightUiTest --env=testing`: 7 passed, 15 assertions.
- `php artisan test --filter=AttendanceCreateReadOnlyTest --env=testing`: 5 passed, 7 assertions.
- `php artisan test --filter=AttendanceBulkPreflightEndpointTest --env=testing`: 6 passed, 18 assertions.
- `php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing`: 12 passed, 16 assertions.

Notes:

- PHPUnit emitted existing deprecation warnings for unrelated doc-comment metadata in other tests during bootstrap/discovery.
- An initial direct route-render test for `/attendance/bulk-mark` exposed an existing route-order collision with `attendance/{attendance}` in this environment; the final guard test renders `attendance.bulk_mark` directly to verify the Blade UI without changing routes.

## Confirmations

- Direct web bulk write is guarded: YES.
- Direct bulk `Mark Attendance` submit button is removed from the bulk UI: YES.
- Preview button still exists and points to the available preflight-view route: YES.
- Preflight result page remains read-only: YES.
- Individual/per-student store branch was not changed: YES.
- No apply/write approval flow was added: YES.
- No hidden token or session-backed apply behavior was added: YES.
- No API attendance write behavior was changed: YES.
- No biometric sync code was changed or run: YES.
- No migrations or schema files were touched: YES.
- No real/local MySQL data was touched: YES.
- No full test suite was run: YES.

## Remaining Risks

- API bulk-mark still uses raw insert and remains risky.
- Individual/per-student web store still uses raw insert and legacy `class` strings.
- Bulk preflight expansion still uses legacy `students.class` strings.
- Terminal/inactive students are still only detected in preflight, not safely handled by a write apply flow.
- There is still no transactional bulk apply path.
- Existing route order appears to allow `/attendance/bulk-mark` to be interpreted by the resource `show` route in some contexts if routes are registered in the current order.

## Recommended Phase 5H Next Step

Design a safe read-only-to-write apply contract before enabling any bulk writes:

- Define the exact preflight payload hash/session approval model.
- Decide create/update/skip behavior for existing attendance rows.
- Exclude terminal/inactive students from writes.
- Use a transaction for the whole bulk apply.
- Normalize or explicitly document legacy `class` versus canonical `class_id` behavior.
- Add tests proving direct bulk writes remain blocked unless the safe apply path is intentionally used.
