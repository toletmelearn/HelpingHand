# PHASE 7Q — API Attendance Report Consumer Audit

Summary of the read-only audit performed on 2026-06-08 to inspect API attendance report/analytics consumers and identify if any legacy `present / total` percentage calculation remains.

## 1. Files Inspected
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/DashboardController.php` (audited extra as dashboard consumer)
- `app/Http/Controllers/API/GuardianController.php` (audited extra as guardian consumer)
- `app/Http/Controllers/API/StudentController.php` (audited extra as student records endpoint)
- `app/Models/Attendance.php`
- `app/Support/Attendance/AttendanceCreditCalculator.php`
- `app/Services/AttendanceService.php`
- `routes/api.php`
- `routes/web.php`
- `tests/Unit/Models/AttendanceCreditReportHelperTest.php`
- `tests/Feature/Attendance/AttendanceApiPeriodDisplayResponseTest.php`
- `docs/project-autopsy/PHASE_7P_SMART_ATTENDANCE_CONTROLLER_CREDIT_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_7N_ATTENDANCE_MODEL_HELPER_CREDIT_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_7O_ATTENDANCE_CONTROLLER_STATS_CREDIT_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_7L_LEGACY_ATTENDANCE_RATE_CONSUMER_AUDIT.md`

## 2. Commands Run
- Workspace `grep_search` and `view_file` calls (used in place of shell `rg` searches due to system path shell execution restrictions). No write actions or database mutation commands were proposed or executed.

## 3. API Route Findings
The `api/v1/attendance` and student/guardian/dashboard attendance-related routes were analyzed:
- **`GET /api/v1/attendance`** (`index()`): Returns a list of raw attendance records. No percentage is calculated. Active.
- **`GET /api/v1/attendance/{attendance}`** (`show()`): Returns a single raw attendance record. Active.
- **`POST /api/v1/attendance`** (`store()`): Write route. Validated and active, marked_by derived, terminal statuses rejected. No percentage.
- **`PUT /api/v1/attendance/{attendance}`** (`update()`): Update route. Identity fields guarded from mutations. Active.
- **`DELETE /api/v1/attendance/{attendance}`** (`destroy()`): Delete route. Permanently disabled (returns 423 directly from controller).
- **`GET /api/v1/attendance/student/{studentId}/monthly/{month}/{year}`** (`studentMonthlyReport()`): Read/report route. Active. Exposes calculator-aligned percentages and stats via the model helper.
- **`GET /api/v1/attendance/class/{classSection}/daily/{date}`** (`dailyReport()`): Read/report route. Active. Exposes raw records only.
- **`POST /api/v1/attendance/bulk-mark`** (`bulkMark()`): Write route. Permanently disabled (returns 423 directly).
- **`GET /api/v1/students/{id}/attendance`** (`StudentController@attendance`): Returns raw records for the student. No percentage.
- **`GET /api/v1/dashboard/student`** (`DashboardController@studentDashboard`): Exposes student dashboard details, including monthly attendance stats computed locally using the legacy present-only formula.
- **`GET /api/v1/dashboard/parent`** (`DashboardController@parentDashboard`): Exposes parent dashboard details, including children's monthly stats computed using the legacy formula.
- **`GET /api/v1/guardians/{id}/children`** (`GuardianController@children`): Exposes child attendance progress using the legacy formula.

## 4. API Controller Report Findings
Audited all methods in `API\AttendanceController`:
- **`index()`**: Fetches raw attendances with student, teacher, and markedBy relationships. Maps `period_display` via `AttendancePeriodPresenter`. Does not calculate rate.
- **`show()`**: Retrieves single attendance. Maps `period_display`. Does not calculate rate.
- **`store()`**: Validates status and details, checks duplicate and terminal states, resolves class, creates record, maps `period_display`. Does not calculate rate.
- **`update()`**: Updates remarks/status, prevents identity mutation, maps `period_display`. Does not calculate rate.
- **`destroy()`**: Permanently disabled; returns a 423 error immediately.
- **`bulkMark()`**: Permanently disabled; returns a 423 error immediately.
- **`dailyReport()`**: Fetches raw records for class and date. Maps `period_display`. Does not calculate rate.
- **`studentMonthlyReport()`**: Retrieves student monthly report. Delegates to `Attendance::getStudentMonthlyReport()` which is fully calculator-aligned. Returns aligned `percentage` (equal to `attendance_rate`) and maps all credit policy status counts.

## 5. Model Helper Dependency Findings
Since `API\AttendanceController::studentMonthlyReport()` delegates to the model helper `Attendance::getStudentMonthlyReport()`:
- **Calculator Alignment**: Yes, the helper is fully calculator-aligned as of Phase 7N.
- **Percentage Equality**: Yes, the helper maps `'percentage' => $summary['attendance_rate']` directly.
- **Keys Available**: Yes, the returned `summary` block contains:
  - `total_days`
  - `present`
  - `absent`
  - `late`
  - `half_day`
  - `leave`
  - `attendance_credit`
  - `attendance_rate`
  - `percentage`

## 6. Legacy Formula Search Findings
The search for legacy formulas (`present / total`, `presentDays / totalDays`, etc.) in API and supporting consumers revealed:
- **`API\DashboardController::getStudentAttendanceStats()`**:
  `$percentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;`
  (Uses legacy formula; treats all non-present statuses, including late/half-days/leaves, as absent).
- **`API\GuardianController::calculateAttendancePercentage()`**:
  `return round(($presentCount / $attendances->count()) * 100, 2);`
  (Uses legacy formula. Furthermore, it performs a case-sensitive check for `'Present'`, which fails to match lowercase `'present'` entries in the database, resulting in an attendance rate of 0%).
- **`AttendanceService::getClassAttendanceSummary()`**:
  `round(($attendance->where('status', 'present')->count() / $attendance->count()) * 100, 2)`
  (Uses legacy present-only division; affects teacher dashboard and summary metrics).
- **`ParentController.php`**, **`Admin\AISmartFeaturesController.php`**, **`Admin\RoleDashboardController.php`**, **`Teacher\BiometricController.php`**, and **`Admin\AdvancedReportController.php`** still compute attendance rate using the legacy present-only numerator.

## 7. API Response Contract Risks
- **Dashboard Discrepancies**: A student checking their progress via the dashboard API will see lower attendance rates (e.g., 60%) than the actual credit-adjusted rate (e.g., 75%) shown on the web reports. This inconsistency creates stakeholder confusion.
- **Guardian Case-Sensitivity Bug**: The `'Present'` check in `GuardianController` is a severe risk that causes child attendance rates to appear as `0%` in guardian responses.
- **Contract Compatibility**: Directly modifying existing keys like `percentage` or `present_days` could affect mobile client parsing. Response contracts must be extended additively (e.g. keeping `percentage` but mapping it to the aligned rate, and adding `attendance_credit`, `late_days`, `half_days`).

## 8. Safe Implementation Options
- **Option A (No changes in API AttendanceController)**: True for `API\AttendanceController` because its only reporting method is already calculator-aligned via the model helper.
- **Option B (Align other API dashboard controllers)**: Refactor `API\DashboardController` and `API\GuardianController` to use `AttendanceCreditCalculator::summarizeRecords` to compute their attendance stats, mapping the aligned rate to `percentage` (preserving contract) while adding credit policy details.
- **Option C (Metadata annotations)**: Append a `credit_policy` object detailing HSL calculations. Changes payload structures.
- **Option D (Document only)**: Maintain current code and document the differences. This leaves dashboard-to-report inconsistencies active.

## 9. Recommended Phase 7R Next Steps
- **Goal**: Align API dashboard and parent/guardian endpoints with the centralized credit policy, correcting case-sensitivity bugs.
- **First Task**: Refactor `API\DashboardController::getStudentAttendanceStats()` and `API\GuardianController::calculateAttendancePercentage()` to use `AttendanceCreditCalculator::summarizeRecords`. Ensure:
  1. The legacy `percentage` key is mapped to the aligned `attendance_rate`.
  2. Aligned status counts (late, half-day, leave, credit) are returned additively.
  3. The case-sensitive status check bug in `GuardianController` is fixed.
- **Secondary Task**: Refactor `AttendanceService::getClassAttendanceSummary()` to use the credit calculator.

## 10. Confirmation of Strict Non-Modification
- No application code, controllers, models, routes, or services were changed.
- No write actions, database migrations, notification sends, exports, or device sync commands were executed.
- Only read-only inspections were carried out.
