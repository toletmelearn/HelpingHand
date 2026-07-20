# Phase 2J - Sanctum Token Ability Tests

## 1. Files Inspected

- `.env.testing`
- `phpunit.xml`
- `config/database.php`
- `tests/Feature/API/AuthControllerTest.php`
- `tests/TestCase.php`
- `app/Http/Controllers/API/AuthController.php`
- `app/Models/User.php`
- `app/Models/Role.php`
- `database/factories/UserFactory.php`
- `database/seeders/RoleSeeder.php`
- `docs/project-autopsy/PHASE_2I_TEST_ENVIRONMENT_SAFETY_AUDIT.md`
- `docs/project-autopsy/PHASE_2H_SANCTUM_TOKEN_ABILITY_VERIFICATION.md`
- `docs/project-autopsy/PHASE_2G_SANCTUM_TOKEN_ABILITIES_IMPLEMENTATION.md`

## 2. Files Changed

- `.env.testing`
- `tests/Feature/API/SanctumTokenAbilityTest.php`
- `docs/project-autopsy/PHASE_2J_SANCTUM_TOKEN_ABILITY_TESTS.md`

No real `.env`, API routes, `ApiAccessControl`, migrations, database schema, or token ability enforcement code was changed.

## 3. `.env.testing` Changes

Old unsafe values:

```dotenv
APP_ENV=local
DB_CONNECTION=mysql
DB_DATABASE=helpinghand
DB_USERNAME=root
```

New safe testing values:

```dotenv
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
BCRYPT_ROUNDS=4
CACHE_STORE=array
MAIL_MAILER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

The MySQL `helpinghand` database reference was removed from `.env.testing`.

## 4. Test Environment Verification Result

Before running tests, the testing environment was checked with:

```powershell
php artisan tinker --env=testing --execute="dump(app()->environment()); dump(config('database.default')); dump(config('database.connections.sqlite.database'));"
```

Result:

```text
"testing"
"sqlite"
":memory:"
```

This confirmed the targeted test command would use SQLite in-memory rather than the local MySQL `helpinghand` database.

Note:

```powershell
php artisan config
```

is not a valid runnable command in this Laravel 12 project. It printed the config namespace help and listed valid commands such as `config:show`, `config:cache`, and `config:clear`.

## 5. Test File Created

Created:

- `tests/Feature/API/SanctumTokenAbilityTest.php`

The test file uses `RefreshDatabase` only after `.env.testing` was repaired and the test environment was verified as SQLite memory.

The tests create only the records required for token ability checks:

- Users via `User::factory()`
- Roles via `Role::firstOrCreate()`
- Role assignments through `$user->roles()->attach($role->id)`

The tests do not call `DatabaseSeeder`, `ResetStudentsSeeder`, or broad seeders.

## 6. Tests Added

Added targeted tests:

- `test_admin_login_receives_mobile_admin_ability`
- `test_teacher_login_receives_mobile_teacher_ability`
- `test_student_login_receives_mobile_student_ability`
- `test_parent_login_receives_mobile_parent_ability`
- `test_login_without_recognized_role_receives_mobile_user_only`
- `test_refresh_token_recomputes_role_abilities`

Test intent:

- Confirm login-created tokens receive role-derived mobile abilities.
- Confirm users without recognized roles receive only `mobile:user`.
- Confirm refresh-token deletes the current token and creates a new token with recomputed role abilities.

No ability enforcement was tested or added.

## 7. Commands Run

Safe and targeted commands only:

```powershell
Get-Content .env.testing
Get-Content phpunit.xml
Get-Content config/database.php
Get-Content tests/Feature/API/AuthControllerTest.php
Get-Content tests/TestCase.php
Get-Content app/Http/Controllers/API/AuthController.php
Get-Content app/Models/User.php
Get-Content database/factories/UserFactory.php
Get-Content database/seeders/RoleSeeder.php
Get-Content docs/project-autopsy/PHASE_2I_TEST_ENVIRONMENT_SAFETY_AUDIT.md
Get-Content docs/project-autopsy/PHASE_2H_SANCTUM_TOKEN_ABILITY_VERIFICATION.md
Get-Content docs/project-autopsy/PHASE_2G_SANCTUM_TOKEN_ABILITIES_IMPLEMENTATION.md
php artisan config
php artisan tinker --env=testing --execute="dump(app()->environment()); dump(config('database.default')); dump(config('database.connections.sqlite.database'));"
php -l tests/Feature/API/SanctumTokenAbilityTest.php
php artisan test --filter=SanctumTokenAbilityTest --env=testing
Select-String -Path database/migrations/*.php -Pattern "fullText|fulltext"
```

No full test suite was run.

## 8. Test Result Summary

Command run:

```powershell
php artisan test --filter=SanctumTokenAbilityTest --env=testing
```

Result:

- The targeted test file was selected.
- 6 tests failed before assertions.
- Failure happened during database migration setup under SQLite memory.
- No application assertions ran.

First failure:

```text
RuntimeException: This database driver does not support fulltext index creation.
```

Stack trace location:

```text
vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/Grammar.php:224
```

Read-only search identified the migration source:

```text
database/migrations/2026_01_21_093000_create_exam_papers_table.php:60
$table->fullText(['title', 'subject', 'class_section']);
```

Per task instructions, migrations were not changed and testing stopped at this first schema blocker.

Additional warning:

- PHPUnit printed doc-comment metadata deprecation warnings from existing test files. These are not related to the new token ability test behavior.

## 9. Confirmation: Full Test Suite Was Not Run

Only this filtered command was run:

```powershell
php artisan test --filter=SanctumTokenAbilityTest --env=testing
```

No full `php artisan test` command was run.

## 10. Confirmation: Real `.env` and MySQL Data Were Not Touched

- The real `.env` file was not edited.
- `.env.testing` no longer points to MySQL `helpinghand`.
- The environment check confirmed `sqlite` and `:memory:` before the targeted test command.
- No local MySQL data was created, updated, deleted, migrated, wiped, or seeded.
- No `DatabaseSeeder` or `ResetStudentsSeeder` was run.

## 11. Remaining Risks

- Token ability tests cannot run successfully until SQLite-incompatible migrations are handled.
- The first blocker is fulltext index creation in `2026_01_21_093000_create_exam_papers_table.php`.
- There may be additional SQLite migration blockers after the fulltext issue is handled.
- Existing filtered test execution still scans existing test metadata and reports PHPUnit doc-comment deprecation warnings.
- Existing API auth tests still create broad direct tokens and do not assert abilities.

## 12. Recommended Next Step

Phase 2K should choose one safe testing path:

1. Make migrations SQLite-compatible in a controlled migration-test compatibility phase.
   - For example, conditionally skip fulltext index creation when the connection driver is SQLite.
   - Do this carefully because migrations are currently known to be inconsistent.

2. Or configure a separate MySQL test database such as `helpinghand_test`.
   - Never use `helpinghand`.
   - Run targeted tests only against the separate test database.

After the test database can migrate safely, rerun only:

```powershell
php artisan test --filter=SanctumTokenAbilityTest --env=testing
```

Then, only after token issuance is dynamically proven, proceed to token ability enforcement in `ApiAccessControl`.

## 13. Confirmation

No migrations were run manually.
No migration files were changed.
No database schema was changed.
No full test suite was run.
No real `.env` file was edited.
No API routes were changed.
No `ApiAccessControl` changes were made.
No token ability enforcement was added.
No real/local MySQL data was touched.
