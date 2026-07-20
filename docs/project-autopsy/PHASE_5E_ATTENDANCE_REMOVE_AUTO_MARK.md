PHASE 5E — Remove auto-mark side-effect from AttendanceController::create

Date: 2026-06-05

Summary
- Goal: Ensure GET/read routes are strictly read-only. Specifically, prevent the `attendance.create` page from performing writes (auto-marking students present via `ensureAllStudentsPresent()`).
- Outcome: The call to `ensureAllStudentsPresent()` was removed from `AttendanceController::create()`. A clear comment was added to the helper to mark it as a write-only helper and warn against calling it from GET/read routes.

Files changed
- `app/Http/Controllers/AttendanceController.php`
  - Removed invocation of `ensureAllStudentsPresent($class, $date)` from `create()`.
  - Added Phase 5E comment in `create()` noting the page must remain read-only.
  - Added a "WRITE HELPER" warning comment above `ensureAllStudentsPresent()` describing its behavior and warning not to call it from GET routes.

Tests added
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
  - Verifies the `attendance.create` route is registered.
  - Asserts a GET to the create page does not insert records into the `attendances` table.
  - Confirms the create page still renders (contains "Mark Daily Attendance").
  - Includes a sanity check that the existing preflight UI endpoint still responds.

Why this change
- During the Phase 5A audit we found a schema drift and noted a red risk: the GET-side `create()` method could invoke writes via `ensureAllStudentsPresent()`. This violated the read-only principle for GET endpoints and introduced a risk of accidental data mutation during page views.

How to verify locally
1. Run the targeted test added for this change:

```bash
php artisan test --filter=AttendanceCreateReadOnlyTest --env=testing
```

2. Optionally run the related preflight and service tests already present:

```bash
php artisan test --filter=AttendancePreflightUiTest --env=testing
php artisan test --filter=AttendanceBulkPreflightEndpointTest --env=testing
php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing
```

Notes and follow-ups
- The `ensureAllStudentsPresent()` helper remains in place because it is used by explicit write paths (store/apply flows) and by other parts of the system; it is now clearly labelled as a write helper and should be refactored or deprecated in a later phase if desired.
- No schema migrations were required.
- This change is deliberately minimal and surgical to remove the GET-side write risk while keeping existing flows intact.

Author: Automated change recorded by the coding agent
