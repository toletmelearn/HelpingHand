# Phase 3V - Reconciliation Command Signature Rename

## Files Inspected

- `app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `docs/project-autopsy/PHASE_3U_TERMINAL_STATUS_RECONCILIATION_DETECTOR.md`

## Files Changed

- `app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `docs/project-autopsy/PHASE_3V_RECONCILIATION_COMMAND_SIGNATURE_RENAME.md`

## Old Command Signature

```php
helpinghand {--dry-run : Show detected drift without changing data}
```

Runnable form:

```bash
php artisan helpinghand --dry-run
```

## New Command Signature

```php
helpinghand:reconcile-terminal-statuses {--dry-run : Show detected drift without changing data}
```

Runnable form:

```bash
php artisan helpinghand:reconcile-terminal-statuses --dry-run
```

Note: the Phase 3V prompt repeated `helpinghand --dry-run` in a few places, but its command-list expectation explicitly required `helpinghand:reconcile-terminal-statuses` to appear and generic `helpinghand` to be gone. This phase followed that explicit command-list/old-command-removal requirement.

## Dry-Run-Only Behavior Confirmation

Dry-run-only behavior was preserved.

Running without `--dry-run` still refuses safely:

```text
This command is dry-run only in Phase 3U. Use --dry-run to inspect issues.
```

The dry-run command still reports the same live read-only findings:

| Detection | Count |
| --- | ---: |
| Latest terminal statuses with class/section drift | 1 |
| Latest `passed_out` statuses without `Passed Out` promotion log | 1 |
| `Passed Out` promotion logs without latest `passed_out` status | 0 |
| Suspicious `Passed Out` promotion logs | 0 |
| `class_id` / `school_class_id` conflicts | 1 |
| `class_id` null/non-null mismatches | 0 |

## Apply Mode Confirmation

No `--apply` option was added.

The command remains read-only and still has no repair mode.

## Command List Verification

Command list inspection:

```bash
php artisan list --raw | Select-String "helpinghand"
```

Result:

```text
helpinghand:reconcile-terminal-statuses   Detect terminal student status and class compatibility drift without changing data.
```

The old generic `helpinghand` command is no longer listed.

## Commands Run

- `Get-Content app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `Get-Content tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `Get-Content docs/project-autopsy/PHASE_3U_TERMINAL_STATUS_RECONCILIATION_DETECTOR.md`
- `php -l app/Console/Commands/ReconcileTerminalStatusesCommand.php`
- `php -l tests/Feature/Admin/TerminalStatusReconciliationCommandTest.php`
- `php artisan list --raw | Select-String "helpinghand"`
- `php artisan helpinghand:reconcile-terminal-statuses --dry-run`
- `php artisan test --filter=TerminalStatusReconciliationCommandTest --env=testing`
- `php artisan test --filter=StudentPassedOutStatusTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`
- `php artisan test --filter=StudentStatusCrudRestrictionTest --env=testing`

## Test Result Summary

- `TerminalStatusReconciliationCommandTest`: 6 passed, 25 assertions
- `StudentPassedOutStatusTest`: 5 passed, 19 assertions
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions
- `StudentStatusCrudRestrictionTest`: 9 passed, 32 assertions

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated tests during discovery. No targeted test failed.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was changed.
- No data repair was performed.
- No apply mode was added.
- No routes, controllers, student promotion logic, student status CRUD logic, reports, imports, or API student writes were changed.

## Remaining Risks

- Historical drift remains detected but unrepaired by design.
- Student 6 still needs manual review for terminal-status drift and missing `Passed Out` promotion log.
- Student 301 still needs manual review for class FK conflict.
- The command remains console-only; no admin UI report exists yet.

## Recommended Next Step

Phase 3W should keep the detector read-only and improve review ergonomics by extracting detector data into a small service that can back both the Artisan command and a future admin report page. Do not add `--apply` until detected rows have been reviewed.
