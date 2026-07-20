# PHASE 4J - StudentsExport Normalization

## Files inspected

- `app/Exports/StudentsExport.php`
- `app/Http/Controllers/StudentController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `routes/web.php`
- `tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `docs/project-autopsy/PHASE_4I_STUDENT_EXPORT_SURFACE_AUDIT.md`
- `docs/project-autopsy/PHASE_4H_STUDENT_CSV_EXPORT_TEMPLATE_ALIGNMENT.md`

## Files changed

- `app/Exports/StudentsExport.php`
- `tests/Unit/Exports/StudentsExportTest.php`
- `docs/project-autopsy/PHASE_4J_STUDENTSEXPORT_NORMALIZATION.md`

## Previous raw export risk

Before Phase 4J, `StudentsExport.php` implemented only `FromCollection` and returned `Student::all()`.

Risks:

- No stable headings.
- No normalized export mapping.
- No eager loading for `schoolClass` or `section`.
- Future route wiring could expose raw model attributes instead of the Phase 4H import/export contract.

## New StudentsExport contract

`StudentsExport.php` now implements:

- `FromCollection`
- `WithHeadings`
- `WithMapping`

It no longer returns raw `Student::all()`.

The collection now uses:

```php
Student::with(['schoolClass', 'section'])->get()
```

## Heading behavior

Headings now match the Phase 4H normalized export/import contract:

- `ID`
- `Name`
- `Father Name`
- `Mother Name`
- `Date of Birth`
- `Aadhar Number`
- `Phone`
- `Mobile`
- `Gender`
- `Category`
- `Class ID`
- `Class`
- `Section ID`
- `Section`
- `Roll Number`
- `Religion`
- `Caste`
- `Blood Group`
- `Address`
- `Admission No`

## Mapping behavior

Mapped values now include normalized class/section fields:

- `Class ID` maps to `students.class_id`.
- `Class` maps to `schoolClass.name`, falling back to legacy `students.class`.
- `Section ID` maps to `students.section_id`.
- `Section` maps to `section.name`, falling back to legacy `students.section`.
- `Mobile` maps to `students.mobile`.
- `Admission No` maps to `students.admission_no`.

The legacy fallback remains for historical rows without canonical IDs.

## Relation/eager-loading behavior

The export eager-loads:

- `schoolClass`
- `section`

Because `Student` has both a `section` column and a `section()` relationship, the mapping uses `relationLoaded('section')` and `getRelation('section')` before falling back to `getAttribute('section')`.

## Route safety confirmation

No routes were added or changed.

Current active student CSV route remains:

- `GET students/export/csv`
- route name: `students.export.csv`
- controller: `StudentController@exportCsv`

`StudentsExport.php` remains unused by active routes after this phase. A test asserts that no active route action references `App\Exports\StudentsExport`.

## Tests created/updated

Created:

- `tests/Unit/Exports/StudentsExportTest.php`

Tests added:

- `students_export_has_normalized_headings`
- `students_export_maps_class_id_and_section_id`
- `students_export_uses_canonical_class_and_section_display_names`
- `students_export_includes_mobile_and_admission_no`
- `students_export_does_not_return_raw_student_columns_only`
- `students_export_handles_legacy_class_section_fallbacks`
- `no_active_route_uses_students_export_directly_yet`

The test uses an isolated SQLite-memory schema and does not use project migrations.

## Commands run

- `Get-Content app/Exports/StudentsExport.php`
- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content app/Models/Student.php`
- `Get-Content tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `Get-Content docs/project-autopsy/PHASE_4I_STUDENT_EXPORT_SURFACE_AUDIT.md`
- `rg -n "StudentsExport|students\\.export\\.csv|exportCSV|exportCsv|exportExcel|students/export" app resources routes tests`
- `Get-Content routes/web.php`
- `Get-Content app/Models/SchoolClass.php`
- `Get-Content app/Models/Section.php`
- `php -l app/Exports/StudentsExport.php`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l tests/Unit/Exports/StudentsExportTest.php`
- `php artisan route --name=students.export.csv`
- `php artisan route:list --name=students.export.csv`
- `php artisan test --filter=StudentsExportTest --env=testing`
- `php artisan test --filter=StudentCsvExportTemplateTest --env=testing`
- `php artisan test --filter=StudentImportApplyTest --env=testing`
- `git diff -- app/Exports/StudentsExport.php tests/Unit/Exports/StudentsExportTest.php`
- `git status --short`
- `Get-ChildItem tests/Unit/Exports`
- `Get-Content tests/Unit/Exports/StudentsExportTest.php`

Note:

- `php artisan route --name=students.export.csv` is not a valid Artisan command in this Laravel 12 app and returned route namespace help.
- Route verification was completed with `php artisan route:list --name=students.export.csv`.

## Test result summary

Syntax checks:

- `php -l app/Exports/StudentsExport.php` passed.
- `php -l app/Http/Controllers/StudentController.php` passed.
- `php -l tests/Unit/Exports/StudentsExportTest.php` passed.

Targeted tests:

- `php artisan test --filter=StudentsExportTest --env=testing`: passed, 7 tests / 1003 assertions.
- `php artisan test --filter=StudentCsvExportTemplateTest --env=testing`: passed, 7 tests / 26 assertions.
- `php artisan test --filter=StudentImportApplyTest --env=testing`: passed, 13 tests / 45 assertions.

Warnings:

- PHPUnit emitted pre-existing metadata deprecation warnings from unrelated tests that were discovered during filtered test bootstrapping.

## Confirmation no full suite was run

No full test suite was run. Only the targeted commands listed above were executed.

## Confirmation no migrations/schema/real MySQL data were touched

- No migrations were run.
- No schema changes were made.
- No import/apply actions were run against real/local MySQL.
- No export/download action was run against real/local MySQL.
- No real/local MySQL data was created, updated, deleted, seeded, imported, or exported.

## Remaining risks

1. `StudentController@exportExcel()` is normalized but still lacks a dedicated route-level test because no active Excel route exists.
2. Active CSV export still includes `ID` for reference only; admins must remember import creates new rows rather than updating by ID.
3. Historical student data still has one known `class_id` / `school_class_id` conflict from earlier audits.
4. `StudentsExport.php` is now safer but remains unused, so future route wiring should still be reviewed.

## Recommended next step

Phase 4K should audit whether root `StudentController` create/update/delete routes or other legacy student surfaces should remain active, be redirected to admin CRUD, or be normalized later. Keep import/export changes stable and avoid touching the apply flow unless a direct regression appears.
