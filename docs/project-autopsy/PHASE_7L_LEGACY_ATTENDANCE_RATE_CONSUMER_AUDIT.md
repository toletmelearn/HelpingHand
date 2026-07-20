# PHASE 7L — Legacy Attendance Rate Consumer Audit (Read-only)

Date: 2026-06-07

Summary
- Goal: Read-only audit of remaining consumers that compute attendance percentage using legacy present/total formula.
- Scope: Identify files/methods using `present / total` or otherwise not using the new `attendance_credit`-based rate; document formula, whether late/half_day/leave are counted, and whether outputs are user-visible or trigger alerts.
- Constraints: READ-ONLY. No code or DB changes. No notification sends or attendance writes.

Files inspected
- app/Services/AttendanceService.php
- app/Http/Controllers/Admin/SmartAttendanceController.php
- app/Http/Controllers/AttendanceController.php
- app/Http/Controllers/API/AttendanceController.php
- app/Models/Attendance.php
- resources/views/attendance/index.blade.php
- resources/views/attendance/reports.blade.php
- resources/views/teacher/attendance/dashboard.blade.php
- tests/Unit/Services/AttendanceServiceStatusCalculationTest.php
- docs/project-autopsy/PHASE_7K_ATTENDANCE_CREDIT_POLICY_DISPLAY.md
- docs/project-autopsy/PHASE_7J_ATTENDANCE_REPORT_CONSUMER_AUDIT.md
- docs/project-autopsy/PHASE_7I_ATTENDANCE_REPORT_STATUS_CALCULATION_FIX.md

Commands run (read-only)
- Syntax checks:
  - `php -l app/Services/AttendanceService.php`
  - `php -l app/Http/Controllers/Admin/SmartAttendanceController.php`
  - `php -l app/Http/Controllers/AttendanceController.php`
  - `php -l app/Http/Controllers/API/AttendanceController.php`
  - `php -l app/Models/Attendance.php`
- Route listing (filtered):
  - `php artisan route:list | Select-String "attendance"`
- Code searches (grep across PHP & blade files):
  - searched for `attendance_rate`, `present /`, `presentDays`, `presentCount`, `percentage`, `getAttendanceStats`, `getStudentMonthlyReport`, `calculateAttendanceStats`, `getAttendanceStatistics`, `getAttendanceWarnings`, `getAttendanceTrends`.

Legacy formula findings (summary)
- Canonical new policy (Phase 7I): `attendance_credit` = present + late + 0.5 * half_day; `attendance_rate` = attendance_credit / total_days.
- Legacy formula observed in multiple consumers: `attendance_rate` or `percentage` computed as `(present / total) * 100` (or present_count / total_records).

Files/methods still using legacy `present / total`
1. `app/Http/Controllers/Admin/SmartAttendanceController.php`
   - Methods: `getAttendanceStatistics()`, `getAttendanceTrends()`, `getAttendanceWarnings()`, `getOverallAttendanceStats()`, `getClassWiseAttendance()`, `getMonthlyAttendanceTrends()`.
   - Formula examples:
     - `attendance_rate` => `round(($present / $total) * 100, 2)`
     - trends: `round(($item->present / $item->total) * 100, 2)`
     - warnings: `($presentDays / $totalDays) * 100`
   - Counts: separate counts for `present`, `absent`, `late`, `half_day` but the rate uses only `present` in numerator.
   - `late` and `half_day`: counted as separate buckets but not credited in the percentage.
   - `leave`: not specially handled; counts as part of `total` if present in records.
   - Uses `Attendance` model queries directly; does not invoke `AttendanceService`.
   - Output: user-visible (admin smart-dashboard, analytics, warnings), can drive warnings/notification code paths (though sends are currently guarded).
   - Recommendation: update to use `attendance_credit`-based calculation or to call a shared calculator.

2. `app/Http/Controllers/AttendanceController.php`
   - Method: `calculateAttendanceStats()` (private)
   - Formula: `$attendanceRate = $totalAttendance > 0 ? round(($presentToday / $totalAttendance) * 100, 1) : 0;`
   - Counts: `present_today` uses `status = 'present'`; `totalAttendance` counts all attendance records for date.
   - `late`/`half_day` are not credited in rate; `leave` included in denominator if present.
   - Used to render `attendance.index` and `attendance.reports` (controller calls `Attendance::getAttendanceStats()` in `reports()` when class present, but `index()` uses `calculateAttendanceStats()` for dashboard stats).
   - Output: user-visible in admin attendance dashboards and KPIs.
   - Recommendation: move to shared calculator or adapt to use `attendance_credit`.

3. `app/Models/Attendance.php`
   - Methods: `getAttendanceStats($date = null, $class = null)` and `getStudentMonthlyReport($studentId, $month, $year)`.
   - `getAttendanceStats()` computes:
     - `total = $query->count(); present = $query->present()->count(); percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0`.
   - `getStudentMonthlyReport()` loops statuses and tallies `present`, `absent`, `late`. Computes `percentage` as `round(($present / $total) * 100, 2)` where `$total = present + absent + late`.
   - `half_day` is not handled in `getStudentMonthlyReport()` switch; `half_day` records are ignored in the switch (treated like absent unless raw status appears elsewhere). (Note: method handles 'late' but not 'half_day' explicitly.)
   - `leave` is not specially managed; if present in records, it won't be counted in the present/late/absent counters unless switch covers it.
   - Output: used by `AttendanceController::reports()`, admin monthly reports, exports — visible to users and included in exports.
   - Recommendation: refactor to include `half_day` and `late` credit or call a shared calculator.

4. `app/Http/Controllers/API/AttendanceController.php`
   - Uses `Attendance::getStudentMonthlyReport()` for student monthly report API endpoint and `Attendance` collections for daily reports. The model helper returns legacy `percentage` fields as above.
   - Recommendation: ensure API reports explicitly document the basis of percentage or migrate to credit-based calculation.

5. Views that consume legacy percentages
   - `resources/views/attendance/index.blade.php` consumes `$stats['attendance_rate']` from `AttendanceController::calculateAttendanceStats()` (legacy present/total).
   - `resources/views/attendance/reports.blade.php` consumes `$stats['percentage']` from `Attendance::getAttendanceStats()` (legacy present/total).
   - `resources/views/teacher/attendance/dashboard.blade.php` displays `class['summary']['attendance_rate']` and `todaySummary['attendance_rate']`; depending on controller/service path these may be from the new `AttendanceService` (credit) or legacy controller (present/total). Phase 7J discovered mixed usage — some callers use service, others use model/controller calculations.
   - Output: user-visible; may show different numbers across pages.

6. Tests referencing percentages
   - `tests/Unit/Services/AttendanceServiceStatusCalculationTest.php` targets `AttendanceService` and passes using new credit policy.
   - Several feature tests (exports, dashboards) assert `percentage`/`attendance_rate` values using test-provided data — they assume either legacy or new values depending on test setup. Tests passed earlier for targeted sets.

SmartAttendanceController detailed findings
- Attendance statistics formula: `attendance_rate = round(($present / $total) * 100, 2)` — legacy present-only numerator.
- Attendance trends formula: per-day `present / total` mapping to percentage.
- Attendance warning formula: per-student in last 30 days `presentDays / totalDays * 100`.
- `late` and `half_day` are counted as separate buckets but NOT credited towards the numerator; `half_day` is stored separately but not used in percentage calculations.
- Notifications: `sendAttendanceAlerts()` is currently guarded (early return) and would compute attendancePercentage using `present / total` if uncommented; notification sends are currently disabled.
- Admin dashboard: user-visible rates calculated by these legacy formulas — inconsistent with credit-based service outputs in other parts of the app.

AttendanceController detailed findings
- `calculateAttendanceStats()` uses `presentToday / totalAttendance` to compute `attendance_rate` for index dashboard.
- `reports()` uses `Attendance::getAttendanceStats()` when `class` is provided — that model helper uses legacy present/total too.
- Index and reports pages will thus display legacy percentages unless the controller is altered to use `AttendanceService` outputs.

Attendance model helper detailed findings
- `getAttendanceStats()` returns `percentage` computed as `present / total`.
- `getStudentMonthlyReport()` tallies `present`, `absent`, `late` and computes `percentage` as `present / total` (total = present + absent + late). It does not explicitly handle `half_day` as a credit and therefore `half_day` records may be omitted from totals or treated inconsistently.
- Recommendation: centralize the credit policy and make model helpers call the shared calculator.

User-facing inconsistency risks
- Different pages (teacher dashboard, admin smart dashboard, reports, exports, API) may show different attendance percentages for the same student/time period because some consumers use credit-based service outputs and others use legacy present/total calculations.
- This can create stakeholder confusion and incorrect decisioning if notifications are re-enabled or exports are shared.

Safe fix options (analysis)
Option A — Shared Calculator (recommended long-term):
- Implement a pure `AttendanceCreditCalculator` service/helper with methods:
  - `creditForStatus(string $status): float` (mapping)
  - `calculateCredit(array|Collection $records): float`
  - `calculateRate(array|Collection $records): float`
  - `tallies(array|Collection $records): array` (present, late, half, leave, total_days)
- Replace callers incrementally: update `AttendanceService` to use it (no behavior change), then migrate `AttendanceController`, `SmartAttendanceController`, and `Attendance` model helpers to use it.
- Pros: Single source of truth; small pure functions easy to test; safe incremental rollout.
- Cons: touches multiple files; needs careful coordination and tests.

Option B — Update `AttendanceController::calculateAttendanceStats()` only:
- Quick UI alignment for the admin index view.
- Pros: small change, low risk.
- Cons: leaves many other legacy consumers inconsistent.

Option C — Update `Attendance::getStudentMonthlyReport()` and `getAttendanceStats()` first:
- Aligns exports and monthly reports quickly.
- Pros: fixes exported reports which are critical for stakeholders.
- Cons: dashboards and warnings still inconsistent until controllers updated.

Option D — Keep legacy formulas but label them as legacy:
- Add explicit UI labels to pages using legacy formulas stating "Legacy percentage: computed as present/total".
- Pros: minimal code change.
- Cons: continues inconsistency and user confusion; undermines Phase 7I objective.

Phase 7M recommendation (smallest safe first code task)
- Create a small, pure `AttendanceCreditCalculator` helper/service (no DB changes). Implement mapping and calculation functions and unit tests.
- Immediately refactor `AttendanceService::getStudentAttendanceStats()` to call this shared helper (no change to external behavior — this validates the helper).
- After the helper exists and tested, schedule Phase 7N to migrate controllers and model helpers in small, test-covered PRs (SmartAttendanceController and Attendance model helpers prioritized).

Summary of actions taken (read-only)
- Performed repository-wide search for legacy percentage patterns.
- Read and documented formulas in: `SmartAttendanceController`, `AttendanceController`, `API\AttendanceController`, `Attendance` model helpers, and relevant views.
- Ran safe syntax checks and `route:list` to map attendance routes.

Report path
- docs/project-autopsy/PHASE_7L_LEGACY_ATTENDANCE_RATE_CONSUMER_AUDIT.md

Confirmations (safety)
- No application code, tests, controllers, models, views, migrations, or routes were modified.
- No notification sends, attendance writes/updates/deletes, exports, biometric/device syncs, or full test suite runs were performed.
- Only read-only commands and syntax checks were executed.

Remaining risks
- Inconsistent percentages remain across different views and exports until consumers are migrated.
- `leave` records and `half_day` handling in model helper `getStudentMonthlyReport()` need explicit treatment.

Recommended next steps
1. Phase 7M: create `AttendanceCreditCalculator` helper + unit tests; refactor `AttendanceService` to call it (no behavioral change). (small, testable, low-risk)
2. Phase 7N: migrate `Attendance` model helpers and `AttendanceController::calculateAttendanceStats()` to use the shared helper (focus exports and reports first).
3. Phase 7O: migrate `SmartAttendanceController` analytics and warning generators.
4. Add integration tests comparing legacy and new outputs for a period during rollout to validate parity and highlight differences.

