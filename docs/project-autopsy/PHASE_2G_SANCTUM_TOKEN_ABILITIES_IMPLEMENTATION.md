# Phase 2G - Sanctum Token Abilities Implementation

## 1. Files Inspected

- `app/Http/Controllers/API/AuthController.php`
- `config/sanctum.php`
- `.env.example`
- `app/Models/User.php`
- `app/Models/Role.php`
- `docs/project-autopsy/PHASE_2F_SANCTUM_TOKEN_HARDENING_AUDIT.md`

## 2. Files Changed

- `app/Http/Controllers/API/AuthController.php`
- `config/sanctum.php`
- `.env.example`
- `docs/project-autopsy/PHASE_2G_SANCTUM_TOKEN_ABILITIES_IMPLEMENTATION.md`

No API routes, middleware authorization logic, parent/guardian ownership logic, migrations, database schema, real `.env`, or token ability enforcement were changed.

Note: `.env.example` already had unrelated working-tree changes before this phase. Phase 2G only added the Sanctum documentation and variables listed below.

## 3. Token Ability Helper Summary

Added this private helper to `AuthController`:

```php
private function tokenAbilitiesFor(User $user): array
```

Behavior:

- Always includes `mobile:user`.
- Adds `mobile:admin` when the user has any admin-like role:
  - `admin`
  - `super-admin`
  - `super_admin`
- Adds `mobile:teacher` when the user has `teacher`.
- Adds `mobile:student` when the user has `student`.
- Adds `mobile:parent` when the user has `parent`.
- Returns a unique ability array.

Also added:

```php
private function userHasAnyRole(User $user, array $roles): bool
```

This method defensively uses `hasAnyRole()` when available, falls back to `hasRole()`, and returns `false` if neither helper exists.

## 4. Login Token Creation Change

Old login token creation:

```php
$token = $user->createToken($deviceName)->plainTextToken;
```

New login token creation:

```php
$token = $user->createToken($deviceName, $this->tokenAbilitiesFor($user))->plainTextToken;
```

New login tokens now receive role-derived mobile abilities.

## 5. Refresh-Token Creation Change

Old refresh-token creation:

```php
$token = $user->createToken($deviceName)->plainTextToken;
```

New refresh-token creation:

```php
$token = $user->createToken($deviceName, $this->tokenAbilitiesFor($user))->plainTextToken;
```

The refresh-token flow deletes the current token and recomputes abilities from the current user's roles. It does not preserve stale abilities from the old token.

## 6. Sanctum Expiration Config Change

Old config:

```php
'expiration' => null,
```

New config:

```php
'expiration' => env('SANCTUM_EXPIRATION', null),
```

This is the safer Laravel-compatible option because it keeps the current no-expiration behavior unless `SANCTUM_EXPIRATION` is explicitly set.

The real `.env` file was not edited.

## 7. `.env.example` Changes

Added non-secret Sanctum guidance:

```dotenv
# Sanctum personal access token expiration in minutes.
# 43200 = 30 days. Leave empty locally only when explicitly needed.
SANCTUM_EXPIRATION=43200
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1
SANCTUM_TOKEN_PREFIX=
```

This documents the recommended production value of 30 days while leaving the actual runtime behavior controlled by the real `.env`.

## 8. Ability Enforcement Not Added

No ability enforcement was added in this phase.

Confirmed unchanged:

- No `tokenCan()` checks were added.
- No `currentAccessToken()->can()` checks were added.
- No `ApiAccessControl` ability checks were added.
- No route `abilities` middleware was added.
- No API route definitions were changed.

This preserves the Phase 2F rollout plan: issue scoped tokens first, then enforce abilities only after token issuance is verified.

## 9. Commands Run

Safe commands only:

```powershell
Get-Content app/Http/Controllers/API/AuthController.php
Get-Content config/sanctum.php
Get-Content .env.example
Get-Content app/Models/User.php
Get-Content app/Models/Role.php
Get-Content docs/project-autopsy/PHASE_2F_SANCTUM_TOKEN_HARDENING_AUDIT.md -TotalCount 260
php -l app/Http/Controllers/API/AuthController.php
php -l config/sanctum.php
php artisan route --path=api/v1 | Select-String login
php artisan route:list --path=api/v1 | Select-String login
rg -n "createToken|tokenAbilitiesFor|SANCTUM_EXPIRATION|tokenCan|currentAccessToken\(\)->can" app config .env.example routes tests -g "*.php" -g "*.example"
git diff -- app/Http/Controllers/API/AuthController.php config/sanctum.php .env.example
Select-String -Path app/Http/Middleware/ApiAccessControl.php,routes/api.php -Pattern "tokenCan|currentAccessToken\(\)->can|abilities:"
Select-String -Path app/Http/Controllers/API/AuthController.php -Pattern "createToken|tokenAbilitiesFor|userHasAnyRole" -Context 2,2
Select-String -Path .env.example -Pattern "SANCTUM" -Context 2,1
Select-String -Path config/sanctum.php -Pattern "expiration" -Context 3,2
```

Note:

- `php artisan route --path=api/v1` is not a valid Artisan command form in this Laravel 12 project and printed the route namespace help.
- The valid equivalent `php artisan route:list --path=api/v1 | Select-String login` was run and confirmed `POST api/v1/login` is registered.

## 10. Verification Summary

- `php -l app/Http/Controllers/API/AuthController.php` passed.
- `php -l config/sanctum.php` passed.
- Login route remains registered as `POST api/v1/login`.
- `AuthController@login()` now issues role-derived abilities.
- `AuthController@refreshToken()` now issues recomputed role-derived abilities.
- `config/sanctum.php` now reads expiration from `SANCTUM_EXPIRATION` with a `null` fallback.
- `.env.example` now documents `SANCTUM_EXPIRATION`, `SANCTUM_STATEFUL_DOMAINS`, and `SANCTUM_TOKEN_PREFIX`.
- No token ability enforcement was added.

## 11. Remaining Risks

- Existing tokens issued before Phase 2G remain unscoped until users log in again or refresh their tokens.
- Token abilities are now issued but not enforced yet.
- API tests still create broad tokens directly with `createToken('test-device')`.
- `SANCTUM_EXPIRATION=43200` is documented in `.env.example`, but production behavior depends on the real `.env`.
- Parent/guardian ownership remains blocked and unchanged.

## 12. Recommended Next Step

Phase 2H should verify token issuance in a safe, non-database-destructive way:

- Add or inspect isolated tests that confirm login/refresh tokens receive expected abilities.
- Avoid running existing `RefreshDatabase` tests unless an isolated test database is explicitly approved.
- After issuance is proven, add ability checks in `ApiAccessControl` as a second gate behind the existing role and ownership rules.

## 13. Confirmation

No migrations were run.
No migration files were changed.
No database schema was changed.
No database-changing tests were run.
No real `.env` file was edited.
No token ability enforcement was added.
