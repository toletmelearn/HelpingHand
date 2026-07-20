# PHASE 4M - Student Import / Export Controller Extraction

## Scope

Phase 4M extracted the active student import/export responsibilities out of the legacy root `StudentController` into a dedicated controller while preserving the existing route URIs, route names, and behavior.

No migrations, schema changes, real imports, real exports, real applies, or real/local MySQL data mutations were performed.

## Files Inspected

- `routes/web.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Services/Students/StudentImportNormalizer.php`
- `app/Exports/StudentsExport.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/import-preview.blade.php`
- `tests/Feature/Students/StudentImportApplyTest.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`
- `docs/project-autopsy/PHASE_4L_STUDENT_ROUTE_SAFETY_REGRESSION.md`
- `docs/project-autopsy/PHASE_4F_STUDENT_IMPORT_APPLY_FLOW.md`
- `docs/project-autopsy/PHASE_4H_STUDENT_CSV_EXPORT_TEMPLATE_ALIGNMENT.md`

## Files Changed

- `app/Http/Controllers/StudentImportExportController.php`
- `app/Http/Controllers/StudentController.php`
- `routes/web.php`
- `tests/Feature/Students/StudentImportExportControllerExtractionTest.php`
- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`
- `docs/project-autopsy/PHASE_4M_STUDENT_IMPORT_EXPORT_CONTROLLER_EXTRACTION.md`

## New Controller Summary

Created `App\Http\Controllers\StudentImportExportController` as the dedicated home for root student import/export surfaces.

The controller now owns:

- `exportCSV()`
- `exportExcel()`
- `previewImportCsv()`
- `applyImportCsv()`
- guarded `importCSV()`
- CSV/Excel parsing helpers
- preview hashing and expiry helpers
- duplicate recheck helper
- import payload mapping helper
- export class/section display helpers

## Methods Moved

The active import/export and preview/apply methods were removed from root `StudentController` and moved into `StudentImportExportController`.

Root `StudentController` still retains dormant legacy CRUD methods for this phase. Those methods were not deleted or normalized.

## Route URI / Name Preservation

The following route URIs and names were preserved exactly, with only the controller target changed:

| URI | Route name | Controller |
| --- | --- | --- |
| `students/export/csv` | `students.export.csv` | `StudentImportExportController@exportCsv` |
| `students/import/csv/preview` | `students.import.csv.preview` | `StudentImportExportController@previewImportCsv` |
| `students/import/csv/apply` | `students.import.csv.apply` | `StudentImportExportController@applyImportCsv` |
| `students/import/csv` | `students.import.csv` | `StudentImportExportController@importCsv` |

No admin student CRUD route, API route, legacy `/students/create` redirect, or route name was changed.

## Direct Import Guard Confirmation

The direct import route remains registered but guarded.

`StudentImportExportController@importCSV()` still redirects with:

`Direct CSV import is temporarily disabled. Please use Preview first. Safe import apply flow is not enabled yet.`

It does not parse for write import, open a transaction, call `Student::create()`, or mutate student data.

## Preview / Apply Behavior Preservation

Preview behavior remains preview-only:

- validates uploaded CSV/Excel file
- parses rows
- normalizes rows through `StudentImportNormalizer`
- stores an applyable session payload only when the preview has zero errors and zero warnings
- imports zero students during preview

Apply behavior remains session-backed and guarded:

- requires preview session
- checks preview ID and payload hash
- rejects expired previews
- rejects summaries with errors or warnings
- rechecks duplicates before writing
- writes normalized class/section fields
- uses `DB::transaction()`
- clears the preview session after success
- prevents repeated apply

## Export Behavior Preservation

CSV export behavior remains aligned with the Phase 4H contract:

- includes `Class ID`
- includes `Section ID`
- includes `Mobile`
- includes `Admission No`
- uses canonical class display fallback
- uses canonical section display fallback
- eager-loads `schoolClass` and `section`

No new export routes were added.

## Root StudentController Cleanup Summary

Root `StudentController` no longer contains the active import/export code or its private helper methods.

It retains dormant legacy CRUD methods only. A small Phase 4M comment notes that import/export routes were moved to `StudentImportExportController`.

## Tests Created / Updated

Created:

- `tests/Feature/Students/StudentImportExportControllerExtractionTest.php`

Updated:

- `tests/Feature/Students/StudentRouteSafetyRegressionTest.php`

The extraction test verifies:

- import/export routes point to `StudentImportExportController`
- direct import remains guarded
- preview still works
- apply still imports from clean preview
- CSV export still emits normalized headers
- root `StudentController` CRUD routes remain inactive

## Commands Run

Static checks:

- `php -l app/Http/Controllers/StudentImportExportController.php`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l routes/web.php`
- `php -l tests/Feature/Students/StudentImportExportControllerExtractionTest.php`

Route checks:

- `php artisan route --path=students`
- `php artisan route --path=admin/students`
- `php artisan route:list --path=students`
- `php artisan route:list --path=admin/students`

Note: this Laravel app did not expose the requested bare `php artisan route --path=...` command form, so `route:list --path=...` was used as the read-only fallback.

Targeted tests:

- `php artisan test --filter=StudentImportExportControllerExtractionTest --env=testing`
- `php artisan test --filter=StudentRouteSafetyRegressionTest --env=testing`
- `php artisan test --filter=StudentImportApplyTest --env=testing`
- `php artisan test --filter=StudentImportPreviewTest --env=testing`
- `php artisan test --filter=StudentImportDirectRouteGuardTest --env=testing`
- `php artisan test --filter=StudentCsvExportTemplateTest --env=testing`
- `php artisan test --filter=StudentsExportTest --env=testing`

Additional inspection:

- `git status --short`

## Test Result Summary

Passed:

- `StudentImportExportControllerExtractionTest`: 6 passed, 39 assertions
- `StudentRouteSafetyRegressionTest`: 10 passed, 34 assertions
- `StudentImportApplyTest`: 13 passed, 45 assertions
- `StudentImportPreviewTest`: 8 passed, 28 assertions
- `StudentImportDirectRouteGuardTest`: 5 passed, 14 assertions
- `StudentCsvExportTemplateTest`: 7 passed, 26 assertions
- `StudentsExportTest`: 7 passed, 1003 assertions

Observed warnings:

- PHPUnit reported existing doc-comment metadata deprecation warnings in unrelated tests. These were warnings only and did not fail the targeted test runs.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No real/local MySQL import was run.
- No real/local MySQL export/download was run.
- No real/local MySQL apply flow was run.
- No student data was created, updated, deleted, imported, exported, or applied against real/local MySQL.
- Direct legacy import remains guarded.
- Route URIs and names for import/export remained unchanged.

## Remaining Risks

1. Root `StudentController` still contains dormant legacy CRUD methods, though root web CRUD routes remain inactive.
2. Legacy root student views still exist and may confuse future development if re-wired accidentally.
3. The route table still includes API route names such as `students.store`, `students.show`, `students.update`, and `students.destroy`, so Blade code must continue to prefer `admin.students.*`.
4. Import/export concerns now have a dedicated controller, but routes still live in the broader web route file.
5. The working tree is very dirty from prior phases and unrelated project changes; future diffs should remain carefully scoped.

## Recommended Next Step

Phase 4N should be a read-only audit of dormant root `StudentController` CRUD methods and legacy root student Blade views, with a decision on whether to quarantine, redirect, or delete those legacy surfaces later.
