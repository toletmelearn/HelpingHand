# PHASE 4K - Legacy Root StudentController Surface Audit

## Files inspected

- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Models/Student.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/students/import-preview.blade.php`
- `resources/views/admin/students/index.blade.php`
- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `resources/views/admin/class-teacher-control/edit-student.blade.php`
- `tests/Feature/Students/StudentImportApplyTest.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `docs/project-autopsy/PHASE_3G_STUDENT_ROUTE_UI_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_4F_STUDENT_IMPORT_APPLY_FLOW.md`
- `docs/project-autopsy/PHASE_4H_STUDENT_CSV_EXPORT_TEMPLATE_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_4J_STUDENTSEXPORT_NORMALIZATION.md`

## Commands run

- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content app/Http/Controllers/Admin/AdminStudentController.php`
- `Get-Content app/Models/Student.php`
- `rg -n "StudentController|AdminStudentController|students\\.import|students\\.export|students\\.create|students\\.store|students\\.update|students\\.destroy|admin\\.students" routes app resources tests docs/project-autopsy`
- `Get-Content resources/views/students/index.blade.php`
- `Get-Content resources/views/students/create.blade.php`
- `Get-Content resources/views/students/edit.blade.php`
- `Get-Content resources/views/students/import-preview.blade.php`
- `Get-Content resources/views/admin/students/index.blade.php`
- `Get-Content resources/views/admin/students/create.blade.php`
- `Get-Content resources/views/admin/students/edit.blade.php`
- `Get-Content resources/views/admin/class-teacher-control/student-records.blade.php`
- `Get-Content resources/views/admin/class-teacher-control/edit-student.blade.php`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l app/Models/Student.php`
- `php artisan route | Select-String "students"`
- `php artisan route | Select-String "admin/students"`
- `php artisan route | Select-String "api/v1/students"`
- `php artisan route:list | Select-String "students"`
- `php artisan route:list | Select-String "admin/students"`
- `php artisan route:list | Select-String "api/v1/students"`
- `php artisan route:list --path=students`
- `php artisan route:list --path=admin/students`
- `php artisan route:list --path=api/v1/students`
- `rg -n "students\\.store|students\\.update|students\\.destroy|students\\.show|students\\.edit" resources/views app routes tests`
- `Get-Content routes/api.php`
- `Get-Content docs/project-autopsy/PHASE_4H_STUDENT_CSV_EXPORT_TEMPLATE_ALIGNMENT.md`
- `Get-Content docs/project-autopsy/PHASE_4J_STUDENTSEXPORT_NORMALIZATION.md`
- `rg -n "function (index|create|store|show|edit|update|destroy|exportCSV|exportCsv|exportExcel|previewImportCsv|applyImportCsv|importCSV|importCsv|readCSVFile|readExcelFile|parseDate|exportClassName|exportSectionName|combineImportRowWithHeaders|hashImportPreviewRows|importPreviewExpired|duplicateErrorsForPreviewRows|studentImportPayload|firstImportValue)" app/Http/Controllers/StudentController.php`
- `php artisan tinker --execute="dump(['total' => DB::table('students')->count(), 'class_id_conflicts' => DB::table('students')->whereNotNull('class_id')->whereNotNull('school_class_id')->whereColumn('class_id', '<>', 'school_class_id')->count(), 'null_class_id' => DB::table('students')->whereNull('class_id')->count(), 'null_school_class_id' => DB::table('students')->whereNull('school_class_id')->count(), 'null_section_id' => DB::table('students')->whereNull('section_id')->count()]);"`

Notes:

- `php artisan route ...` is not available in this Laravel 12 app and returned Artisan route namespace help.
- Equivalent route inspection was completed with `php artisan route:list`.
- Two attempted `rg` commands with complex quote patterns failed in PowerShell due quote parsing; simpler `rg` searches were used instead.

## Root StudentController method map

| Method | Purpose | Actively routed? | Writes student data? | Class/section behavior | Recommendation |
| --- | --- | --- | --- | --- | --- |
| `index()` | Legacy student list view, filters by legacy `class` and `section` strings | No direct web route found; `/students` points to `AdminStudentController@index` | No | Legacy string filters only | Quarantine or redirect later |
| `create()` | Legacy root create form | No; `/students/create` is a redirect closure to `admin.students.create` | No | Legacy create form uses string `class` and `section` | Keep unreachable; quarantine later |
| `store()` | Legacy root create write | No active web route found | Yes if reactivated | Writes only string `class` and `section`; no `class_id`, `school_class_id`, or `section_id` | Quarantine or redirect later |
| `show()` | Legacy root show | No active root `/students/{id}` web route found | No | N/A | Quarantine later |
| `edit()` | Legacy root edit form | No active root `/students/{id}/edit` web route found | No | Legacy edit form does not submit class/section controls | Quarantine later |
| `update()` | Legacy root update write | No active root web route found | Yes if reactivated | Validates/writes only string `class` and `section`; edit view likely would fail validation if reached | Quarantine or redirect later |
| `destroy()` | Legacy root delete | No active root web route found | Yes if reactivated | N/A | Quarantine or redirect later |
| `exportCSV()` | Active normalized CSV export | Yes: `GET students/export/csv`, `students.export.csv` | No | Uses `class_id`, canonical class display, `section_id`, canonical section display | Keep active |
| `exportExcel()` | Normalized Excel export method | No active Excel route found | No | Normalized if wired later | Leave documented; test before routing |
| `previewImportCsv()` | Preview-only CSV/Excel import | Yes: `POST students/import/csv/preview` | No | Uses `StudentImportNormalizer` | Keep active |
| `applyImportCsv()` | Session-backed safe import apply | Yes: `POST students/import/csv/apply` | Yes | Writes normalized `class_id`, `school_class_id`, `class`, `section_id`, `section` inside transaction | Keep active |
| `importCSV()` | Direct legacy import route guard | Yes: `POST students/import/csv` | No; early return guard | Old legacy loop remains below guard but unreachable | Keep guarded now; remove/extract later |
| private CSV/Excel parsing helpers | Parse import preview input | Used by preview and old guarded import code | No by themselves | N/A | Keep until extracted |
| private import/apply helpers | Preview hash, expiry, duplicate checks, payload mapping | Used by preview/apply | Apply helpers support normalized writes | Normalized payload uses canonical class/section results | Keep; extract later if controller is split |

## Student route map

### Root / global web routes

| Method | URI | Route name | Controller / action | Active status | Risk |
| --- | --- | --- | --- | --- | --- |
| `GET` | `students` | `students.index` | `AdminStudentController@index` | Active | Medium due name collision with API `students.index`; behavior is canonical admin index in web route list |
| `GET` | `students/create` | `students.create` | closure redirect to `admin.students.create` | Active redirect | Low; intentionally avoids legacy create view |
| `GET` | `students/export/csv` | `students.export.csv` | `StudentController@exportCsv` | Active | Low; normalized Phase 4H |
| `POST` | `students/import/csv/preview` | `students.import.csv.preview` | `StudentController@previewImportCsv` | Active | Low; preview-only |
| `POST` | `students/import/csv/apply` | `students.import.csv.apply` | `StudentController@applyImportCsv` | Active | Medium; writes students but only from clean preview |
| `POST` | `students/import/csv` | `students.import.csv` | `StudentController@importCsv` | Active guarded route | Low while guard remains; high if guard removed |

### Admin web routes

| Method | URI | Route name | Controller / action | Active status | Risk |
| --- | --- | --- | --- | --- | --- |
| `GET` | `admin/students` | `admin.students.index` | `AdminStudentController@index` | Active canonical | Low |
| `GET` | `admin/students/create` | `admin.students.create` | `AdminStudentController@create` | Active canonical | Low |
| `POST` | `admin/students` | `admin.students.store` | `AdminStudentController@store` | Active canonical | Low after Phase 3E/3H |
| `GET` | `admin/students/{student}` | `admin.students.show` | `AdminStudentController@show` | Active canonical | Low |
| `GET` | `admin/students/{student}/edit` | `admin.students.edit` | `AdminStudentController@edit` | Active canonical | Low |
| `PUT` | `admin/students/{student}` | `admin.students.update` | `AdminStudentController@update` | Active canonical | Low after Phase 3E/3H |
| `DELETE` | `admin/students/{student}` | `admin.students.destroy` | `AdminStudentController@destroy` | Active canonical | Medium; destructive but canonical/admin protected |
| multiple | `admin/students-crud...` | `admin.*` unnamed/partial names | `AdminStudentController` duplicate CRUD surface | Active duplicate surface | Medium; duplicated URIs can confuse future links/tests |

### API student routes

`routes/api.php` registers:

```php
Route::apiResource('students', StudentController::class);
```

Active API names include:

- `students.index`
- `students.store`
- `students.show`
- `students.update`
- `students.destroy`

These names collide with legacy Blade assumptions and with global web names. They should eventually be renamed/prefixed to `api.students.*` or otherwise isolated.

### Class-teacher student routes

Class-teacher student records are active under admin/class-teacher-control. The inspected list view uses canonical filter inputs:

- `class_id`
- `section_id`

However, `resources/views/admin/class-teacher-control/student-records.blade.php` still links:

```php
route('students.show', $student)
```

Because `students.show` is an API route name in the current route list, this is a navigation risk and should be changed to `admin.students.show` or a class-teacher-specific show route in a future phase.

## Legacy student view findings

### `resources/views/students/index.blade.php`

Status:

- Not returned by active `/students`; that route returns `admin.students.index`.
- Still contains useful import/export UI that Phase 4C/4F/4H updated.
- Import form posts to `students.import.csv.preview`.
- Export link points to `students.export.csv`.
- Add Student link points to `admin.students.create`.

Risks:

- Table action links use raw URLs:
  - `/students/{id}`
  - `/students/{id}/edit`
  - `DELETE /students/{id}`
- Those root resource routes are not active, so the actions are broken if this view is reached.
- Bulk action buttons are placeholders only.

Decision:

- Keep only as legacy/import-export UI until import/export is extracted.
- Do not use it as canonical CRUD.

### `resources/views/students/create.blade.php`

Status:

- Legacy view remains in repo.
- `/students/create` no longer reaches it; route redirects to admin create.

Risks:

- Form posts to `route('students.store')`.
- Current `students.store` is an API route name, not canonical admin web CRUD.
- Form submits only string `class` and string `section`.

Decision:

- Quarantine or delete later after confirming no route renders it.

### `resources/views/students/edit.blade.php`

Status:

- Legacy view remains in repo.
- No active root edit route found.

Risks:

- Form posts to `route('students.update', $student->id)`.
- Current `students.update` is an API route name.
- The form does not include class/section controls, while root `StudentController@update()` validates `class` as required.

Decision:

- Quarantine or delete later after confirming no route renders it.

### `resources/views/students/import-preview.blade.php`

Status:

- Active through `StudentController@previewImportCsv`.
- Read-only preview page with apply button only for clean previews.

Risks:

- Uses `route('students.index')` for back navigation. Because there are duplicate `students.index` route names in web/API route lists, this should eventually be made explicit, preferably `admin.students.index` or a dedicated import/export route.

Decision:

- Keep active until import/export is extracted.

## Route-name collision findings

High-confidence route-name risks:

1. API route names are unprefixed:
   - `students.index`
   - `students.store`
   - `students.show`
   - `students.update`
   - `students.destroy`
2. Legacy root create/edit views post to `students.store` and `students.update`, which currently resolve to API route names, not canonical admin web routes.
3. Class-teacher student records link to `students.show`, which currently points to API student show.
4. `students.index` appears in both API and web route lists.
5. Admin canonical routes use `admin.students.*`, which is the safer naming pattern and should be preferred in views.

## Canonical student CRUD decision

Canonical student CRUD should remain:

- `AdminStudentController@index`
- `AdminStudentController@create`
- `AdminStudentController@store`
- `AdminStudentController@show`
- `AdminStudentController@edit`
- `AdminStudentController@update`
- `AdminStudentController@destroy`

Reason:

- Admin create/update normalize `class_id`, `school_class_id`, `class`, `section_id`, and `section`.
- Admin forms submit canonical `class_id` and `section_id`.
- Admin index filters prefer `section_id`.
- Admin views use `admin.students.*` route names.

Root `StudentController` should be reduced to import/export responsibilities temporarily, then eventually split into a dedicated `StudentImportExportController`.

## CSV import/export surface status

CSV import/export surfaces are controlled:

- Preview route exists and is preview-only.
- Apply route imports only clean previews.
- Apply writes normalized class/section fields.
- Direct import route remains guarded and writes zero students.
- CSV export matches the normalized contract.
- `StudentsExport.php` no longer returns raw `Student::all()`.

Remaining import/export concern:

- Import/export is still hosted in the broad root `StudentController`, alongside dormant legacy CRUD methods.

## Read-only data snapshot

Read-only counts:

| Check | Count |
| --- | ---: |
| Total students | 760 |
| `class_id != school_class_id` conflicts | 1 |
| Null `class_id` | 0 |
| Null `school_class_id` | 0 |
| Null `section_id` | 0 |

No repair was performed.

## Top 10 remaining student surface risks

1. `students.store`, `students.update`, `students.show`, and `students.destroy` route names are owned by API routes and can mislead legacy Blade forms/links.
2. `resources/views/students/create.blade.php` still posts to `students.store` and writes legacy string class/section if reactivated.
3. `resources/views/students/edit.blade.php` still posts to `students.update` and is inconsistent with root update validation.
4. `resources/views/admin/class-teacher-control/student-records.blade.php` links `route('students.show')`, likely pointing to API instead of admin show.
5. `resources/views/students/index.blade.php` contains raw `/students/{id}` view/edit/delete links that are not backed by active root resource routes.
6. Root `StudentController@store/update/destroy` are dormant but dangerous if a future route reactivates them.
7. Old legacy import code still exists below the Phase 4D early return guard.
8. Import/export responsibilities are mixed into the same controller that contains dormant legacy CRUD.
9. Duplicate admin `students-crud` routes expose extra AdminStudentController surfaces that may confuse future route ownership.
10. One historical `class_id` / `school_class_id` conflict remains in data.

## Recommended Phase 4L first task

Recommended first code task: **quarantine dormant root `StudentController` CRUD methods and legacy root student views by adding safe redirects/aborts or extracting import/export to a dedicated controller first.**

Safest implementation sequence:

1. Create `StudentImportExportController` and move only:
   - `exportCSV`
   - `exportExcel`
   - `previewImportCsv`
   - `applyImportCsv`
   - guarded `importCsv`
   - parsing/import helper methods
2. Keep existing import/export route URIs and names pointing to the new controller.
3. Leave direct import guard intact.
4. Add route tests proving root `StudentController@store/update/destroy` are not routed.
5. In a later phase, quarantine/delete legacy root `resources/views/students/create.blade.php` and `edit.blade.php`.
6. Fix class-teacher `students.show` link to `admin.students.show`.

If extraction feels too large for Phase 4L, the smallest alternative is:

- Change class-teacher `route('students.show')` to `route('admin.students.show')`.
- Add route safety tests proving legacy root CRUD routes remain inactive.

## Confirmation

- No application code was modified.
- No routes were modified.
- No views/controllers/models/migrations were modified.
- No import/apply/export route was executed against real/local MySQL.
- No students were created, updated, deleted, imported, exported, seeded, promoted, or mutated.
- No migrations or schema changes were run.
- No full test suite was run.
- Real/local MySQL was touched only by read-only count queries.
