# Phase 2L - Sanctum Ability Enforcement Plan

## 1. Files Inspected

- `app/Http/Middleware/ApiAccessControl.php`
- `app/Http/Controllers/API/AuthController.php`
- `tests/Feature/API/SanctumTokenAbilityTest.php`
- `routes/api.php`
- `app/Models/User.php`
- `app/Models/Student.php`
- `app/Models/Teacher.php`
- `docs/project-autopsy/PHASE_2D_API_ACCESS_CONTROL_IMPLEMENTATION.md`
- `docs/project-autopsy/PHASE_2G_SANCTUM_TOKEN_ABILITIES_IMPLEMENTATION.md`
- `docs/project-autopsy/PHASE_2K_ISOLATED_SANCTUM_TOKEN_ABILITY_TESTS.md`

## 2. Commands Run

Safe read-only commands only:

```powershell
php -l app/Http/Middleware/ApiAccessControl.php
php -l app/Http/Controllers/API/AuthController.php
php -l tests/Feature/API/SanctumTokenAbilityTest.php
rg -n "tokenCan|currentAccessToken|mobile|ApiAccessControl|authorizeRequest" app routes tests docs -g "*.php" -g "*.md"
Get-Content app/Http/Middleware/ApiAccessControl.php
Get-Content routes/api.php
Get-Content tests/Feature/API/SanctumTokenAbilityTest.php
Get-Content docs/project-autopsy/PHASE_2D_API_ACCESS_CONTROL_IMPLEMENTATION.md -TotalCount 260
Get-Content docs/project-autopsy/PHASE_2G_SANCTUM_TOKEN_ABILITIES_IMPLEMENTATION.md -TotalCount 220
Get-Content docs/project-autopsy/PHASE_2K_ISOLATED_SANCTUM_TOKEN_ABILITY_TESTS.md -TotalCount 230
```

No migrations, schema changes, tests, composer setup, application-code edits, or database-changing commands were run.

## 3. Current `ApiAccessControl` Flow

`handle()` currently performs:

1. Rate limiting.
2. Immediate return of a 429 JSON response if `rateLimit()` returns a response.
3. API access logging.
4. `authorizeRequest()` decision.
5. JSON 403 on authorization failure.
6. `$next($request)` on success.

`authorizeRequest()` currently flows as:

1. Resolve route name.
2. Public temporary blocklist.
   - `api.exam-papers.available-for-class`
   - `api.exam-papers.search`
   - `api.bell-timing.today`
3. Public allowlist.
   - `api.login`
   - `api.register`
4. Authenticated user check with `$request->user()`.
5. Admin broad allow.
   - `admin`
   - `super-admin`
   - `super_admin`
6. Auth-self routes.
   - `api.logout`
   - `api.logout-all`
   - `api.me`
   - `api.update-profile`
   - `api.change-password`
   - `api.refresh-token`
7. Notification routes.
   - `api.notifications.index`
   - `api.notifications.mark-as-read`
   - `api.notifications.mark-all-read`
   - `api.notifications.unread-count`
8. High-risk blocklist.
9. Parent blocked routes.
10. Student self routes with student role and ownership.
11. Teacher self routes with teacher role and ownership.
12. Deny by default.

Current important property:

- Role/ownership/blocklist logic is the primary authorization gate.
- Token abilities are issued but not yet enforced.

## 4. Token Ability Gate Design

Minimal helper design for Phase 2M:

```php
private function tokenAllows(User $user, array|string $abilities): bool
{
    $token = $user->currentAccessToken();

    if (!$token || !method_exists($token, 'can')) {
        return false;
    }

    foreach ((array) $abilities as $ability) {
        if ($token->can($ability)) {
            return true;
        }
    }

    return false;
}
```

Behavior:

- Public routes must not call this helper.
- Protected routes must fail if there is no current access token.
- Multiple abilities should be treated as "any of these abilities".
- Use Sanctum's `can()` method on the current token.
- Do not use route-level ability middleware yet.

Recommended broad ability constants for Phase 2M:

- `mobile:user`
- `mobile:admin`
- `mobile:teacher`
- `mobile:student`
- `mobile:parent`

## 5. Old Token Handling

Old tokens issued before Phase 2G may have no explicit abilities.

Recommended policy:

- For development now, deny ability-protected routes when the token lacks required abilities.
- Keep `api.login` public.
- Prefer allowing `api.logout`, `api.logout-all`, and possibly `api.refresh-token` through a short transitional path only if the user is authenticated and the existing role/ownership rule allows it.

Reason:

- If `api.refresh-token` immediately requires `mobile:user`, old unscoped tokens cannot refresh into scoped tokens.
- If all auth-self routes are immediately ability-gated, old-token users may only recover by logging in again.

Safest development rollout:

1. Enforce abilities on student self, teacher self, notification, and admin non-auth-self routes first.
2. Keep auth-self routes temporarily authenticated-only, or only exempt `api.refresh-token` and `api.logout`.
3. Communicate or document that old tokens must refresh or re-login.
4. After a grace period, require `mobile:user` on auth-self routes too.

If this app is not yet in external use:

- Enforce `mobile:user` on all protected allowed routes immediately in Phase 2M.
- Old tokens can be invalidated by requiring users to log in again.

## 6. Route-to-Ability Map

### Public

| Routes | Ability |
| --- | --- |
| `api.login`, `api.register` | None |

### Public Temporary Blocklist

| Routes | Ability |
| --- | --- |
| `api.exam-papers.available-for-class`, `api.exam-papers.search`, `api.bell-timing.today` | Blocked before auth/ability |

### Auth Self

| Routes | Initial ability |
| --- | --- |
| `api.logout` | `mobile:user` later; optional temporary authenticated-only |
| `api.logout-all` | `mobile:user` later; optional temporary authenticated-only |
| `api.me` | `mobile:user` |
| `api.update-profile` | `mobile:user` |
| `api.change-password` | `mobile:user` |
| `api.refresh-token` | `mobile:user` later; optional temporary authenticated-only for old-token recovery |

### Notifications

| Routes | Initial ability | Later ability |
| --- | --- | --- |
| `api.notifications.index` | `mobile:user` | `notifications:read` |
| `api.notifications.mark-as-read` | `mobile:user` | `notifications:read` |
| `api.notifications.mark-all-read` | `mobile:user` | `notifications:read` |
| `api.notifications.unread-count` | `mobile:user` | `notifications:read` |

### Student Self

| Routes | Initial ability | Existing role/ownership rule remains |
| --- | --- | --- |
| `api.dashboard.student` | `mobile:student` | Student has `students.user_id = user.id` |
| `students.show` | `mobile:student` | Route student belongs to user |
| `api.students.attendance` | `mobile:student` | Route student belongs to user |
| `api.students.results` | `mobile:student` | Route student belongs to user |
| `api.students.fees` | `mobile:student` | Route student belongs to user |
| `api.attendance.student-monthly` | `mobile:student` | Route student belongs to user |

### Teacher Self

| Routes | Initial ability | Existing role/ownership rule remains |
| --- | --- | --- |
| `api.dashboard.teacher` | `mobile:teacher` | Teacher has `teachers.user_id = user.id` |
| `teachers.show` | `mobile:teacher` | Route teacher belongs to user |
| `api.teachers.classes` | `mobile:teacher` | Route teacher belongs to user |
| `api.teachers.papers` | `mobile:teacher` | Route teacher belongs to user |
| `api.teachers.subject-classes` | `mobile:teacher` | Route teacher belongs to user |
| `api.teachers.attendance-data` | `mobile:teacher` | Route teacher belongs to user |
| `api.teachers.grading-data` | `mobile:teacher` | Route teacher belongs to user |
| `api.lesson-plans.my` | `mobile:teacher` | Teacher has `teachers.user_id = user.id` |

### Admin

| Routes | Initial ability | Existing role rule remains |
| --- | --- | --- |
| Admin broad allow | `mobile:admin` | User has admin-like role |

### Parent

Parent routes remain blocked for non-admin users.

No `mobile:parent` access should be opened until parent/guardian ownership is repaired.

## 7. Backward Compatibility Risk

Existing old tokens may be unscoped because they were issued before Phase 2G.

Risk if Phase 2M enforces abilities immediately:

- Old tokens may receive 403 on protected routes even when the user role and ownership are valid.
- Old-token users may need to log in again.
- If `api.refresh-token` is ability-gated immediately, old tokens cannot self-upgrade into scoped tokens.

Recommended handling:

- Development/non-production: strict enforcement is acceptable if users can re-login.
- Production/external users: allow a short transition path for `api.refresh-token` or communicate forced re-login.

Recommended explicit old-token policy for Phase 2M:

- Add enforcement for student self, teacher self, notification, and admin branches.
- Keep `api.login` and `api.register` unchanged.
- Temporarily allow `api.refresh-token` for any authenticated user whose role/ownership check passes, then the new token will receive abilities.
- Consider keeping `api.logout` and `api.logout-all` authenticated-only so old tokens can be revoked cleanly.
- Require `mobile:user` for `api.me`, `api.update-profile`, and `api.change-password`.

## 8. Targeted Isolated Enforcement Test Plan

Suggested file:

- `tests/Feature/API/ApiAccessControlAbilityTest.php`

Avoid full migrations, matching the Phase 2K pattern.

Minimal schema:

- `users`
- `roles`
- `role_user`
- `students`
- `teachers`
- `personal_access_tokens`

Suggested tests:

- Token without `mobile:user` cannot access `/api/v1/me`.
- Token with `mobile:user` can access `/api/v1/me`.
- Student token with `mobile:student` can access own student dashboard/record.
- Student token without `mobile:student` cannot access student self routes.
- Teacher token with `mobile:teacher` can access own teacher dashboard/record.
- Teacher token without `mobile:teacher` cannot access teacher self routes.
- Student token cannot access teacher self route.
- Teacher token cannot access student self route.
- Admin token with `mobile:admin` and admin role bypasses allowed admin paths.
- Old unscoped token behavior is explicitly tested according to the chosen rollout policy.

The test should:

- Create only minimal tables in test setup.
- Create users/roles/students/teachers directly.
- Create tokens with explicit abilities using `createToken('test', [...])`.
- Avoid `RefreshDatabase`.
- Avoid full migrations.
- Avoid seeders.

## 9. Safe Phase 2M Implementation Plan

1. Inspect current `ApiAccessControl` again.
2. Add `tokenAllows(User $user, array|string $abilities): bool`.
3. Add ability checks only to branches that are already allowed:
   - Admin broad allow requires `mobile:admin`.
   - Auth-self routes require `mobile:user`, except optional temporary `refresh-token`/logout grace.
   - Notification routes require `mobile:user`.
   - Student self routes require `mobile:student` plus existing role and ownership checks.
   - Teacher self routes require `mobile:teacher` plus existing role and ownership checks.
4. Do not open parent routes.
5. Do not remove high-risk blocklists.
6. Do not change route definitions.
7. Do not add route-level ability middleware.
8. Add isolated `ApiAccessControlAbilityTest` with a minimal schema.
9. Run only targeted tests:
   - `php artisan test --filter=SanctumTokenAbilityTest --env=testing`
   - `php artisan test --filter=ApiAccessControlAbilityTest --env=testing`
10. Do not run the full test suite.

## 10. Confirmation

No application code was modified.
No middleware was changed.
No controller was changed.
No Sanctum config was changed.
No routes were changed.
No migrations were run.
No database-changing tests were run.
No database records were created, updated, or deleted.
