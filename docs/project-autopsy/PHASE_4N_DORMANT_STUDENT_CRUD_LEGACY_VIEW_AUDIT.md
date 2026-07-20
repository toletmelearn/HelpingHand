# PHASE 4N - Dormant Student CRUD / Legacy View Audit

## Scope

This was a read-only audit of dormant root student CRUD code, legacy root student Blade views, and student route-name collision risks after Phase 4M moved import/export behavior into `StudentImportExportController`.

No application code, routes, views, controllers, models, tests, migrations, or database data were modified.

## Files Inspected

- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/StudentImportExportController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/students/import-preview.blade.php`
- `resources/views/admin/students/index.blade.php`
- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`
- `tests/Feature/Students/StudentImportExportControllerExtractionTest.php`
- `docs/project-autopsy/PHASE_4K_LEGACY_STUDENT_SURFACE_AUDIT.md`
- `docs/project-autopsy/PHASE_4L_STUDENT_ROUTE_SAFETY_REGRESSION.md`
- `docs/project-autopsy/PHASE_4M_STUDENT_IMPORT_EXPORT_CONTROLLER_EXTRACTION.md`

## Commands Run

- `Get-Content app\Http\Controllers\StudentController.php`
- `Get-Content app\Http\Controllers\StudentImportExportController.php`
- `Get-Content app\Http\Controllers\Admin\AdminStudentController.php`
- `Get-Content resources\views\students\index.blade.php`
- `Get-Content resources\views\students\create.blade.php`
- `Get-Content resources\views\students\edit.blade.php`
- `Get-Content resources\views\students\import-preview.blade.php`
- `Get-Content tests\Feature\Students\StudentRouteSafetyRegressionTest.php`
- `Get-Content tests\Feature\Students\StudentImportExportControllerExtractionTest.php`
- `Get-Content routes\web.php | Select-Object -Skip 286 -First 130`
- `Get-Content routes\api.php | Select-Object -First 90`
- `rg -n "students\.|admin\.students|StudentController|StudentImportExportController|students/create|students/import|students/export|Route::.*students" routes\web.php routes\api.php resources\views\students resources\views\admin\students tests\Feature\Students docs\project-autopsy\PHASE_4K_LEGACY_STUDENT_SURFACE_AUDIT.md docs\project-autopsy\PHASE_4L_STUDENT_ROUTE_SAFETY_REGRESSION.md docs\project-autopsy\PHASE_4M_STUDENT_IMPORT_EXPORT_CONTROLLER_EXTRACTION.md`
- `rg -n 'route\([''"]students\.(store|update|show|destroy|index|create|import|export)|students\.store|students\.update|students\.show|students\.destroy|admin\.students|url\(''/students' resources\views\students resources\views\admin\students resources\views\admin\class-teacher-control routes\web.php routes\api.php`
- `php -l app\Http\Controllers\StudentController.php`
- `php -l app\Http\Controllers\StudentImportExportController.php`
- `php -l app\Http\Controllers\Admin\AdminStudentController.php`
- `php artisan route --path=students`
- `php artisan route --path=admin/students`
- `php artisan route --path=api/v1/students`

Notes:

- The three requested `php artisan route --path=...` commands are not available in this Laravel app. Artisan showed the available route namespace commands as `route:cache`, `route:clear`, `route:health-check`, and `route:list`.
- One attempted PowerShell range command, `Get-Content routes\web.php | Select-Object -Index 286..410`, failed because `Select-Object -Index` does not accept that range string. The route section was then read with `Select-Object -Skip 286 -First 130`.
- No tests were run.
- No import/export/apply route was executed against real/local MySQL.

## Root StudentController Dormant CRUD Findings

Root `app/Http/Controllers/StudentController.php` still contains legacy CRUD methods only. Import/export responsibilities were removed in Phase 4M.

| Method | Actively routed through web? | Writes data? | Class/section behavior | Risk | Recommendation |
| --- | --- | --- | --- | --- | --- |
| `index(Request $request)` | No direct root `StudentController@index` web route found. `/students` points to `AdminStudentController@index`. | No | Filters legacy string `class` and `section`. | Medium if re-routed. | Quarantine or redirect later. |
| `create()` | No. `/students/create` is a closure redirect to `admin.students.create`. | No | Would render legacy `students.create`. | Medium if re-routed. | Replace with safe redirect/410 in Phase 4O if keeping method. |
| `store(Request $request)` | No root `StudentController@store` web route found. | Yes, if routed. | Validates/writes legacy string `class` and `section`; does not write `class_id`, `school_class_id`, or `section_id`. | High if re-routed. | Guard/abort or quarantine; do not leave as a usable write path. |
| `show($id)` | No root `StudentController@show` web route found. | No | Uses legacy root view `students.show` if present. | Medium if re-routed. | Redirect to `admin.students.show` or quarantine. |
| `edit($id)` | No root `StudentController@edit` web route found. | No | Would render legacy `students.edit`. | Medium if re-routed. | Redirect to `admin.students.edit` or quarantine. |
| `update(Request $request, $id)` | No root `StudentController@update` web route found. | Yes, if routed. | Validates/writes legacy string `class` and `section`; does not write compatibility FKs. | High if re-routed. | Guard/abort or quarantine; do not leave as a usable write path. |
| `destroy($id)` | No root `StudentController@destroy` web route found. | Yes, if routed. | Not class/section related, but destructive. | High if re-routed. | Guard/abort or redirect to canonical admin route later. |

Current route safety tests already assert there is no active root `StudentController` web route for:

- `POST students` -> `store`
- `GET students/{student}` -> `show`
- `PUT/PATCH students/{student}` -> `update`
- `DELETE students/{student}` -> `destroy`

## Legacy Root Student View Findings

### `resources/views/students/index.blade.php`

Active rendering:

- No active route was found that renders this view through root `StudentController@index`.
- `/students` currently points to `AdminStudentController@index`, which renders `admin.students.index`.

Risky helpers/actions:

- Row action links use raw URLs:
  - `url('/students/' . $student->id)`
  - `url('/students/' . $student->id . '/edit')`
  - `url('/students/' . $student->id)` with `DELETE`
- These URLs are not the canonical `admin.students.*` route helpers.
- The import form posts to `students.import.csv.preview`, which is currently controlled and safe.
- The export link points to `students.export.csv`, which is currently normalized.
- The add-student links use `admin.students.create`, which is safe.

Recommendation:

- Do not delete immediately because this view still contains the import/export UI pattern from earlier phases.
- If it remains unreachable, quarantine it or replace it with an explicit import/export-only view in a later phase.

### `resources/views/students/create.blade.php`

Active rendering:

- No active route was found that renders it.
- `/students/create` redirects to `admin.students.create`.

Risky helpers/actions:

- Form posts to `route('students.store')`.
- The form submits legacy `class` and `section` strings.
- It does not submit canonical `class_id` or `section_id`.

Route collision risk:

- `students.store` is owned by API route naming in `routes/api.php` through `Route::apiResource('students', StudentController::class)`.
- Admin web routes define `name('students.store')` inside an `admin.` name group, which makes the effective route name `admin.students.store`, not `students.store`.
- Therefore this legacy form is broken or dangerous if rendered.

Recommendation:

- Quarantine or replace with a safe redirect/message in Phase 4O.

### `resources/views/students/edit.blade.php`

Active rendering:

- No active route was found that renders it.

Risky helpers/actions:

- Form posts to `route('students.update', $student->id)`.
- The form does not include class/section controls, while root `StudentController@update()` requires `class`.
- It does not submit canonical `class_id` or `section_id`.

Route collision risk:

- `students.update` is owned by API route naming, not canonical admin web CRUD.
- Canonical admin update is `admin.students.update`.

Recommendation:

- Quarantine or replace with a safe redirect/message in Phase 4O.

### `resources/views/students/import-preview.blade.php`

Active rendering:

- Active through `StudentImportExportController@previewImportCsv`.

Route helpers:

- Back link uses `route('students.index')`.
- Apply form uses `route('students.import.csv.apply')`.

Risk:

- `students.index` is a web route to `AdminStudentController@index`, so the back link currently lands on canonical student index behavior. It is still a name-collision risk because API resource routes also create a `students.index` name.

Recommendation:

- Keep active.
- In a future cleanup, prefer a dedicated import/export route name or `admin.students.index` for the back link if that does not break UX.

## Route-Name Collision Findings

### Web route names

Confirmed web surfaces:

- `students.export.csv` -> `StudentImportExportController@exportCsv`
- `students.import.csv.preview` -> `StudentImportExportController@previewImportCsv`
- `students.import.csv.apply` -> `StudentImportExportController@applyImportCsv`
- `students.import.csv` -> guarded `StudentImportExportController@importCsv`
- `students.index` -> `AdminStudentController@index`
- `students.create` -> closure redirect to `admin.students.create`
- `admin.students.index` -> `AdminStudentController@index`
- `admin.students.create` -> `AdminStudentController@create`
- `admin.students.store` -> `AdminStudentController@store`
- `admin.students.show` -> `AdminStudentController@show`
- `admin.students.edit` -> `AdminStudentController@edit`
- `admin.students.update` -> `AdminStudentController@update`
- `admin.students.destroy` -> `AdminStudentController@destroy`

The `admin` route group source code uses `->name('students.store')`, `->name('students.show')`, and similar names inside a `name('admin.')` group, so the effective route names are `admin.students.*`.

### API route names

`routes/api.php` contains:

```php
Route::apiResource('students', StudentController::class);
```

Because the API group does not visibly prefix route names with `api.`, this creates or risks creating unprefixed names such as:

- `students.index`
- `students.store`
- `students.show`
- `students.update`
- `students.destroy`

Additional API routes are explicitly prefixed:

- `api.students.attendance`
- `api.students.results`
- `api.students.fees`

### Blade risk summary

Risky legacy root Blade references:

- `resources/views/students/create.blade.php`: `route('students.store')`
- `resources/views/students/edit.blade.php`: `route('students.update', $student->id)`
- `resources/views/students/index.blade.php`: raw `/students/{id}`, `/students/{id}/edit`, and `DELETE /students/{id}` URLs

Safe canonical admin Blade references:

- `resources/views/admin/students/index.blade.php`: `admin.students.*`
- `resources/views/admin/students/create.blade.php`: `admin.students.store`
- `resources/views/admin/students/edit.blade.php`: `admin.students.update`
- `resources/views/admin/class-teacher-control/student-records.blade.php`: `admin.students.show`

Additional legacy hit:

- `resources/views/students/dashboard.blade.php` references raw `/students` and `/students/create` URLs. This file was outside the primary requested legacy view trio but should be included in any later quarantine sweep.

## Quarantine / Delete / Redirect Recommendation

Recommended Phase 4O policy:

1. Do not delete root `StudentController` CRUD methods yet.
2. Add explicit comments or method-level guards stating root CRUD is legacy/dormant and canonical CRUD is `AdminStudentController`.
3. Replace or quarantine `resources/views/students/create.blade.php` and `resources/views/students/edit.blade.php` because they are not actively rendered and contain dangerous `students.store` / `students.update` assumptions.
4. Preserve `resources/views/students/import-preview.blade.php` because it is active and part of the safe import flow.
5. Decide whether `resources/views/students/index.blade.php` should become a dedicated import/export page or be moved to `docs/project-autopsy/quarantined-code` after confirming no active route renders it.
6. Keep existing route safety regression tests and add static assertions for legacy view quarantine in Phase 4O.

Do not bulk delete in the next step. The safer move is quarantine plus explicit comments/guards.

## Final Student Module Stabilization Summary

Current student module stabilization status:

- Canonical student CRUD: `AdminStudentController`.
- Root `StudentController` CRUD routes: inactive.
- `/students/create`: redirects to `admin.students.create`.
- Class-teacher student links: use `admin.students.show`.
- Admin create/update: normalizes `class_id`, `school_class_id`, `class`, `section_id`, and `section`.
- Admin forms: submit `class_id` and `section_id`.
- Admin index: filters by `section_id` with legacy fallback.
- Promotion store: writes `class_id`, `school_class_id`, and `class` together.
- Passed-out: writes `student_statuses`, clears class/section compatibility fields, and logs promotion.
- Generic student status CRUD: restricted to `active` / `inactive`.
- CSV import: preview + session-backed apply only.
- Direct CSV import: guarded, writes zero students.
- CSV export/template: aligned to normalized import contract.
- `StudentsExport.php`: normalized to Phase 4H export contract.
- Terminal-status reconciliation: read-only command/service/admin report exists.
- Known historical data issue from prior reports: one class FK conflict remains documented, not repaired.

## Top Remaining Student-Surface Risks

1. Dormant `StudentController@store()` and `update()` still contain legacy string-only class/section write logic if accidentally re-routed.
2. Dormant `StudentController@destroy()` is destructive if accidentally re-routed.
3. `resources/views/students/create.blade.php` posts to `students.store`, which is not canonical admin web CRUD and may resolve to API naming.
4. `resources/views/students/edit.blade.php` posts to `students.update`, lacks class/section fields, and is inconsistent with root update validation.
5. `resources/views/students/index.blade.php` contains raw `/students/{id}` show/edit/delete URLs and should not be used as canonical student index UI.
6. API route names still overlap with historical Blade assumptions for `students.*`.
7. `students.index` remains a shared-looking global name even though current web behavior points to admin student index.
8. Legacy root views still exist beside canonical admin views, increasing future developer confusion.
9. Duplicate admin `students-crud` routes remain an extra student surface.
10. Historical class/status drift remains detectable but not repaired.

## Phase 5A Readiness Recommendation

The student module is controlled enough to move to **Phase 5A Attendance module audit**, provided Phase 4O is either:

- skipped intentionally with the legacy student CRUD/view risks documented, or
- kept very small: quarantine legacy create/edit views and add explicit root `StudentController` CRUD warnings/guards.

Recommended next step:

- **Phase 4O first** if the goal is to close the last student surface risk before moving on.
- **Phase 5A next** if the team accepts that dormant root CRUD/view risks are documented and covered by route safety tests.

My safer recommendation is Phase 4O: quarantine legacy root student create/edit views and add explicit dormant CRUD warnings/guards, then move to Attendance.

## Confirmation

- No application code was modified.
- No routes were modified.
- No views were modified.
- No controllers were modified.
- No models were modified.
- No tests were modified.
- No migrations were modified.
- No imports, exports, applies, creates, updates, deletes, seeds, promotions, or pass-out actions were run.
- No full test suite was run.
- No real/local MySQL data was touched.
