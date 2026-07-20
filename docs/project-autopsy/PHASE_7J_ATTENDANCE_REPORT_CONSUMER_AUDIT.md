# PHASE 7J — Attendance Report Consumer Audit (Read-only)

Date: 2026-06-07

Summary
- Goal: Read-only audit of all consumers of `attendance_rate`, `attendance_credit`, `late_days`, `half_days`, `leave_days`, and derived attendance report outputs after Phase 7I.
- Constraints: Read-only. No code, migrations, tests, views, controllers, or models were modified. No notification sends, attendance writes, exports, device syncs, or full test suite runs.

Files inspected (read-only)
- app/Services/AttendanceService.php
- app/Services/AttendanceNotificationService.php
- app/Http/Controllers/Teacher/TeacherAttendanceController.php
- app/Http/Controllers/Admin/SmartAttendanceController.php
- app/Http/Controllers/AttendanceController.php
- app/Http/Controllers/API/AttendanceController.php
- app/Models/Attendance.php
- resources/views/teacher/attendance/dashboard.blade.php
- resources/views/attendance/index.blade.php
- app/Notifications/LowAttendanceAlert.php
- app/Notifications/AttendanceMarked.php
- tests/Unit/Services/AttendanceServiceStatusCalculationTest.php
- docs/project-autopsy/PHASE_7I_ATTENDANCE_REPORT_STATUS_CALCULATION_FIX.md
- docs/project-autopsy/PHASE_7G_ATTENDANCE_NOTIFICATION_SEND_GUARD.md
- docs/project-autopsy/PHASE_7H_ATTENDANCE_REPORT_STATUS_POLICY_AUDIT.md

Commands / read operations performed (read-only)
- Inspected files via repository read operations (file reads of the list above).
- Executed targeted `php artisan test` and `php -l` during Phase 7I earlier; for this Phase 7J I performed read-only file inspections and selected controller linting (`php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php`) to confirm syntax.
- Attempted repo search via PowerShell; fell back to direct file inspection because environment `rg`/recursive Select-String behaved inconsistently in the terminal. All inspections were read-only file reads.

Work Part A — Consumer search findings
(Consumers found by inspecting the files above)

Consumers that reference `attendance_rate` or compute a percentage:
- `app/Services/AttendanceService::getStudentAttendanceStats()` — returns new `attendance_rate` (Phase 7I) and `attendance_credit`.
- `app/Services/AttendanceService::getAttendanceTrends()` — pulls `attendance_rate` from `getStudentAttendanceStats()` for monthly trends.
- `app/Services/AttendanceService::generateAttendanceReport()` — aggregates `attendance_rate` to produce `summary.class_average`.
- `app/Services/AttendanceService::getLowAttendanceAlerts()` — uses `attendance_rate` to select alerting students.
- `app/Http/Controllers/Teacher/TeacherAttendanceController::dashboard()` — calls `getTeacherClassAttendance()` and displays `class['summary']['attendance_rate']` in view.
- `app/Http/Controllers/Teacher/TeacherAttendanceController::reports()` — uses `generateAttendanceReport()` for report display.
- `app/Http/Controllers/Admin/SmartAttendanceController::getAttendanceStatistics()` — computes `attendance_rate` locally using `present / total` (does not use `attendance_credit`).
- `app/Http/Controllers/Admin/SmartAttendanceController::getAttendanceTrends()` — computes daily attendance rates using `present/total` (controller-local query).
- `app/Http/Controllers/Admin/SmartAttendanceController::getAttendanceWarnings()` — computes `attendancePercentage` as `presentDays / totalDays` and produces warnings.
- `app/Http/Controllers/AttendanceController::calculateAttendanceStats()` — computes `attendance_rate` as `presentToday / totalAttendance`.
- `app/Models/Attendance::getAttendanceStats()` and `getStudentMonthlyReport()` — compute percentages using present/total; do not use `attendance_credit`.
- `resources/views/teacher/attendance/dashboard.blade.php` — displays `attendance_rate` and class summaries (from `class['summary']['attendance_rate']`) and low-attendance alerts showing `attendance_rate` badges.
- `resources/views/attendance/index.blade.php` — displays `stats['attendance_rate']` (controller-supplied), and various attendance pages rely on `Attendance::getAttendanceStats()` output.
- `app/Notifications/LowAttendanceAlert` — uses `alertData['attendance_rate']` and `absent_days` when composing messages.

Consumers that reference `attendance_credit`, `late_days`, `half_days`, `leave_days` (after Phase 7I additions):
- `app/Services/AttendanceService` now returns `attendance_credit`, `late_days`, `half_days`, and `leave_days` from `getStudentAttendanceStats()`.
- Most other consumers (controllers, models, views, notifications) have not been updated to read `attendance_credit`, `late_days`, or `half_days`; they still compute percentages from `present`/`total` or use model helpers.
- `LowAttendanceAlert` notification payload and views still use `attendance_rate` and `absent_days` only; `late_days`/`half_days` are not included.

Work Part B — Dashboard consumer findings
1. Where attendance rates are displayed
- Teacher dashboard (`resources/views/teacher/attendance/dashboard.blade.php`) displays:
  - Top-level `Attendance Rate` (from `todaySummary['attendance_rate']`).
  - Per-class `attendance_rate` badges/progress bars via `class['summary']['attendance_rate']`.
  - Low attendance alerts show `attendance_rate` and `absent_days`.
- Admin attendance pages (`resources/views/attendance/index.blade.php`) show `stats['attendance_rate']` in performance metrics.

2. Whether labels explain the new credit policy
- No. There are no descriptions or labels explaining that `late` is now counted as full credit or that `half_day` counts as 0.5 credit. Dashboards show only `Attendance Rate` with percentage values and no explanatory tooltip or help text.

3. Whether late/half-day are shown separately
- Some views show individual attendance records with status badges (Present/Late/Half Day/Absent).
- Summary widgets and class summaries do not display `late_days` or `half_days` counts; they only show `present`/`absent` or computed `attendance_rate`.

4. Whether leave appears anywhere
- `leave` is not a valid status under current migration/validation; some legacy code counts `leave` (e.g., `AttendanceService::getStudentAttendanceStats()` still returns `leave_days`), but dashboards/views do not generally render `leave` as a top-level summary. Admin/teacher summary methods sometimes compute `leave` in controller-level arrays (e.g., SmartAttendanceController::getAttendanceStatistics returns `half_day` and `late` and `attendance_rate` computed with present/total; it does not include `leave`).

5. Whether dashboard summaries may confuse users after Phase 7I
- Yes. Since dashboards continue to compute or display `attendance_rate` using `present/total` in several places (controllers and model helpers) while `AttendanceService` now computes `attendance_rate` based on `attendance_credit`, different pages/consumers may show inconsistent percentages. Also, dashboards lack explanatory text for `late`/`half_day` credit, so users won't understand why rates changed.

6. Whether dashboard should show “late counted as attendance credit” later
- Yes. It's recommended to add a short explanatory label or tooltip near overall/class `Attendance Rate` and Low Attendance alerts: e.g., "Attendance credit: present=1, late=1, half_day=0.5; leave = legacy".

Work Part C — Report consumer findings
1. Where class averages are computed/displayed
- `AttendanceService::generateAttendanceReport()` computes `summary.class_average` by averaging per-student `attendance_rate` returned by `getStudentAttendanceStats()`.
- Admin report views (`admin.attendance.monthly-report`, `admin.attendance.bulk-monthly-reports`) consume `Attendance::getStudentMonthlyReport()` which still computes percentages using present/total.

2. Whether class average now uses `attendance_credit`
- When `generateAttendanceReport()` is invoked via `AttendanceService` it uses the new `attendance_rate` (based on `attendance_credit`) for class averages; however many report paths still call model helpers (`Attendance::getStudentMonthlyReport()` or `Attendance::getAttendanceStats()`), which compute percentages using present/total, leading to inconsistent values across different report screens.

3. Whether late/half-day are visible in reports
- Detailed student monthly reports show per-day `status` entries (Present/Late/Half Day), but report summaries (`percentage`) do not currently account for partial/full credit for late/half_day. They will need to be updated to include `attendance_credit` or expose `late_days`/`half_days`.

4. Whether legacy leave is visible
- Legacy `leave` is present in some service outputs (e.g., `getStudentAttendanceStats()` returns `leave_days`) but is not part of model migration or active write flows. Reports may not show `leave` unless historical data contains it.

5. Whether report labels need update
- Yes. Any report that shows `percentage` should include a note describing the credit policy. Additionally, consider adding `attendance_credit` and `late_days`/`half_days` columns in summary exports to make the basis of the percentage explicit.

6. Whether export/report output consumers should be updated later
- Yes. Export consumers (CSV/Excel) should include `attendance_credit`, `late_days`, and `half_days` and preferably a header note explaining credit calculation before unguarding notifications or alerting stakeholders.

Work Part D — Alert / Notification consumer findings
1. Do notifications remain guarded
- Yes. `AttendanceNotificationService` methods are fail-closed and `SmartAttendanceController::sendAttendanceAlerts` is guarded with an early return; notification sends are not executed.

2. What would happen if re-enabled today
- If re-enabled without updating consumers and labels, notifications would use `getLowAttendanceAlerts()` which now uses `attendance_rate` from `getStudentAttendanceStats()` (Phase 7I). However many controller-level and model helper consumers still compute percentage by `present/total`. If notifications relied on controller logic elsewhere, inconsistent criteria could exist. Because notifications are currently guarded, re-enabling them now risks sending alerts based on inconsistent or unexpected criteria.

3. Whether low attendance thresholds use new credit policy
- `AttendanceService::getLowAttendanceAlerts()` uses `attendance_rate` from `getStudentAttendanceStats()` (new credit policy). However other warning generators (e.g., `SmartAttendanceController::getAttendanceWarnings()`) compute warnings based on `present/total`, not the new credit policy. Therefore different alerting paths could disagree.

4. Whether alert text explains late/half-day credit
- `LowAttendanceAlert` notification text uses `attendance_rate` and `absent_days` only; it does not explain how `attendance_rate` was computed nor mention `late_days` or `half_days`. This should be updated before re-enabling notifications.

5. Whether leave denominator policy should be settled before re-enabling sends
- Yes. Since `leave` currently reduces `attendance_rate` (0 credit but in denominator), sending alerts without a decision on `leave` rule may unfairly penalize students on legacy/approved leaves.

Work Part E — Leave denominator decision audit
Current behavior (Phase 7I state)
- `leave` remains in `total_days` denominator and contributes 0 credit, so it lowers attendance rate for students with legacy `leave` entries.

Options considered
- A. Keep `leave` in denominator as legacy non-credit (current behavior).
- B. Exclude legacy `leave` from denominator (treat as non-counting day).
- C. Map legacy `leave` entries to `absent` (0 credit) and keep denominator unchanged.
- D. Implement an approved-leave workflow: store `leave` as approved, exclude from denominator when approved.

Recommendation
- Short term (Phase 7K): keep `leave` behavior unchanged but flag it as a product decision. Do NOT change denominator in code until stakeholders decide. Add reporting that surfaces `leave_days` per student so consumers can detect affected records.
- Long term: implement approved-leave workflow (Option D) and migrate historical `leave` entries with business rules.

Work Part F — Safe next options (summary)
- Option A: Update labels only — minimal, low-risk. Add tooltips/notes explaining credit policy across dashboards and reports.
- Option B: Surface `late_days`, `half_days`, and `attendance_credit` in dashboards/reports to make calculation transparent.
- Option C: Change leave denominator behavior — moderate risk, requires policy and potential data migration.
- Option D: Keep notifications guarded until labeling and leave policy are finalized.

Work Part G — Phase 7K recommendation (smallest safe code task)
- Phase 7K first task (safe, read-only UI change + tests):
  1. Update dashboard and report templates to show `late_days`, `half_days`, and `attendance_credit` where possible (read from `AttendanceService` outputs). Add a short help label: "Attendance credit policy: present=1, late=1, half_day=0.5; leave = legacy (0 credit)."
  2. Add unit/feature tests asserting that the dashboard/report templates show the new keys when `AttendanceService` returns them (test-only wiring/mock), without changing calculation logic or notification state.
- Rationale: this is a minimal visible change that reduces confusion, makes the new policy explicit, and does not alter behavior or thresholds.

Risk classification
- High: Re-enabling notifications before consumer consistency and leave policy decisions (do NOT re-enable).
- Medium: Changing leave denominator without product decision or data cleanup.
- Low: Adding labels and surfacing `late_days`/`half_days` in UI.

Final confirmations
- No application code, controllers, models, views, tests, migrations, or database records were modified during this Phase 7J audit.
- No notifications were sent, no attendance writes were performed, no exports were run, and no biometric/device sync was triggered.
- No full test suite was executed; this audit used read-only file inspections and a small syntax check on one controller for assurance.

Report path
- docs/project-autopsy/PHASE_7J_ATTENDANCE_REPORT_CONSUMER_AUDIT.md

---

If you want, I can now:
- Prepare the Phase 7K PR branch with only UI label changes and test scaffolding (no logic changes), or
- Draft the small UI template edits (views) as a patch for review (still not enabling notifications), or
- Produce a short stakeholder-ready summary email describing the implications of the credit policy change.

Which of these should I proceed with?