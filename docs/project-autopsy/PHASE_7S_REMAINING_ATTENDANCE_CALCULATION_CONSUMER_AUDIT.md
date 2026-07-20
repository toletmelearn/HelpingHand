# PHASE 7S — Remaining Attendance Calculation Consumer Audit

Date: 2026-06-08

## 1. Files Inspected
The following files were inspected as part of this read-only audit:
* `app/Services/AttendanceService.php`
* `app/Services/PDFReportService.php`
* `app/Services/ProfessionalDashboardService.php`
* `app/Http/Controllers/Admin/AISmartFeaturesController.php`
* `app/Http/Controllers/Admin/RoleDashboardController.php`
* `app/Http/Controllers/Admin/AdvancedReportController.php`
* `app/Http/Controllers/Admin/PerformanceAnalyticsController.php`
* `app/Http/Controllers/Admin/ProfessionalDashboardController.php`
* `app/Http/Controllers/ParentController.php`
* `app/Http/Controllers/Parent/ParentDashboardController.php`
* `app/Http/Controllers/AttendanceController.php`
* `app/Models/Attendance.php`
* `app/Support/Attendance/AttendanceCreditCalculator.php`
* `routes/web.php`
* `routes/api.php`
* `resources/views/attendance/index.blade.php`
* `resources/views/parents/dashboard.blade.php`
* `resources/views/parent/dashboard.blade.php`
* `resources/views/teacher/attendance/dashboard.blade.php`
* `docs/project-autopsy/PHASE_7R_API_DASHBOARD_GUARDIAN_CREDIT_ALIGNMENT.md`
* `docs/project-autopsy/PHASE_7L_LEGACY_ATTENDANCE_RATE_CONSUMER_AUDIT.md`

## 2. Commands Run
Since PowerShell path issues prevented the execution of command-line tools in the container/sandbox environment, all audits were performed strictly using the read-only file viewing (`view_file`) and grep searching (`grep_search`) tools. No code modifications, route listings, or DB sync commands were run.

## 3. Global Legacy Formula Findings
Below is the breakdown of all remaining calculations for attendance rates/percentages found in the project:

| File Path | Method / Function | Legacy Formula Used | Late Credited? | Half Day Credited? | Leave Handled? | Uses Calculator? | Output Target | Recommendation |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `app/Services/AttendanceService.php` | `getClassAttendanceSummary` | `round((present / total) * 100, 2)` | No | No | Denominator only | No | Teacher dashboard class stats | Migrate in Phase 7T |
| `app/Services/PDFReportService.php` | `calculateAttendanceStats` | `($presentDays / $totalDays) * 100` | No | No | Denominator only | No | Student/Class Report PDFs | Migrate in Phase 7T |
| `app/Http/Controllers/Admin/AISmartFeaturesController.php` | `getAttendanceWarnings` | `($presentDays / $totalDays) * 100` | No | No | Denominator only | No | Admin warnings dashboard | Migrate in Phase 7U |
| `app/Http/Controllers/Admin/RoleDashboardController.php` | `getStudentAttendanceRate` | `round((present / total) * 100, 2)` | No | No | Denominator only | No | Student web dashboard | Migrate in Phase 7U |
| `app/Http/Controllers/Admin/AdvancedReportController.php` | `getAttendanceAnalytics` | `round((present / total) * 100, 2)` | No | No | Denominator only | No | Admin advanced reports | Migrate in Phase 7U |
| `app/Http/Controllers/Admin/PerformanceAnalyticsController.php` | `getOverallAttendanceRate` | `round((present_or_late / total) * 100, 2)` | Yes (1.0)* | No | Denominator only | No | Performance metrics | Migrate in Phase 7U |
| `app/Http/Controllers/ParentController.php` | `getChildAttendancePercentage` | `round((present / total) * 100, 2)` | No | No | Denominator only | No | Legacy parent dashboard | Migrate in Phase 7U |
| `app/Services/ProfessionalDashboardService.php` | `getTodayAttendanceRate` / `getMonthlyAttendanceRate` | Counts all marked records as present | Yes (1.0)* | Yes (1.0)* | Yes (1.0)* | No | Professional widgets | Migrate in Phase 7U |

> [!NOTE]
> * In `PerformanceAnalyticsController::getOverallAttendanceRate()`, a SQL query precedence bug exists where `orWhere('status', 'late')` is called without bracket grouping, causing all late records across the entire history to match regardless of the date filter.
> * In `ProfessionalDashboardService`, the calculations are simplified placeholders where all marked attendances of any status are counted as present (giving 1.0 credit to late, half_day, and leave).

## 4. `AttendanceService::getClassAttendanceSummary()` Findings
1. **Legacy Division**: The method still uses the legacy formula:
   ```php
   'attendance_rate' => $attendance->count() > 0 ? 
       round(($attendance->where('status', 'present')->count() / $attendance->count()) * 100, 2) : 0
   ```
2. **Reachability**: It is fully active and reachable:
   * Called by `TeacherAttendanceController::dashboard()` (index route).
   * Called by `TeacherAttendanceController::markAttendance()` (form page).
3. **Consuming Views**: The output is rendered on the Teacher Attendance Dashboard (`teacher/attendance/dashboard.blade.php`) as the overall today's rate (`$todaySummary['attendance_rate']`) and class-wise summaries (`$class['summary']['attendance_rate']`).
4. **Discrepancy risk**: The same dashboard displays student low attendance warnings computed using `AttendanceService::getLowAttendanceAlerts()`. Since the alerts use `getStudentAttendanceStats()` (which is calculator-aligned), a teacher will see calculator-aligned warnings side-by-side with legacy class averages, creating immediate visual discrepancies.
5. **Phase 7T Target**: This should be migrated first in Phase 7T.

## 5. Report / PDF Service Findings
1. **PDFReportService**: The service exists at `app/Services/PDFReportService.php`.
2. **Legacy Formula**: `calculateAttendanceStats($attendances)` computes legacy present-only rates:
   ```php
   $attendancePercentage = $totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0;
   ```
3. **Credit Policy**: `late` (1.0 credit) and `half_day` (0.5 credit) are ignored. `half_day` is not even tracked.
4. **Stakeholder Exposure**: Generated PDF reports (Student Report card, Class Attendance summary PDF) are downloaded and shared with parents, guardians, and school administrators.
5. **Phase 7T Target**: Since PDF reports represent external/printable records, PDFReportService is a high-risk area and must be aligned to `AttendanceCreditCalculator` in Phase 7T.

## 6. Admin / Role Dashboard Audit Findings
* **AISmartFeaturesController**: Computes student attendance percentages in `getAttendanceWarnings()` for the AI Smart features page. It uses legacy present-only calculations.
* **RoleDashboardController**: Computes student attendance rate for the student web dashboard homepage (`getStudentAttendanceRate()`). It uses legacy present-only calculations.
* **AdvancedReportController**: Computes class and date-range attendance rates in `getAttendanceAnalytics()` for the admin reports tab. It uses legacy present-only calculations.
* **User Impact**: Administrators viewing the AI Warning, Student, and Advanced Report tabs will see different numbers for the exact same students than what the students/guardians see on their API dashboards. These should be migrated collectively in a later step (Phase 7U).

## 7. View Display Findings
* **Aligned Views**:
  * `attendance/index.blade.php` (Admin index) — uses aligned controller stats.
  * `attendance/reports.blade.php` (Admin reports) — uses aligned model helpers.
  * `admin/teacher-attendance/reports.blade.php` — uses teacher-specific rules.
* **Legacy Views**:
  * `teacher/attendance/dashboard.blade.php` (Teacher dashboard) — displays legacy today's rate and class-wise averages, but aligned student warnings.
  * `admin/reports/advanced/dashboard.blade.php` (Admin advanced reports) — displays legacy rates.
  * `admin/performance-analytics/dashboard.blade.php` (Admin performance dashboard) — displays legacy/buggy rates.
  * `parents/dashboard.blade.php` (Web parent dashboard) — displays legacy parent child-details percentages.
* **UI Transparency**: None of the legacy views explain that they use a present-only calculation or list the credit policy. This leaves users completely unaware of why numbers differ between sections.

## 8. User-Facing Risk Classification
The remaining risk is classified as **MEDIUM-HIGH** due to stakeholder confusion:
* **Teacher dashboard inconsistency**: A class with multiple late or half-day students will show a low average attendance rate, but no low attendance alerts will trigger because alerts are calculator-aligned.
* **Report discrepancies**: A printed PDF report card downloaded by a parent will show different attendance rates than what is shown on their mobile API dashboard.
* **Admin dashboard mismatches**: AI warnings and advanced analytics reports will show different rates than the main web attendance logs.

## 9. Safe Fix Options

### Option A: Service-Level First (Recommended)
* Phase 7T migrates `AttendanceService::getClassAttendanceSummary()` and `PDFReportService` first.
* **Pros**: Targets the shared service layers first; resolves printable PDF discrepancies and teacher dashboard mismatches; keeps implementation scoped and low risk.
* **Cons**: Admin dashboard views remain legacy for one more phase.

### Option B: UI-Only First
* Phase 7T migrates only the web views and controllers (`RoleDashboardController`, `ParentController`) first.
* **Pros**: Fixes student/parent UI quickly.
* **Cons**: Leaves PDF reports and services inconsistent.

### Option C: Bulk Controller Migration
* Phase 7T migrates all remaining admin dashboard controllers (`AISmartFeaturesController`, `RoleDashboardController`, `AdvancedReportController`, `PerformanceAnalyticsController`) in one phase.
* **Pros**: Resolves all admin dashboard discrepancies at once.
* **Cons**: High surface area, riskier changes, and harder to isolate testing.

## 10. Recommended Phase 7T First Code Task
We recommend **Option A** as the next step (Phase 7T):
1. Refactor `AttendanceService::getClassAttendanceSummary()` to delegate status counting and rate calculation to `AttendanceCreditCalculator`.
2. Refactor `PDFReportService::calculateAttendanceStats()` to use `AttendanceCreditCalculator`.
3. Add targeted integration tests validating that both services correctly apply the 1.0 late credit and 0.5 half-day credit.

## 11. Strict Read-Only Audit Confirmation
As required by the Phase 7S rules:
* **No application code** (controllers, services, models, views, templates, routes, or tests) was altered.
* **No database migrations** or write actions were executed.
* **No attendance records** were updated, deleted, or inserted.
* **No notification dispatches** or device sync actions were triggered.
* All findings are documented strictly in this read-only audit report.
