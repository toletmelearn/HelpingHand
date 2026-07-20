# PHASE 4F - Student Import Apply Flow

## Files inspected

- `app/Http/Controllers/StudentController.php`
- `app/Services/Students/StudentImportNormalizer.php`
- `app/Models/Student.php`
- `resources/views/students/import-preview.blade.php`
- `resources/views/students/index.blade.php`
- `routes/web.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `tests/Unit/Services/StudentImportNormalizerTest.php`
- `docs/project-autopsy/PHASE_4E_SAFE_STUDENT_IMPORT_APPLY_FLOW_AUDIT.md`
- `docs/project-autopsy/PHASE_4D_STUDENT_IMPORT_DIRECT_WRITE_GUARD.md`
- `docs/project-autopsy/PHASE_4C_STUDENT_IMPORT_PREVIEW.md`

## Files changed

- `app/Http/Controllers/StudentController.php`
- `routes/web.php`
- `resources/views/students/import-preview.blade.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `tests/Feature/Students/StudentImportApplyTest.php`
- `docs/project-autopsy/PHASE_4F_STUDENT_IMPORT_APPLY_FLOW.md`

## Preview session behavior

`StudentController@previewImportCsv()` now creates a session-backed apply payload only when the preview is clean.

Session key:

- `student_import_preview`

Stored payload:

- `preview_id`
- `created_at`
- `hash`
- `rows`
- `summary`

If the preview has row errors or warnings, any previous `student_import_preview` session payload is cleared and no applyable payload is stored.

Preview remains read-only. It does not create, update, or import students.

## Apply route added

Added one POST-only route:

- URI: `students/import/csv/apply`
- Route name: `students.import.csv.apply`
- Controller: `StudentController@applyImportCsv`

No GET, DELETE, or apply/repair route variants were added.

## Apply method behavior

`StudentController@applyImportCsv()` now:

- Reads the `student_import_preview` session payload.
- Rejects missing previews.
- Rejects expired previews after a 30-minute TTL.
- Validates submitted `preview_id`.
- Validates submitted `hash`.
- Recomputes the stored row hash before applying.
- Rejects previews whose summary contains errors or warnings.
- Also inspects stored rows directly for errors or warnings.
- Rechecks duplicates immediately before writing:
  - `aadhar_number`
  - `roll_number`
  - `phone`
  - `mobile`
- Imports all students inside one `DB::transaction()`.
- Clears the preview session after successful apply.
- Prevents repeated apply by clearing the session.

## Duplicate-warning blocking behavior

Duplicate warnings block apply.

Warnings block at two points:

- Preview with duplicate warnings does not store an applyable session payload.
- Apply rechecks duplicates against the database before writing and rejects if duplicate values now exist.

This covers the common race where a duplicate is introduced after preview but before apply.

## Normalized class/section write behavior

Apply writes normalized class and section compatibility fields:

- `class_id`
- `school_class_id`
- `class`
- `section_id`
- `section`

Because `school_class_id` and `section_id` are not currently in `Student::$fillable`, apply explicitly assigns them before saving each `Student`.

Legacy `section` remains the section ID string for compatibility.

## Transaction safety summary

All apply writes run inside a single `DB::transaction()`.

The targeted transaction test creates a duplicate `roll_number` conflict during apply and verifies the first inserted row is rolled back when the second row fails.

## Direct import guard confirmation

`StudentController@importCsv()` still returns immediately with the Phase 4D warning:

`Direct CSV import is temporarily disabled. Please use Preview first. Safe import apply flow is not enabled yet.`

The old legacy import loop remains unreachable. It was not re-enabled.

## Users/password confirmation

The apply flow creates only `Student` records.

It does not:

- create `User` records
- set passwords
- introduce default password `123456`

## Tests created/updated

Created:

- `tests/Feature/Students/StudentImportApplyTest.php`

Updated:

- `tests/Feature/Students/StudentImportPreviewTest.php`

Tests added/updated cover:

- clean preview shows Apply button
- preview with errors hides Apply button
- preview with duplicate warnings hides Apply button
- missing preview session is rejected
- mismatched preview hash is rejected
- clean preview imports normalized class/section fields
- preview session is cleared after success
- repeated apply is prevented
- duplicates are rechecked before writing
- apply is transactional and rolls back on failure
- apply does not create users or passwords
- direct import route remains guarded
- apply route is POST-only

## Commands run

- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Services/Students/StudentImportNormalizer.php`
- `php -l tests/Feature/Students/StudentImportApplyTest.php`
- `php -l routes/web.php`
- `php artisan route --name=students.import.csv.apply`
- `php artisan route --name=students.import.csv`
- `php artisan route --name=students.import.csv.preview`
- `php artisan route:list --name=students.import.csv.apply`
- `php artisan route:list --name=students.import.csv`
- `php artisan route:list --name=students.import.csv.preview`
- `php artisan test --filter=StudentImportApplyTest --env=testing`
- `php artisan test --filter=StudentImportPreviewTest --env=testing`
- `php artisan test --filter=StudentImportDirectRouteGuardTest --env=testing`
- `php artisan test --filter=StudentImportNormalizerTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`

Notes:

- `php artisan route --name=...` is not a valid command in this Laravel 12 app. It returned route namespace help.
- Equivalent route verification was completed with `php artisan route:list --name=...`.
- Targeted tests printed existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests during discovery. They did not fail the targeted tests.

## Test result summary

- `StudentImportApplyTest`: 13 passed, 45 assertions
- `StudentImportPreviewTest`: 8 passed, 28 assertions
- `StudentImportDirectRouteGuardTest`: 5 passed, 14 assertions
- `StudentImportNormalizerTest`: 10 passed, 21 assertions
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions

## Failures and fixes

Initial `StudentImportApplyTest` run failed because the test harness disables middleware, which also removed the request-bound session store.

Fix:

- Switched the new preview/apply session access from `$request->session()` to Laravel's `session()` helper.

This preserves web-session behavior in the real app while allowing isolated middleware-bypassed tests to exercise the flow.

## Confirmation

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No real/local MySQL data was touched.
- No export behavior was changed.
- No users were created by the apply flow.
- No passwords were created by the apply flow.
- No default password `123456` was introduced.
- Direct legacy import remains guarded.

## Remaining risks

- Export/sample CSV still exposes legacy Class/Section-only columns.
- The old legacy import loop still exists below the direct import guard and should be removed or converted in a future cleanup phase.
- Apply duplicate checks currently block duplicates found in the database; future work may add explicit duplicate detection within the uploaded preview itself.
- The import flow still relies on the current CSV column order for legacy positional rows.
- Larger uploads may eventually need temporary file/token storage instead of session payload storage.

## Recommended next step

Phase 4G should audit and align student CSV export/template columns with the normalized import apply contract, without changing the already guarded direct import route.
