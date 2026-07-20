# PHASE 4L - Student Route Safety Regression

## Files inspected

- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `resources/views/admin/class-teacher-control/edit-student.blade.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/students/index.blade.php`
- `tests/Feature/Students/StudentImportApplyTest.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `docs/project-autopsy/PHASE_4K_LEGACY_STUDENT_SURFACE_AUDIT.md`

## Files changed

- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`
- `docs/project-autopsy/PHASE_4L_STUDENT_ROUTE_SAFETY_REGRESSION.md`

## Class-teacher link fix summary

The class-teacher student records view previously linked the View action through:

```php
route('students.show', $student)
```

That route name is currently owned by the API student resource route, not canonical admin web CRUD.

Phase 4L changed the link to:

```php
route('admin.students.show', $student)
```

No filters, table data, edit links, history links, import/export flows, or controller logic were changed.

## Route safety assertions added

Created:

- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`

Assertions added:

- `canonical_admin_student_show_route_exists`
- `class_teacher_student_records_view_uses_admin_students_show`
- `legacy_root_student_store_route_is_not_registered_as_web_crud`
- `legacy_root_student_update_route_is_not_registered_as_web_crud`
- `legacy_root_student_destroy_route_is_not_registered_as_web_crud`
- `legacy_root_student_show_route_is_not_registered_as_web_crud`
- `students_create_redirect_route_remains_registered`
- `direct_import_route_remains_guarded`
- `preview_and_apply_routes_remain_registered`
- `csv_export_route_remains_registered`

The test deliberately does not assert that `students.show`, `students.store`, `students.update`, or `students.destroy` are absent, because API routes still use those names. Instead, it checks concrete URI, HTTP method, and controller action to ensure there is no active root web CRUD route pointing at root `StudentController`.

## Confirmation legacy root CRUD routes remain inactive

The route safety test confirms these root web CRUD routes are not registered against root `StudentController`:

- `POST students` -> `StudentController@store`
- `GET students/{student}` -> `StudentController@show`
- `PUT students/{student}` -> `StudentController@update`
- `PATCH students/{student}` -> `StudentController@update`
- `DELETE students/{student}` -> `StudentController@destroy`

`GET students/create` remains registered as a redirect to `admin.students.create`.

## Confirmation import/export routes remain controlled

The route safety test confirms:

- `students.import.csv.preview` remains a POST route to `StudentController@previewImportCsv`.
- `students.import.csv.apply` remains a POST route to `StudentController@applyImportCsv`.
- `students.import.csv` remains a POST route to `StudentController@importCsv`.
- `students.import.csv` still returns the Phase 4D controlled warning and redirects without importing.
- `students.export.csv` remains a GET route to `StudentController@exportCsv`.

No import, apply, or export action was run against real/local MySQL.

## Tests created/updated

Created:

- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`

Existing targeted regression tests run:

- `StudentImportApplyTest`
- `StudentImportDirectRouteGuardTest`
- `StudentCsvExportTemplateTest`

## Commands run

- `Get-Content resources/views/admin/class-teacher-control/student-records.blade.php`
- `Get-Content routes/web.php`
- `Get-Content routes/api.php`
- `Get-Content docs/project-autopsy/PHASE_4K_LEGACY_STUDENT_SURFACE_AUDIT.md`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l tests/Feature/Students/StudentRouteSafetyRegressionTest.php`
- `php artisan route --path=students`
- `php artisan route --path=admin/students`
- `php artisan route --path=api/v1/students`
- `php artisan route:list --path=students`
- `php artisan route:list --path=admin/students`
- `php artisan route:list --path=api/v1/students`
- `php artisan test --filter=StudentRouteSafetyRegressionTest --env=testing`
- `php artisan test --filter=StudentImportApplyTest --env=testing`
- `php artisan test --filter=StudentImportDirectRouteGuardTest --env=testing`
- `php artisan test --filter=StudentCsvExportTemplateTest --env=testing`
- `git diff -- resources/views/admin/class-teacher-control/student-records.blade.php tests/Feature/Students/StudentRouteSafetyRegressionTest.php`

Notes:

- `php artisan route --path=...` is not a valid command in this Laravel 12 app and returned Artisan route namespace help.
- Equivalent read-only route verification was completed with `php artisan route:list --path=...`.

## Test result summary

Syntax checks:

- `php -l app/Http/Controllers/StudentController.php`: passed.
- `php -l app/Http/Controllers/Admin/AdminStudentController.php`: passed.
- `php -l tests/Feature/Students/StudentRouteSafetyRegressionTest.php`: passed.

Targeted tests:

- `StudentRouteSafetyRegressionTest`: passed, 10 tests / 34 assertions.
- `StudentImportApplyTest`: passed, 13 tests / 45 assertions.
- `StudentImportDirectRouteGuardTest`: passed, 5 tests / 14 assertions.
- `StudentCsvExportTemplateTest`: passed, 7 tests / 26 assertions.

Warnings:

- PHPUnit emitted pre-existing doc-comment metadata deprecation warnings from unrelated tests during filtered test bootstrapping. They did not fail the targeted runs.

## Confirmation no full suite was run

No full test suite was run. Only the targeted tests listed above were executed.

## Confirmation no migrations/schema/real MySQL data were touched

- No migrations were run.
- No schema changes were made.
- No real/local MySQL data was imported, exported, applied, created, updated, deleted, seeded, promoted, or mutated.
- No import/apply/export route was executed against real/local MySQL.
- No API routes were changed.
- No import apply logic was changed.
- No direct import guard logic was changed.
- No export behavior was changed.

## Remaining risks

1. Legacy root `resources/views/students/create.blade.php` still posts to `students.store` if it is ever reactivated.
2. Legacy root `resources/views/students/edit.blade.php` still posts to `students.update` if it is ever reactivated.
3. API routes still own unprefixed names such as `students.show`, `students.store`, `students.update`, and `students.destroy`.
4. Root `StudentController` still contains dormant CRUD methods alongside import/export methods.
5. Old legacy import code still exists below the direct import guard.

## Recommended next step

Phase 4M should either:

1. Extract import/export/preview/apply methods from root `StudentController` into a dedicated `StudentImportExportController`, keeping the same route names and the direct import guard; or
2. If extraction is too large, quarantine the legacy root create/edit views and add tests proving no route renders them.
