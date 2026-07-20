# Phase 2D - API Access Control Implementation

## 1. Files Inspected

- `app/Http/Middleware/ApiAccessControl.php`
- `routes/api.php`
- `app/Models/User.php`
- `app/Models/Student.php`
- `app/Models/Teacher.php`
- `app/Models/Role.php`
- `docs/project-autopsy/PHASE_2C_API_AUTHORIZATION_MAP.md`

## 2. Files Changed

- `app/Http/Middleware/ApiAccessControl.php`
- `docs/project-autopsy/PHASE_2D_API_ACCESS_CONTROL_IMPLEMENTATION.md`

No routes, controllers, migrations, database schema, Sanctum config, token expiration, or token abilities were changed.

## 3. Public Allowlist Implemented

The middleware now allows these route names without authentication:

- `api.login`
- `api.register`

These were implemented in `publicAllowlist()`.

## 4. Public Temporary Blocklist Implemented

The middleware now blocks these route names before the public allowlist or auth checks:

- `api.exam-papers.available-for-class`
- `api.exam-papers.search`
- `api.bell-timing.today`

These routes were blocked because Phase 2C found that they are public/guest-routed and point to missing or unresolved controller methods.

## 5. Authentication Check Implemented

For all non-public routes, the middleware now resolves:

```php
$user = $request->user();
```

If no authenticated `App\Models\User` is present, authorization returns `false` and the existing middleware response returns JSON 403:

```json
{
  "success": false,
  "message": "Access denied. Insufficient permissions.",
  "timestamp": "..."
}
```

## 6. Admin Broad Allow Implemented

Admin-like users are now broadly allowed after authentication:

- `admin`
- `super-admin`
- `super_admin`

The middleware uses `hasAnyRole()` when available and falls back to `hasRole()`.

## 7. Auth Self Routes Allowed

The middleware now allows any authenticated user for:

- `api.logout`
- `api.logout-all`
- `api.me`
- `api.update-profile`
- `api.change-password`
- `api.refresh-token`

These are implemented in `authSelfRoutes()`.

## 8. Notification Routes Allowed

The middleware now allows any authenticated user for:

- `api.notifications.index`
- `api.notifications.mark-as-read`
- `api.notifications.mark-all-read`
- `api.notifications.unread-count`

These are allowed because the notification controller scopes records through `$request->user()`.

## 9. Student Self Helper Implementation Summary

Added:

```php
private function isStudentSelf(Request $request, User $user): bool
```

Behavior:

- For `api.dashboard.student`, it allows only if a `Student` exists with `user_id = $user->id`.
- For student-specific read routes, it resolves route params from `student`, `id`, or `studentId`.
- It allows only if:

```php
Student::where('id', $studentId)
    ->where('user_id', $user->id)
    ->exists();
```

Student self routes:

- `api.dashboard.student`
- `students.show`
- `api.students.attendance`
- `api.students.results`
- `api.students.fees`
- `api.attendance.student-monthly`

The middleware also requires the user to have the `student` role for these routes unless they are admin.

## 10. Teacher Self Helper Implementation Summary

Added:

```php
private function isTeacherSelf(Request $request, User $user): bool
```

Behavior:

- For `api.dashboard.teacher` and `api.lesson-plans.my`, it allows only if a `Teacher` exists with `user_id = $user->id`.
- For teacher-specific read routes, it resolves route params from `teacher` or `id`.
- It allows only if:

```php
Teacher::where('id', $teacherId)
    ->where('user_id', $user->id)
    ->exists();
```

Teacher self routes:

- `api.dashboard.teacher`
- `teachers.show`
- `api.teachers.classes`
- `api.teachers.papers`
- `api.teachers.subject-classes`
- `api.teachers.attendance-data`
- `api.teachers.grading-data`
- `api.lesson-plans.my`

The middleware also requires the user to have the `teacher` role for these routes unless they are admin.

## 11. Parent Routes Blocked

Parent child routes remain blocked for non-admin users:

- `api.dashboard.parent`
- `guardians.index`
- `guardians.store`
- `guardians.show`
- `guardians.update`
- `guardians.destroy`
- `api.guardians.children`
- `api.guardians.notifications`

Reason: Phase 2C found that parent/guardian ownership is not reliable enough yet for safe API access.

## 12. High-Risk Blocklist Implemented

The middleware now blocks non-admin access to the high-risk routes identified in Phase 2C, including:

- Student list/write/delete routes
- Teacher list/write/delete routes
- Guardian routes
- Attendance broad list/write/delete/bulk routes
- Exam paper broad list/write/delete/toggle-publish routes
- Bell timing write/bulk routes
- Lesson plan write routes

The blocklist is implemented in `highRiskBlocklist()`.

## 13. Deny-By-Default Confirmation

`authorizeRequest()` no longer returns unconditional `true`.

After public allowlist, authentication, admin allow, auth self routes, notification routes, explicit blocklists, and student/teacher self checks, the method ends with:

```php
return false;
```

## 14. Commands Run

Safe commands only:

```powershell
Get-Content app\Http\Middleware\ApiAccessControl.php
Get-Content routes\api.php
Get-Content app\Models\User.php
Get-Content app\Models\Student.php
Get-Content app\Models\Teacher.php
Get-Content docs\project-autopsy\PHASE_2C_API_AUTHORIZATION_MAP.md
php -l app\Http\Middleware\ApiAccessControl.php
php artisan route:list --path=api/v1
php artisan route:list --path=api/v1 | Select-String login
php artisan route:list --path=api/v1 | Select-String students
php artisan route:list --path=api/v1 | Select-String teachers
php artisan route --path=api/v1
php artisan route --path=api/v1 | Select-String login
php artisan route --path=api/v1 | Select-String students
php artisan route --path=api/v1 | Select-String teachers
```

Note: Laravel 12 rejected the shorthand `php artisan route --path=api/v1`. It displayed the route namespace help and listed valid commands such as `route:list`. The valid `php artisan route:list --path=api/v1` form was run and passed.

No migrations, schema changes, composer setup, database reset, or database-changing tests were run.

## 15. Verification Summary

- `php -l app\Http\Middleware\ApiAccessControl.php` passed.
- `php artisan route:list --path=api/v1` showed 73 active `/api/v1/*` routes.
- `Select-String login` confirmed `api.login` is visible.
- `Select-String students` confirmed student API routes are still registered.
- `Select-String teachers` confirmed teacher API routes are still registered.
- Route definitions were not changed.
- Middleware now denies by default instead of allowing all requests.

## 16. Remaining Risks

- `ApiAccessControl::rateLimit()` can return a 429 response internally, but `handle()` does not currently return that response. This was pre-existing and was not changed in Phase 2D.
- Parent/guardian API ownership remains unresolved and blocked for non-admin users.
- Student and teacher self checks depend on `users` being linked through `students.user_id` and `teachers.user_id`.
- Many class-scoped read routes remain denied for non-admin users until safer ownership helpers are added.
- Sanctum tokens are still long-lived and unscoped by design for this phase.
- No database-backed authorization tests were run because database-changing tests are prohibited.

## 17. Recommended Next Step

Phase 2E should repair and verify parent/guardian ownership mapping or explicitly quarantine parent API routes at the route/controller level. After that, add safe class-scoped helpers for lesson plans, attendance reads, bell timing reads, and exam paper reads.
