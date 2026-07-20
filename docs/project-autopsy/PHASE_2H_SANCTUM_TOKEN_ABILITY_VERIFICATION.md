# Phase 2H - Sanctum Token Ability Verification

## 1. Files Inspected

- `app/Http/Controllers/API/AuthController.php`
- `config/sanctum.php`
- `.env.example`
- `app/Models/User.php`
- `app/Models/Role.php`
- `tests/Feature/API/AuthControllerTest.php`
- `docs/project-autopsy/PHASE_2G_SANCTUM_TOKEN_ABILITIES_IMPLEMENTATION.md`

Inspection was performed with safe lint, static search, and one read-only config check. No token creation, user creation, data update, migration, or test execution was performed.

## 2. Files Changed

- `docs/project-autopsy/PHASE_2H_SANCTUM_TOKEN_ABILITY_VERIFICATION.md`

No application code, route file, middleware, config file, `.env`, `.env.example`, migration, or database schema was changed in this phase.

## 3. Existing API Test Risk

`tests/Feature/API/AuthControllerTest.php` uses:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
```

and:

```php
use RefreshDatabase;
```

Therefore the current API auth test suite is unsafe to run in this phase because it can reset, migrate, or otherwise modify the active database depending on test configuration.

Additional findings:

- The test creates broad test tokens directly with `createToken('test-device')`.
- The test currently asserts token presence and response structure.
- The test does not assert token abilities.

Conclusion:

- Existing API tests need updating for token abilities.
- They should only be run against an isolated testing database, not the current project database.

## 4. Static Verification Findings

Static search verified that `AuthController@login()` now creates tokens with role-derived abilities:

```php
$token = $user->createToken($deviceName, $this->tokenAbilitiesFor($user))->plainTextToken;
```

Static search verified that `AuthController@refreshToken()` also creates tokens with recomputed role-derived abilities:

```php
$token = $user->createToken($deviceName, $this->tokenAbilitiesFor($user))->plainTextToken;
```

Static search verified that `tokenAbilitiesFor(User $user): array` exists and includes:

- `mobile:user`
- `mobile:admin`
- `mobile:teacher`
- `mobile:student`
- `mobile:parent`

Static search verified that abilities are returned as a unique array:

```php
return array_values(array_unique($abilities));
```

Static search verified that `userHasAnyRole(User $user, array $roles): bool` exists and is defensive:

- Uses `hasAnyRole()` when available.
- Falls back to `hasRole()` when available.
- Returns `false` if neither helper exists.

## 5. Sanctum Config Verification

`config/sanctum.php` now uses env-based expiration:

```php
'expiration' => env('SANCTUM_EXPIRATION', null),
```

Read-only config check:

```powershell
php artisan tinker --execute="dump(config('sanctum.expiration'));"
```

Result:

```text
null
```

Interpretation:

- The config is env-driven.
- The real `.env` has not opted into `SANCTUM_EXPIRATION`.
- Runtime token expiration remains non-disruptive for now.

`.env.example` documents:

```dotenv
SANCTUM_EXPIRATION=43200
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1
SANCTUM_TOKEN_PREFIX=
```

## 6. Ability Enforcement Status

No ability enforcement was added or detected in this phase.

Static search did not find new usage of:

- `tokenCan()`
- `currentAccessToken()->can()`
- route ability middleware

`ApiAccessControl` was not changed.

## 7. Safe Manual Verification Recipe

Do not run this against production data.

Only run the following on a safe local database where creating login tokens is acceptable:

1. Pick a known local user without exposing passwords.
2. Log in through `/api/v1/login` using a safe local credential.
3. Inspect only that user's latest personal access token record in a safe local environment.
4. Confirm `abilities` contains expected values:
   - Admin: `mobile:user`, `mobile:admin`
   - Teacher: `mobile:user`, `mobile:teacher`
   - Student: `mobile:user`, `mobile:student`
   - Parent: `mobile:user`, `mobile:parent`
5. Call `/api/v1/refresh-token` with the bearer token.
6. Confirm the old token was deleted and the new token recomputed abilities from current roles.

Example read-only inspection shape after a safe local login:

```php
dump($user->tokens()->latest()->first()?->abilities);
```

This recipe intentionally does not include passwords and was not executed in Phase 2H.

## 8. Future Isolated Test Plan

Update tests only when an isolated testing database is approved.

Recommended test updates:

1. Use a dedicated testing database.
2. Keep `RefreshDatabase` only in the isolated test environment.
3. Add role setup helpers for:
   - `admin`
   - `teacher`
   - `student`
   - `parent`
4. Test login ability issuance:
   - Admin receives `mobile:user`, `mobile:admin`.
   - Teacher receives `mobile:user`, `mobile:teacher`.
   - Student receives `mobile:user`, `mobile:student`.
   - Parent receives `mobile:user`, `mobile:parent`.
   - Unrecognized authenticated role receives at least `mobile:user`.
5. Test refresh-token ability recomputation:
   - Current token is deleted.
   - New token abilities match current roles.
   - Stale old-token abilities are not preserved.
6. Add a regression assertion that no ability enforcement is required for login/logout/me until the dedicated enforcement phase.

## 9. Commands Run

Safe commands only:

```powershell
php -l app/Http/Controllers/API/AuthController.php
php -l config/sanctum.php
php artisan route --path=api/v1 | Select-String login
php artisan tinker --execute="dump(config('sanctum.expiration'));"
rg -n "createToken|tokenAbilitiesFor|RefreshDatabase|assert|abilities|SANCTUM_EXPIRATION" app tests config .env.example docs -g "*.php" -g "*.example" -g "*.md"
```

Note:

- `php artisan route --path=api/v1` is not a valid Artisan command form in this Laravel 12 project. It printed the route namespace help and listed valid route commands such as `route:list`.
- No route-list fallback was run in this phase because the allowed command list was intentionally narrow.

## 10. Verification Summary

- `AuthController.php` lint passed.
- `config/sanctum.php` lint passed.
- Existing API auth tests are unsafe to run now because they use `RefreshDatabase`.
- Token ability issuance is statically verified in both login and refresh-token.
- `tokenAbilitiesFor()` is present, defensive, and returns unique role-derived abilities.
- Runtime `sanctum.expiration` is currently `null` because real `.env` has not set `SANCTUM_EXPIRATION`.
- No ability enforcement was added.

## 11. Remaining Risks

- Static verification does not prove database token rows contain the expected abilities.
- Existing tokens created before Phase 2G remain unscoped until login or refresh-token occurs.
- Existing API tests still mint broad direct tokens and do not assert ability issuance.
- `SANCTUM_EXPIRATION=43200` exists in `.env.example`, but production behavior depends on the real `.env`.

## 12. Recommended Next Step

Phase 2I should add isolated tests or a temporary local-only verification harness that runs against a dedicated test database. After token issuance is proven dynamically, add ability checks in `ApiAccessControl` as a second gate behind the existing role and ownership rules.

## 13. Confirmation

No migrations were run.
No database-changing tests were run.
No users or tokens were created.
No database records were updated.
No database schema was changed.
No real `.env` file was edited.
No `ApiAccessControl` changes were made.
No token ability enforcement was added.
