# Phase 2F - Sanctum Token Hardening Audit

## 1. Files Inspected

- `config/sanctum.php`
- `.env.example`
- `app/Http/Controllers/API/AuthController.php`
- `app/Http/Middleware/ApiAccessControl.php`
- `app/Models/User.php`
- `app/Models/Role.php`
- `routes/api.php`
- `tests/Feature/API/AuthControllerTest.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/UserRoleSeeder.php`
- `docs/project-autopsy/PHASE_2A_API_BASE_REPAIR_AUDIT.md`
- `docs/project-autopsy/PHASE_2D_API_ACCESS_CONTROL_IMPLEMENTATION.md`
- `docs/project-autopsy/PHASE_2E_PARENT_GUARDIAN_API_OWNERSHIP_AUDIT.md`

## 2. Commands Run

Safe read-only and syntax commands only:

```powershell
Get-Content config/sanctum.php
Get-Content .env.example
Get-Content app/Http/Controllers/API/AuthController.php
Get-Content app/Http/Middleware/ApiAccessControl.php
Get-Content app/Models/User.php
Get-Content app/Models/Role.php
Get-Content routes/api.php
Get-ChildItem tests/Feature/API -Recurse -ErrorAction SilentlyContinue
Get-Content tests/Feature/API/AuthControllerTest.php
Get-Content docs/project-autopsy/PHASE_2A_API_BASE_REPAIR_AUDIT.md -TotalCount 220
Get-Content docs/project-autopsy/PHASE_2D_API_ACCESS_CONTROL_IMPLEMENTATION.md -TotalCount 220
Get-Content docs/project-autopsy/PHASE_2E_PARENT_GUARDIAN_API_OWNERSHIP_AUDIT.md -TotalCount 220
Get-Content database/seeders/RoleSeeder.php
Get-Content database/seeders/UserRoleSeeder.php
Select-String -Path docs/project-autopsy/PHASE_2A_API_BASE_REPAIR_AUDIT.md -Pattern "Sanctum|expiration|createToken|tokenCan|abilities" -Context 1,2
php -l config/sanctum.php
php -l app/Http/Controllers/API/AuthController.php
php -l app/Http/Middleware/ApiAccessControl.php
rg -n "createToken|tokenCan|currentAccessToken|abilities|SANCTUM_EXPIRATION|expiration" config app routes tests .env.example docs -g "*.php" -g "*.md" -g "*.example"
rg -n "Role::|roles\(|assignRole|hasRole|hasAnyRole|admin|teacher|student|parent|super" database app tests -g "*.php"
```

No migrations, schema changes, composer setup, database reset, database-changing tests, application-code edits, Sanctum config edits, `.env` edits, or `.env.example` edits were run.

## 3. Current Sanctum Config Findings

`config/sanctum.php` currently contains:

- `stateful`: `explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'))`
- `guard`: `['web']`
- `expiration`: `null`
- `token_prefix`: `env('SANCTUM_TOKEN_PREFIX', '')`
- `middleware.authenticate_session`: `Laravel\Sanctum\Http\Middleware\AuthenticateSession::class`
- `middleware.encrypt_cookies`: `Illuminate\Cookie\Middleware\EncryptCookies::class`
- `middleware.validate_csrf_token`: `Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class`

Current token expiration value:

```php
'expiration' => null,
```

Meaning:

- Personal access tokens do not expire globally.
- Token revocation currently depends on explicit logout, logout-all, or manual token deletion.

`.env.example` findings:

- Does not document `SANCTUM_EXPIRATION`.
- Does not document `SANCTUM_STATEFUL_DOMAINS`.
- Does not document `SANCTUM_TOKEN_PREFIX`.

## 4. Current Token Creation and Revocation Findings

### Login

`AuthController@login()`:

- Validates `email`, `password`, and optional `device_name`.
- Looks up users through `App\Models\User`.
- Verifies password with `Hash::check()`.
- Uses `device_name` when present, otherwise defaults to `mobile-app`.
- Creates the token with:

```php
$token = $user->createToken($deviceName)->plainTextToken;
```

No ability array is passed.

### Logout

`AuthController@logout()`:

```php
$request->user()->currentAccessToken()->delete();
```

This revokes only the current token.

Risk:

- It assumes `currentAccessToken()` is non-null. This is generally true behind `auth:sanctum`, but a defensive null-safe check could be considered later.

### Logout All

`AuthController@logoutAll()`:

```php
$request->user()->tokens()->delete();
```

This revokes all tokens for the authenticated user.

### Refresh Token

`AuthController@refreshToken()`:

- Resolves `$user = $request->user()`.
- Deletes the current token.
- Creates a new token with:

```php
$token = $user->createToken($deviceName)->plainTextToken;
```

No ability array is passed.

Refresh-token behavior:

- Does not preserve existing token abilities.
- Does not recompute token abilities.
- This is currently invisible because issued tokens have no explicit abilities.

### Tests

`tests/Feature/API/AuthControllerTest.php` uses `RefreshDatabase`, so it was inspected but not run.

The tests create broad tokens directly:

```php
$token = $user->createToken('test-device')->plainTextToken;
```

The tests do not assert token abilities.

## 5. Current Token Ability Usage Findings

The search found:

- `createToken()` in `AuthController`.
- `createToken()` in API auth tests.
- `currentAccessToken()->delete()` in logout and refresh-token.
- No `tokenCan()` checks in application code.
- No `currentAccessToken()->can()` checks in application code.
- No route ability middleware usage was found in `routes/api.php`.
- No `SANCTUM_EXPIRATION` usage was found.

Conclusion:

- Token abilities are not currently used for issuance or enforcement.
- API authorization currently depends on `auth:sanctum`, route throttling, and `ApiAccessControl` role/ownership logic.
- Adding abilities during token issuance is mostly backward-compatible for new tokens, but enforcing abilities immediately would not be backward-compatible for old broad tokens.

## 6. Role and RBAC Findings

`User` uses:

- `Laravel\Sanctum\HasApiTokens`
- `roles()` many-to-many through `role_user`
- `hasRole($roleName)`
- `hasAnyRole($roles)`
- `hasAllRoles($roles)`
- `hasPermission($permissionName)`
- `hasPermissionTo($permissionName)`

`RoleSeeder` seeds lowercase role names:

- `admin`
- `teacher`
- `student`
- `parent`

`UserRoleSeeder` assigns role `admin` to user ID 1 if present.

Phase 2D `ApiAccessControl` also recognizes:

- `admin`
- `super-admin`
- `super_admin`

Role names are primarily lowercase in the current seeders.

## 7. Recommended Role-to-Ability Map

Recommended first-pass mobile ability mapping:

| User role | Token ability |
| --- | --- |
| `admin` | `mobile:admin` |
| `super-admin` | `mobile:admin` |
| `super_admin` | `mobile:admin` |
| `teacher` | `mobile:teacher` |
| `student` | `mobile:student` |
| `parent` | `mobile:parent` |
| authenticated user without recognized role | `mobile:user` |

If a user has multiple roles, issue all matching mobile abilities plus `mobile:user`.

Recommended examples:

- Admin user: `['mobile:user', 'mobile:admin']`
- Teacher user: `['mobile:user', 'mobile:teacher']`
- Student user: `['mobile:user', 'mobile:student']`
- Parent user: `['mobile:user', 'mobile:parent']`

Do not issue `*` as the default mobile token ability.

## 8. Optional Narrower Abilities for Later

After broad mobile role abilities are stable, consider narrower abilities:

- `attendance:read`
- `attendance:write`
- `lesson-plans:read`
- `lesson-plans:write`
- `fees:read`
- `results:read`
- `notifications:read`

Recommended later mapping:

- Admin: all supported API abilities.
- Teacher: `attendance:read`, `attendance:write`, `lesson-plans:read`, `lesson-plans:write`, `notifications:read`, plus role-scoped dashboard abilities.
- Student: `attendance:read`, `fees:read`, `results:read`, `notifications:read`.
- Parent: only after ownership repair, `fees:read`, `results:read`, `attendance:read`, `notifications:read` for owned children.

## 9. Recommended Expiration Strategy

Do not hardcode an immediate expiration in a way that could surprise existing users.

Recommended Phase 2G config shape:

```php
'expiration' => env('SANCTUM_EXPIRATION'),
```

or, if a numeric local default is desired:

```php
'expiration' => env('SANCTUM_EXPIRATION', null),
```

Recommended `.env.example` documentation:

```dotenv
# Sanctum personal access token expiration in minutes.
# 43200 = 30 days. Leave empty/null locally only when explicitly needed.
SANCTUM_EXPIRATION=43200
```

Recommended production value for a school ERP mobile app:

- `43200` minutes, equal to 30 days.

Why 30 days:

- Safer than non-expiring tokens.
- Usable for parent/student/teacher mobile sessions.
- Compatible with explicit logout-all revocation.

Stricter alternatives:

- `10080` minutes, equal to 7 days, for high-security deployments.
- `20160` minutes, equal to 14 days, as a middle ground.

## 10. Risks of Changing Expiration Immediately

Sanctum expiration is checked globally for personal access tokens.

If production users already have tokens:

- Setting expiration may cause older tokens to stop authenticating based on their creation time.
- Mobile users may experience forced logout.
- Support/admin teams need communication before rollout.
- Refresh-token flow must issue tokens using the new ability map before ability enforcement is enabled.

Recommended rollout:

1. Add env support first.
2. Keep local/default behavior non-disruptive.
3. Set production value deliberately.
4. Monitor login and refresh-token behavior.
5. Enforce abilities only after newly issued tokens include abilities.

## 11. Token Ability Enforcement Strategy

Ability enforcement does not exist now.

Do not add ability checks in `ApiAccessControl` before token issuance is updated, because old tokens have no explicit role abilities.

Recommended later route-to-ability rules:

| Route group | Future ability |
| --- | --- |
| `api.logout`, `api.logout-all`, `api.me`, profile/password/refresh | `mobile:user` |
| Notification routes | `notifications:read` or `mobile:user` initially |
| Student self routes | `mobile:student` plus ownership |
| Teacher self routes | `mobile:teacher` plus ownership |
| Admin broad routes | `mobile:admin` |
| Fee read routes | `fees:read` plus student/parent ownership |
| Result read routes | `results:read` plus student/parent ownership |
| Attendance read routes | `attendance:read` plus ownership |
| Attendance write routes | `attendance:write` plus teacher/admin assignment |
| Lesson plan read routes | `lesson-plans:read` plus teacher/admin/visibility rules |
| Lesson plan write routes | `lesson-plans:write` plus teacher/admin ownership |

Recommended enforcement order:

1. Issue abilities on new login and refresh-token.
2. Add tests that tokens contain expected abilities.
3. Keep `ApiAccessControl` role/ownership rules as the primary gate.
4. Add ability checks in `ApiAccessControl` as a second gate.
5. Only then consider route-level `abilities` middleware.

## 12. Safe Phase 2G Implementation Plan

Make the next phase small and backward-compatible.

1. Update `AuthController`.
   - Add private method:
     ```php
     private function tokenAbilitiesFor(User $user): array
     ```
   - Use `$user->hasAnyRole()` or `$user->hasRole()`.
   - Return at least `mobile:user`.
   - Add role abilities based on current roles:
     - `mobile:admin`
     - `mobile:teacher`
     - `mobile:student`
     - `mobile:parent`

2. Update login token creation.
   - Change:
     ```php
     $user->createToken($deviceName)
     ```
   - To:
     ```php
     $user->createToken($deviceName, $this->tokenAbilitiesFor($user))
     ```

3. Update refresh-token creation.
   - Recompute abilities from the current user's roles.
   - Do not preserve stale abilities from the deleted token.

4. Update `.env.example`.
   - Add `SANCTUM_EXPIRATION=43200` with a comment explaining 30 days.
   - Do not edit real `.env`.

5. Update `config/sanctum.php` only if still hardcoded.
   - Prefer:
     ```php
     'expiration' => env('SANCTUM_EXPIRATION'),
     ```
   - Or:
     ```php
     'expiration' => env('SANCTUM_EXPIRATION', null),
     ```

6. Do not enforce token abilities yet.
   - Keep `ApiAccessControl` unchanged in Phase 2G except documenting that enforcement remains role/ownership-based.

7. Verification for Phase 2G.
   - Run PHP lint on changed files.
   - Do not run `tests/Feature/API/AuthControllerTest.php` unless database reset/migration behavior is explicitly approved, because it uses `RefreshDatabase`.
   - Add or inspect tests only in a non-database-changing way unless the test environment is isolated.

## 13. Confirmation

No application code was modified.
No Sanctum config was modified.
No `.env` or `.env.example` file was modified.
No migrations were run.
No migration files were changed.
No database schema was changed.
No database-changing tests were run.
