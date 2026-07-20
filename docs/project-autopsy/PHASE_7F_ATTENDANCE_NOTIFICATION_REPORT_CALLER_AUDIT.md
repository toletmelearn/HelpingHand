PHASE 7F — ATTENDANCE NOTIFICATION / REPORT CALLER AUDIT

Date: 2026-06-07

Summary
- Goal: Read-only audit of AttendanceNotificationService and all teacher-attendance read/report callers that use AttendanceService.
- Constraints: No code changes, no writes, no notification sends, no migrations, no full test runs. All inspection performed by reading files and running safe lint / route-list commands.

Files inspected
- app/Services/AttendanceNotificationService.php
- app/Services/AttendanceService.php
- app/Http/Controllers/Teacher/TeacherAttendanceController.php
- app/Models/Attendance.php
- app/Models/Student.php
- app/Models/User.php
- app/Models/Teacher.php
- app/Notifications/LowAttendanceAlert.php
- app/Notifications/AttendanceMarked.php
- app/Http/Controllers/Admin/SmartAttendanceController.php
- app/Console/Commands/AttendanceNullPeriodDiagnosticsCommand.php
- resources/views/teacher/attendance/dashboard.blade.php (not modified, inspected by path existence)
- tests/Unit/Services/AttendanceServiceMarkAttendanceGuardTest.php (not executed)
- tests/Feature/Attendance/TeacherAttendanceDashboardDisabledUiTest.php (not executed)
- docs/project-autopsy/PHASE_7D_ATTENDANCE_SERVICE_RESIDUAL_RISK_AUDIT.md
- docs/project-autopsy/PHASE_7E_ATTENDANCE_SERVICE_MARKATTENDANCE_GUARD.md

Commands run (read-only / safe)
- `php -l app/Services/AttendanceNotificationService.php` — syntax OK
- `php -l app/Services/AttendanceService.php` — syntax OK
- `php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php` — syntax OK
- `php artisan route:list | Select-String "attendance"` — displayed attendance-related routes (teacher and API routes observed)
- `php artisan route:list | Select-String "notification"` — displayed admin notification routes
- `php artisan list --raw | Select-String "attendance"` — showed custom attendance CLI command `helpinghand:attendance-null-period-diagnostics`
- `rg`/codegrep searches inside `app/**` for: `AttendanceNotificationService`, `getLowAttendanceAlerts`, `getTeacherClassAttendance`, `sendBulkAttendanceNotifications`, `LowAttendanceAlert`, `AttendanceMarked` (internal workspace grep used; no runtime actions)

Work Part A — AttendanceNotificationService findings
1. Public methods (observed)
  - `sendLowAttendanceAlerts($threshold = 75, $periodDays = 30)`
  - `sendAttendanceMarkedNotification($studentId, $date, $status)`
  - `sendDailyAttendanceSummary($teacherId, $date = null)`
  - `sendWeeklyAttendanceReport($adminId = null)`
  - `sendBulkAttendanceNotifications($attendanceRecords)`
  - `getUserNotificationPreferences($userId)`
  - `scheduleNotifications()`

2. Does it send email/SMS/notifications?
  - Yes. The service uses `Notification::send()` and constructs `LowAttendanceAlert` and `AttendanceMarked` notification objects. Those notification classes' `via()` return `['mail','database']`, so executing these methods will attempt to send email and write a database notification record (and queue them if configured).

3. Does it write database records?
  - Indirectly: notifications call `via()` channels that include `database` (so DatabaseNotification entries would be written when executed). The service itself does not directly call `create()` on models, but `Notification::send()` can persist notifications.

4. Does it directly instantiate `AttendanceService`?
  - Yes. Methods `sendLowAttendanceAlerts`, `sendDailyAttendanceSummary`, and `sendWeeklyAttendanceReport` create `new AttendanceService()`.

5. Which `AttendanceService` methods are called?
  - `getLowAttendanceAlerts($threshold, $periodDays)` — used by `sendLowAttendanceAlerts` and `sendWeeklyAttendanceReport`.
  - `getTeacherClassAttendance($teacherId, $date)` — used by `sendDailyAttendanceSummary`.
  - No other AttendanceService methods are called here.

6. Does it call `markAttendance()`?
  - No. `AttendanceNotificationService` does not call `markAttendance()`; it only calls read/report methods.

7. Does it depend on `leave` semantics?
  - Indirectly. `getLowAttendanceAlerts()` (in AttendanceService) inherits the `leave`-based handling from `getStudentAttendanceStats()` which counts `leave` days explicitly; therefore alerts based on those methods inherit `leave` semantics.

8. Does it depend on teacher assignments/class_id?
  - `getTeacherClassAttendance()` uses `TeacherClassSubjectAssignment` to resolve classes and then queries `Student::where('class_id', $classId)`. `getLowAttendanceAlerts()` operates over all students (`Student::with('attendances')->get()`), so both methods depend on `Student.class_id` / assignment relationships.

9. Could it be run automatically from commands/schedulers/jobs?
  - Not found to be wired to a scheduled Kernel job in `app/Console` (no `Kernel.php` scheduling file was present in `app/Console` during this read). However:
    - Admin UI exposes `SmartAttendanceController::sendAttendanceAlerts()` which directly constructs and sends `LowAttendanceAlert` notifications.
    - There are admin routes and notification settings routes that could trigger bulk sends from the web UI or admin console.
    - No automatic scheduler entry was found in the inspected project files, but manual triggers exist (admin UI and potential CLI commands).

10. Is it safe to leave active while teacher writes are guarded?
  - Partial: The service itself does not call `markAttendance()` so it cannot perform writes to attendance records. However it will send notifications (email and database notifications) when executed, and it depends on AttendanceService read methods that are known to use `leave` and `class_id` semantics which may produce inaccurate alerts. Therefore leaving it active is safe from a write-safety perspective, but it may generate misleading alerts due to read/report vocabulary issues.

Work Part B — Caller / Trigger audit (callers found)
1. Controllers that call `AttendanceNotificationService` or AttendanceService read/report methods:
  - `App\Http\Controllers\Teacher\TeacherAttendanceController` (constructor-injected `AttendanceNotificationService` and `AttendanceService`) — calls:
    - `$this->attendanceService->getTeacherClassAttendance(...)`
    - `$this->attendanceService->getClassAttendanceSummary()`
    - `$this->attendanceService->getLowAttendanceAlerts(...)`
    - `notificationService->sendBulkAttendanceNotifications(...)` is present in `storeAttendance()` but that controller method returns early (writes disabled), so route won't trigger notifications via store.
  - `App\Http\Controllers\Admin\SmartAttendanceController` — directly constructs and sends notifications using `LowAttendanceAlert`, and has `sendAttendanceAlerts()` endpoint that will notify guardians/teachers.
  - Other controllers: standard attendance controllers and API controllers call AttendanceService read methods (routes were listed by `php artisan route:list`), but explicit call-sites in `app/**` for `getLowAttendanceAlerts` were limited to the three files above.

2. Commands / Jobs that call it:
  - No console command was found that calls `AttendanceNotificationService` directly in `app/Console/Commands` (search results showed `AttendanceNullPeriodDiagnosticsCommand` and other admin utilities but not a scheduled notification runner).

3. Scheduled tasks (visible):
  - No scheduler entry found in `app/Console` files inspected. I did not find `Kernel.php` in the expected location during this read; scheduling may be configured elsewhere, but no scheduled tasks calling `AttendanceNotificationService` were found in inspected files.

4. Can any trigger send notifications automatically?
  - Yes: admin UI endpoints such as `Admin\SmartAttendanceController::sendAttendanceAlerts` are capable of sending notifications when executed manually by an admin user. Also any future CLI or job that calls `AttendanceNotificationService` would send notifications.

5. Are any callers behind guarded teacher store?
  - Teacher controller's `storeAttendance()` route includes a call to `sendBulkAttendanceNotifications`, but the method currently returns early (writes/notifications disabled), so it will not execute. Admin controller methods are not guarded and can send notifications via admin UI routes.

6. Are any callers still active?
  - Teacher dashboard reads are active and will display `lowAttendanceAlerts` via read methods (may show inaccurate results).
  - Admin routes that send alerts appear active and could send notifications when invoked (manual action required).

Work Part C — Read/report accuracy audit (AttendanceService)
1. Where `leave` is counted
  - `getStudentAttendanceStats()` explicitly counts `leave` with `$attendanceRecords->where('status', 'leave')->count()`.
  - `getClassAttendanceSummary()` also includes `'leave' => $attendance->where('status', 'leave')->count()`.
  - `getLowAttendanceAlerts()` uses `getStudentAttendanceStats()` so it inherits `leave` handling.

2. Where `late` / `half_day` are omitted or misrepresented
  - `getStudentAttendanceStats()` computes `present_days` as only `status == 'present'`. It does not treat `late` or `half_day` as partial or full present. However `totalDays` includes all records (including `late` and `half_day`), so attendance rate is `present_days / totalDays`. This undercounts `late` (and `half_day`) contributions and therefore under-reports attendance when those statuses should count toward present in some policy definitions.
  - `generateAttendanceReport()` and `getAttendanceTrends()` also rely on `getStudentAttendanceStats()` and thus inherit the same omission.

3. Whether notification thresholds may be wrong
  - Yes. Since `getLowAttendanceAlerts()` uses `attendance_rate` computed as `present_days / totalDays` without crediting `late` or `half_day` toward present, some students with mostly `late` or `half_day` records may be incorrectly flagged as low attendance.

4. Whether dashboard summaries may be stale/inaccurate
  - Dashboard summaries using `getClassAttendanceSummary()` and `getLowAttendanceAlerts()` may be inaccurate relative to the intended policy if `late`/`half_day` should count differently, or if `Student.class_id` is not canonical for class membership.

5. Whether reports should be marked legacy/disabled until refactor
  - Given the known vocabulary mismatch and class_id reliance, reports and notification alerts should be considered legacy and potentially misleading until the reporting methods are refactored or updated to the current attendance status vocabulary and canonical class resolution logic.

Work Part D — Service boundary options (trade-offs)
- Option A: Leave read/report services active but mark as legacy in docs
  - Pros: Minimal code changes; reporting remains available for exploratory use.
  - Cons: Users may receive misleading alerts; continued risk of miscommunication.

- Option B: Guard notification sending methods temporarily (e.g., require admin flag)
  - Pros: Prevents accidental notification sends; quick mitigation.
  - Cons: Alerts disabled until unguarded; requires adding guards in code (deferred to Phase 7G).

- Option C: Split read-only reporting into `AttendanceReportService`
  - Pros: Clear separation; can implement safe read-only semantics and canonical class resolution.
  - Cons: Larger refactor; more work.

- Option D: Refactor read/report methods to the main status vocabulary
  - Pros: Correct long-term behavior; ensures `late`/`half_day` are handled per policy.
  - Cons: Requires careful policy decisions and tests; medium effort.

- Option E: Disable teacher notification/report calls until attendance policy is rebuilt
  - Pros: Eliminates risk of incorrect notifications.
  - Cons: Removes functionality; may impact stakeholders relying on alerts.

Work Part E — Phase 7G recommended first code task
- If admin-triggered notifications (e.g., `SmartAttendanceController::sendAttendanceAlerts`) are actively used, the smallest safe next task is to temporarily guard notification-sending endpoints to prevent accidental sends while reporting semantics are corrected. Specifically:
  1. Add a short-term guard (feature-flag or environment check) around admin notification endpoints and `AttendanceNotificationService::send*` methods to prevent `Notification::send()` from executing in production until read/report methods are reconciled. (Low work, reversible.)
  2. In parallel, add unit tests that assert how `late` and `half_day` should be counted for attendance rates, then update `getStudentAttendanceStats()` and derived methods to reflect the agreed policy (Phase 7G larger task).

- If admin notification endpoints are not actively used, prioritize refactoring `AttendanceService` reporting methods to adopt canonical status handling (`present` includes `late`/`half_day` according to policy or mapped as partial credit) and canonical `Student` class resolution (use `canonicalClassId()`), then re-enable notifications.

Risk classification
- Write-safety risk: LOW. `AttendanceNotificationService` does not call `markAttendance()` and therefore cannot write attendance records. However notifications will write to the notifications table and send emails when executed.
- Accuracy risk: MEDIUM-HIGH. Read/report methods undercount `late`/`half_day` and depend on `class_id`, risking incorrect alerts and dashboard summaries.
- Trigger risk: MEDIUM. Admin UI endpoints (`SmartAttendanceController::sendAttendanceAlerts`) can trigger notifications; teacher store paths that would call notification methods are currently short-circuited/disabled.

Recommended minimal next action (Phase 7G first task)
- Implement a temporary guard around notification sends (admin UI and service) to prevent Notification::send() from executing until reporting semantics are fixed; and
- Add tests defining desired policy for `late` and `half_day` so `getStudentAttendanceStats()` can be updated safely.

Rationale
- Guarding prevents accidental message sends (which include database notification entries and emails) with minimal risk and low code churn. Defining and testing the intended attendance vocabulary first avoids repeated notification churn after fixes.

Final confirmations (constraints)
- No application code was modified during this audit. (READ-ONLY)
- No database data was created, updated, or deleted.
- No notification sends, export jobs, or biometric syncs were executed.
- No migrations, composer, or full test suite runs were performed.

Appendix — Notable code excerpts (read-only references)
- `AttendanceNotificationService::sendLowAttendanceAlerts()` instantiates `new AttendanceService()` and calls `getLowAttendanceAlerts()` and then `Notification::send($parent, new LowAttendanceAlert(...))` which uses `via()` = `['mail','database']`.
- `AttendanceService::getStudentAttendanceStats()` computes `attendance_rate` as `present_days / totalDays` and explicitly counts `leave` but not `late`/`half_day` towards `present_days`.
- `SmartAttendanceController::sendAttendanceAlerts()` directly calls `notify(new LowAttendanceAlert(...))` for guardians and teachers — this route can send notifications when admin triggers it.

Report path
- docs/project-autopsy/PHASE_7F_ATTENDANCE_NOTIFICATION_REPORT_CALLER_AUDIT.md

If you want, I can:
- Create a small PR that adds temporary guards around admin notification endpoints (non-invasive, feature-flag based), or
- Open Phase 7G tasks: (A) add tests for `late`/`half_day` policy, (B) refactor `getStudentAttendanceStats()` and derived methods, (C) split `AttendanceReportService`.

