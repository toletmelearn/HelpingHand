# Phase 7A - Teacher Attendance Update Guard

Date: 2026-06-07

Scope: Temporarily guard teacher attendance update so unsafe teacher status/remarks/`updated_by` writes cannot occur until class/status/schema policy is aligned.

## Files Inspected

- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/AttendanceService.php`
- `app/Models/Attendance.php`
- `app/Models/Teacher.php`
- `app/Models/User.php`
- `routes/web.php`
- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`
- `docs/project-autopsy/PHASE_6Z_TEACHER_ATTENDANCE_UPDATE_STATUS_AUDIT.md`
- `docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md`

## Files Changed

- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php`
- `docs/project-autopsy/PHASE_7A_TEACHER_ATTENDANCE_UPDATE_GUARD.md`

## Previous Teacher Update Risk

Phase 6Z found that teacher attendance update was still active:

- route: `PUT teacher/attendance/{id}`
- method: `TeacherAttendanceController@updateAttendance`

Before this phase, teacher update:

- accepted `present`, `absent`, and `leave`
- allowed `leave`, which does not match the shared attendance status policy of `present`, `absent`, `late`, `half_day`
- attempted to write `updated_by => $teacher->id`
- used teacher id even though the `attendances.updated_by` migration references `users.id`
- likely did not actually write `updated_by` because `Attendance::$fillable` does not include it
- could become a wrong identity write later if `updated_by` is added to `$fillable`

Teacher store was already guarded in Phase 6Y, so leaving teacher update active created an inconsistent teacher attendance safety posture.

## New Teacher Update Guard Behavior

`TeacherAttendanceController@updateAttendance()` now returns immediately before validation, teacher lookup, authorization, attendance lookup, or update.

The route remains registered.

The method no longer reaches:

- status validation
- remarks validation
- `Attendance::findOrFail($attendanceId)`
- teacher guard lookup
- `$this->authorize('update', $attendance)`
- `$attendance->update(...)`

This phase did not change teacher store, dashboard/read routes, teacher export, `AttendanceService`, routes, views, API, web attendance, or preflight behavior.

## Warning Message

The warning message is:

```text
Teacher attendance updates are temporarily disabled until class/status/schema policy is aligned.
```

It is returned with `redirect()->back()->with('warning', ...)`.

## Status / Remarks / Updated By Confirmation

Focused isolated tests prove:

- `status` is not changed by teacher update
- `remarks` is not changed by teacher update
- `updated_by` is not written by teacher update

Because the guard returns before validation, unsupported teacher status values such as `leave` cannot reach persistence through this update path.

## Teacher Store Behavior

Teacher store behavior was not changed in this phase.

Phase 6Y guard remains in place:

- `TeacherAttendanceController@storeAttendance()` still returns warning before validation/service write
- `AttendanceService::markAttendance()` remains unreachable from teacher store

## API / Web / Preflight Behavior

This phase did not change:

- API attendance store/update/destroy/bulkMark
- web attendance store/update/destroy
- web delete UI
- attendance preflight
- bulk direct-write guard
- teacher dashboard/read/export routes
- `AttendanceService`
- migrations/schema

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php`

Coverage:

- `teacher_attendance_update_returns_warning`
- `teacher_attendance_update_does_not_change_status`
- `teacher_attendance_update_does_not_change_remarks`
- `teacher_attendance_update_does_not_write_updated_by`
- `teacher_attendance_update_route_remains_registered`

Targeted regressions:

- `TeacherAttendanceStoreGuardTest`
- `AttendanceWebDestroyGuardTest`

The new tests use isolated SQLite-memory tables only and do not use project migrations or real/local MySQL.

## Commands Run

```powershell
Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content app/Services/AttendanceService.php
Get-Content app/Models/Attendance.php
Get-Content docs/project-autopsy/PHASE_6Z_TEACHER_ATTENDANCE_UPDATE_STATUS_AUDIT.md
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l app/Services/AttendanceService.php
php -l tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php
php artisan test --filter=TeacherAttendanceUpdateGuardTest --env=testing
php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing
```

No full test suite was run.

## Test Result Summary

```text
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
No syntax errors detected

php -l app/Services/AttendanceService.php
No syntax errors detected

php -l tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php
No syntax errors detected

php artisan test --filter=TeacherAttendanceUpdateGuardTest --env=testing
PASS - 5 tests, 10 assertions

php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
PASS - 5 tests, 10 assertions

php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing
PASS - 4 tests, 8 assertions
```

Targeted test runs emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests/classes during discovery. No targeted test failed.

## No Full Suite / No Schema / No Real MySQL

Confirmed:

- no full suite was run
- no migrations were run
- no schema changes were made
- no real/local MySQL data was touched
- no attendance writes were executed against real/local MySQL
- no attendance deletes were executed
- no biometric sync or device command was run

All write assertions used isolated SQLite-memory test schemas only.

## Remaining Risks

- Teacher attendance marking and update are now temporarily disabled until policy is aligned.
- `AttendanceService::markAttendance()` still contains old teacher attendance assumptions if called from another path.
- Teacher status `leave` still appears in old unreachable teacher store/update validation and service reporting logic.
- Teacher attendance mark/edit/report/student views referenced by the controller may still be missing.
- Teacher dashboard/export UI still has separate contract issues.
- A proper teacher attendance alignment design is still needed.

## Recommended Phase 7B Next Step

Phase 7B should perform a read-only audit of `AttendanceService` teacher-attendance usage and decide whether to quarantine or refactor it.

Recommended focus:

- identify every caller of `AttendanceService::markAttendance()`
- determine whether the service should be split into read-only teacher reporting and safe write-specific services
- design a future teacher attendance write policy using main statuses, correct user identity, `AttendanceClassResolver`, terminal/inactive checks, and duplicate-safe persistence

