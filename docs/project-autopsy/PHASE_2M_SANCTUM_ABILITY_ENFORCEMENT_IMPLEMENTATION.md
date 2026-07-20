# Phase 2M - Sanctum Ability Enforcement Implementation

## 1. Files Inspected

- `app/Http/Middleware/ApiAccessControl.php`
- `tests/Feature/API/SanctumTokenAbilityTest.php`
- `routes/api.php`
- `app/Models/User.php`
- `app/Models/Student.php`
- `app/Models/Teacher.php`
- `app/Http/Controllers/API/DashboardController.php`
- `app/Http/Controllers/API/TeacherController.php`
- `docs/project-autopsy/PHASE_2L_SANCTUM_ABILITY_ENFORCEMENT_PLAN.md`
- `docs/project-autopsy/PHASE_2K_ISOLATED_SANCTUM_TOKEN_ABILITY_TESTS.md`

## 2. Files Changed

- `app/Http/Middleware/ApiAccessControl.php`
- `tests/Feature/API/ApiAccessControlAbilityTest.php`
- `docs/project-autopsy/PHASE_2M_SANCTUM_ABILITY_ENFORCEMENT_IMPLEMENTATION.md`

No migrations, schema files, API routes, Sanctum config, or `AuthController` code were changed.

## 3. tokenAllows() Implementation Summary

Added a private helper in `ApiAccessControl`:

```php
private function tokenAllows(User $user, array|string $abilities): bool
```

Behavior:

- Reads the current Sanctum token with `$user->currentAccessToken()`.
- Returns `false` if no token exists.
- Returns `false` if the token object does not support `can()`.
- Accepts either one ability string or an array of ability strings.
- Returns `true` if the token can at least one requested ability.
- Returns `false` by default.

## 4. Ability Enforcement Branches Added

Ability checks were added only to routes already allowed by Phase 2D role/ownership logic:

| Branch | Existing rule preserved | Ability now required |
| --- | --- | --- |
| Public login/register | Explicit public allowlist | None |
| Public temporary blocklist | Still blocked | Not applicable |
| Admin broad allow | Admin-like role required | `mobile:admin` |
| Auth self routes | Authenticated user required | `mobile:user` |
| Notification routes | Authenticated user required | `mobile:user` |
| Student self routes | Student role + own student record | `mobile:student` |
| Teacher self routes | Teacher role + own teacher record | `mobile:teacher` |
| Parent child routes | Still blocked for non-admin | Not applicable |
| High-risk routes | Still blocked for non-admin | Not applicable |
| Default | Deny | Not applicable |

## 5. Old-Token Recovery Behavior

Old unscoped tokens issued before Phase 2G may not have `mobile:*` abilities.

To preserve the recovery path, these authenticated routes remain allowed without ability checks:

- `api.logout`
- `api.logout-all`
- `api.refresh-token`

This allows old-token users to revoke or refresh into newly scoped tokens without opening broader API access.

## 6. Parent Routes Blocked Confirmation

Parent/guardian child routes remain blocked for non-admin users:

- `api.dashboard.parent`
- `guardians.index`
- `guardians.store`
- `guardians.show`
- `guardians.update`
- `guardians.destroy`
- `api.guardians.children`
- `api.guardians.notifications`

No parent ownership logic was added and no parent routes were opened.

## 7. Test File Created

Created:

- `tests/Feature/API/ApiAccessControlAbilityTest.php`

The test file:

- Does not use `RefreshDatabase`.
- Does not run full project migrations.
- Builds a minimal SQLite in-memory schema inside the test lifecycle.
- Drops the minimal schema after each test.
- Does not call `DatabaseSeeder`.
- Does not touch real/local MySQL data.

Minimal tables created:

- `users`
- `roles`
- `role_user`
- `students`
- `teachers`
- `audit_logs`
- `attendances`
- `fees`
- `exams`
- `results`
- `lesson_plans`
- `notifications`
- `class_management`
- `class_teacher`
- `exam_papers`
- `personal_access_tokens`

The extra support tables exist only to satisfy the controllers reached after middleware authorization succeeds.

## 8. Tests Added

Added 10 targeted enforcement tests:

- `token_without_mobile_user_cannot_access_me`
- `token_with_mobile_user_can_access_me`
- `student_token_with_mobile_student_can_access_own_student_dashboard`
- `student_token_without_mobile_student_cannot_access_student_dashboard`
- `teacher_token_with_mobile_teacher_can_access_own_teacher_route`
- `teacher_token_without_mobile_teacher_cannot_access_teacher_route`
- `admin_role_without_mobile_admin_cannot_bypass`
- `admin_role_with_mobile_admin_can_reach_admin_allowed_route`
- `old_unscoped_token_can_call_refresh_token_for_transition`
- `old_unscoped_token_can_call_logout_for_transition`

## 9. Commands Run

```powershell
php -l app/Http/Middleware/ApiAccessControl.php
php -l tests/Feature/API/ApiAccessControlAbilityTest.php
php artisan tinker --env=testing --execute="dump(app()->environment()); dump(config('database.default')); dump(config('database.connections.sqlite.database'));"
php artisan test --filter=SanctumTokenAbilityTest --env=testing
php artisan test --filter=ApiAccessControlAbilityTest --env=testing
php artisan route:list --path=api/v1/teachers | Select-String "teachers"
rg -n "tokenAllows|mobile:user|mobile:student|mobile:teacher|mobile:admin|currentAccessToken|tokenCan|RefreshDatabase" app tests docs -g "*.php" -g "*.md"
git diff -- app/Http/Middleware/ApiAccessControl.php tests/Feature/API/ApiAccessControlAbilityTest.php
git status --short
```

Note: An attempted shorthand command, `php artisan route --path=api/v1/teachers --env=testing`, returned Laravel command usage because this app exposes `route:list`, not a `route` alias. It did not run migrations or modify data.

## 10. Test Result Summary

Environment verification:

- `app()->environment()` returned `testing`.
- `config('database.default')` returned `sqlite`.
- `config('database.connections.sqlite.database')` returned `:memory:`.

Targeted tests:

- `php artisan test --filter=SanctumTokenAbilityTest --env=testing`
  - Passed: 6 tests, 19 assertions.
- `php artisan test --filter=ApiAccessControlAbilityTest --env=testing`
  - Passed: 10 tests, 10 assertions.

PHP lint:

- `ApiAccessControl.php`: passed.
- `ApiAccessControlAbilityTest.php`: passed.

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated tests during discovery. Those warnings were not introduced in Phase 2M.

## 11. Failures and Fixes

Initial failures in the new isolated enforcement test were test-harness issues, not application migration changes:

- `audit_logs` table was missing because `Student`/`Teacher` auditing fired during model creation.
  - Fixed by adding a minimal `audit_logs` table to the isolated test schema.
- `lesson_plans.deleted_at` was missing after the student dashboard reached `LessonPlan` queries.
  - Fixed by adding `softDeletes()` to the isolated `lesson_plans` schema.
- `App\Models\Notification` does not exist, but `DashboardController` imports it.
  - Fixed in the isolated test runtime with a `class_alias()` to Laravel's `DatabaseNotification`.
  - Application controller code was not changed.
- `Teacher::$fillable` does not include `user_id`, so test helper-created teachers were not linked to the user.
  - Fixed in the isolated test helper with `forceFill()`.
  - Application model code was not changed.

## 12. Full Suite / Database Safety Confirmation

- Full test suite was not run.
- Project migrations were not run manually.
- `migrate`, `migrate:fresh`, `db:wipe`, and composer setup were not run.
- Real `.env` was not edited.
- Real/local MySQL data was not touched.
- Tests were limited to targeted filters under `--env=testing` with SQLite `:memory:`.

## 13. Remaining Risks

- Old unscoped tokens can still call logout/logout-all/refresh-token by design during the transition window.
- Parent/guardian API access remains blocked until reliable User -> Parent/Guardian -> Student ownership is repaired.
- `DashboardController` references `App\Models\Notification`, which appears missing. The test worked around this only in isolated runtime; the controller should be repaired in a later API stability phase.
- Existing unrelated tests still use `RefreshDatabase` and should not be run broadly until full migration safety is resolved.
- PHPUnit doc-comment metadata warnings remain in unrelated test files.

## 14. Recommended Next Step

Phase 2N should be a read-only API controller stability audit for routes that are now reachable after middleware authorization, starting with:

- `DashboardController` missing `App\Models\Notification`.
- Teacher dashboard relationship assumptions such as `classAssignments()`.
- Student/teacher route controller schema assumptions under the canonical current database.

Do not remove the old-token recovery path until token rotation has been completed or a forced re-login decision is made.
