# Phase 6Y - Teacher Attendance Store Guard

Date: 2026-06-07

Scope: Temporarily guard the teacher attendance store write path so unsafe teacher attendance rows cannot be written until class/status/schema policy is aligned.

## Files Inspected

- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/AttendanceService.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `routes/web.php`
- `resources/views/teacher/attendance/dashboard.blade.php`
- `docs/project-autopsy/PHASE_6X_TEACHER_ATTENDANCE_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_6W_ATTENDANCE_DELETE_UI_DISABLED.md`
- `docs/project-autopsy/PHASE_6R_WEB_ATTENDANCE_STORE_CLASS_DERIVATION.md`
- `tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php`
- `tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php`

## Files Changed

- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`
- `docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md`

## Previous Teacher Store Write Risk

Phase 6X found the active teacher attendance store route `POST teacher/attendance/store` used `TeacherAttendanceController@storeAttendance`.

The previous path:

- accepted request-supplied `class_id`
- did not verify each submitted student belonged to the submitted `class_id`
- did not use `AttendanceClassResolver`
- did not reject terminal/inactive students
- accepted teacher-specific status `leave`
- called `AttendanceService::markAttendance()`
- relied on `AttendanceService::markAttendance()` writing `class_id`, even though the main attendance table/model are legacy `class` based
- used `updateOrCreate()` keyed only by `student_id` and `date`, creating overwrite risk

Risk classification before this phase: RED.

## New Teacher Store Guard Behavior

`TeacherAttendanceController@storeAttendance()` now returns immediately before validation or write preparation.

The method returns a redirect back with a `warning` flash message:

```text
Teacher attendance marking is temporarily disabled until class/status/schema policy is aligned.
```

Because the guard is at the start of the method:

- attendance payload validation is not reached
- teacher lookup is not reached
- attendance row preparation is not reached
- `AttendanceService::markAttendance()` is not reached
- `AttendanceNotificationService::sendBulkAttendanceNotifications()` is not reached
- no teacher attendance rows are written by this path

The route remains registered. This phase does not remove or rename the teacher attendance store route.

## Warning Message

The warning message is:

```text
Teacher attendance marking is temporarily disabled until class/status/schema policy is aligned.
```

The warning is intentionally clear and temporary. It avoids implying that teacher attendance has been permanently removed.

## AttendanceService Reachability

`AttendanceService::markAttendance()` is no longer reachable from `TeacherAttendanceController@storeAttendance()` because the controller returns before validation and service call.

Focused tests bind a mock `AttendanceService` and assert `markAttendance()` is not called.

`AttendanceService.php` itself was not changed.

## Teacher Update Behavior

Teacher update behavior was not changed in this phase.

The following methods were left unchanged:

- `dashboard()`
- `markAttendance()`
- `reports()`
- `studentAttendance()`
- `editAttendance()`
- `updateAttendance()`
- `exportAttendance()`

Phase 6Y only guarded `storeAttendance()`.

## API / Web / Preflight Behavior

This phase did not change:

- API attendance store/update/destroy/bulkMark behavior
- web attendance store behavior
- web attendance update behavior
- web attendance destroy behavior
- web delete UI behavior
- bulk direct-write guard
- preflight behavior
- teacher dashboard/read routes
- routes
- views
- migrations or schema

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`

Coverage added:

- teacher attendance store returns the warning
- teacher attendance store does not create attendance rows in isolated SQLite
- teacher attendance store does not call `AttendanceService::markAttendance()`
- teacher attendance store route remains registered
- `AttendanceService` remains present/unchanged at the API surface needed by future phases

Targeted regression tests also confirmed:

- web destroy guard remains intact
- web store class derivation remains intact

## Commands Run

```powershell
Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content app/Services/AttendanceService.php
Get-Content routes/web.php
Get-Content docs/project-autopsy/PHASE_6X_TEACHER_ATTENDANCE_WRITE_AUDIT.md
Get-Content tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php
rg -n "AttendanceNotificationService|Mockery|shouldNotReceive|teacher.attendance.store|withoutMiddleware" tests app
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l app/Services/AttendanceService.php
php -l tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php
php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing
php artisan test --filter=AttendanceWebStoreClassDerivationTest --env=testing
```

No full test suite was run.

## Test Result Summary

```text
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
No syntax errors detected

php -l app/Services/AttendanceService.php
No syntax errors detected

php -l tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php
No syntax errors detected

php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
PASS - 5 tests, 10 assertions

php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing
PASS - 4 tests, 8 assertions

php artisan test --filter=AttendanceWebStoreClassDerivationTest --env=testing
PASS - 7 tests, 24 assertions
```

The targeted test runs emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests/classes during test discovery. No Phase 6Y tests failed.

## No Full Suite / No Schema / No Real MySQL

Confirmed:

- no full suite was run
- no migrations were run
- no schema changes were made
- no real/local MySQL data was touched
- no attendance writes were executed against real/local MySQL
- no attendance deletes were executed
- no export route was executed
- no biometric sync or device command was run

The new guard tests use isolated SQLite in memory.

## Remaining Risks

- Teacher `updateAttendance()` remains active and still allows teacher status vocabulary `present`, `absent`, `leave`.
- `AttendanceService::markAttendance()` still contains unsupported/mismatched write behavior if called from another path.
- Teacher attendance mark/edit/report/student views referenced by the controller may still be missing.
- Teacher dashboard/export UI still has separate contract issues from the Phase 6X audit.
- Teacher attendance is temporarily disabled for marking until a safe class/status/schema policy is implemented.

## Recommended Phase 6Z Next Step

Phase 6Z should perform a read-only audit of teacher attendance update behavior and status vocabulary, then decide whether to guard teacher update temporarily or align it with the hardened main attendance policy.

Recommended focus:

- confirm whether `leave` should map to an existing main status or become a separate workflow
- audit `updated_by` and `marked_by` identity assumptions
- decide whether teacher update should also be guarded until the shared attendance policy is aligned

