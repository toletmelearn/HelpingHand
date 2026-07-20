# Phase 2I - Test Environment Safety Audit for API Token Tests

## 1. Files Inspected

- `phpunit.xml`
- `.env.example`
- `.env.testing`
- `config/database.php`
- `tests/Feature/API/AuthControllerTest.php`
- `tests/TestCase.php`
- `database/factories/UserFactory.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/UserRoleSeeder.php`
- `database/factories`
- `database/seeders`
- `docs/project-autopsy/PHASE_2H_SANCTUM_TOKEN_ABILITY_VERIFICATION.md`

## 2. Commands Run

Safe read-only commands only:

```powershell
Get-Content phpunit.xml
Get-Content .env.example
Get-Content .env.testing
Get-Content config/database.php
Get-Content tests/Feature/API/AuthControllerTest.php
Get-Content tests/TestCase.php
Get-ChildItem database/factories
Get-ChildItem database/seeders
Get-Content database/factories/UserFactory.php
Get-Content database/seeders/RoleSeeder.php
Get-Content database/seeders/UserRoleSeeder.php
Get-Content database/seeders/DatabaseSeeder.php
Get-Content docs/project-autopsy/PHASE_2H_SANCTUM_TOKEN_ABILITY_VERIFICATION.md -TotalCount 220
rg -n "DB_CONNECTION|DB_DATABASE|RefreshDatabase|DatabaseMigrations|DatabaseTransactions|createToken|Role|User::factory|seed" phpunit.xml .env.example .env.testing config tests database -g "*.php" -g "*.xml" -g "*.example" -g "*.testing"
rg -n "use RefreshDatabase|use DatabaseMigrations|use DatabaseTransactions|User::factory|createToken\(" tests -g "*.php"
```

No tests, migrations, tinker commands, composer setup, database creation, database deletion, or database-changing commands were run.

## 3. Current PHPUnit Database Config

`phpunit.xml` sets:

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

If PHPUnit is invoked normally through this `phpunit.xml`, the intended test database is:

- Connection: `sqlite`
- Database: `:memory:`

This is the preferred isolated shape for API token tests.

## 4. `.env.testing` Status

`.env.testing` exists and contains:

```dotenv
APP_ENV=local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=helpinghand
DB_USERNAME=root
```

This is unsafe as a test environment file because it points to the real-looking local project database name `helpinghand`.

Risk:

- Laravel can load environment files for the current environment.
- `phpunit.xml` does set `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, which should override environment values in a normal PHPUnit run.
- However, the presence of `.env.testing` pointing at `helpinghand` creates operator risk, especially if tests are run through an alternate command, cached config, IDE runner, or environment where phpunit overrides are not applied as expected.

Conclusion:

- Tests are not approved to run yet.
- `.env.testing` must be repaired or neutralized before running `RefreshDatabase` tests.

## 5. Database Config Findings

`config/database.php` uses:

```php
'default' => env('DB_CONNECTION', 'sqlite'),
```

The sqlite connection uses:

```php
'database' => env('DB_DATABASE', database_path('database.sqlite')),
```

The MySQL connection uses:

```php
'database' => env('DB_DATABASE', 'laravel'),
```

Implication:

- With `phpunit.xml`, tests should use in-memory SQLite.
- With `.env.testing` alone, tests would target MySQL database `helpinghand`.

## 6. RefreshDatabase Risk

The following test files use `RefreshDatabase`:

- `tests/Feature/API/AuthControllerTest.php`
- `tests/Feature/Admin/StudentControllerTest.php`
- `tests/Unit/Models/StudentTest.php`
- `tests/Unit/Services/NotificationServiceTest.php`

`RefreshDatabase` can migrate, refresh, or wrap transactions depending on migration state and database driver.

Because migration state has already been identified as inconsistent in earlier phases, running these tests is risky until the database target is guaranteed isolated.

If the target database is accidentally MySQL `helpinghand`, running tests could reset or alter real local project data.

## 7. Existing API Auth Test Findings

`tests/Feature/API/AuthControllerTest.php` currently includes tests for:

- Successful login with valid credentials.
- Failed login with invalid credentials.
- Required-field validation.
- Authenticated `/api/v1/me`.
- Logout.

The test creates users with:

```php
User::factory()->create(...)
```

The test creates direct broad tokens with:

```php
$token = $user->createToken('test-device')->plainTextToken;
```

Current gaps:

- Does not create or attach roles.
- Does not assert Sanctum token abilities.
- Direct test tokens are still broad/unscoped.
- Does not test refresh-token ability recomputation.

Recommended updates later:

- Add targeted role setup.
- Assert latest token abilities after login.
- Assert refreshed token abilities are recomputed.
- Update direct token creation helpers to include at least `mobile:user` where routes require an authenticated token.

## 8. Factory and Seeder Readiness

Factories:

- `database/factories/UserFactory.php` exists and can create users.
- No `RoleFactory` was found.
- No `StudentFactory`, `TeacherFactory`, `ParentModelFactory`, or `GuardianFactory` was found in `database/factories`.

Seeders:

- `RoleSeeder` safely creates role names with `firstOrCreate()`:
  - `admin`
  - `teacher`
  - `student`
  - `parent`
- `UserRoleSeeder` assigns `admin` to user ID 1 if present.
- `DatabaseSeeder` calls many seeders, including broad data seeders and `ResetStudentsSeeder`.

Important risk:

- Do not run full `DatabaseSeeder` for token ability tests.
- Token ability tests only need users, roles, and `role_user` pivot rows.
- Creating only the required role records inside isolated tests is safer than calling the full seeder chain.

## 9. Whether Tests Are Safe to Run Now

Tests are unsafe to run now.

Reason:

- `phpunit.xml` is configured for safe in-memory SQLite.
- But `.env.testing` points at MySQL database `helpinghand`.
- Several tests use `RefreshDatabase`.
- Migration state is known to be inconsistent.
- No test command has been verified against an isolated database in this phase.

Do not run `php artisan test`, PHPUnit, or the API auth tests until the test environment is repaired and explicitly confirmed.

## 10. Recommended Isolated Testing Approach

Recommended path: Option A, isolated SQLite in-memory testing.

Why:

- `phpunit.xml` already declares `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.
- It avoids touching MySQL local data.
- Token ability tests need only users, roles, role pivots, and Sanctum personal access token tables.

Required safety repair before tests:

1. Fix `.env.testing` so it does not point at `helpinghand`.
2. Prefer:
   ```dotenv
   APP_ENV=testing
   DB_CONNECTION=sqlite
   DB_DATABASE=:memory:
   ```
3. Confirm config is not cached for testing.
4. Run only targeted API token tests after confirmation.

Alternative path: Option B, separate MySQL database.

- Use a dedicated database such as `helpinghand_test`.
- Never use `helpinghand`.
- Requires explicit database creation outside application test runs.

Fallback path: Option C, pure unit tests.

- Refactor or expose token ability mapping in a testable way.
- Use fake/stub users with `hasRole()`/`hasAnyRole()` behavior.
- This avoids database writes but may require code design changes, so it is less minimal than isolated SQLite feature tests.

## 11. Safe Phase 2J Implementation Plan

1. Update test environment only.
   - Change `.env.testing` to use SQLite in-memory or a clearly separate test database.
   - Do not edit real `.env`.

2. Add targeted API token ability tests.
   - Extend `tests/Feature/API/AuthControllerTest.php` or create a new focused test file.
   - Use isolated test database only.
   - Create roles directly in the test with `Role::create()` or `Role::firstOrCreate()`.
   - Attach roles through `$user->roles()->attach($role->id)`.

3. Test login abilities.
   - Admin user gets `mobile:user`, `mobile:admin`.
   - Teacher user gets `mobile:user`, `mobile:teacher`.
   - Student user gets `mobile:user`, `mobile:student`.
   - Parent user gets `mobile:user`, `mobile:parent`.

4. Test refresh-token ability recomputation.
   - Create authenticated token.
   - Call `/api/v1/refresh-token`.
   - Confirm old token is deleted.
   - Confirm new token abilities match current user roles.

5. Avoid full seeders.
   - Do not run `DatabaseSeeder`.
   - Do not run `ResetStudentsSeeder`.

6. Safe commands after confirmation only.
   - `php artisan test --filter=AuthControllerTest`
   - Or a narrower filter for the new token ability tests.

7. Keep enforcement out.
   - Do not add `tokenCan()` checks in Phase 2J.
   - Do not change `ApiAccessControl`.
   - Do not change route middleware.

## 12. Confirmation

No application code was modified.
No `phpunit.xml` changes were made.
No `.env`, `.env.example`, or `.env.testing` changes were made.
No tests were run.
No migrations were run.
No database was created or dropped.
No database records were created, updated, or deleted.
