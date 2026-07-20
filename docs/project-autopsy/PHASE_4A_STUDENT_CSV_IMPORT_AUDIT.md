# Phase 4A - Student CSV Import Audit

## Files Inspected

- `routes/web.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `app/Exports/StudentsExport.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/admin/students/index.blade.php`
- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`
- `docs/project-autopsy/PHASE_3H_ADMIN_STUDENT_FORM_CANONICAL_IDS.md`
- `docs/project-autopsy/PHASE_3X_TERMINAL_STATUS_RECONCILIATION_ADMIN_REPORT.md`

## Commands Run

- `Get-Content routes/web.php`
- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content app/Http/Controllers/Admin/AdminStudentController.php`
- `Get-Content app/Models/Student.php`
- `Get-Content resources/views/students/index.blade.php`
- `Get-Content resources/views/students/create.blade.php`
- `Get-Content resources/views/students/edit.blade.php`
- `Get-Content resources/views/admin/students/index.blade.php`
- `Get-Content resources/views/admin/students/create.blade.php`
- `Get-Content resources/views/admin/students/edit.blade.php`
- `Get-Content app/Models/SchoolClass.php`
- `Get-Content app/Models/Section.php`
- `Get-Content app/Exports/StudentsExport.php`
- `Get-Content docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`
- `Get-Content docs/project-autopsy/PHASE_3H_ADMIN_STUDENT_FORM_CANONICAL_IDS.md`
- `Get-Content docs/project-autopsy/PHASE_3X_TERMINAL_STATUS_RECONCILIATION_ADMIN_REPORT.md`
- `Get-ChildItem -Recurse -File | Where-Object { $_.Name -match '(csv|sample|import|export)' } | Select-Object FullName`
- `rg -n "importCsv|exportCsv|exportCSV|exportExcel|students\\.import|students\\.export|csv_file|CSV|Excel|Student::create|class_id|school_class_id|section_id|class'|section'" app resources routes docs -g "*.php" -g "*.blade.php" -g "*.md"`
- `rg -n "view\\('students\\.index'|students\\.import\\.csv|students\\.export\\.csv|csv_file|downloadSampleCSV|route\\('students\\.store'|route\\('students\\.update'|/students/import/csv|/students/export/csv" app resources routes -g "*.php" -g "*.blade.php"`
- `rg -n "function importCsv|function exportCSV|function exportCsv|Student::create\\(|DB::beginTransaction|readCSVFile|readExcelFile|parseDate|class' => \\$row|section' => \\$row" app/Http/Controllers/StudentController.php`
- `rg -n "students/export/csv|students/import/csv|Global students routes|Route::get\\('students'|students/create" routes/web.php`
- `rg -n 'name="class_id"|name="section_id"|name="class"|name="section"|students\\.import\\.csv|students\\.export\\.csv' resources/views/admin/students resources/views/students -g "*.blade.php"`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l app/Models/Student.php`
- `php artisan route | Select-String "students"`
- `php artisan route | Select-String "csv"`
- `php artisan route | Select-String "import"`
- `php artisan route | Select-String "export"`
- `php artisan route:list | Select-String "students"`
- `php artisan route:list | Select-String "csv"`
- `php artisan route:list | Select-String "import"`
- `php artisan route:list | Select-String "export"`
- `php artisan route:list --name=students.export.csv`
- `php artisan route:list --name=students.import.csv`
- `php artisan route:list --name=students.index`
- `php artisan route:list --name=students.import.csv -vv`
- `php artisan route:list --name=students.export.csv -vv`
- `php artisan tinker --execute="dump(['students_total' => DB::table('students')->count(), 'null_class_id_with_class' => DB::table('students')->whereNull('class_id')->whereNotNull('class')->where('class', '<>', '')->count(), 'null_school_class_id_with_class_id' => DB::table('students')->whereNotNull('class_id')->whereNull('school_class_id')->count(), 'null_section_id_with_section' => DB::table('students')->whereNull('section_id')->whereNotNull('section')->where('section', '<>', '')->count(), 'class_id_conflicts' => DB::table('students')->whereNotNull('class_id')->whereNotNull('school_class_id')->whereColumn('class_id', '<>', 'school_class_id')->count()]);"`
- `php artisan tinker --execute="dump(['legacy_classes_without_class_id' => DB::table('students')->whereNull('class_id')->whereNotNull('class')->where('class', '<>', '')->distinct()->pluck('class'), 'legacy_sections_without_section_id' => DB::table('students')->whereNull('section_id')->whereNotNull('section')->where('section', '<>', '')->distinct()->pluck('section')]);"`

Note: `php artisan route | Select-String ...` is not valid in this Laravel 12 project and returned Artisan route namespace help. The read-only equivalent `php artisan route:list | Select-String ...` was run to collect route evidence.

## Import / Export Route Map

| Method | URI | Route Name | Controller Method | Middleware | Active | Controller Source |
|---|---|---|---|---|---|---|
| `GET` / `HEAD` | `students/export/csv` | `students.export.csv` | `StudentController@exportCsv` | `web`, `auth`, CSRF/session middleware, bindings | Yes | root `App\Http\Controllers\StudentController` |
| `POST` | `students/import/csv` | `students.import.csv` | `StudentController@importCsv` | `web`, `auth`, CSRF/session middleware, bindings | Yes | root `App\Http\Controllers\StudentController` |

The import/export routes are inside the authenticated web group. They are not inside the canonical admin student CRUD controller and are not protected by the admin student normalization helper.

Related student routes:

- `/students` is active and maps to `AdminStudentController@index`.
- `/students/create` redirects to `admin.students.create` after Phase 3G.
- `Route::resource('students', StudentController::class)` remains commented out.
- API route names `students.store` / `students.update` still exist under `/api/v1`, but the root student create/edit views are no longer intended as active admin create/update UI.

## Import Method Findings

Method:

```php
StudentController#importCsv(Request $request)
```

Accepted upload:

- Field: `csv_file`
- Validation: `required|file|mimes:csv,txt,xlsx,xls`
- CSV/TXT parsed through `readCSVFile()`
- XLSX/XLS parsed through `readExcelFile()`

Expected positional columns:

| Index | Header From Export / Sample | Imported Field |
|---:|---|---|
| 0 | `ID` | ignored |
| 1 | `Name` | `name` |
| 2 | `Father Name` | `father_name` |
| 3 | `Mother Name` | `mother_name` |
| 4 | `Date of Birth` | `date_of_birth` via `parseDate()` |
| 5 | `Aadhar Number` | `aadhar_number` |
| 6 | `Phone` | `phone` |
| 7 | `Gender` | `gender` lowercased |
| 8 | `Category` | `category` |
| 9 | `Class` | legacy `class` string |
| 10 | `Section` | legacy `section` string |
| 11 | `Roll Number` | `roll_number` |
| 12 | `Religion` | `religion` |
| 13 | `Caste` | `caste` |
| 14 | `Blood Group` | `blood_group` |
| 15 | `Address` | `address` |

Write behavior:

- Creates new `Student` records with `Student::create([...])`.
- Does not update existing students.
- Does not write `class_id`.
- Does not write `school_class_id`.
- Does write legacy `class` string from column 9.
- Does not write `section_id`.
- Does write legacy `section` string from column 10.
- Does not write `admission_no`.
- Does not write `mobile`; it writes `phone`.
- Does not create users or passwords.
- Does not set student status.
- Does not call the Phase 3E admin normalization helper.

Transaction behavior:

- Wraps the loop in `DB::beginTransaction()`, `commit()`, and `rollBack()`.
- Rolls back and returns on the first row exception.
- This avoids partial import for database exceptions inside the loop, but the method still lacks preflight row validation/dry-run reporting.

Duplicate / validation behavior:

- There is no row-level validation equivalent to admin create/update validation.
- There is no explicit duplicate check before insert for `aadhar_number`, `roll_number`, `admission_no`, email, or phone/mobile.
- Duplicate/invalid data only fails if the database/model layer throws an exception.
- Re-importing an exported CSV will create new rows rather than update existing rows because exported `ID` is ignored.

Mass assignment behavior:

- Uses `Student::create([...])`.
- `Student::$fillable` includes `class_id` but omits `school_class_id` and `section_id`.
- The import payload does not include those canonical/compatibility fields anyway.

## Export Method Findings

Method:

```php
StudentController@exportCSV()
```

Exported columns:

- `ID`
- `Name`
- `Father Name`
- `Mother Name`
- `Date of Birth`
- `Aadhar Number`
- `Phone`
- `Gender`
- `Category`
- `Class`
- `Section`
- `Roll Number`
- `Religion`
- `Caste`
- `Blood Group`
- `Address`

Export field behavior:

- Exports legacy `$student->class`, not canonical `school_classes.name` through `class_id`.
- Exports legacy `$student->section`, not canonical `sections.name` or `section_id`.
- Does not export `class_id`.
- Does not export `school_class_id`.
- Does not export `section_id`.
- Export format matches the current import expectations, but that match preserves legacy-only class/section data flow.

Other export class:

- `app/Exports/StudentsExport.php` exists and returns `Student::all()` as a raw collection.
- No active student CSV route was found using this export class during this audit.

## UI Exposure Findings

Active canonical admin student views:

- `resources/views/admin/students/index.blade.php` does not show CSV import/export controls.
- `resources/views/admin/students/create.blade.php` submits canonical `class_id` and `section_id`.
- `resources/views/admin/students/edit.blade.php` submits canonical `class_id` and `section_id`.

Legacy root student views:

- `resources/views/students/index.blade.php` contains the visible CSV import/export card:
  - export button uses `route('students.export.csv')`
  - import form posts to `route('students.import.csv')`
  - upload field name is `csv_file`
  - visible file accept is `.csv`
  - JavaScript sample CSV uses legacy `Class` and `Section` columns with values like `Class 5` and `A`
- `resources/views/students/create.blade.php` still posts to `route('students.store')` and uses legacy `class` / `section`, but `/students/create` redirects to canonical admin create.
- `resources/views/students/edit.blade.php` still posts to `route('students.update')`, but root resource routes are not active.

Reachability:

- The root `students.index` route now maps to `AdminStudentController@index`, so the legacy `students.index` Blade is not reached by the active `/students` route.
- The CSV import/export endpoints remain active independently and can still be called directly by authenticated web users.
- No admin student page currently advertises the CSV import/export routes, based on inspected views and route helper searches.

## Current CSV Drift Risks

1. Import creates students with legacy `class` and `section` only, bypassing `class_id`, `school_class_id`, and `section_id`.
2. Export/sample CSV format reinforces legacy-only class/section columns and omits canonical IDs.
3. Import is active under generic authenticated web middleware, not a specific admin normalization path.
4. Re-importing exported data creates new students because exported `ID` is ignored.
5. There is no preflight dry-run validation, so row problems surface as runtime exceptions during import.
6. Import writes `phone` but not `mobile`, while canonical admin workflows use and search `mobile`.
7. Import does not write `admission_no`, which can produce records that do not align with admin search/report expectations.
8. Section values from the sample are names like `A`, while current compatibility policy stores legacy `students.section` as the section ID string.
9. `school_class_id` and `section_id` are not fillable, so any future import normalization must explicitly assign them or use a dedicated helper/service.
10. Duplicate checks are not performed before insert; a row-level failure rolls back the batch but gives limited structured feedback.

## Read-Only Data Risk Counts

Read-only snapshot:

| Check | Count |
|---|---:|
| Total students | 760 |
| Students with null `class_id` but non-empty legacy `class` | 0 |
| Students with non-null `class_id` but null `school_class_id` | 0 |
| Students with null `section_id` but non-empty legacy `section` | 0 |
| Students where `class_id != school_class_id` | 1 |
| Distinct legacy class strings without `class_id` | none |
| Distinct legacy section strings without `section_id` | none |

Interpretation:

- Current live data does not show widespread import-style string-only class/section drift.
- The active import path can still create that drift if used.
- The known historical `class_id` / `school_class_id` conflict remains outside this phase.

## Recommended Import Normalization Behavior

Future import should not directly create students row-by-row from raw positional values. Recommended behavior:

1. Add a read-only/dry-run validation path first.
2. Parse CSV/Excel rows into named fields based on headers.
3. Resolve class:
   - Prefer `class_id` if supplied and valid.
   - Else resolve `Class` / `class` by exact `school_classes.name`.
   - If resolved, set:
     - `class_id = school_classes.id`
     - `school_class_id = school_classes.id`
     - `class = school_classes.name`
   - If unresolved, collect a row error and skip import/apply.
4. Resolve section:
   - Prefer `section_id` if supplied and valid.
   - Else if section is numeric, treat it as a section ID.
   - Else resolve by `sections.name`.
   - If resolved, set:
     - `section_id = sections.id`
     - `section = (string) sections.id`
   - If unresolved, collect a row error and skip import/apply.
5. Validate required fields before transaction.
6. Detect duplicates before transaction:
   - `aadhar_number`
   - `roll_number` where present
   - `admission_no` if future template includes it
   - any future email/user identifiers
7. Keep all database writes inside a transaction only after the dry-run/preflight passes.
8. Return row-numbered errors without importing partial data.

Recommended CSV template changes after dry-run support:

- Add `class_id` and `section_id` columns.
- Keep `Class` and `Section` as display/fallback columns temporarily.
- Export canonical `class_id` / `section_id` plus display names to avoid encouraging legacy-only re-imports.

## Safe Phase 4B Implementation Plan

Safest first code task:

1. Create a student import normalizer/dry-run validator without changing active import writes yet.
2. Reuse the Phase 3E normalization rules, preferably by extracting shared class/section resolution into a small service instead of duplicating controller-private logic.
3. Add a dry-run report for uploaded files:
   - rows that would import
   - rows with unresolved class/section
   - duplicate identifiers
   - rows that would create legacy-only drift
4. Add isolated SQLite-memory tests for the normalizer/dry-run detector.
5. Keep the current import route active but consider displaying a warning or routing the UI to dry-run first.
6. Only after dry-run behavior is tested, update `StudentController#importCsv` to require a clean preflight before creating records.
7. Do not bulk repair existing students in Phase 4B.
8. Do not change export behavior until import normalization and dry-run validation are settled.

If import is considered too risky to leave callable before implementation:

- A smaller safety phase could temporarily quarantine `students.import.csv` or make it return a controlled warning page.
- That would be a route/policy decision and should be done separately from the read-only audit.

## Confirmation

- No application code was modified.
- No routes were modified.
- No views were modified.
- No models were modified.
- No migrations were run.
- No schema files were changed.
- No import/export action was executed.
- No student data was created, updated, deleted, seeded, imported, exported, promoted, passed out, or otherwise mutated.
- Database access was limited to read-only count/pluck inspection.
