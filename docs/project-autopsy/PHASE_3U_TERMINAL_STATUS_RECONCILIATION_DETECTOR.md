# Phase 3U - Terminal Status Reconciliation Detector

## Files Inspected

- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/StudentPromotionLog.php`
- `app/Console/Commands`
- `routes/console.php`
- `bootstrap/app.php`
- `tests/Feature/Admin/StudentPassedOutStatusTest.php`
- `tests/Unit/Models/StudentClassCompatibilityTest.php`
- `docs/project-autopsy/PHASE_3T_TERMINAL_STATUS_RECONCILIATION_AUDIT.md`

## Files Changed

- `app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `docs/project-autopsy/PHASE_3U_TERMINAL_STATUS_RECONCILIATION_DETECTOR.md`

No controller, route, model, migration, schema, import, promotion, passed-out, report, or API logic was changed.

## Command Signature

```bash
php artisan helpinghand --dry-run
```

Implemented signature:

```php
helpinghand {--dry-run : Show detected drift without changing data}
```

The command was added as `App\Console\Commands\ReconcileTerminalStatusesCommand`. Laravel discovered it through the existing `app/Console/Commands` command discovery pattern; no bootstrap or route registration change was needed.

## Dry-Run-Only Safety Behavior

If `--dry-run` is omitted, the command refuses to run:

```text
This command is dry-run only in Phase 3U. Use --dry-run to inspect issues.
```

The command has no `--apply` option and no repair mode.

The implementation uses read-only query builder queries. It does not call:

- `save()`
- `update()`
- `delete()`
- `insert()`
- `create()`
- `truncate()`
- migrations
- seeders
- promotion/pass-out actions

## Detection Categories Implemented

The dry-run detector reports:

1. Latest terminal statuses with class/section drift.
   - Terminal statuses: `passed_out`, `left_school`, `tc_issued`
   - Drift when `class_id`, `school_class_id`, `section_id`, or `section` is populated
   - Also drift when latest status is `passed_out` and `students.class` is not `Passed Out`
2. Latest `passed_out` statuses without matching `student_promotion_logs.to_class = Passed Out`.
3. `Passed Out` promotion logs where latest status is not `passed_out`.
4. Suspicious `Passed Out` promotion logs where `from_class` is null, blank, `Unknown`, or `Passed Out`.
5. Class FK conflicts where `class_id != school_class_id`.
6. Class FK null/non-null mismatches:
   - `class_id` null and `school_class_id` populated
   - `class_id` populated and `school_class_id` null

## Console Output Summary

Live dry-run command:

```bash
php artisan helpinghand --dry-run
```

Output summary:

| Detection | Count |
| --- | ---: |
| Latest terminal statuses with class/section drift | 1 |
| Latest `passed_out` statuses without `Passed Out` promotion log | 1 |
| `Passed Out` promotion logs without latest `passed_out` status | 0 |
| Suspicious `Passed Out` promotion logs | 0 |
| `class_id` / `school_class_id` conflicts | 1 |
| `class_id` null/non-null mismatches | 0 |

Affected students reported by the command:

| Detection | Student ID | Name |
| --- | ---: | --- |
| Terminal status class/section drift | 6 | Demo Student 116 |
| Missing `Passed Out` promotion log | 6 | Demo Student 116 |
| `class_id` / `school_class_id` conflict | 301 | Demo Student 831 |

The command printed recommended action text only; it did not repair anything.

## Tests Created / Updated

Created:

- `tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`

Tests added:

- `command_requires_dry_run_option`
- `dry_run_detects_terminal_status_with_class_section_drift`
- `dry_run_detects_passed_out_without_promotion_log`
- `dry_run_detects_class_id_school_class_id_conflict`
- `dry_run_does_not_modify_database`
- `dry_run_reports_clean_state_when_no_issues`

The test uses isolated SQLite-memory tables only:

- `students`
- `student_statuses`
- `student_promotion_logs`

No full project migrations are used.

One initial test run failed because assertions expected full long recommendation text inside Symfony console table cells. The command behavior was correct; the test assertions were narrowed to stable detection labels and affected student names.

## Commands Run

- `Get-ChildItem app/Console -Recurse`
- `Get-Content routes/console.php`
- `Get-Content bootstrap/app.php`
- `Get-Content docs/project-autopsy/PHASE_3T_TERMINAL_STATUS_RECONCILIATION_AUDIT.md`
- `php artisan list --raw | Select-String "route:health|assign-missing|admin|system|biometric|helpinghand"`
- `php -l app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `php -l tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `php artisan helpinghand --dry-run`
- `php artisan test --filter=TerminalStatusReconciliationCommandTest --env=testing`
- `php artisan test --filter=StudentPassedOutStatusTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`
- `php artisan test --filter=StudentStatusCrudRestrictionTest --env=testing`

## Test Result Summary

- `TerminalStatusReconciliationCommandTest`: 6 passed, 25 assertions
- `StudentPassedOutStatusTest`: 5 passed, 19 assertions
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions
- `StudentStatusCrudRestrictionTest`: 9 passed, 32 assertions

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated tests during discovery. No targeted test failed after the test assertion adjustment.

## Safety Confirmations

- No apply mode was added.
- No full test suite was run.
- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was changed.
- The live `php artisan helpinghand --dry-run` command performed read-only inspection only.
- No student data was repaired, promoted, passed out, imported, seeded, inserted, updated, or deleted.

## Remaining Risks

- The historical drift rows still exist because this phase intentionally detects only.
- Student 6 still needs manual review before any terminal cleanup or promotion-log repair.
- Student 301 still needs manual review before any class FK reconciliation.
- The detector uses latest status by `MAX(id)`, matching the Phase 3T audit. If historical imports create out-of-order IDs later, a future version may need a stricter latest-status rule using date plus ID.
- No admin UI report page exists yet; output is currently console-only.

## Recommended Next Step

Phase 3V should remain non-mutating and improve review ergonomics:

1. Add a small read-only admin report page or downloadable report for terminal-status reconciliation findings, backed by the same detector logic.
2. Keep command output and admin report aligned.
3. Add tests for the shared detector/report data.
4. Do not add `--apply` or automated repair until an admin has reviewed affected student IDs.
