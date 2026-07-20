# PHASE 4H - Student CSV Export / Template Alignment

## Files inspected

- `app/Http/Controllers/StudentController.php`
- `app/Exports/StudentsExport.php`
- `app/Services/Students/StudentImportNormalizer.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/import-preview.blade.php`
- `routes/web.php`
- `tests/Feature/Students/StudentImportApplyTest.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `tests/Unit/Services/StudentImportNormalizerTest.php`
- `docs/project-autopsy/PHASE_4G_STUDENT_CSV_EXPORT_TEMPLATE_AUDIT.md`
- `docs/project-autopsy/PHASE_4F_STUDENT_IMPORT_APPLY_FLOW.md`

## Files changed

- `app/Http/Controllers/StudentController.php`
- `resources/views/students/index.blade.php`
- `tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `docs/project-autopsy/PHASE_4H_STUDENT_CSV_EXPORT_TEMPLATE_ALIGNMENT.md`

## CSV export header changes

`StudentController@exportCSV()` now exports these headers:

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

This adds normalized import contract fields:

- `Class ID`
- `Section ID`
- `Mobile`
- `Admission No`

## CSV export value mapping

`StudentController@exportCSV()` now eager-loads:

- `schoolClass`
- `section`

Export mapping:

- `Class ID` uses `$student->class_id`.
- `Class` uses `$student->schoolClass?->name ?? $student->class`.
- `Section ID` uses `$student->section_id`.
- `Section` uses the eager-loaded `section` relation name when available, falling back to the legacy `section` attribute.
- `Mobile` uses `$student->mobile`.
- `Admission No` uses `$student->admission_no`.

Because `Student` has both a `section` column and a `section()` relation, the export uses `relationLoaded('section')` and `getRelation('section')` for the display name to avoid the column/relation name collision.

`ID` remains exported for reference only. Import apply still creates new students and does not update existing students by `ID`.

## Sample CSV/template changes

`resources/views/students/index.blade.php` sample CSV now uses the same normalized headers as CSV export:

- `Class ID`
- `Class`
- `Section ID`
- `Section`
- `Mobile`
- `Admission No`

Sample values include:

- `Class ID`: `5`
- `Class`: `Class 5`
- `Section ID`: `1`
- `Section`: `A`
- `Mobile`: `9876543210`
- `Admission No`: `ADM-001`

Helper text was added near the import/sample area:

`Class ID and Section ID are preferred for import accuracy. ID is for reference only; import creates new students and does not update existing rows.`

The visible import form still posts to preview, not direct import.

## Import normalizer compatibility confirmation

`StudentImportNormalizer` already supported:

- `Class ID`
- `class_id`
- `School Class ID`
- `school_class_id`
- `Section ID`
- `section_id`
- `Class`
- `Section`

No normalizer resolution logic change was required.

However, the preview parser previously passed only numeric CSV rows to the normalizer. Since the normalized template changes column positions, `StudentController@previewImportCsv()` now combines each data row with the header row before normalization.

This keeps old positional fallback support while adding header-name keys such as:

- `Class ID`
- `Section ID`
- `Mobile`
- `Admission No`

## Excel export decision

`StudentController@exportExcel()` used the same legacy headers as CSV export.

Because it was straightforward and low-risk, it was aligned in this phase too:

- adds `Mobile`
- adds `Class ID`
- adds `Section ID`
- adds `Admission No`
- uses canonical class/section display fallbacks
- eager-loads `schoolClass` and `section`

`app/Exports/StudentsExport.php` remains unchanged because it is not used by the current CSV route and has no headings/mapping contract.

## Tests created/updated

Created:

- `tests/Feature/Students/StudentCsvExportTemplateTest.php`

Tests cover:

- CSV export includes `Class ID` and `Section ID` headers.
- CSV export uses canonical class and section display names.
- CSV export includes `Mobile` and `Admission No`.
- Sample CSV includes `Class ID` and `Section ID`.
- Sample CSV displays the reference-ID warning.
- Import normalizer accepts the new export template headers.
- Import apply still accepts the new template format.

## Commands run

- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Services/Students/StudentImportNormalizer.php`
- `php -l tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `php artisan route --name=students.export.csv`
- `php artisan route:list --name=students.export.csv`
- `php artisan test --filter=StudentCsvExportTemplateTest --env=testing`
- `php artisan test --filter=StudentImportApplyTest --env=testing`
- `php artisan test --filter=StudentImportPreviewTest --env=testing`
- `php artisan test --filter=StudentImportDirectRouteGuardTest --env=testing`
- `php artisan test --filter=StudentImportNormalizerTest --env=testing`

Notes:

- `php artisan route --name=students.export.csv` is not a valid command in this Laravel 12 app. It returned route namespace help.
- Equivalent route verification was completed with `php artisan route:list --name=students.export.csv`.
- Targeted tests emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests during discovery. They did not fail the targeted tests.

## Test result summary

- `StudentCsvExportTemplateTest`: 7 passed, 26 assertions
- `StudentImportApplyTest`: 13 passed, 45 assertions
- `StudentImportPreviewTest`: 8 passed, 28 assertions
- `StudentImportDirectRouteGuardTest`: 5 passed, 14 assertions
- `StudentImportNormalizerTest`: 10 passed, 21 assertions

## Confirmation

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No real/local MySQL data was touched.
- No import/apply action was run against real/local MySQL.
- Direct import guard remains unchanged.
- Import apply logic remains intact and targeted tests still pass.

## Remaining risks

- `StudentsExport.php` is still a raw `Student::all()` export class if another future route wires it in.
- The CSV export still includes `ID`, which is useful for reference but can still be misunderstood as an update key.
- Current import duplicate detection blocks duplicate values against the database, but does not yet provide a richer duplicate review UI.
- The root student CSV UI remains visually rough and still contains unrelated bulk action placeholders.
- Large CSV files still use session-backed apply payloads from Phase 4F; a token/file-backed flow may be better later.

## Recommended next step

Phase 4I should audit `StudentsExport.php` and any Excel/export routes to decide whether to remove unused raw export behavior, formally wire it to the normalized export contract, or leave it quarantined/documented.
