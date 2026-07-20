# Phase 7D - Attendance Service Residual Risk Audit

Date: 2026-06-07

Scope: Read-only audit of `AttendanceService` usage, write methods, read/report methods, and status/class assumptions after teacher attendance store/update UI and write guards.

## Files Inspected

- `app/Services/AttendanceService.php`
- `app/Services/AttendanceNotificationService.php`
- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `routes/web.php`
- `routes/api.php`
- `resources/views/teacher/attendance/dashboard.blade.php`
- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`
- `tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php`
- `tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php`
- `database/migrations/2026_01_21_083000_create_attendances_table.php`
- `database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php`
- `docs/project-autopsy/PHASE_6X_TEACHER_ATTENDANCE_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md`
- `docs/project-autopsy/PHASE_7A_TEACHER_ATTENDANCE_UPDATE_GUARD.md`
- `docs/project-autopsy/PHASE_7C_TEACHER_ATTENDANCE_DASHBOARD_DISABLED_UI.md`

## Commands Run

```powershell
Get-Content app/Services/AttendanceService.php
Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content app/Models/Attendance.php
Get-Content resources/views/teacher/attendance/dashboard.blade.php
rg -n "AttendanceService" app routes resources tests
rg -n "markAttendance\(" app routes resources tests
rg -n "getTeacherClassAttendance|getClassAttendanceSummary|getLowAttendanceAlerts|generateAttendanceReport|getStudentAttendanceStats|getClassAttendanceStats|getAttendanceTrends|getWorkingDays" app routes resources tests
Get-ChildItem resources/views/teacher/attendance -Force
Get-Content app/Services/AttendanceNotificationService.php
Get-Content app/Services/Attendance/AttendanceClassResolver.php
Get-Content app/Services/Attendance/AttendanceBulkPreflightService.php
Get-Content routes/web.php
rg -n "create_attendances|table\('attendances'|attendances" database/migrations app/Models/Attendance.php app/Services/AttendanceService.php
php -l app/Services/AttendanceService.php
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l app/Models/Attendance.php
Get-Content database/migrations/2026_01_21_083000_create_attendances_table.php
Get-Content database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php
php artisan route:list | Select-String "attendance"
php artisan route:list | Select-String "teacher"
Get-Content docs/project-autopsy/PHASE_6X_TEACHER_ATTENDANCE_WRITE_AUDIT.md
Get-Content docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md
Get-Content docs/project-autopsy/PHASE_7A_TEACHER_ATTENDANCE_UPDATE_GUARD.md
Get-Content docs/project-autopsy/PHASE_7C_TEACHER_ATTENDANCE_DASHBOARD_DISABLED_UI.md
Get-Content tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php
Get-Content tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php
Get-Content tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php
Get-Content routes/api.php
```

No optional live database checks were run. The residual service risks were clear from source, migration, route, and caller inspection, and avoiding local MySQL kept this phase read-only.

## Service Method Inventory

| Method | Category | Callers found | Writes data | Key assumptions |
| --- | --- | --- | --- | --- |
| `getStudentAttendanceStats($studentId, $startDate = null, $endDate = null)` | read/report | internal service methods, `TeacherAttendanceController@studentAttendance` | No | Counts `present`, `absent`, and legacy `leave`; ignores `late` and `half_day` in returned counts. |
| `getClassAttendanceStats($classId, $startDate = null, $endDate = null)` | read/report | no external app caller found by `rg` | No | Selects students by `students.class_id`; delegates to `getStudentAttendanceStats()`. |
| `getWorkingDays($startDate = null, $endDate = null)` | utility/helper | `generateAttendanceReport()` | No | Weekdays only; not attendance-schema sensitive. |
| `markAttendance($attendanceData, $teacherId = null)` | write | only known app caller is guarded `TeacherAttendanceController@storeAttendance()` | Yes | Uses unsafe teacher-era assumptions: `updateOrCreate`, `class_id`, `leave`, and teacher id as `marked_by`. |
| `getAttendanceTrends($studentId, $months = 6)` | read/report | `TeacherAttendanceController@studentAttendance` | No | Delegates to `getStudentAttendanceStats()`, so inherits `leave` vocabulary. |
| `getClassAttendanceSummary($classId = null, $date = null)` | read/report | teacher dashboard, teacher mark/report paths, `getTeacherClassAttendance()` | No | Counts `present`, `absent`, and `leave`; filters classes through `students.class_id`. |
| `getLowAttendanceAlerts($threshold = 75, $periodDays = 30)` | read/report | teacher dashboard, `AttendanceNotificationService` | No | Uses all students and `getStudentAttendanceStats()`; inherits `leave` vocabulary. |
| `getTeacherClassAttendance($teacherId, $date = null)` | read/report | teacher dashboard, teacher reports, `AttendanceNotificationService` | No | Uses teacher assignments, `students.class_id`, and student/date attendance lookup. |
| `generateAttendanceReport($classId, $startDate, $endDate)` | read/report | teacher reports route | No | Uses `students.class_id`, `SchoolClass::find($classId)`, and stats that count `leave`. |

No notification-related public method exists in `AttendanceService` itself. Notification-related callers live in `AttendanceNotificationService`, which directly instantiates `AttendanceService` for read/report methods.

## Write Method Findings

`AttendanceService::markAttendance()` remains risky if called.

Current write behavior:

- Starts a manual DB transaction.
- Iterates submitted records.
- Calls `Attendance::updateOrCreate()`.
- Lookup key is only:
  - `student_id`
  - `date`
- Writes:
  - `status`
  - `remarks`
  - `marked_by`
  - `class_id`
- Commits on success, rolls back/logs/rethrows on exception.

Residual risks:

- It can overwrite an existing attendance row for the same `student_id/date`, regardless of `period`.
- It ignores the actual unique key shape of `student_id,date,period`.
- It does not use `AttendanceClassResolver`.
- It does not derive legacy `attendances.class` from the student.
- It writes `class_id`, but the main `attendances` table and `Attendance::$fillable` are legacy `class` based.
- It writes `marked_by` from the supplied `$teacherId`, while `attendances.marked_by` references `users.id`.
- It accepts whatever status is passed by the caller; the old teacher controller path used `present`, `absent`, and `leave`.
- It does not check terminal/inactive student status.
- It does not verify student/class membership.
- It does not set `period`, `subject`, `session`, `ip_address`, or `device_info`.

Reachability:

- The only app caller found for `AttendanceService::markAttendance()` is the old unreachable line inside `TeacherAttendanceController@storeAttendance()`.
- Phase 6Y placed a return guard at the start of `storeAttendance()`, before validation and before `markAttendance()` is called.
- No unguarded active caller was found by `rg`.
- Tests also mock `AttendanceService` and assert `markAttendance()` is not called from the guarded teacher store route.

Conclusion: no known unguarded runtime path currently reaches `markAttendance()`, but the method itself remains a RED residual risk because it is public, broadly named, and unsafe if reused later.

## Read / Report Method Findings

The read/report methods are safer than the write method because they do not mutate attendance rows, but several still carry stale teacher-era assumptions.

Status vocabulary:

- `getStudentAttendanceStats()` counts `leave`.
- `getClassAttendanceSummary()` counts `leave`.
- `getAttendanceTrends()`, `getLowAttendanceAlerts()`, and `generateAttendanceReport()` inherit `leave` handling through `getStudentAttendanceStats()`.
- These methods do not count `late` or `half_day` in the same way the main attendance model/API/web paths do.
- The main migration defines attendance status as `present`, `absent`, `late`, and `half_day`.

Class assumptions:

- `getClassAttendanceStats()` loads students by `students.class_id`.
- `getClassAttendanceSummary()` filters attendance by `whereHas('student', fn => class_id)`, not by legacy `attendances.class`.
- `getTeacherClassAttendance()` uses `TeacherClassSubjectAssignment`, `schoolClass->id`, and `students.class_id`.
- `generateAttendanceReport()` uses `students.class_id`.
- These class-id reads may be acceptable for teacher assignment reports, but they do not use the canonical compatibility helpers or `AttendanceClassResolver`.

Period/null assumptions:

- Read/report methods generally query by date and student ids.
- They do not explicitly distinguish full-day null-period records from period-specific rows.
- `markAttendance()` would write without a period if reached, which is unsafe given prior null-period audit findings.

Current visible UI:

- The teacher dashboard still calls read methods:
  - `getTeacherClassAttendance()`
  - `getClassAttendanceSummary()`
  - `getLowAttendanceAlerts()`
- Phase 7C disabled dashboard links to missing/unsafe teacher flows, but dashboard summaries remain visible.
- These read methods are acceptable to keep active only as best-effort legacy summaries, not as a source of policy truth.

## Caller Findings

`AttendanceService` callers found:

- `TeacherAttendanceController` receives it through constructor injection.
- `AttendanceNotificationService` directly creates `new AttendanceService()` for read/report methods.
- Tests bind/mimic `AttendanceService` to confirm teacher store/update guards.

`markAttendance()` callers found:

- `TeacherAttendanceController@storeAttendance()` contains an old call, but it is after the Phase 6Y guard return and is unreachable in ordinary execution.
- No other app caller was found.

Read/report callers found:

- `TeacherAttendanceController@dashboard()` calls:
  - `getTeacherClassAttendance()`
  - `getClassAttendanceSummary()`
  - `getLowAttendanceAlerts()`
- `TeacherAttendanceController@markAttendance()` calls `getClassAttendanceSummary()`, but its returned view is missing and dashboard no longer links to it.
- `TeacherAttendanceController@reports()` calls `getTeacherClassAttendance()` and `generateAttendanceReport()`, but the reports view is missing and dashboard no longer links to it.
- `TeacherAttendanceController@studentAttendance()` calls `getStudentAttendanceStats()` and `getAttendanceTrends()`, but the student view is missing and dashboard no longer links to it.
- `AttendanceNotificationService` calls:
  - `getLowAttendanceAlerts()`
  - `getTeacherClassAttendance()`

Reachability assessment:

- No unsafe write caller remains unguarded.
- Read/report methods remain reachable through the teacher dashboard and notification service helpers.
- Missing view routes still exist and can be hit by crafted/direct navigation, but the dashboard no longer promotes them.
- The service can still be accidentally reused later because the unsafe write method remains public and unguarded internally.

## Model / Schema Assumption Findings

`Attendance::$fillable` includes:

- `student_id`
- `teacher_id`
- `date`
- `status`
- `remarks`
- `period`
- `subject`
- `class`
- `session`
- `marked_by`
- `ip_address`
- `device_info`

`Attendance::$fillable` does not include:

- `class_id`
- `updated_by`

Main attendance migration defines:

- `status` enum: `present`, `absent`, `late`, `half_day`
- legacy `class` string column
- nullable `period`
- `marked_by` as a users foreign key
- unique key on `student_id,date,period`

Additional relationship migration conditionally adds:

- `updated_by` to `attendances`, referencing `users.id`

Service mismatches:

- `markAttendance()` writes `class_id`, but the attendance model/table are not designed around `attendances.class_id`.
- `markAttendance()` writes `marked_by` using a teacher id from the old teacher path, not a user id.
- Old teacher update code attempted `updated_by => $teacher->id`, while the migration references `users.id`; that path is now guarded.
- Teacher-era status `leave` is counted by the service but is not part of the main attendance status enum.
- Write identity key `student_id/date` does not match the database unique key `student_id/date/period`.

## Risk Classification

RED:

- `AttendanceService::markAttendance()` remains public and internally unsafe if any future or hidden caller reaches it.
- The write method can overwrite rows using only `student_id/date`.
- The write method uses unsupported `class_id`.
- The write method does not use `AttendanceClassResolver`.
- The write method does not enforce main status vocabulary.
- The write method does not check terminal/inactive students.

YELLOW:

- Teacher dashboard and notification read methods still count `leave`.
- Read/report methods depend on `students.class_id` and do not use canonical compatibility helpers.
- Teacher report/student/mark routes remain registered even though dashboard links are disabled and views are missing.
- `AttendanceNotificationService` constructs `AttendanceService` directly instead of container injection, making future quarantining/splitting slightly harder.
- Read summaries omit or underrepresent `late` and `half_day` in teacher-era calculations.

GREEN:

- No unguarded app caller to `markAttendance()` was found.
- Teacher store and update are guarded before service write/update logic.
- Teacher dashboard no longer links to missing mark/report/student/export flows.
- The service read/report methods do not write data.
- API/web/preflight hardened paths do not depend on `AttendanceService::markAttendance()`.

## Safe Implementation Options

### Option A - Add an internal guard to `AttendanceService::markAttendance()`

Make `markAttendance()` fail closed with a clear exception or disabled result until teacher class/status/schema policy is aligned.

Pros:

- Smallest direct protection against future accidental reuse.
- Matches the controller guard posture.
- Does not affect dashboard read/report methods.

Cons:

- Could break any hidden caller not found by source search.
- Requires tests to assert the disabled behavior.

### Option B - Leave the service unchanged because known callers are guarded

Pros:

- No immediate behavior change.
- Avoids risk of surprising any obscure caller.

Cons:

- Leaves a public dangerous method in place.
- Future developers may reasonably call it because of its generic name.

### Option C - Split the service

Create a read-only `AttendanceReportService` and isolate/quarantine future teacher write behavior in a separate service.

Pros:

- Clean long-term boundary.
- Makes read/report surfaces easier to keep active while teacher writes are disabled.

Cons:

- Larger refactor.
- More risk than needed for the next safety step.

### Option D - Refactor `markAttendance()` to the hardened policy

Use `AttendanceClassResolver`, main status vocabulary, correct user identity, terminal/inactive checks, and duplicate-safe keys.

Pros:

- Moves toward re-enabling teacher attendance safely.

Cons:

- Larger design phase.
- Requires product decision for `leave` and teacher workflow semantics.

## Recommended Phase 7E First Code Task

Phase 7E should guard `AttendanceService::markAttendance()` internally with a clear disabled exception or disabled result, because no safe active caller remains and the method is still hazardous if reused.

Recommended details:

- Add an early fail-closed guard to `markAttendance()`.
- Message:
  - `AttendanceService::markAttendance is temporarily disabled until class/status/schema policy is aligned.`
- Keep read/report methods unchanged.
- Add focused tests proving:
  - direct calls to `markAttendance()` do not write rows
  - the disabled message is clear
  - teacher store guard still prevents the controller from calling the service
  - teacher dashboard read tests still pass
- Do not refactor status/class policy yet.

After Phase 7E, a later phase can split read/report service responsibilities and design a safe teacher attendance write workflow.

## Confirmation

This phase only created this report.

No application code, controllers, routes, services, models, views, tests, migrations, schema, database data, attendance writes, attendance deletes, exports, imports, seeds, sync jobs, biometric device commands, full test suite, or real/local MySQL data were modified or run.
