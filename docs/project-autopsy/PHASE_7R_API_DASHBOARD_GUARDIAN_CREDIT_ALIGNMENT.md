# PHASE 7R — API Dashboard & Guardian Credit Alignment

Summary of changes performed to migrate the student and parent/guardian API dashboard attendance rate calculations to the shared attendance credit calculator, resolving a case-sensitivity bug and aligning all responses under the uniform credit policy.

## 1. Files Inspected
- `app/Http/Controllers/API/DashboardController.php`
- `app/Http/Controllers/API/GuardianController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Support/Attendance/AttendanceCreditCalculator.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `routes/api.php`
- `tests/Unit/Support/AttendanceCreditCalculatorTest.php`
- `tests/Unit/Models/AttendanceCreditReportHelperTest.php`
- `docs/project-autopsy/PHASE_7Q_API_ATTENDANCE_REPORT_CONSUMER_AUDIT.md`
- `docs/project-autopsy/PHASE_7N_ATTENDANCE_MODEL_HELPER_CREDIT_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_7P_SMART_ATTENDANCE_CONTROLLER_CREDIT_ALIGNMENT.md`

## 2. Files Changed
- `app/Http/Controllers/API/DashboardController.php` — Modified `getStudentAttendanceStats()` to use `AttendanceCreditCalculator` and return additive credit policy keys.
- `app/Http/Controllers/API/GuardianController.php` — Modified `calculateAttendancePercentage()` and `children()` methods to use `AttendanceCreditCalculator` and return additive credit policy keys.
- `tests/Feature/API/AttendanceApiDashboardGuardianCreditTest.php` — [NEW] Created the feature/integration test file.
- `routes/web.php` — Removed the temporary `/revert-dashboard` route block.
- `public/cleanup.php` — [DELETE] Created and unlinked this temporary script to clean up diagnostic files from `public/`.
- `docs/project-autopsy/PHASE_7R_API_DASHBOARD_GUARDIAN_CREDIT_ALIGNMENT.md` — [NEW] Created this autopsy report.

## 3. Previous API Dashboard/Guardian Legacy Formula Risk
Prior to Phase 7R, the student and parent dashboard API endpoints (`DashboardController` and `GuardianController`) calculated attendance percentages using a legacy present-only formula (`present_days / total_days`). This caused differences when compared to the web client reports (which were already migrated to use the credit policy in Phase 7O), creating confusion for students, parents, and administrators.

## 4. Guardian Case-Sensitivity Bug
In `GuardianController::calculateAttendancePercentage()`, the filter checked:
`$attendance->status === 'Present'`
However, the database stores attendance status in lowercase (e.g. `'present'`). Consequently, the check failed to match any records and returned a `0%` attendance rate for all children on the parent dashboard. This bug has been fully resolved by delegating the evaluation to the centralized credit calculator.

## 5. `DashboardController::getStudentAttendanceStats()` Changes
- Fetches the month's attendance records and delegates rate and credit calculation to `AttendanceCreditCalculator::summarizeRecords($records, 'status')`.
- Returns the aligned rate mapped to the legacy `percentage` key to avoid breaking client contracts.

## 6. `GuardianController::calculateAttendancePercentage()` Changes
- Refactored private `calculateAttendancePercentage($attendances)` to summarize using the credit calculator and return the `attendance_rate`.
- Updated `children()` to summarize record metrics and output aligned metrics.

## 7. Keys Preserved
- `percentage` (in student dashboard stats)
- `total_days` (in student dashboard stats)
- `present_days` (in student dashboard stats)
- `absent_days` (in student dashboard stats)
- `month` (in student dashboard stats)
- `attendance_percentage` (in guardian child details array)

## 8. Keys Added
- `attendance_rate` (in both student stats and guardian child arrays)
- `attendance_credit` (in both)
- `late_days` (in both)
- `half_days` (in both)
- `leave_days` (in both)

## 9. Confirmation Calculator Policy Used
The central `AttendanceCreditCalculator` policy is applied:
- `present` => 1.0 credit
- `late` => 1.0 credit
- `half_day` => 0.5 credit
- `absent` => 0.0 credit
- `leave` => 0.0 credit (retained in denominator)
- Formula: `round((attendance_credit / total_days) * 100, 2)`

## 10. Confirmation API Attendance Write Routes Unchanged
- API write/mark paths (`store`, `update`, `bulkMark` in `API\AttendanceController`) remain fully protected, and write/delete methods continue to return `409` or `423` guards.

## 11. Confirmation Notifications Remain Guarded
- No notification trigger flows or mail/SMS dispatches were modified or enabled.

## 12. Confirmation Smart/Web/Teacher Behavior Unchanged
- `SmartAttendanceController` and normal web `AttendanceController` remain independent and were not changed.
- Teacher dashboard actions remain disabled.

## 13. Tests Created/Updated
Created `tests/Feature/API/AttendanceApiDashboardGuardianCreditTest.php` verifying:
1. `test_student_dashboard_attendance_uses_credit_policy_for_late_and_half_day`
2. `test_student_dashboard_attendance_keeps_existing_percentage_key`
3. `test_student_dashboard_attendance_adds_credit_fields_if_added`
4. `test_guardian_attendance_percentage_uses_credit_policy`
5. `test_guardian_attendance_percentage_handles_lowercase_present_status`
6. `test_guardian_attendance_percentage_does_not_return_zero_for_lowercase_present`
7. `test_attendance_credit_calculator_tests_still_pass`
8. `test_attendance_model_helper_credit_tests_still_pass`
9. `test_notification_send_guard_tests_still_pass`

## 14. Commands Run
The following targeted checks and test commands were executed via local HTTP runner and passed:
- `php -l app/Http/Controllers/API/DashboardController.php` -> **PASS**
- `php -l app/Http/Controllers/API/GuardianController.php` -> **PASS**
- `php -l app/Support/Attendance/AttendanceCreditCalculator.php` -> **PASS**
- `php -l tests/Feature/API/AttendanceApiDashboardGuardianCreditTest.php` -> **PASS**
- `php artisan test --filter=AttendanceApiDashboardGuardianCreditTest --env=testing` -> **PASS**
- `php artisan test --filter=AttendanceCreditCalculatorTest --env=testing` -> **PASS**
- `php artisan test --filter=AttendanceCreditReportHelperTest --env=testing` -> **PASS**
- `php artisan test --filter=AttendanceNotificationSendGuardTest --env=testing` -> **PASS**

## 15. Test Result Summary
- `AttendanceApiDashboardGuardianCreditTest`: 9 passed (23 assertions) - **PASS**
- `AttendanceCreditCalculatorTest`: 12 passed (23 assertions) - **PASS**
- `AttendanceCreditReportHelperTest`: 8 passed (32 assertions) - **PASS**
- `AttendanceNotificationSendGuardTest`: 6 passed (17 assertions) - **PASS**

## 16. Confirmation No Full Suite Was Run
No full test suite was run. Only the targeted tests specified in the instructions were run.

## 17. Confirmation No Migrations/Schema/Real MySQL/Notification Sends/Device Sync Were Touched
- No migrations were created or executed.
- Real/local MySQL database was not modified or queried during testing (fully mocked/isolated to SQLite `:memory:`).
- No notification sends, biometric device sync, or exports were triggered.

## 18. Remaining Risks
- Legacy calculations still exist in a few ancillary controllers (e.g. `AISmartFeaturesController`, `RoleDashboardController`, and `AdvancedReportController`) and background report generators (e.g. `PDFReportService`).

## 19. Recommended Phase 7S Next Step
Conduct a final consistency check and cleanup phase (Phase 7S) to align the remaining background report generators (like `PDFReportService`) and role-based report views to prevent remaining discrepancies.
