# Phase 4C - Student Import Preview

## Files Inspected

- `app/Http/Controllers/StudentController.php`
- `app/Services/Students/StudentImportNormalizer.php`
- `resources/views/students/index.blade.php`
- `routes/web.php`
- `tests/Unit/Services/StudentImportNormalizerTest.php`
- `docs/project-autopsy/PHASE_4B_STUDENT_IMPORT_NORMALIZER_DRY_RUN.md`
- `docs/project-autopsy/PHASE_4A_STUDENT_CSV_IMPORT_AUDIT.md`

## Files Changed

- `app/Http/Controllers/StudentController.php`
- `routes/web.php`
- `resources/views/students/import-preview.blade.php`
- `resources/views/students/index.blade.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `docs/project-autopsy/PHASE_4C_STUDENT_IMPORT_PREVIEW.md`

## Preview Route Added

Added a preview-only route beside the existing student CSV import/export routes:

```php
Route::post(
    'students/import/csv/preview',
    [App\Http\Controllers\StudentController::class, 'previewImportCsv']
)->name('students.import.csv.preview');
```

Route details:

- Method: `POST`
- URI: `students/import/csv/preview`
- Name: `students.import.csv.preview`
- Controller: `StudentController@previewImportCsv`
- Middleware: same authenticated web group as current student import/export routes

Existing routes were preserved:

- `students.export.csv`
- `students.import.csv`

## Controller Preview Behavior

Added:

```php
StudentController@previewImportCsv(Request $request, StudentImportNormalizer $normalizer)
```

Behavior:

- Validates upload with `required|file|mimes:csv,txt,xlsx,xls`.
- Parses CSV/TXT using existing `readCSVFile()`.
- Parses XLSX/XLS using existing `readExcelFile()`.
- Skips the header row.
- Skips empty rows.
- Calls `StudentImportNormalizer::normalizeRow($row, $index + 1)` for each data row.
- Builds summary counts:
  - `total_rows`
  - `valid_rows`
  - `rows_with_errors`
  - `rows_with_warnings`
- Returns `resources/views/students/import-preview.blade.php`.

Safety behavior:

- Does not call `Student::create()`.
- Does not call `Student::update()`.
- Does not call `save()`.
- Does not open a database transaction.
- Does not invoke the active import write loop.

## Preview View Behavior

Created:

```text
resources/views/students/import-preview.blade.php
```

The view displays:

- Clear warning:
  - `Preview only - no students have been imported.`
- Summary cards for:
  - total rows
  - valid rows
  - rows with errors
  - rows with warnings
- Per-row table showing:
  - row number
  - original class
  - original section
  - normalized `class_id`
  - normalized `school_class_id`
  - normalized `class`
  - normalized `section_id`
  - normalized `section`
  - errors
  - warnings

## Visible Form Change Summary

Updated the legacy student index import form:

```text
resources/views/students/index.blade.php
```

The visible CSV upload form now posts to:

```php
route('students.import.csv.preview')
```

The button text was changed from `Upload` to `Preview`.

Helper text added:

```text
Preview only; no students are imported from this form.
```

The active write route `students.import.csv` still exists but is no longer the visible form target in this view.

## Apply / Import Controls Confirmation

No apply/import confirmation controls were added.

The preview page contains no:

- import-now button
- apply button
- confirm-import button
- write form
- POST form

## Tests Created / Updated

Created:

- `tests/Feature/Students/StudentImportPreviewTest.php`

The test uses isolated SQLite-memory schema only. It does not use full project migrations, seeders, or real/local MySQL.

Minimal tables:

- `students`
- `school_classes`
- `sections`

Tests added:

- `preview_route_exists_and_is_post_only`
- `preview_upload_parses_rows_and_returns_summary`
- `preview_uses_normalizer_and_shows_normalized_class_section`
- `preview_reports_row_errors_without_importing_students`
- `preview_reports_warnings_without_importing_students`
- `preview_page_does_not_show_apply_or_import_now_controls`
- `visible_import_form_points_to_preview_route`

## Commands Run

- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content app/Services/Students/StudentImportNormalizer.php`
- `Get-Content resources/views/students/index.blade.php`
- `Get-Content routes/web.php | Select-Object -Skip 286 -First 20`
- `Get-Content docs/project-autopsy/PHASE_4B_STUDENT_IMPORT_NORMALIZER_DRY_RUN.md`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Services/Students/StudentImportNormalizer.php`
- `php -l tests/Feature/Students/StudentImportPreviewTest.php`
- `php artisan route --name=students.import.csv.preview`
- `php artisan route:list --name=students.import.csv.preview`
- `php artisan test --filter=StudentImportPreviewTest --env=testing`
- `php artisan test --filter=StudentImportNormalizerTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`

Note: `php artisan route --name=students.import.csv.preview` is not valid in this Laravel 12 project and returned Artisan route namespace help. The safe equivalent `php artisan route:list --name=students.import.csv.preview` was run successfully and showed the new POST route.

## Test Result Summary

- Syntax check passed for `StudentController.php`.
- Syntax check passed for `StudentImportNormalizer.php`.
- Syntax check passed for `StudentImportPreviewTest.php`.
- Route-list verification showed:
  - `POST students/import/csv/preview students.import.csv.preview`
- `StudentImportPreviewTest`: 7 passed, 26 assertions.
- `StudentImportNormalizerTest`: 10 passed, 21 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.

PHPUnit emitted existing unrelated doc-comment metadata deprecation warnings during test discovery. No targeted test failed.

## Active Import Write Behavior

`StudentController@importCsv` write behavior was not changed.

It still currently:

- parses uploaded files
- opens a transaction
- loops over data rows
- calls `Student::create([...])`
- writes legacy `class`
- writes legacy `section`
- does not write `class_id`
- does not write `school_class_id`
- does not write `section_id`

This phase only added a preview route and changed the visible upload form to target preview.

## Safety Confirmations

- No students were imported.
- No students were created.
- No students were updated.
- No students were deleted.
- No seeders were run.
- No migrations were run.
- No schema files were changed.
- No full test suite was run.
- No real/local MySQL data was touched.
- Test schema changes were limited to isolated SQLite-memory tables.
- No apply/import confirmation write route was added.

## Remaining Risks

- `students.import.csv` remains active and can still be called directly.
- Active import writes still bypass canonical class/section normalization.
- Export and sample CSV still use legacy class/section columns only.
- Preview is not yet required server-side before direct import writes.
- Duplicate checks are warnings in preview and not active import blockers yet.

## Recommended Next Step

Phase 4D should quarantine or guard the direct write route until a safe apply flow exists.

Recommended safe sequence:

1. Keep preview route active.
2. Make direct `students.import.csv` either unavailable from UI or return a controlled warning unless a later clean-preview token/session is present.
3. Add tests proving direct import no longer bypasses preview.
4. Only after that, implement a separate apply flow that imports rows using normalized `class_id`, `school_class_id`, `class`, `section_id`, and `section`.
