# Phase 7E - Attendance Service markAttendance Guard

Date: 2026-06-07

Scope: Internally guard `AttendanceService::markAttendance()` so accidental future calls cannot write unsafe teacher-era attendance rows.

## Files Inspected

- `app/Services/AttendanceService.php`
- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Models/Attendance.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`
- `tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php`
- `tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php`
- `docs/project-autopsy/PHASE_7D_ATTENDANCE_SERVICE_RESIDUAL_RISK_AUDIT.md`
- `docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md`
- `docs/project-autopsy/PHASE_7A_TEACHER_ATTENDANCE_UPDATE_GUARD.md`
- `docs/project-autopsy/PHASE_7C_TEACHER_ATTENDANCE_DASHBOARD_DISABLED_UI.md`

## Files Changed

- `app/Services/AttendanceService.php`
- `tests/Unit/Services/AttendanceServiceMarkAttendanceGuardTest.php`
- `docs/project-autopsy/PHASE_7E_ATTENDANCE_SERVICE_MARKATTENDANCE_GUARD.md`

## Previous Service Write Risk

Phase 7D found that `AttendanceService::markAttendance()` remained public and risky if reused.

Previous method behavior:

- Started a DB transaction.
- Called `Attendance::updateOrCreate()`.
- Used lookup keys:
  - `student_id`
  - `date`
- Wrote:
  - `status`
  - `remarks`
  - `marked_by`
  - `class_id`

Risks:

- `class_id` is not supported by the main `attendances` table/model contract.
- `marked_by` came from teacher-id assumptions, while attendance schema expects user ids.
- The key ignored `period`, even though the unique index includes `student_id,date,period`.
- The method did not use `AttendanceClassResolver`.
- The method did not enforce the main status vocabulary.
- The old teacher path accepted `leave`.
- The method did not check terminal/inactive students.

No unguarded app caller was found in Phase 7D, but the method remained hazardous as a public service method.

## New Internal Guard Behavior

`AttendanceService::markAttendance()` now fails closed immediately at the start of the method.

Guard added:

```php
// Phase 7E: disabled until teacher attendance class/status/schema policy is aligned.
throw new \RuntimeException(
    'AttendanceService::markAttendance is temporarily disabled until class/status/schema policy is aligned.'
);
```

The method signature is unchanged:

```php
public function markAttendance($attendanceData, $teacherId = null)
```

The old write code remains below the guard for minimal churn, but it is unreachable.

## Exception Type / Message

Exception type:

- `RuntimeException`

Exception message:

```text
AttendanceService::markAttendance is temporarily disabled until class/status/schema policy is aligned.
```

## Transaction / Write Path Confirmation

Because the guard is the first executable statement in `markAttendance()`:

- `DB::beginTransaction()` is not reached.
- `Attendance::updateOrCreate()` is not reached.
- `DB::commit()` is not reached.
- `DB::rollback()` is not needed for the guard path.
- No attendance rows are written by direct calls to this method.

The isolated unit test confirms:

- the exception is thrown
- the message is exact
- no rows are created in the in-memory `attendances` table
- `DB::transactionLevel()` remains `0` after the guarded call

## Read / Report Methods Unchanged

This phase did not change:

- `getStudentAttendanceStats()`
- `getClassAttendanceStats()`
- `getWorkingDays()`
- `getAttendanceTrends()`
- `getClassAttendanceSummary()`
- `getLowAttendanceAlerts()`
- `getTeacherClassAttendance()`
- `generateAttendanceReport()`

Their legacy `leave` and `class_id` reporting assumptions remain for a later audit/refactor.

## Teacher Store / Update / Dashboard Behavior

This phase did not change `TeacherAttendanceController`.

Existing behavior remains:

- `storeAttendance()` is still guarded from Phase 6Y before validation/service write.
- `updateAttendance()` is still guarded from Phase 7A before validation/update.
- Teacher dashboard disabled UI remains unchanged from Phase 7C.
- `AttendanceService::markAttendance()` is still not reached by the guarded teacher store path.

## Tests Created / Updated

Created:

- `tests/Unit/Services/AttendanceServiceMarkAttendanceGuardTest.php`

Coverage:

- `mark_attendance_throws_disabled_exception`
- `mark_attendance_does_not_create_attendance_rows`
- `mark_attendance_does_not_start_write_path`

Targeted regression tests:

- `TeacherAttendanceStoreGuardTest`
- `TeacherAttendanceUpdateGuardTest`
- `TeacherAttendanceDashboardDisabledUiTest`

## Commands Run

```powershell
Get-Content app/Services/AttendanceService.php
Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php
Get-Content docs/project-autopsy/PHASE_7D_ATTENDANCE_SERVICE_RESIDUAL_RISK_AUDIT.md
Get-ChildItem tests/Unit -Recurse -Filter *.php | Select-Object -First 20 -ExpandProperty FullName
Get-ChildItem tests/Unit/Services -Force
Get-Content phpunit.xml
Get-Content tests/Unit/Services/AttendanceClassResolverTest.php
Get-Content tests/Unit/Services/AttendanceBulkPreflightServiceTest.php -TotalCount 80
php -l app/Services/AttendanceService.php
php -l tests/Unit/Services/AttendanceServiceMarkAttendanceGuardTest.php
php artisan test --filter=AttendanceServiceMarkAttendanceGuardTest --env=testing
php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
php artisan test --filter=TeacherAttendanceUpdateGuardTest --env=testing
php artisan test --filter=TeacherAttendanceDashboardDisabledUiTest --env=testing
git diff -- app/Services/AttendanceService.php tests/Unit/Services/AttendanceServiceMarkAttendanceGuardTest.php
Get-Content tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php
```

## Test Result Summary

```text
php -l app/Services/AttendanceService.php
No syntax errors detected

php -l tests/Unit/Services/AttendanceServiceMarkAttendanceGuardTest.php
No syntax errors detected

php artisan test --filter=AttendanceServiceMarkAttendanceGuardTest --env=testing
PASS - 3 tests, 7 assertions

php artisan test --filter=TeacherAttendanceStoreGuardTest --env=testing
PASS - 5 tests, 10 assertions

php artisan test --filter=TeacherAttendanceUpdateGuardTest --env=testing
PASS - 5 tests, 10 assertions

php artisan test --filter=TeacherAttendanceDashboardDisabledUiTest --env=testing
PASS - 6 tests, 13 assertions
```

The targeted test runs emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests/classes during discovery. No targeted test failed.

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

The new guard tests use isolated SQLite in memory.

## Remaining Risks

- `AttendanceService` read/report methods still count `leave`.
- `AttendanceService` read/report methods still depend on `students.class_id`.
- Teacher attendance mark/edit/report/student controller routes remain registered, though dashboard links are disabled and backing views are missing.
- A safe teacher attendance write workflow still needs design before teacher marking/update can be re-enabled.
- The old unreachable write code still exists below the guard and should be removed or replaced when the future workflow is designed.

## Recommended Phase 7F Next Step

Phase 7F should perform a read-only audit of `AttendanceNotificationService` and teacher attendance notification/report callers.

Recommended focus:

- confirm notification methods only use read/report paths
- audit direct `new AttendanceService()` usage
- decide whether read/report methods should be split into a dedicated read-only attendance report service
- keep teacher writes disabled until class/status/schema policy is rebuilt
