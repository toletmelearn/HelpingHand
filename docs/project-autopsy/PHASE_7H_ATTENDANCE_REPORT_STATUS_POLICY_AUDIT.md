PHASE 7H — READ-ONLY ATTENDANCE REPORT STATUS POLICY AUDIT

Date: 2026-06-07

Overview
- Goal: Read-only audit of current attendance reporting calculations and a safe, test-first recommendation for Phase 7I.
- Constraints: Read-only; no code/migrations/tests changed; no notification sends or attendance writes executed.

Files inspected (read-only)
- app/Services/AttendanceService.php
- app/Services/AttendanceNotificationService.php
- app/Http/Controllers/Teacher/TeacherAttendanceController.php
- app/Http/Controllers/AttendanceController.php
- app/Http/Controllers/API/AttendanceController.php
- app/Models/Attendance.php
- database/migrations/2026_01_21_083000_create_attendances_table.php
- tests/Unit/Services/AttendanceServiceMarkAttendanceGuardTest.php
- tests/Unit/Services/AttendanceNotificationSendGuardTest.php
- docs/project-autopsy/PHASE_7F_ATTENDANCE_NOTIFICATION_REPORT_CALLER_AUDIT.md
- docs/project-autopsy/PHASE_7G_ATTENDANCE_NOTIFICATION_SEND_GUARD.md
- docs/project-autopsy/PHASE_7D_ATTENDANCE_SERVICE_RESIDUAL_RISK_AUDIT.md

Commands run (safe/read-only)
- `php -l app/Services/AttendanceService.php` — syntax OK
- `php -l app/Models/Attendance.php` — syntax OK
- `php artisan route:list | Select-String "attendance"` — inspected attendance-related routes

Work Part A — Current status calculation audit (AttendanceService::getStudentAttendanceStats)
1. Which statuses are counted
   - The method reads attendance records in the date range and counts by `status` values present in the records. It explicitly computes counts for `present`, `absent`, and `leave`.
2. How `present_days` is calculated
   - `$presentDays = $attendanceRecords->where('status', 'present')->count();` — only records with `status == 'present'` are counted as present.
3. How `absent_days` is calculated
   - `$absentDays = $attendanceRecords->where('status', 'absent')->count();`.
4. How `leave_days` is calculated
   - `$leaveDays = $attendanceRecords->where('status', 'leave')->count();` — `leave` is counted if present in data, though migration/validation no longer define `leave` as a valid status.
5. Whether `late` is counted
   - `late` is not included in `present_days` in `getStudentAttendanceStats()`. The method does not credit `late` toward `present_days`.
6. Whether `half_day` is counted
   - `half_day` is not included in `present_days` (not counted as present). The method does not compute a separate `half_day` count.
7. How `total_days` is calculated
   - `$totalDays = $attendanceRecords->count();` — total number of attendance records within the period, including records with `late`, `half_day`, `leave`, etc.
8. How `attendance_rate` is calculated
   - `attendanceRate = totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;` — only `present` counts toward numerator.
9. Whether calculations match main attendance status policy
   - No. The migration and controller validation show the canonical statuses are `present`, `absent`, `late`, `half_day`. `leave` is not present in the migration enum and is therefore legacy. `getStudentAttendanceStats()` counting `leave` and excluding `late`/`half_day` from `present_days` creates a mismatch with current status semantics.

Additional observations from `app/Models/Attendance.php` and model helpers
- Database migration `create_attendances_table.php` defines `status` enum as `['present','absent','late','half_day']` (no `leave`).
- Some model helpers (`Attendance::getAttendanceStats()` and `getStudentMonthlyReport()`) count `late` explicitly but do not count `half_day`, indicating inconsistent handling across helpers and services.

Work Part B — Derived method audit (impact)
1. `getLowAttendanceAlerts()`
   - Uses `getStudentAttendanceStats()` to compute `attendance_rate`. Since `late`/`half_day` are not credited, students with many `late` or `half_day` records may be flagged incorrectly as low attendance.
2. `getAttendanceTrends()`
   - Calls `getStudentAttendanceStats()` for each month; trends will underrepresent presence if `late` or `half_day` should be credited.
3. `generateAttendanceReport()`
   - Aggregates `attendance_rate` from `getStudentAttendanceStats()` across students; class averages and student rates will be affected by the same undercounting.
4. `sendLowAttendanceAlerts()` (indirect)
   - Any notification logic that relies on `getLowAttendanceAlerts()` will be affected; Phase 7G has already guarded sends, but alerts produced when re-enabled will be impacted.
5. Teacher dashboard summaries
   - Teacher dashboard uses `getTeacherClassAttendance()` and `getClassAttendanceSummary()` which in turn compute present/absent/leave counts. They will misrepresent attendance if `late`/`half_day` should be counted differently.

Work Part C — Main status policy audit (validation/migration)
1. Allowed statuses in main attendance migration
   - Migration `2026_01_21_083000_create_attendances_table.php` defines `status` as enum `['present','absent','late','half_day']`.
2. Allowed statuses in API store/update
   - `app/Http/Controllers/API/AttendanceController.php` validates `status` as `in:present,absent,late,half_day` for `store` and `update`.
3. Allowed statuses in web store/update
   - `app/Http/Controllers/AttendanceController.php` validates `status` in `present,absent,late,half_day` (store/update validation and update route use these values).
4. Whether `leave` is part of any active safe write path
   - No. Current migrations and validations do not include `leave`. `leave` appears in historical code and in `AttendanceService::getStudentAttendanceStats()` as a legacy value, but it is not accepted by writes validated by controllers or API.
5. Whether `leave` should be treated as legacy only
   - Yes. Since migrations and current write paths do not accept `leave`, it is effectively legacy. Any code counting `leave` should be reconciled (either removed or treated as legacy during data cleanup) before re-enabling notifications.

Work Part D — Status calculation options (trade-offs)
Option A (recommended conservative):
- `present` = 1.0 credit
- `late` = 1.0 credit (count as present for attendance rate), but tracked separately for discipline/metrics
- `half_day` = 0.5 credit
- `absent` = 0.0 credit
- `leave` = legacy: count separately, exclude or treat as non-credit until policy defined
Pros: aligns with lenient presence semantics (late still counts as presence); minimal disruption to historic dashboards.
Cons: masks lateness in attendance rates; requires separate discipline metrics.

Option B (balanced):
- `present` = 1.0
- `late` = 0.5
- `half_day` = 0.5
- `absent` = 0.0
Pros: recognizes lateness as partial attendance credit; reduces false positives for low attendance.
Cons: more policy nuance; needs stakeholder alignment.

Option C (strict):
- Only `present` counts as present; `late` and `half_day` do not contribute.
Pros: strict and simple
Cons: likely too harsh and will generate many low-attendance flags; not recommended.

Option D (exclude approved leave from denominator):
- Exclude `leave` from the denominator when computing attendance rate (requires explicit leave workflow and storage of approved leave).
Pros: fair to students on approved leave.
Cons: requires leave workflow and verification; not currently supported by write paths.

Work Part E — Recommended Phase 7I first code task (test-first)
- Objective: Implement a test-first change to reporting semantics that correctly credits `late` and `half_day`.
- Proposed policy (safe, conservative):
  - `present` = 1.0 credit
  - `late` = 1.0 credit (counts toward presence), but keep `late` as a separate counter/flag for reporting
  - `half_day` = 0.5 credit
  - `absent` = 0.0 credit
  - `leave` = legacy: count separately; exclude from numerator and (optionally) denominator pending policy decision
- Phase 7I tasks (high level):
  1. Add unit tests for `AttendanceService::getStudentAttendanceStats()` to assert how `present`, `late`, `half_day`, and `absent` affect `attendance_rate` and per-status counts.
  2. Add integration tests for `getLowAttendanceAlerts()` and `generateAttendanceReport()` to verify class averages and alert thresholds under the new policy.
  3. Implement changes to `AttendanceService` read/report methods to compute credits (use decimal credits and round only for presentation) while preserving per-status counts for auditing.
  4. Re-enable notifications after tests pass and after stakeholder review.

Rationale
- Adding tests first prevents regressions and documents expected behavior. Treating `late` as full credit (Option A) is least disruptive and maintains continuity for dashboards; Option B is an alternative if stakeholders prefer partial credit for lateness.

Remaining risks
- Historical data may contain legacy `leave` values; decide whether to map/clean legacy values or treat them as separate legacy counts.
- Stakeholder agreement required for how `late` should be credited.

Confirmations (constraints)
- No application code, migrations, or database data were changed in this audit.
- No notification sends, attendance writes, exports, biometric syncs, or full test suites were executed.

Report path
- docs/project-autopsy/PHASE_7H_ATTENDANCE_REPORT_STATUS_POLICY_AUDIT.md

If you'd like, I can next:
- Draft the Phase 7I unit test file(s) (test-first) for your review, or
- Open a short PR branch that adds the tests only (no production logic), so stakeholders can review expected behavior before code changes.
