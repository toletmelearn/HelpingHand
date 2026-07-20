# PHASE 4O - Legacy Student Surface Quarantine

## Scope

Phase 4O quarantined dormant root student CRUD and risky legacy root student create/edit views while preserving canonical admin student CRUD and the safe student import/export flow.

No migrations, schema changes, import/export/apply actions against real/local MySQL, or full test suite were run.

## Files Inspected

- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/StudentImportExportController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/import-preview.blade.php`
- `routes/web.php`
- `routes/api.php`
- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`
- `tests/Feature/Students/StudentImportExportControllerExtractionTest.php`
- `docs/project-autopsy/PHASE_4N_DORMANT_STUDENT_CRUD_LEGACY_VIEW_AUDIT.md`
- `docs/project-autopsy/PHASE_4M_STUDENT_IMPORT_EXPORT_CONTROLLER_EXTRACTION.md`

## Files Changed

- `app/Http/Controllers/StudentController.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/students/index.blade.php`
- `docs/project-autopsy/quarantined-code/students-create.blade.php.txt`
- `docs/project-autopsy/quarantined-code/students-edit.blade.php.txt`
- `tests/Feature/Students/StudentLegacySurfaceQuarantineTest.php`
- `docs/project-autopsy/PHASE_4O_LEGACY_STUDENT_SURFACE_QUARANTINE.md`

## Dormant StudentController Guard Summary

Root `StudentController` now has an explicit class-level warning:

- root student CRUD is legacy/dormant
- canonical CRUD is `AdminStudentController`
- import/export is handled by `StudentImportExportController`
- these methods should not be routed without class/section normalization

Read methods now redirect to canonical admin routes:

- `index()` -> `admin.students.index`
- `create()` -> `admin.students.create`
- `show($id)` -> `admin.students.show`
- `edit($id)` -> `admin.students.edit`

Dangerous write/delete methods now abort before authorization, validation, model lookup, save, update, or delete:

- `store()` -> `abort(410, 'Legacy student store is disabled. Use admin student CRUD.')`
- `update()` -> `abort(410, 'Legacy student update is disabled. Use admin student CRUD.')`
- `destroy()` -> `abort(410, 'Legacy student delete is disabled. Use admin student CRUD.')`

## Legacy Create/Edit View Quarantine Summary

The original legacy root student create/edit views were copied into `docs/project-autopsy/quarantined-code` and replaced with inert informational Blade stubs.

The new stubs:

- contain no forms
- do not call `route('students.store')`
- do not call `route('students.update')`
- link users to canonical `admin.students.*` routes
- explain that the legacy root student view is disabled

## Quarantined Backup Paths

- `docs/project-autopsy/quarantined-code/students-create.blade.php.txt`
- `docs/project-autopsy/quarantined-code/students-edit.blade.php.txt`

## Import Preview View Confirmation

`resources/views/students/import-preview.blade.php` was not changed.

It remains the active preview/apply result page for safe student import.

## Import / Export / Apply Route Confirmation

Import/export/apply routes remain unchanged:

- `GET students/export/csv` -> `students.export.csv`
- `POST students/import/csv/preview` -> `students.import.csv.preview`
- `POST students/import/csv/apply` -> `students.import.csv.apply`
- `POST students/import/csv` -> `students.import.csv`

All still point to `StudentImportExportController`.

Direct import remains guarded.

## Tests Created / Updated

Created:

- `tests/Feature/Students/StudentLegacySurfaceQuarantineTest.php`

The test covers:

- direct `StudentController@store()` aborts with 410
- direct `StudentController@update()` aborts with 410
- direct `StudentController@destroy()` aborts with 410
- legacy create view no longer posts to `students.store`
- legacy edit view no longer posts to `students.update`
- quarantined view backup files exist
- root CRUD routes remain inactive
- import/export routes still point to `StudentImportExportController`

## Commands Run

Inspection and backup:

- `Get-Content app\Http\Controllers\StudentController.php`
- `Get-Content resources\views\students\create.blade.php`
- `Get-Content resources\views\students\edit.blade.php`
- `Get-Content resources\views\students\index.blade.php -First 40`
- `Get-Content tests\Feature\Students\StudentRouteSafetyRegressionTest.php`
- `New-Item -ItemType Directory -Force docs\project-autopsy\quarantined-code`
- `Copy-Item resources\views\students\create.blade.php docs\project-autopsy\quarantined-code\students-create.blade.php.txt`
- `Copy-Item resources\views\students\edit.blade.php docs\project-autopsy\quarantined-code\students-edit.blade.php.txt`

Static checks:

- `php -l app\Http\Controllers\StudentController.php`
- `php -l app\Http\Controllers\StudentImportExportController.php`
- `php -l app\Http\Controllers\Admin\AdminStudentController.php`
- `php -l tests\Feature\Students\StudentLegacySurfaceQuarantineTest.php`

Route checks:

- `php artisan route --path=students`
- `php artisan route --path=admin/students`
- `php artisan route:list --path=students`
- `php artisan route:list --path=admin/students`

Note: this Laravel app does not expose the requested bare `php artisan route --path=...` command form. It prints the route namespace help and lists `route:list` as the available command. `route:list --path=...` was used as the read-only fallback.

Targeted tests:

- `php artisan test --filter=StudentLegacySurfaceQuarantineTest --env=testing`
- `php artisan test --filter=StudentRouteSafetyRegressionTest --env=testing`
- `php artisan test --filter=StudentImportExportControllerExtractionTest --env=testing`
- `php artisan test --filter=StudentImportApplyTest --env=testing`

## Test Result Summary

Passed:

- `StudentLegacySurfaceQuarantineTest`: 8 passed, 39 assertions
- `StudentRouteSafetyRegressionTest`: 10 passed, 34 assertions
- `StudentImportExportControllerExtractionTest`: 6 passed, 39 assertions
- `StudentImportApplyTest`: 13 passed, 45 assertions

Observed warnings:

- PHPUnit reported existing doc-comment metadata deprecation warnings in unrelated test files. These warnings did not fail the targeted tests.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No real/local MySQL data was touched.
- No import was run against real/local MySQL.
- No export/download was run against real/local MySQL.
- No apply flow was run against real/local MySQL.
- Canonical `AdminStudentController` CRUD behavior was not changed.
- `StudentImportExportController` behavior was not changed.
- Direct import guard behavior was not changed.
- API routes were not changed.

## Remaining Risks

1. `resources/views/students/index.blade.php` remains a legacy root view with raw `/students/{id}` show/edit/delete URLs, although it is not canonical CRUD and now has a warning comment.
2. `resources/views/students/dashboard.blade.php` was noted in Phase 4N as another legacy surface but was outside this phase's requested edit set.
3. API route names still expose unprefixed `students.store`, `students.show`, `students.update`, and `students.destroy`.
4. Duplicate admin `students-crud` routes remain as an extra student surface.
5. Historical class/status drift remains detectable but not automatically repaired.

## Recommended Next Step

Begin **Phase 5A Attendance module audit**.

The remaining student risks are now either guarded, quarantined, or explicitly documented. Attendance is the next high-risk domain to inspect for class/section alignment and student status filtering.
