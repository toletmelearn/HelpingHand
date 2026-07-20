# PHASE 7N — Attendance Model Helper Credit Alignment

Summary of changes performed on 2026-06-07 to migrate Attendance model report helpers to the shared attendance credit calculator (read-only, behavior-preserving).

Files inspected
- `app/Models/Attendance.php`
- `app/Support/Attendance/AttendanceCreditCalculator.php`
- `app/Services/AttendanceService.php`
- `app/Http/Controllers/AttendanceController.php` (inspected only)
- `app/Http/Controllers/API/AttendanceController.php` (inspected only)
- `tests/Unit/Support/AttendanceCreditCalculatorTest.php`
- `tests/Unit/Services/AttendanceServiceStatusCalculationTest.php`
- `docs/project-autopsy/PHASE_7M_ATTENDANCE_CREDIT_CALCULATOR.md`
- `docs/project-autopsy/PHASE_7L_LEGACY_ATTENDANCE_RATE_CONSUMER_AUDIT.md`

Files changed
- Modified: `app/Models/Attendance.php` — `getAttendanceStats()` and `getStudentMonthlyReport()` now delegate credit/rate calculation to `AttendanceCreditCalculator` while preserving legacy keys.
- Added: `tests/Unit/Models/AttendanceCreditReportHelperTest.php` — in-memory SQLite tests validating model helper behavior and edge cases.

Previous model helper legacy formula risk
- Legacy helpers computed percentages as `present / total` (present counted as 1.0; late and half_day were not consistently credited). That created divergence after Phase 7I when `AttendanceService` adopted the credit policy. This migration reduces that blind spot by centralizing calculation for model helpers.

`getAttendanceStats()` changes
- Preserves existing filter behavior (by date and class) but uses date-safe comparisons (`whereDate`) to match stored date values.
- Counts per-status remain available via legacy keys: `total`, `present`, `absent`, `late`.
- New/added keys (safe additions): `half_day`, `leave`, `attendance_credit`, `attendance_rate`.
- `percentage` is preserved but now equals `attendance_rate` (calculator percentage).

`getStudentMonthlyReport()` changes
- Day-wise `details` remain unchanged (same date, status, remarks structure).
- `summary` now uses calculator outputs and includes:
  - `total_days`, `present`, `absent`, `late`, `half_day`, `leave`, `attendance_credit`, `attendance_rate`, and `percentage` (mapped to `attendance_rate`).
- Existing keys used by API consumers remain present; new keys are additive and documented here.

Keys preserved
- `total` (mapped to `total_days` where appropriate)
- `present` (mapped)
- `absent` (mapped)
- `late` (mapped)
- `percentage` (mapped to `attendance_rate`)

Keys added
- `half_day`
- `leave`
- `attendance_credit`
- `attendance_rate`

Confirmation calculator policy used
- `AttendanceCreditCalculator` policy applied:
  - `present` => 1.0
  - `late` => 1.0
  - `half_day` => 0.5
  - `absent` => 0.0
  - `leave` => 0.0 (legacy no-credit, still counted in totals)
  - `attendance_rate` = round((attendance_credit / total_days) * 100, 2)

Confirmation controllers/notifications/writes unchanged
- No controllers were modified in this phase (inspected only).
- No notification code or notification guards were changed.
- No attendance write/update/delete behavior was changed.

Tests created/updated
- Added: `tests/Unit/Models/AttendanceCreditReportHelperTest.php` — uses SQLite in-memory schema created in test `setUp()`; validates counts, credit, rate, and details.
- Existing tests run (and passed) as part of verification: `AttendanceCreditCalculatorTest`, `AttendanceServiceStatusCalculationTest`, `AttendanceNotificationSendGuardTest`.

Commands run (exact)
```
php -l app/Models/Attendance.php
php -l app/Support/Attendance/AttendanceCreditCalculator.php
php -l tests/Unit/Models/AttendanceCreditReportHelperTest.php
php artisan test --filter=AttendanceCreditReportHelperTest --env=testing
php artisan test --filter=AttendanceCreditCalculatorTest --env=testing
php artisan test --filter=AttendanceServiceStatusCalculationTest --env=testing
php artisan test --filter=AttendanceNotificationSendGuardTest --env=testing
```

Test result summary
- `php -l` checks: no syntax errors detected for modified files.
- `AttendanceCreditReportHelperTest`: PASS — 8 tests passed (32 assertions).
- `AttendanceCreditCalculatorTest`: PASS — 12 tests passed (23 assertions).
- `AttendanceServiceStatusCalculationTest`: PASS — 11 tests passed (47 assertions).
- `AttendanceNotificationSendGuardTest`: PASS — 6 tests passed (17 assertions).

Confirmation no full suite was run
- Only the four filtered test runs above were executed; no full test suite was run.

Confirmation no migrations/schema/real MySQL/notification sends/device sync were touched
- No migration or schema files were modified.
- Tests use an isolated SQLite in-memory schema created and dropped during test `setUp`/`tearDown` only.
- No write paths, notification sends, device sync, exports against real/local MySQL, or migrations were executed.

Remaining risks
- Legacy controllers and model helpers outside the scope of this change still compute `present/total` (Phase 7L list). UI or API consumers that rely on those legacy percentages will still diverge until migrated (Phase 7O).
- Consumers that assume `percentage` was strictly `present / total` (for example, rounding behavior) may see small differences; tests cover key cases but not every consumer path.

Recommended Phase 7O next step
- Migrate all legacy consumers (controllers and model helpers) to use `AttendanceCreditCalculator::summarizeRecords()` or `summarize()` for consistent reporting. Prioritize public APIs and admin/teacher-facing controllers.

Report file path
- `docs/project-autopsy/PHASE_7N_ATTENDANCE_MODEL_HELPER_CREDIT_ALIGNMENT.md`

-- End of Phase 7N report
