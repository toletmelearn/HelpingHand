# Phase 7C - Teacher Attendance Dashboard Disabled UI

Date: 2026-06-07

Scope: Update the teacher attendance dashboard UI so teachers are not sent to missing, placeholder, or temporarily disabled teacher attendance flows.

## Files Inspected

- `resources/views/teacher/attendance/dashboard.blade.php`
- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/AttendanceService.php`
- `routes/web.php`
- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`
- `tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php`
- `docs/project-autopsy/PHASE_7B_TEACHER_ATTENDANCE_DASHBOARD_EXPORT_VIEW_AUDIT.md`
- `docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md`
- `docs/project-autopsy/PHASE_7A_TEACHER_ATTENDANCE_UPDATE_GUARD.md`

## Files Changed

- `resources/views/teacher/attendance/dashboard.blade.php`
- `tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php`
- `docs/project-autopsy/PHASE_7C_TEACHER_ATTENDANCE_DASHBOARD_DISABLED_UI.md`

## Previous Dashboard Link / Export Risk

Phase 7B found that the teacher attendance dashboard still showed active user-facing controls even though the backing flows were unavailable or incomplete.

Previous active controls:

- `Mark Attendance`
- `View Details`
- `View Reports`
- `Export Attendance`

Previous missing views:

- `resources/views/teacher/attendance/mark.blade.php`
- `resources/views/teacher/attendance/edit.blade.php`
- `resources/views/teacher/attendance/reports.blade.php`
- `resources/views/teacher/attendance/student.blade.php`

Previous export risk:

- dashboard copy promised Excel/CSV export
- `TeacherAttendanceController@exportAttendance()` returns placeholder JSON, not an export

Teacher store and update were already guarded server-side, but the dashboard still looked active.

## New Disabled Message

Added a warning alert near the top of the dashboard:

```text
Teacher attendance marking, updates, reports, and export are temporarily disabled until class/status/schema policy is aligned.
```

The dashboard now communicates the temporary disabled state before users reach class cards or quick actions.

## Mark Attendance UI Change

Previous behavior:

- active button called JavaScript `markAttendance(classId)`
- JavaScript navigated to `/teacher/attendance/mark/{classId}`

New behavior:

- active navigation was removed
- `markAttendance()` JavaScript function was removed
- each class row now shows a disabled button:

```text
Mark Attendance Disabled
```

No dashboard control now links to `/teacher/attendance/mark/{classId}`.

## View Details UI Change

Previous behavior:

- low attendance alert rows linked to `teacher.attendance.student`
- backing `teacher.attendance.student` view is missing

New behavior:

- active student detail link was removed
- each alert row now shows a disabled button:

```text
Details Disabled
```

No dashboard control now links to the missing teacher attendance student detail view.

## Reports UI Change

Previous behavior:

- quick action linked to `teacher.attendance.reports`
- backing `teacher.attendance.reports` view is missing

New behavior:

- active reports link was removed
- dashboard copy now says:

```text
Teacher attendance reports are temporarily unavailable.
```

- button now shows:

```text
Reports Disabled
```

## Export UI / Copy Change

Previous behavior:

- quick action linked to `teacher.attendance.export`
- dashboard promised `Export attendance records to Excel/CSV`
- controller export method returns placeholder JSON

New behavior:

- active export link was removed
- Excel/CSV promise was removed
- dashboard copy now says:

```text
Teacher attendance export is not enabled yet.
```

- button now shows:

```text
Export Disabled
```

No teacher attendance export route is executed or linked from the dashboard.

## Unchanged Controller / Route / Store / Update / Export Behavior

This phase did not change:

- `TeacherAttendanceController`
- `AttendanceService`
- routes
- teacher store guard
- teacher update guard
- teacher export controller behavior
- API attendance behavior
- web/admin attendance behavior
- preflight behavior
- migrations/schema

The controller-side guards remain the source of truth against crafted write/update requests.

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php`

Coverage:

- dashboard shows temporary-disabled message
- dashboard does not render active mark attendance links
- dashboard does not render active reports link
- dashboard does not render active export link
- dashboard does not promise Excel/CSV export
- dashboard does not render active student detail links

Targeted guard regressions:

- `TeacherAttendanceStoreGuardTest`
- `TeacherAttendanceUpdateGuardTest`

The dashboard test renders the Blade view directly with fake in-memory data and does not execute teacher routes or touch real/local MySQL.

## Commands Run

```powershell
Get-Content resources/views/teacher/attendance/dashboard.blade.php
Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content docs/project-autopsy/PHASE_7B_TEACHER_ATTENDANCE_DASHBOARD_EXPORT_VIEW_AUDIT.md
rg -n "view\('teacher.attendance.dashboard'|assertView|view\(|withoutView|dashboard_shows|render\(" tests/Feature tests/Unit
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php
php artisan test --filter=TeacherAttendanceDashboardDisabledUiTest --env=testing
php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
php artisan test --filter=TeacherAttendanceUpdateGuardTest --env=testing
```

No full test suite was run.

## Test Result Summary

```text
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
No syntax errors detected

php -l tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php
No syntax errors detected

php artisan test --filter=TeacherAttendanceDashboardDisabledUiTest --env=testing
Initial run failed because layouts.teacher expects the shared $errors view variable.
The isolated view test harness was fixed with an empty view error bag.
Final run: PASS - 6 tests, 13 assertions

php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
PASS - 5 tests, 10 assertions

php artisan test --filter=TeacherAttendanceUpdateGuardTest --env=testing
PASS - 5 tests, 10 assertions
```

Targeted test runs emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests/classes during discovery. No final targeted test failed.

## No Full Suite / No Schema / No Real MySQL

Confirmed:

- no full suite was run
- no migrations were run
- no schema changes were made
- no real/local MySQL data was touched
- no attendance writes were executed against real/local MySQL
- no attendance deletes were executed against real/local MySQL
- no teacher attendance export route was executed
- no biometric sync or device command was run

## Remaining Risks

- Teacher attendance mark/edit/reports/student views are still missing.
- Teacher export remains placeholder JSON in the controller.
- Teacher attendance store and update remain temporarily disabled.
- `AttendanceService` still contains old teacher attendance assumptions and `leave` reporting logic.
- A complete teacher attendance workflow still needs design before re-enabling teacher actions.

## Recommended Phase 7D Next Step

Phase 7D should perform a read-only audit of `AttendanceService` teacher attendance usage and determine whether the service should be split or quarantined before rebuilding teacher attendance.

Recommended focus:

- find all callers of `AttendanceService::markAttendance()`
- confirm which methods are read-only and safe for dashboard use
- decide how future teacher attendance writes should use main attendance statuses, correct user identity, `AttendanceClassResolver`, terminal/inactive checks, and duplicate-safe persistence

