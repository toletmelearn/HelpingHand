# Phase 3G - Student Route/UI Alignment

## Files Inspected

- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `resources/views/home.blade.php`
- `resources/views/admin-dashboard.blade.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `docs/project-autopsy/PHASE_3F_STUDENT_UI_ROOT_CONTROLLER_AUDIT.md`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`

## Files Changed

- `routes/web.php`
- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `resources/views/home.blade.php`
- `resources/views/admin-dashboard.blade.php`
- `resources/views/students/index.blade.php`
- `tests/Feature/Admin/StudentRouteAlignmentTest.php`
- `docs/project-autopsy/PHASE_3G_STUDENT_ROUTE_UI_ALIGNMENT.md`

## Admin-Facing Links Updated

Updated admin-facing create links from `route('students.create')` to `route('admin.students.create')` in:

- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `resources/views/home.blade.php`
- `resources/views/admin-dashboard.blade.php`
- `resources/views/students/index.blade.php`

Import/export links were not changed.

Remaining `students.create` references after this phase:

- `app/Http/Controllers/StudentController.php` redirects to `students.create` after `store()`, but root web `StudentController@store` is not currently routed.
- `resources/views/welcome.blade.php` uses raw `/students/create` URL links.
- `resources/views/students/dashboard.blade.php` uses raw `/students/create` URL.
- `tests/Feature/Admin/StudentRouteAlignmentTest.php` intentionally asserts the redirect behavior.

Because `/students/create` now redirects to the canonical admin create route, those remaining raw URL links no longer open the legacy root create form.

## Legacy `/students/create` Route Decision

Changed the active legacy route:

```php
Route::get('students/create', [App\Http\Controllers\StudentController::class, 'create'])->name('students.create');
```

to a compatibility redirect:

```php
// Phase 3G: legacy student create route redirected to canonical admin student create to avoid route-name/API confusion.
Route::get('students/create', function () {
    return redirect()->route('admin.students.create');
})->name('students.create');
```

This keeps the old URI and route name available while preventing users from reaching the legacy root create Blade form.

## Root Student Form Status After This Phase

- `resources/views/students/create.blade.php` remains in the repository but is no longer the intended active create UI because `/students/create` redirects to `admin.students.create`.
- `resources/views/students/edit.blade.php` remains unchanged.
- Root `Route::resource('students', StudentController::class)` remains commented out.
- Root `StudentController@store/update/show/destroy` were not modified and remain non-canonical.

## Route-Name Collision Status

Resolved for admin-facing create navigation:

- Admin-facing links now use `admin.students.create`.
- `/students/create` redirects to `/admin/students/create`.

Still documented:

- `students.store` remains registered by API route `POST /api/v1/students`.
- `admin.students.store` remains the canonical admin web write route.
- `students.update` remains registered by API route `PUT|PATCH /api/v1/students/{student}`.
- Root student create/edit Blade forms still contain `students.store` / `students.update`, but the create view is no longer reached through `/students/create`.

## Import/Export Route Status

Left unchanged:

- `GET /students/export/csv` named `students.export.csv`
- `POST /students/import/csv` named `students.import.csv`

The import route remains a known future risk because it can create students with legacy string-only class/section data. It was intentionally not changed in this phase.

## Tests Created / Updated

Created:

- `tests/Feature/Admin/StudentRouteAlignmentTest.php`

Tests added:

- `admin_facing_class_teacher_add_student_link_uses_admin_students_create`
- `legacy_students_create_redirects_to_admin_students_create`

The test file is database-free. It performs a static Blade assertion and a route redirect assertion with middleware disabled.

## Commands Run

- `rg -n -F "route('students.create')" routes resources app tests`
- `rg -n -F 'route("students.create")' routes resources app tests`
- `rg -n -F "/students/create" routes resources app tests`
- `php -l routes/web.php`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l tests/Feature/Admin/StudentRouteAlignmentTest.php`
- `php artisan route --name=students.create`
- `php artisan route --name=admin.students.create`
- `php artisan route --name=students.store`
- `php artisan route --name=students.import.csv`
- `php artisan route:list --name=students.create`
- `php artisan route:list --name=admin.students.create`
- `php artisan route:list --name=students.store`
- `php artisan route:list --name=students.import.csv`
- `php artisan test --filter=StudentRouteAlignmentTest --env=testing`
- `php artisan test --filter=AdminStudentClassNormalizationTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`

Note: `php artisan route --name=...` is not a valid command in this Laravel install and returned Artisan route namespace help. `php artisan route:list --name=...` was used as the valid read-only route inspection equivalent.

## Test Result Summary

- `php -l routes/web.php`: passed.
- `php -l app/Http/Controllers/StudentController.php`: passed.
- `php -l app/Http/Controllers/Admin/AdminStudentController.php`: passed.
- `php -l tests/Feature/Admin/StudentRouteAlignmentTest.php`: passed.
- `StudentRouteAlignmentTest`: 2 passed, 4 assertions.
- `AdminStudentClassNormalizationTest`: 6 passed, 18 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated tests. They did not fail the targeted runs.

## Failures and Fixes

No targeted test failures occurred.

The requested `php artisan route --name=...` commands failed because that command form does not exist in the project. The equivalent `php artisan route:list --name=...` checks succeeded.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was touched.
- No student data was created, updated, deleted, imported, seeded, or bulk-mutated.
- CSV import/export routes were left unchanged.
- API StudentController was not changed.
- Promotion and passed-out flows were not changed.
- Root `Route::resource('students', StudentController::class)` was not reactivated.

## Remaining Risks

- `students.store` and `students.update` route names still belong to API routes; this should be cleaned up in a later API route-name normalization phase.
- Root `resources/views/students/create.blade.php` and `resources/views/students/edit.blade.php` still contain legacy form actions and class/section fields, but the create form is no longer reached through `/students/create`.
- `POST /students/import/csv` remains active and can still create class/section drift.
- Raw `/students/create` URL links remain in public/legacy views, but they now redirect to canonical admin create.
- `StudentController@store` still redirects to `students.create`, but it is not currently active through a web route.

## Recommended Next Step

Phase 3H should update the admin create/edit Blade forms to submit `class_id` and `section_id` directly while preserving the Phase 3E backend fallback. CSV import normalization should remain a separate later phase because it is bulk-write riskier than manual admin CRUD.
