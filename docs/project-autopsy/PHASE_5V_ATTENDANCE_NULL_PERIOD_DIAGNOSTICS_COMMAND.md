PHASE 5V — ATTENDANCE NULL-PERIOD DIAGNOSTICS COMMAND

1. Files inspected
- app/Console/Commands (existing commands)
- app/Console/Commands/ReconcileTerminalStatusesCommand.php (pattern)
- app/Models/Attendance.php
- app/Http/Controllers/AttendanceController.php
- app/Http/Controllers/API/AttendanceController.php
- app/Services/Attendance/AttendanceBulkPreflightService.php
- docs/project-autopsy/PHASE_5U_ATTENDANCE_NULL_PERIOD_POLICY_AUDIT.md
- docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md
- tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php (pattern)

2. Files changed / added
- Added: app/Console/Commands/AttendanceNullPeriodDiagnosticsCommand.php
  - Read-only Artisan command that inventories attendance period values and duplicate risks.
- Added: tests/Feature/Attendance/AttendanceNullPeriodDiagnosticsCommandTest.php
  - Focused tests using SQLite in-memory schema only.

3. Command signature
- `helpinghand:attendance-null-period-diagnostics {--json : Output JSON} {--limit=50 : Limit sample rows}`

4. Diagnostics reported
The command computes and reports (read-only):
- total attendance rows
- rows where `period IS NULL`
- rows where `period = ''`
- rows where trimmed period is empty
- distinct period values with counts (top results)
- duplicate groups by `student_id,date,period` (count > 1)
- duplicate groups by `student_id,date` where `period IS NULL`
- duplicate groups by `student_id,date` where `period = ''`
- suspicious sentinel-like period values (samples) for values: `full_day`, `full-day`, `full day`, `fullday`, `all_day`, `all-day`
- sample rows for null period, empty period, duplicate null groups, and suspicious sentinel rows

5. JSON output behavior
- When passed `--json`, the command prints a single JSON document (no extra prose) with keys: `summary`, `distinct_periods`, `duplicate_exact_groups`, `duplicate_null_period_groups`, `duplicate_empty_period_groups`, `suspicious_sentinel_rows`, `samples`.
- The JSON document is machine-readable and suitable for piping to external tools.

6. Text output behavior
- Default (no `--json`) prints readable tables and includes the explicit line: "Read-only diagnostics. No attendance data was modified." and a final warning that diagnostics are complete and no data was modified.

7. Read-only safety confirmation
- The command uses only SELECT/aggregate queries and `limit`ed reads.
- No INSERT, UPDATE, DELETE, or other write operations are performed.
- No biometric/device commands or syncs are triggered.

8. Tests created/updated
- Added tests/Feature/Attendance/AttendanceNullPeriodDiagnosticsCommandTest.php
  - 9 focused tests created, covering the required assertions and ensuring the command is read-only.
  - Tests create minimal `attendances` table in SQLite in-memory for isolation.

9. Commands run during verification
- php -l app/Console/Commands/AttendanceNullPeriodDiagnosticsCommand.php
  - Result: No syntax errors
- php -l tests/Feature/Attendance/AttendanceNullPeriodDiagnosticsCommandTest.php
  - Result: No syntax errors
- php artisan list --raw | Select-String "attendance-null-period"
  - Result: Command appears in artisan command list as `helpinghand:attendance-null-period-diagnostics`.
- vendor/bin/phpunit --filter=AttendanceNullPeriodDiagnosticsCommandTest tests/Feature/Attendance/AttendanceNullPeriodDiagnosticsCommandTest.php --debug
  - Result: All tests passed (9 tests, exit code 0)

10. Test result summary
- All new tests passed using SQLite in-memory schema. No other tests or full suite were executed.

11. Confirmation of constraints
- No migrations were changed.
- No database schema files were modified.
- No attendance write behavior was changed.
- No apply/fix/repair options were added to the command.
- No live MySQL data was modified; tests used isolated SQLite in-memory only.
- No biometric sync or device commands were touched or invoked.
- Only small test-harness artifacts were added (table creation in tests) — nothing touching production schema files.

12. Remaining risks
- The command inspects `period` values verbatim — inconsistent whitespace/casing across DB could hide sentinel-like values; consider adding normalized-check variants in Phase 5W when planning a normalization strategy.
- For very large production tables, the `distinct` and duplicate group queries can be expensive; consider adding optional date/class filters later to limit scope.

13. Recommended Phase 5W next step
- Provide an analysis UI or export (CSV/JSON) for long-running diagnostics, and consider a non-destructive reporting job that can safely scan production MySQL in batches. Then decide whether to adopt a sentinel normalization (e.g., use empty string vs NULL consistently) or introduce a non-null sentinel column before any schema migration.

Report path
- docs/project-autopsy/PHASE_5V_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_COMMAND.md

If you want, I can commit these files, or adjust the command to include optional filters (date/class) for large DB scans.
