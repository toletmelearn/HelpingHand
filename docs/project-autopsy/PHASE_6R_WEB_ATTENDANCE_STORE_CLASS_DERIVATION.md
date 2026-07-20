# Phase 6R - Web Attendance Store Class Derivation

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `app/Models/Student.php`
- `app/Models/Attendance.php`
- `resources/views/attendance/create.blade.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `tests/Unit/Services/AttendanceClassResolverTest.php`
- `docs/project-autopsy/PHASE_6Q_ATTENDANCE_PREFLIGHT_CLASS_RESOLVER_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md`
- `docs/project-autopsy/PHASE_6P_ATTENDANCE_CLASS_RESOLVER_SERVICE.md`

## Files Changed

- `app/Http/Controllers/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php`
- `tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `docs/project-autopsy/PHASE_6R_WEB_ATTENDANCE_STORE_CLASS_DERIVATION.md`

## Previous Web Store Class Trust Risk

The web individual/per-student `AttendanceController@store()` branch accepted a form/request `class` value and wrote that same value into every inserted `attendances.class` row. A spoofed or stale hidden class field could therefore write attendance rows with a class that did not match the target student.

The guarded web bulk `classes[] + default_status` branch was already disabled and was not changed.

## New Web Store Class Derivation Behavior

The individual/per-student branch now resolves the attendance class for each submitted student through `AttendanceClassResolver` before building insert rows.

Successful resolution stores the resolver-provided class in `attendances.class`. The request `class` value may still exist for UI/context and existing branch flow, but it is no longer trusted as the storage source for inserted rows.

## Per-Student Derivation Behavior

Each submitted `student_id` is loaded individually and resolved independently. This allows mixed submitted rows to store each student's own resolved class instead of applying one request-wide class to all rows.

Resolver precedence remains:

1. Block if the student has a class id conflict.
2. Use canonical `resolveCanonicalSchoolClass()->name` when available.
3. Fall back to legacy `students.class`.
4. Block if no class can be resolved.

## Conflict / Unresolved Behavior

Before any insert occurs, the branch resolves all submitted students.

If any student is missing, has a class id conflict, or has no resolvable class:

- no attendance rows are inserted
- the request redirects back
- old input is preserved
- the resolver error message is shown where available

This prevents partial inserts when one submitted student cannot be safely resolved.

## Request Class Storage Trust

Confirmed: request/form `class` is not used as the stored `attendances.class` value in the individual/per-student insert rows.

Known limitation: the existing duplicate pre-check still uses the request `class` because this phase intentionally avoided duplicate/race policy changes.

## Unchanged Behavior Confirmations

- Bulk direct-write guard stayed unchanged.
- Preflight behavior stayed unchanged.
- API attendance behavior stayed unchanged.
- Teacher attendance behavior stayed unchanged.
- Web update behavior stayed unchanged.
- Terminal/inactive policy stayed unchanged.
- Duplicate/race policy stayed unchanged except for pre-resolving classes before insert.
- Period behavior stayed unchanged.

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php`

Updated isolated test harness compatibility:

- `tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`

The harness updates add `deleted_at` to in-memory `school_classes` fixtures and reset the SQLite connection before schema creation, matching the current `SchoolClass` model's soft-delete-aware lookup used by preflight/resolver code.

## Commands Run

- `Get-Content -Path app/Http/Controllers/AttendanceController.php`
- `Get-Content -Path app/Services/Attendance/AttendanceClassResolver.php`
- `Get-Content -Path resources/views/attendance/create.blade.php`
- `Get-Content -Path tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `Get-Content -Path tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `Get-Content -Path docs/project-autopsy/PHASE_6Q_ATTENDANCE_PREFLIGHT_CLASS_RESOLVER_ALIGNMENT.md`
- `Get-Content -Path docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md`
- `Get-Content -Path docs/project-autopsy/PHASE_6P_ATTENDANCE_CLASS_RESOLVER_SERVICE.md`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php`
- `php -l app/Services/Attendance/AttendanceClassResolver.php`
- `php -l tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `php -l tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `php artisan test --filter=AttendanceWebStoreClassDerivationTest --env=testing`
- `php artisan test --filter=AttendanceClassResolverTest --env=testing`
- `php artisan test --filter=AttendanceBulkDirectWriteGuardTest --env=testing`
- `php artisan test --filter=AttendanceCreateReadOnlyTest --env=testing`
- `git diff -- app/Http/Controllers/AttendanceController.php tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `git status --short`

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php` passed.
- `php -l tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php` passed.
- `php -l app/Services/Attendance/AttendanceClassResolver.php` passed.
- `php -l tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php` passed.
- `php -l tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php` passed.
- `AttendanceWebStoreClassDerivationTest`: passed, 7 tests / 24 assertions.
- `AttendanceClassResolverTest`: passed, 5 tests / 21 assertions.
- `AttendanceBulkDirectWriteGuardTest`: initially exposed an isolated fixture mismatch, then passed after harness-only schema reset/soft-delete column fix, 7 tests / 18 assertions.
- `AttendanceCreateReadOnlyTest`: initially exposed the same isolated fixture mismatch, then passed after harness-only schema reset/soft-delete column fix, 5 tests / 7 assertions.

PHPUnit emitted existing doc-comment metadata deprecation warnings unrelated to this phase.

## Full Suite / Data Safety Confirmation

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No real/local MySQL data was touched.
- No attendance writes were performed against real/local MySQL.
- No export route was executed.
- No biometric sync or device command was run.

All attendance write assertions used isolated SQLite in-memory test schemas only.

## Remaining Risks

- Web update still accepts and writes `class` server-side.
- Web individual store duplicate pre-check still uses request `class`.
- Web individual store still uses raw multi-row `Attendance::insert()` without a transaction.
- Web individual store terminal/inactive policy remains unchanged.
- Teacher attendance class/status/schema risks remain separate.
- Preflight result UI does not yet surface all derived class resolver fields.
- Historical attendance class drift still needs reconciliation/reporting, not mutation.

## Recommended Phase 6S Next Step

Phase 6S should perform a read-only audit of web attendance update identity/class/date/period mutation, with special focus on blocking or deriving `class` in update without changing correction workflow semantics prematurely.
