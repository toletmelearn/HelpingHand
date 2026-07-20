# Phase 2A - API Base Repair Audit

## 1. Files Inspected

- `bootstrap/app.php`
- `bootstrap/providers.php`
- `app/Providers/RouteServiceProvider.php`
- `routes/api.php`
- `routes/web.php`
- `app/Http/Controllers/API/AuthController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Http/Controllers/API/*.php`
- `app/Http/Middleware/ApiAccessControl.php`
- `config/auth.php`
- `config/sanctum.php`
- `app/Models/User.php`
- `tests/Feature/API/AuthControllerTest.php`
- `docs/project-autopsy/00_FINAL_BLIND_SPOT_DISCOVERY.md`
- Local Laravel framework routing builder signature in `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/ApplicationBuilder.php`

## 2. Commands Run

Safe commands only:

```powershell
Get-Content bootstrap\app.php
Get-Content routes\api.php
Get-ChildItem app\Http\Controllers\API -File
Get-Content app\Http\Controllers\API\BaseApiController.php
Get-Content app\Http\Controllers\API\AuthController.php
rg -n "sendResponse\(|sendError\(|success\(|error\(|validationError\(" app\Http\Controllers\API -g "*.php"
Get-Content app\Http\Middleware\ApiAccessControl.php
Get-Content config\auth.php
Get-Content config\sanctum.php
Get-Content app\Models\User.php
Get-Content app\Providers\RouteServiceProvider.php
Get-Content bootstrap\providers.php
Select-String -Path vendor\laravel\framework\src\Illuminate\Foundation\Configuration\ApplicationBuilder.php -Pattern "function withRouting" -Context 0,35
Get-Content tests\Feature\API\AuthControllerTest.php
rg -n "Route::prefix\('api'\)|prefix\('api'\)|api/|v1|biometric|self-service|webhook|exam-papers/available|bell-timing/today|notifications/mark-all-read" routes\web.php routes app resources tests docs -g "*.php" -g "*.blade.php" -g "*.js" -g "*.md"
php artisan route:list --path=api
php artisan route:list --path=v1
php artisan route:list --path=api/v1
php -l routes\api.php
php -l app\Http\Controllers\API\AuthController.php
php -l app\Http\Controllers\API\BaseApiController.php
php -l app\Http\Middleware\ApiAccessControl.php
```

No migrations, schema changes, composer setup, database reset, database-changing tests, or application-code edits were run.

## 3. Current API Registration Status

`routes/api.php` is not registered by the active Laravel 12 bootstrap configuration.

Current `bootstrap/app.php` routing configuration:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

There is no `api:` argument in `withRouting(...)`.

`app/Providers/RouteServiceProvider.php` contains a legacy-style API route registration, but `bootstrap/providers.php` does not register `App\Providers\RouteServiceProvider::class`. The active bootstrap path is the `Application::configure(...)->withRouting(...)` setup.

Baseline route visibility:

- `php artisan route:list --path=v1` returned no matching routes.
- `php artisan route:list --path=api/v1` returned no matching routes.
- `php artisan route:list --path=api` returned 15 `/api/*` routes, but those come from `routes/web.php`, not `routes/api.php`.

Conclusion: the intended `/api/v1/*` mobile/API surface is currently dormant.

## 4. Correct Route Registration Recommendation

The installed Laravel 12 `ApplicationBuilder::withRouting()` signature supports:

```php
api: ...
apiPrefix: 'api'
```

`routes/api.php` already contains:

```php
Route::prefix('v1')->group(function () {
    ...
});
```

Recommended Phase 2B registration:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Because Laravel's default API prefix is `api` and `routes/api.php` already prefixes `v1`, this should produce `/api/v1/*`.

Do not add `apiPrefix: 'api/v1'` unless the `Route::prefix('v1')` wrapper is removed first, otherwise the app risks `/api/v1/v1/*`.

## 5. Whether `routes/api.php` Already Prefixes `v1`

Yes.

All intended API routes are wrapped inside:

```php
Route::prefix('v1')->group(function () {
    ...
});
```

## 6. Intended API Endpoint Map

### Public Auth

| Method | Intended URI after registration | Controller | Middleware | Classification |
| --- | --- | --- | --- | --- |
| `POST` | `/api/v1/login` | `API\AuthController@login` | API group only | Public |
| `POST` | `/api/v1/register` | `API\AuthController@register` | API group only | Public, but registration disabled in controller |

### Public Guest Access With Throttle and ApiAccessControl

| Method | Intended URI | Controller | Middleware | Classification |
| --- | --- | --- | --- | --- |
| `GET` | `/api/v1/exam-papers/available/{classSection}` | `API\ExamPaperController@availableForClass` | `throttle:10,1`, `ApiAccessControl` | Public/guest |
| `POST` | `/api/v1/exam-papers/search` | `API\ExamPaperController@search` | `throttle:10,1`, `ApiAccessControl` | Public/guest |
| `GET` | `/api/v1/bell-timing/today/{classSection}` | `API\BellTimingController@todaysSchedule` | `throttle:10,1`, `ApiAccessControl` | Public/guest |

### Sanctum Protected Auth/Profile

Middleware: `auth:sanctum`, `throttle:60,1`, `ApiAccessControl`

- `POST /api/v1/logout`
- `POST /api/v1/logout-all`
- `GET /api/v1/me`
- `PUT /api/v1/profile`
- `POST /api/v1/change-password`
- `POST /api/v1/refresh-token`

### Sanctum Protected Dashboards

- `GET /api/v1/dashboard/student`
- `GET /api/v1/dashboard/parent`
- `GET /api/v1/dashboard/teacher`

These are role-oriented, but route middleware does not currently enforce student/parent/teacher roles.

### Sanctum Protected Lesson Plans

- `GET /api/v1/lesson-plans`
- `GET /api/v1/lesson-plans/today`
- `GET /api/v1/lesson-plans/week`
- `GET /api/v1/lesson-plans/my`
- `POST /api/v1/lesson-plans`
- `GET /api/v1/lesson-plans/{id}`
- `PUT /api/v1/lesson-plans/{id}`

Teacher-specific checks exist inside some controller methods, but broad access still depends on `ApiAccessControl`, which currently allows all.

### Sanctum Protected Resources

Resource groups intended by `routes/api.php`:

- `students` via `Route::apiResource`
- `teachers` via `Route::apiResource`
- `attendance` via `Route::apiResource`
- `exam-papers` via `Route::apiResource`
- `bell-timing` via `Route::apiResource`
- `guardians` via `Route::apiResource`

Additional resource routes:

- Student attendance/results/fees
- Teacher classes/papers/subject-classes/attendance-data/grading-data
- Attendance monthly/daily reports and bulk-mark
- Exam paper download and toggle-publish
- Bell timing weekly/current-period/bulk-create
- Notifications index/read/mark-all/unread-count
- Guardian children/notifications

## 7. API Controller Method Mismatch Table

`BaseApiController` provides:

- `success($data = null, $message = 'Success', $code = 200)`
- `error($message = 'Error', $code = 400, $errors = null)`
- `validationError($errors)`

It does not provide:

- `sendResponse()`
- `sendError()`

| Controller | Uses `sendResponse()` / `sendError()` | Uses `success()` / `error()` | Break risk after API registration |
| --- | --- | --- | --- |
| `AuthController` | Yes | No | High. Login/register/logout/profile/password/token methods call missing methods. |
| `DashboardController` | Yes | No | High. Student/parent/teacher dashboards call missing methods. |
| `LessonPlanController` | Yes | No | High. Index/show/today/week/my/store/update call missing methods. |
| `AttendanceController` | No | Yes | Low for response contract. |
| `BellTimingController` | No | Yes | Low for response contract. |
| `ExamPaperController` | No | Yes | Low for response contract. |
| `GuardianController` | No | Yes | Low for response contract. |
| `StudentController` | No | Yes | Low for response contract. |
| `TeacherController` | No | Yes | Low for response contract. |
| `TeacherController_backup.php` | No | Yes | Not routed, but backup file exists in API folder. |
| `NotificationController` | No | No | Uses direct `response()->json()` and extends base Laravel controller. |
| `BiometricController` | No | No | Uses direct `response()->json()` and is currently exposed through `routes/web.php` `/api/biometric/*`. |
| `SelfServiceController` | No | No | Uses direct `response()->json()` and is currently exposed through `routes/web.php` `/api/self-service/*`. |

Safest Phase 2B fix recommendation:

Add backwards-compatible wrapper methods to `BaseApiController`:

- `sendResponse($result, $message = 'Success', $code = 200)` delegating to `success(...)`
- `sendError($error, $errorMessages = [], $code = 404)` delegating to `error(...)`

This is safer than editing every controller because it is smaller, preserves existing controller behavior expectations, and avoids broad response-shape churn before route activation.

## 8. AuthController Token and Security Findings

### Login

- Validates `email`, `password`, and optional `device_name`.
- Uses `User::where('email', ...)->first()` and `Hash::check(...)`.
- Creates Sanctum token with `$user->createToken($deviceName)->plainTextToken`.
- Does not assign token abilities/scopes.
- Returns token, token type, user id/name/email/roles/profile photo.
- Uses `$user->roles->pluck('name')`, so role relationship must be valid.

### Register

- Public route exists in `routes/api.php`.
- Controller intentionally disables registration with a 403 response.
- This is safe in intent, but currently calls missing `sendError()`.

### Logout

- Deletes current access token with `currentAccessToken()->delete()`.
- Safe revocation pattern, but should guard null current token defensively later.

### Logout All

- Deletes all tokens for current user with `$request->user()->tokens()->delete()`.

### Refresh Token

- Deletes current token.
- Creates a new token with `$user->createToken($deviceName)->plainTextToken`.
- Does not preserve or assign abilities/scopes.
- Does not rotate with explicit expiry metadata beyond Sanctum global config.

### Profile and Password

- `updateProfile()` validates allowed fields and uses `$request->only(...)`.
- `changePassword()` validates current and new password, checks current password, hashes new password.

### Login Model

- API login uses `users.email` and `users.password` through the default `User` model.
- It does not use role-specific session guards such as `parent` or `teacher`.
- `config/auth.php` defines web/session guards for `web`, `parent`, and `teacher`, but the API uses Sanctum against `App\Models\User`.

## 9. ApiAccessControl Findings

`ApiAccessControl::authorizeRequest()` currently returns `true`.

That means after API activation:

- Public guest routes using this middleware are effectively allowed.
- Sanctum-protected routes only require a valid token, not role/domain ownership.
- Student/teacher/parent/admin route separation is not enforced centrally.

Information available for minimal authorization:

- `$request->user()` from Sanctum on protected routes
- User roles through `User::roles()` and helpers such as `hasRole()`
- User ID through `$request->user()->id`
- Route name through `$request->route()->getName()`
- Route parameters such as `id`, `studentId`, `teacherId`, `classSection`, `collectionId`
- Request method and path
- Related model ownership can be checked through `Student`, `Teacher`, `Guardian`, class assignments, and parent-child guardian links
- No clear `school_id` tenancy boundary was confirmed on `User` during this phase

Recommended minimal Phase 2C strategy:

1. Keep public routes explicitly allowlisted by route name.
2. For protected routes, require authenticated Sanctum user.
3. Allow admins broad access by role.
4. Restrict students to their own student record and related attendance/results/fees.
5. Restrict parents to children linked through guardian/parent relationships.
6. Restrict teachers to their own teacher record, assigned classes, own lesson plans, and relevant attendance workflows.
7. Deny by default when route name or ownership cannot be resolved.

## 10. Sanctum Findings

`config/sanctum.php`:

- `stateful`: from `SANCTUM_STATEFUL_DOMAINS`, defaulting to local domains.
- `guard`: `['web']`.
- `expiration`: `null`, meaning personal access tokens do not expire.
- `token_prefix`: from `SANCTUM_TOKEN_PREFIX`, default empty.
- Middleware:
  - `AuthenticateSession`
  - `EncryptCookies`
  - `ValidateCsrfToken`

Token creation findings:

- `AuthController@login()` and `refreshToken()` call `createToken($deviceName)` without abilities.
- Feature tests also create unscoped test tokens.
- No `tokenCan()` checks were found in API authorization code.

Recommended mobile API strategy:

- Set an explicit token expiration such as 7 to 30 days, depending on app UX requirements.
- Issue role-scoped abilities, for example:
  - `mobile:student`
  - `mobile:parent`
  - `mobile:teacher`
  - `mobile:admin`
  - optional narrower abilities such as `attendance:write`, `lesson-plans:write`
- On refresh, preserve or recompute abilities from the user's roles.
- Add token ability checks only after route activation and response-contract repair are stable.

## 11. Web-Route API-Like Endpoint Conflicts

`routes/web.php` currently exposes API-like routes under `/api/*`:

- `/api/biometric/devices/{deviceId}/test-connection`
- `/api/biometric/devices/{deviceId}/sync`
- `/api/biometric/devices/{deviceId}/status`
- `/api/biometric/devices/{deviceId}/logs`
- `/api/biometric/statistics`
- `/api/biometric/sync-all`
- `/api/biometric/devices/{deviceId}/webhook`
- `/api/self-service/authenticate`
- `/api/self-service/attendance`
- `/api/self-service/summary/{month?}`
- `/api/self-service/trends`
- `/api/self-service/download-report`
- `/api/webhooks/biometric/{webhookToken}`
- `/api/webhooks/health`
- `/api/webhooks/config-info`

These are currently visible in `php artisan route:list --path=api`.

Potential conflicts:

- They occupy `/api/*` paths from `routes/web.php`, while future `routes/api.php` activation will also use `/api/v1/*`.
- There is no direct path conflict with `/api/v1/*`, but there is an architectural conflict because API-like behavior is split between web routes and intended API routes.
- Web routes may carry web middleware/session/CSRF expectations rather than the API middleware group.

Other web/API-like overlaps:

- `exam-papers/available` and `exam-papers/available-for-class` exist in web routes.
- `notifications/mark-all-read` exists in web routes.
- Teacher biometric routes exist under web teacher/admin route groups.

## 12. Safe Phase 2B Fix Plan

Recommended order:

1. Add backwards-compatible `sendResponse()` and `sendError()` wrappers to `BaseApiController`.
2. Register `routes/api.php` in `bootstrap/app.php` using `api: __DIR__.'/../routes/api.php'` while leaving the existing `Route::prefix('v1')` in `routes/api.php`.
3. Run route-list checks for `/api/v1`.
4. Lint touched files only.
5. Do not change `ApiAccessControl` behavior in Phase 2B except documenting that it remains permissive.
6. Do not change Sanctum expiration or abilities until Phase 2C/2D, after route activation is confirmed.
7. Do not run current `tests/Feature/API/AuthControllerTest.php` unless database reset/migration behavior is explicitly approved, because it uses `RefreshDatabase`.

## 13. Confirmation

No application code was modified in Phase 2A.

Only this report file was created.

No database-changing command was run.
