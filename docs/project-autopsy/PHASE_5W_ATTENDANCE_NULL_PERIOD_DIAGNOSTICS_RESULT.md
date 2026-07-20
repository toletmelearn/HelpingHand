# Phase 5W - Attendance Null-Period Diagnostics Result

Date: 2026-06-06

Scope: Read-only execution of the Phase 5V attendance null-period diagnostics command against the current local database. No code, routes, controllers, services, models, migrations, database rows, attendance records, biometric sync, or device commands were modified or run.

## Files Inspected

- `app/Console/Commands/AttendanceNullPeriodDiagnosticsCommand.php`
- `app/Models/Attendance.php`
- `docs/project-autopsy/PHASE_5V_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_COMMAND.md`
- `docs/project-autopsy/PHASE_5U_ATTENDANCE_NULL_PERIOD_POLICY_AUDIT.md`

## Commands Run

- `php -l app/Console/Commands/AttendanceNullPeriodDiagnosticsCommand.php`
  - Result: passed, no syntax errors.
- `Get-Content app/Console/Commands/AttendanceNullPeriodDiagnosticsCommand.php`
- `Get-Content app/Models/Attendance.php`
- `Get-Content docs/project-autopsy/PHASE_5V_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_COMMAND.md`
- `Get-Content docs/project-autopsy/PHASE_5U_ATTENDANCE_NULL_PERIOD_POLICY_AUDIT.md`
- `php artisan list --raw | Select-String "attendance-null-period"`
  - Result: command found as `helpinghand:attendance-null-period-diagnostics`.
- `php artisan helpinghand --limit=50`
  - Result: failed safely because `helpinghand` is an Artisan namespace, not the concrete command name in this app. No data was modified.
- `php artisan helpinghand:attendance-null-period-diagnostics --limit=50`
  - Result: text diagnostics completed successfully.
- `php artisan helpinghand:attendance-null-period-diagnostics --json --limit=50`
  - Result: JSON diagnostics completed successfully.

Note: the task text referenced `php artisan helpinghand`, but the command registered by Phase 5V is `helpinghand:attendance-null-period-diagnostics`. The concrete registered command was used for the actual diagnostics after the short namespace invocation failed without mutation.

## Command Found

Yes. `php artisan list --raw | Select-String "attendance-null-period"` returned:

```text
helpinghand:attendance-null-period-diagnostics   Read-only diagnostics for attendance null/empty period values and duplicate risks.
```

## Text Diagnostics Summary

The text diagnostics printed the read-only banner and completed successfully:

```text
Attendance Null/Empty Period Diagnostics (read-only)
Read-only diagnostics. No attendance data was modified.
...
Diagnostics complete. No attendance data was modified.
```

Summary metrics:

| Metric | Value |
| --- | ---: |
| Total attendance rows | 104 |
| Period IS NULL | 98 |
| Period = empty string | 0 |
| Trimmed period empty | 98 |

## JSON Diagnostics Validation

- JSON mode returned a single JSON document beginning with `{` and ending with `}`.
- No prose banner or trailing warning appeared before or after the JSON document.
- The JSON summary values matched the text output.
- The JSON keys included `summary`, `distinct_periods`, `duplicate_exact_groups`, `duplicate_null_period_groups`, `duplicate_empty_period_groups`, `suspicious_sentinel_rows`, and `samples`.

JSON summary:

```json
{
  "total_rows": 104,
  "period_null_count": 98,
  "period_empty_string_count": 0,
  "period_trim_empty_count": 98
}
```

## Actual Counts

| Count | Value |
| --- | ---: |
| Total attendance rows | 104 |
| Rows where `period IS NULL` | 98 |
| Rows where `period = ''` | 0 |
| Rows where trimmed/coalesced period is empty | 98 |
| Duplicate exact groups by `student_id,date,period` | 0 |
| Duplicate null-period groups by `student_id,date WHERE period IS NULL` | 0 |
| Duplicate empty-period groups by `student_id,date WHERE period = ''` | 0 |
| Suspicious sentinel-like rows | 6 |

## Distinct Period Values Summary

| Period | Count |
| --- | ---: |
| `NULL` | 98 |
| `Full Day` | 6 |

The command's text table displays `NULL` as a blank value because of the current command formatting, but JSON mode confirms the dominant value is actual `null`, not empty string.

## Duplicate Group Summary

- Duplicate exact groups by `student_id,date,period`: none found.
- Duplicate null-period groups by `student_id,date`: none found.
- Duplicate empty-period groups by `student_id,date`: none found.
- Empty-period sample: none found.
- Duplicate null-period sample: none found.

This means the current local dataset has many null-period rows, but no currently detected duplicate full-day/null-period groups.

## Suspicious Sentinel Summary

Suspicious sentinel-like period rows were found.

| Period Value | Count | Notes |
| --- | ---: | --- |
| `Full Day` | 6 | Literal full-day label stored in `period`; all sampled rows were dated `2026-02-09`. |

The configured suspicious candidates include lowercase/slug-style values such as `full_day`, `full-day`, `full day`, `fullday`, `all_day`, and `all-day`. The command also reported `Full Day` rows in the local output, so this local dataset has both canonical `NULL`-like full-day rows and literal `Full Day` period rows.

## Interpretation Of Data Situation

This is primarily **Case B** with a small **Case D-style mixed representation risk**:

- Many null-period rows exist: 98 of 104 rows.
- No duplicate null-period groups were detected.
- No empty-string period rows were detected.
- A small number of literal `Full Day` period rows exist: 6 rows.

Practical interpretation:

- The live/local data already relies heavily on `NULL` as the full-day/no-period representation.
- There is no immediate duplicate cleanup pressure from the current diagnostics because duplicate group counts are zero.
- The six `Full Day` rows show that the system has at least two full-day representations: `NULL` and literal `Full Day`.
- Because the dominant representation is `NULL`, jumping straight to schema migration or sentinel normalization would be risky without first defining the canonical full-day policy and checking UI/report/API compatibility.

## Recommended Phase 5X Next Step

Phase 5X should define and enforce a read-only compatibility policy before any data repair or migration.

Recommended first code task:

Add a shared period presentation/normalization helper in read paths and diagnostics only, with tests, so the application can consistently classify:

- `NULL` as full-day/no-period
- `''` as full-day-like but non-canonical
- `Full Day` as full-day-like but non-canonical
- named periods as period-specific attendance

The helper should initially be used for display/diagnostic classification only, not for write normalization or data repair. After that, Phase 5Y can decide whether to keep `NULL` as canonical with stronger app-level duplicate checks, or plan a staged sentinel migration/backfill.

## Safety Confirmation

- No application code was modified.
- No routes were modified.
- No controllers, services, models, tests, or migrations were modified.
- No attendance write route was executed.
- No API store/update/destroy/bulkMark route was executed.
- No insert, update, delete, seed, sync, import, normalize, or repair command was run.
- No biometric sync or device command was run.
- No migration command was run.
- No full test suite was run.
- The only database access performed was the read-only diagnostics command using SELECT/aggregate queries.
