# Phase 2B - API Base Activation

## 1. Files Inspected

- `bootstrap/app.php`
- `routes/api.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Http/Controllers/API/AuthController.php`
- `app/Http/Controllers/API/DashboardController.php`
- `app/Http/Controllers/API/LessonPlanController.php`
- `app/Http/Middleware/ApiAccessControl.php`
- `docs/project-autopsy/PHASE_2A_API_BASE_REPAIR_AUDIT.md`

## 2. Files Changed

- `bootstrap/app.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `docs/project-autopsy/PHASE_2B_API_BASE_ACTIVATION.md`

## 3. BaseApiController Wrapper Methods Added

Added backward-compatible wrappers only. Existing `success()`, `error()`, and `validationError()` methods were not changed.

```php
/**
 * Backward-compatible success response wrapper.
 */
public function sendResponse($result, $message = 'Success', $code = 200): JsonResponse
{
    return $this->success($result, $message, $code);
}
```

```php
/**
 * Backward-compatible error response wrapper.
 */
public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
{
    return $this->error($error, $code, $errorMessages);
}
```

This fixes the immediate response-contract mismatch for API controllers that call `sendResponse()` and `sendError()`, including:

- `API\AuthController`
- `API\DashboardController`
- `API\LessonPlanController`

## 4. bootstrap/app.php API Registration Change

Added Laravel 12 API route registration:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Existing `web`, `commands`, and `health` routing stayed in place.

## 5. Extra Prefix Confirmation

No `apiPrefix: 'api/v1'` was added.

`routes/api.php` still contains its existing internal `Route::prefix('v1')` wrapper.

Effective route shape is:

- Laravel default API prefix: `api`
- Internal `routes/api.php` prefix: `v1`
- Final URI prefix: `/api/v1`

No `/api/v1/v1` prefix was introduced.

## 6. Route-List Result for `/api/v1`

`php artisan route:list --path=api/v1` now shows 73 routes.

Visible groups include:

- Auth/profile routes
- Student dashboard, parent dashboard, teacher dashboard
- Lesson plans
- Students
- Teachers
- Attendance
- Exam papers
- Bell timing
- Notifications
- Guardians

## 7. Login, Logout, and Me Route Visibility

Confirmed visible:

- `POST api/v1/login` named `api.login`
- `POST api/v1/logout` named `api.logout`
- `POST api/v1/logout-all` named `api.logout-all`
- `GET api/v1/me` named `api.me`
- `POST api/v1/register` named `api.register`
- `POST api/v1/refresh-token` named `api.refresh-token`

## 8. Route-List Errors Found

No route-list command failed after activation.

All required route-list commands completed:

- `php artisan route:list --path=v1`
- `php artisan route:list --path=api/v1`
- `php artisan route:list --path=api`

Important observation:

- `php artisan route:list --path=api` now shows 88 routes.
- That includes 73 intended `/api/v1/*` routes from `routes/api.php`.
- It also includes the existing `/api/biometric/*`, `/api/self-service/*`, and `/api/webhooks/*` routes still defined in `routes/web.php`.
- Those web-defined API-like routes were intentionally not moved in this phase.

## 9. ApiAccessControl Status

`ApiAccessControl` was not changed.

Known remaining risk:

- `authorizeRequest()` still returns `true`.
- API routes now have baseline activation, but role/ownership enforcement is still not implemented centrally.

## 10. Sanctum Status

`config/sanctum.php` was not changed.

Known remaining risks:

- Token expiration remains `null`.
- `AuthController` still creates tokens without abilities/scopes.
- No token ability checks were added in this phase.

## 11. Commands Run

Safe commands only:

```powershell
Get-Content bootstrap\app.php
Get-Content app\Http\Controllers\API\BaseApiController.php
Get-Content routes\api.php
Get-Content docs\project-autopsy\PHASE_2A_API_BASE_REPAIR_AUDIT.md
git status --short -- bootstrap\app.php routes\api.php app\Http\Controllers\API\BaseApiController.php app\Http\Controllers\API\AuthController.php app\Http\Controllers\API\DashboardController.php app\Http\Controllers\API\LessonPlanController.php app\Http\Middleware\ApiAccessControl.php config\sanctum.php
php -l bootstrap\app.php
php -l routes\api.php
php -l app\Http\Controllers\API\BaseApiController.php
php artisan route:list --path=v1
php artisan route:list --path=api/v1
php artisan route:list --path=api
```

No migrations, schema changes, composer setup, database reset, or database-changing tests were run.

## 12. Verification Summary

- `bootstrap/app.php` passed PHP lint.
- `routes/api.php` passed PHP lint.
- `BaseApiController.php` passed PHP lint.
- `/api/v1/*` routes are now visible.
- `api.login`, `api.logout`, `api.logout-all`, `api.me`, and `api.refresh-token` are visible.
- Response-contract wrappers are now present in `BaseApiController`.
- No API controller broad refactor was performed.

## 13. Remaining Risks

- `ApiAccessControl` remains permissive and must not be considered real authorization.
- Sanctum tokens remain long-lived and unscoped.
- `Route::apiResource(...)` route names for several resources are not explicitly prefixed with `api.`; for example, route-list output shows names such as `students.index` and `attendance.index`. This should be normalized only after helper/client usage is audited.
- Existing web-defined `/api/*` routes still share the API URI namespace outside `/api/v1`.
- No database-changing feature tests were run because the current API test suite uses `RefreshDatabase`.

## 14. Recommended Next Step

Phase 2C should harden API authorization with a minimal deny-by-default `ApiAccessControl` strategy:

- Explicitly allow public route names.
- Require Sanctum auth for protected routes.
- Allow admins broad access.
- Restrict students, parents, and teachers to their own domain records.
- Keep token-expiration and token-ability changes for a dedicated Sanctum phase after authorization behavior is mapped.
