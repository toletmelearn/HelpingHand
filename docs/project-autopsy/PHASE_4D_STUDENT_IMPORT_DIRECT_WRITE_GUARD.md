# Phase 4D - Student Import Direct Write Guard

## Files Inspected

- `routes/web.php`
- `app/Http/Controllers/StudentController.php`
- `app/Services/Students/StudentImportNormalizer.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/import-preview.blade.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `docs/project-autopsy/PHASE_4C_STUDENT_IMPORT_PREVIEW.md`
- `docs/project-autopsy/PHASE_4B_STUDENT_IMPORT_NORMALIZER_DRY_RUN.md`

## Files Changed

- `app/Http/Controllers/StudentController.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `docs/project-autopsy/PHASE_4D_STUDENT_IMPORT_DIRECT_WRITE_GUARD.md`

## Direct Import Guard Behavior

`StudentController@importCsv()` now returns before validation, parsing, transactions, or writes:

```php
return redirect()->route('students.index')
    ->with('warning', 'Direct CSV import is temporarily disabled. Please use Preview first. Safe import apply flow is not enabled yet.');
```

This keeps the existing route registered:

```text
POST students/import/csv
route: students.import.csv
controller: StudentController@importCsv
```

Old direct links/forms receive a controlled redirect instead of a hard route failure.

## Direct Import Write Confirmation

The direct import route no longer reaches:

- file validation
- CSV parsing
- Excel parsing
- `DB::beginTransaction()`
- `Student::create()`
- `DB::commit()`

The old legacy write logic remains below the guard for future safe-apply refactor context, but it is unreachable from `StudentController@importCsv()` in Phase 4D.

## Preview Route Preservation

These routes remain active:

- `students.import.csv.preview`
- `students.export.csv`
- `students.import.csv`

Route-list verification showed:

- `POST students/import/csv students.import.csv`
- `POST students/import/csv/preview students.import.csv.preview`

## Visible Form Route Confirmation

`resources/views/students/index.blade.php` already points the visible upload form to:

```php
route('students.import.csv.preview')
```

The helper text remains:

```text
Preview only; no students are imported from this form.
```

No visible form was changed back to the direct import write route.

## Apply / Import Flow Confirmation

No apply/import confirmation flow was added.

No new write route was added.

No import-now/apply/confirm controls were added.

## Tests Created / Updated

Created:

- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`

The test uses isolated SQLite-memory schema only. It does not use full project migrations, seeders, or real/local MySQL.

Tests added:

- `direct_import_route_remains_registered_but_does_not_import`
- `direct_import_returns_controlled_warning_or_redirect`
- `direct_import_does_not_call_legacy_write_path`
- `preview_route_still_renders_preview`
- `visible_import_form_still_points_to_preview_route`

## Commands Run

- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content routes/web.php | Select-Object -Skip 292 -First 14`
- `Get-Content resources/views/students/index.blade.php | Select-Object -Skip 102 -First 24`
- `Get-Content resources/views/students/import-preview.blade.php`
- `Get-Content tests/Feature/Students/StudentImportPreviewTest.php`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `php artisan route --name=students.import.csv`
- `php artisan route --name=students.import.csv.preview`
- `php artisan route:list --name=students.import.csv`
- `php artisan route:list --name=students.import.csv.preview`
- `php artisan test --filter=StudentImportDirectRouteGuardTest --env=testing`
- `php artisan test --filter=StudentImportPreviewTest --env=testing`
- `php artisan test --filter=StudentImportNormalizerTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`

Note: `php artisan route --name=...` is not valid in this Laravel 12 project and returned Artisan route namespace help. The safe equivalent `php artisan route:list --name=...` was run successfully.

## Test Result Summary

- Syntax check passed for `StudentController.php`.
- Syntax check passed for `StudentImportDirectRouteGuardTest.php`.
- Route-list verification showed the guarded direct import route and preview route.
- `StudentImportDirectRouteGuardTest`: 5 passed, 14 assertions.
- `StudentImportPreviewTest`: 7 passed, 26 assertions.
- `StudentImportNormalizerTest`: 10 passed, 21 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.

PHPUnit emitted existing unrelated doc-comment metadata deprecation warnings during test discovery. No targeted test failed.

## Safety Confirmations

- No apply/import flow was added.
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

## Remaining Risks

- The old legacy import write code still exists below the Phase 4D guard and should be refactored or removed when a safe apply flow is implemented.
- Export/sample CSV still uses legacy class/section columns only.
- Preview warnings do not yet block any apply flow because no apply flow exists.
- The direct route still exists by design, but it now redirects instead of writing.

## Recommended Next Step

Phase 4E should design the safe apply flow before enabling any writes:

1. Require preview normalization to pass with zero errors.
2. Use normalized `class_id`, `school_class_id`, `class`, `section_id`, and `section`.
3. Treat duplicate warnings as blockers or require explicit admin resolution.
4. Add isolated apply tests before enabling real imports.
5. Keep direct legacy import writes disabled until the normalized apply path is proven.
