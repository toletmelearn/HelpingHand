# Phase 2K - Isolated Sanctum Token Ability Tests

## 1. Files Inspected

- `tests/Feature/API/SanctumTokenAbilityTest.php`
- `tests/Feature/API/AuthControllerTest.php`
- `tests/TestCase.php`
- `app/Http/Controllers/API/AuthController.php`
- `app/Models/User.php`
- `app/Models/Role.php`
- `config/sanctum.php`
- `.env.testing`
- `phpunit.xml`
- `database/migrations/2026_01_30_070159_create_personal_access_tokens_table.php`
- `docs/project-autopsy/PHASE_2J_SANCTUM_TOKEN_ABILITY_TESTS.md`

## 2. Files Changed

- `tests/Feature/API/SanctumTokenAbilityTest.php`
- `docs/project-autopsy/PHASE_2K_ISOLATED_SANCTUM_TOKEN_ABILITY_TESTS.md`

No real `.env`, migrations, database schema files, API routes, `ApiAccessControl`, or token ability enforcement code was changed.

## 3. Why Full Migrations Were Avoided

Phase 2J proved that running the targeted test with `RefreshDatabase` still triggered full project migrations under SQLite memory.

The first blocker was:

```text
RuntimeException: This database driver does not support fulltext index creation.
```

Source:

```text
database/migrations/2026_01_21_093000_create_exam_papers_table.php
$table->fullText(['title', 'subject', 'class_section']);
```

This failure was unrelated to Sanctum token ability logic. It showed that the full migration set is not currently SQLite-compatible.

Phase 2K avoids full migrations by creating only the minimal schema required for this test file.

## 4. RefreshDatabase Removed

`RefreshDatabase` was removed from:

- `tests/Feature/API/SanctumTokenAbilityTest.php`

Unrelated test files still use `RefreshDatabase`, but they were not run in Phase 2K.

## 5. Minimal Test Schema Created

`SanctumTokenAbilityTest` now creates and drops its own isolated SQLite-memory schema in the test lifecycle.

Tables created:

- `users`
- `roles`
- `role_user`
- `personal_access_tokens`

Minimal columns:

`users`:

- `id`
- `name`
- `email`
- `password`
- timestamps

`roles`:

- `id`
- `name`
- `display_name`
- `description`
- timestamps

`role_user`:

- `user_id`
- `role_id`
- unique pair

`personal_access_tokens`:

- `id`
- `tokenable_type`
- `tokenable_id`
- `name`
- `token`
- `abilities`
- `last_used_at`
- `expires_at`
- timestamps

No Laravel migrations or seeders are invoked by this test file.

## 6. Tests Added or Updated

Updated `tests/Feature/API/SanctumTokenAbilityTest.php` to keep these six tests:

- `test_admin_login_receives_mobile_admin_ability`
- `test_teacher_login_receives_mobile_teacher_ability`
- `test_student_login_receives_mobile_student_ability`
- `test_parent_login_receives_mobile_parent_ability`
- `test_login_without_recognized_role_receives_mobile_user_only`
- `test_refresh_token_recomputes_role_abilities`

The tests:

- Create users directly through `User::create()`.
- Hash passwords.
- Create roles with `Role::firstOrCreate()`.
- Attach roles through `$user->roles()->attach($role->id)`.
- Call `/api/v1/login` through the normal application route bootstrap.
- Inspect the latest Sanctum token abilities.
- Verify refresh-token deletes the old token and recomputes abilities.

## 7. Commands Run

Safe and targeted commands only:

```powershell
Get-Content tests/Feature/API/SanctumTokenAbilityTest.php
Get-Content tests/Feature/API/AuthControllerTest.php
Get-Content tests/TestCase.php
Get-Content app/Http/Controllers/API/AuthController.php
Get-Content app/Models/User.php
Get-Content app/Models/Role.php
php -l tests/Feature/API/SanctumTokenAbilityTest.php
php artisan tinker --env=testing --execute="dump(app()->environment()); dump(config('database.default')); dump(config('database.connections.sqlite.database'));"
rg -n "RefreshDatabase|Schema::create|personal_access_tokens|fullText|createToken|tokenAbilitiesFor" tests app config database docs -g "*.php" -g "*.md"
php artisan test --filter=SanctumTokenAbilityTest --env=testing
```

No full test suite was run.

## 8. Test Result Summary

Command:

```powershell
php artisan test --filter=SanctumTokenAbilityTest --env=testing
```

Result:

```text
PASS  Tests\Feature\API\SanctumTokenAbilityTest
Tests: 6 passed (19 assertions)
```

Passed tests:

- Admin login receives `mobile:user` and `mobile:admin`.
- Teacher login receives `mobile:user` and `mobile:teacher`.
- Student login receives `mobile:user` and `mobile:student`.
- Parent login receives `mobile:user` and `mobile:parent`.
- Login without recognized role receives only `mobile:user`.
- Refresh-token recomputes role abilities.

Warnings:

- PHPUnit emitted doc-comment metadata deprecation warnings from unrelated existing tests during test discovery.
- These warnings did not fail the targeted test.

## 9. Remaining Failures

No failures remain for `SanctumTokenAbilityTest`.

The broader project migration issue remains:

- Full project migrations are still not SQLite-compatible because of at least one fulltext index migration.

## 10. Confirmations

- No migrations were run manually.
- No migration files were changed.
- No database schema files were changed.
- No full test suite was run.
- No `DatabaseSeeder` or `ResetStudentsSeeder` was run.
- No real `.env` file was edited.
- No API routes were changed.
- No `ApiAccessControl` changes were made.
- No token ability enforcement was added.
- No real/local MySQL data was touched.

## 11. Remaining Risks

- `SanctumTokenAbilityTest` intentionally bypasses full migrations, so it verifies token ability behavior, not full schema health.
- Existing API auth tests still use `RefreshDatabase` and broad direct tokens.
- Existing unrelated tests still emit PHPUnit doc-comment metadata deprecation warnings.
- Full project SQLite test compatibility remains blocked by migration issues.

## 12. Recommended Next Step

Phase 2L can safely proceed to ability enforcement planning now that issuance is dynamically proven.

Recommended order:

1. Keep `SanctumTokenAbilityTest` as the isolated issuance safety net.
2. Add ability checks in `ApiAccessControl` carefully as a second gate.
3. Run only targeted API access-control tests against isolated minimal schemas.
4. Leave full migration compatibility for a separate test-infrastructure phase.
