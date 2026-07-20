# Phase 3W - Reconciliation Detector Service

## Files Inspected

- `app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/StudentPromotionLog.php`
- `docs/project-autopsy/PHASE_3V_RECONCILIATION_COMMAND_SIGNATURE_RENAME.md`
- `docs/project-autopsy/PHASE_3U_TERMINAL_STATUS_RECONCILIATION_DETECTOR.md`

## Files Changed

- `app/Services/StudentStatus/TerminalStatusReconciliationDetector.php`
- `app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `tests/Unit/Services/TerminalStatusReconciliationDetectorTest.php`
- `docs/project-autopsy/PHASE_3W_RECONCILIATION_DETECTOR_SERVICE.md`

No controllers, routes, views, migrations, schema files, import flows, API student writes, promotion logic, passed-out logic, or report controllers were changed.

## Service Class Summary

Created:

```php
App\Services\StudentStatus\TerminalStatusReconciliationDetector
```

Public API:

```php
public function detect(): array
```

The service returns structured read-only data:

- `terminal_status_drift`
- `passed_out_without_log`
- `passed_out_logs_without_latest_status`
- `suspicious_passed_out_logs`
- `class_fk_conflicts`
- `class_fk_null_mismatches`
- `summary`

The `summary` array contains counts for each detector category.

The service uses query builder SELECT queries only. It does not call `save`, `update`, `delete`, `insert`, `create`, `truncate`, migrations, seeders, or workflow methods.

## Detection Categories Preserved

The extracted service preserves all Phase 3U/3V detection categories:

1. Latest terminal statuses with class/section drift.
2. Latest `passed_out` statuses without matching `Passed Out` promotion log.
3. `Passed Out` promotion logs without latest `passed_out` status.
4. Suspicious `Passed Out` promotion logs.
5. `class_id` / `school_class_id` conflicts.
6. `class_id` null/non-null mismatches.

Live dry-run counts after extraction remained:

| Detection | Count |
| --- | ---: |
| Latest terminal statuses with class/section drift | 1 |
| Latest `passed_out` statuses without `Passed Out` promotion log | 1 |
| `Passed Out` promotion logs without latest `passed_out` status | 0 |
| Suspicious `Passed Out` promotion logs | 0 |
| `class_id` / `school_class_id` conflicts | 1 |
| `class_id` null/non-null mismatches | 0 |

## Command Behavior Preservation

The command still uses the Phase 3V explicit signature:

```bash
php artisan helpinghand:reconcile-terminal-statuses --dry-run
```

The Phase 3W prompt included `php artisan helpinghand --dry-run` in one verification line, but Phase 3V intentionally removed the generic `helpinghand` command. This phase preserved Phase 3V behavior and used the explicit command.

The command now delegates detection to `TerminalStatusReconciliationDetector` and keeps console rendering in the command class.

Console output format was preserved as much as possible:

- same summary table labels
- same affected student table fields
- same dry-run safety text
- same recommendation text

## Dry-Run-Only Safety Confirmation

Dry-run-only behavior remains enforced.

Running without `--dry-run` still refuses safely:

```text
This command is dry-run only in Phase 3U. Use --dry-run to inspect issues.
```

No `--apply` option was added.

## Tests Created / Updated

Created:

- `tests/Unit/Services/TerminalStatusReconciliationDetectorTest.php`

Service tests added:

- `detector_finds_terminal_status_with_class_section_drift`
- `detector_finds_passed_out_without_promotion_log`
- `detector_finds_class_id_school_class_id_conflict`
- `detector_reports_clean_state_when_no_issues`
- `detector_does_not_modify_database`

The existing command test remained focused on command behavior and still passed.

## Commands Run

- `Get-Content app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `Get-Content tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `Get-Content docs/project-autopsy/PHASE_3V_RECONCILIATION_COMMAND_SIGNATURE_RENAME.md`
- `Get-Content docs/project-autopsy/PHASE_3U_TERMINAL_STATUS_RECONCILIATION_DETECTOR.md`
- `php -l app/Services/StudentStatus/TerminalStatusReconciliationDetector.php`
- `php -l app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `php -l tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `php -l tests/Unit/Services/TerminalStatusReconciliationDetectorTest.php`
- `php artisan helpinghand:reconcile-terminal-statuses --dry-run`
- `php artisan test --filter=TerminalStatusReconciliationDetectorTest --env=testing`
- `php artisan test --filter=TerminalStatusReconciliationCommandTest --env=testing`
- `php artisan test --filter=StudentPassedOutStatusTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`
- `php artisan test --filter=StudentStatusCrudRestrictionTest --env=testing`

## Test Result Summary

- `TerminalStatusReconciliationDetectorTest`: 5 passed, 21 assertions
- `TerminalStatusReconciliationCommandTest`: 6 passed, 25 assertions
- `StudentPassedOutStatusTest`: 5 passed, 19 assertions
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions
- `StudentStatusCrudRestrictionTest`: 9 passed, 32 assertions

PHPUnit emitted existing unrelated doc-comment metadata deprecation warnings during discovery. No targeted test failed.

## Safety Confirmations

- No apply mode was added.
- No full test suite was run.
- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was changed.
- The live dry-run command performed read-only inspection only.
- No data repair, update, delete, promotion, passed-out action, seed, import, or API write was performed.

## Remaining Risks

- Historical drift remains detected and unrepaired by design.
- Student 6 still needs manual review for terminal status drift and missing `Passed Out` promotion log.
- Student 301 still needs manual review for `class_id` / `school_class_id` conflict.
- The detector still uses latest status by `MAX(id)`, matching prior audit phases.
- No admin UI report exists yet.

## Recommended Next Step

Phase 3X can add a read-only admin report page or export that consumes `TerminalStatusReconciliationDetector::detect()`. Keep it display-only and do not add repair/apply behavior.
