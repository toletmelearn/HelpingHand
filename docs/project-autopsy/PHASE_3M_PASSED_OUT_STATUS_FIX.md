# Phase 3M - Passed-Out Status Fix

## Files Inspected

- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/StudentPromotionLog.php`
- `database/migrations/2026_01_29_090059_create_student_statuses_table.php`
- `tests/Feature/Admin/StudentPromotionNormalizationTest.php`
- `docs/project-autopsy/PHASE_3L_PASSED_OUT_STATUS_SYSTEM_AUDIT.md`
- `docs/project-autopsy/PHASE_3K_STUDENT_PROMOTION_NORMALIZATION.md`

## Files Changed

- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `tests/Feature/Admin/StudentPassedOutStatusTest.php`
- `tests/Feature/Admin/StudentPromotionNormalizationTest.php`
- `docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`

## Passed-Out Status Storage Fix Summary

`StudentPromotionController@markAsPassedOut()` no longer writes the missing `students.status` column.

The passed-out status is now recorded through `StudentStatus::create()` using the existing `student_statuses` table.

## StudentStatus Creation Summary

The passed-out operation now creates a `student_statuses` row with:

- `student_id`
- `status = passed_out`
- `status_date = now()->toDateString()`
- `reason = Passed out`
- `remarks = request remarks or default`
- `issued_by = Auth::id()` cast to string when available

This follows the existing `StudentStatus` model/table contract.

## Class / Section Clearing Behavior

Before creating the promotion log, the original class label is captured from:

1. `$student->schoolClass->name`
2. `$student->class`
3. `Unknown`

Then the student compatibility fields are cleared consistently:

- `class_id = null`
- `school_class_id = null`
- `class = Passed Out`
- `section_id = null`
- `section = null`

These fields are assigned explicitly and saved, preserving the current `Student::$fillable` surface.

## Promotion Log Behavior

The passed-out flow still creates `StudentPromotionLog`.

The log now uses the captured original class label for `from_class`, so it does not accidentally record `Passed Out` as the source class after mutation.

Preserved behavior:

- `to_class = Passed Out`
- `promoted_by = Auth::id()`
- `promoted_at = now()`
- `remarks` uses request remarks or default text

## Transaction Safety Summary

`markAsPassedOut()` now wraps the following operations in `DB::transaction()`:

- student compatibility field update
- `StudentStatus::create()`
- `StudentPromotionLog::create()`

This keeps the status record, class/section clearing, and promotion log together for the happy path.

## Promotion Store Not Changed

`StudentPromotionController@store()` was not intentionally changed in this phase.

The Phase 3K promotion normalization tests were rerun to confirm promotion store still:

- writes `class_id`
- writes `school_class_id`
- writes `class`
- preserves section fields
- creates promotion logs

## Routes Not Changed

No routes were changed.

Known route risk remains:

- `Route::resource('student-promotions', ...)` still exposes resource methods that are not implemented on `StudentPromotionController`.

## Tests Created / Updated

Created:

- `tests/Feature/Admin/StudentPassedOutStatusTest.php`

Updated:

- `tests/Feature/Admin/StudentPromotionNormalizationTest.php`

Tests added:

- `passed_out_creates_student_status_record`
- `passed_out_does_not_require_students_status_column`
- `passed_out_clears_class_and_section_compatibility_fields`
- `passed_out_promotion_log_uses_original_class_label`
- `passed_out_operation_is_transaction_wrapped_for_happy_path`

The new test uses an isolated SQLite-memory schema and intentionally omits `students.status`.

## Commands Run

- `Get-Content app/Http/Controllers/Admin/StudentPromotionController.php`
- `Get-Content app/Models/StudentStatus.php`
- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/StudentPromotionLog.php`
- `Get-Content database/migrations/2026_01_29_090059_create_student_statuses_table.php`
- `Get-Content docs/project-autopsy/PHASE_3L_PASSED_OUT_STATUS_SYSTEM_AUDIT.md`
- `php -l app/Http/Controllers/Admin/StudentPromotionController.php`
- `php -l tests/Feature/Admin/StudentPassedOutStatusTest.php`
- `php -l tests/Feature/Admin/StudentPromotionNormalizationTest.php`
- `php artisan test --filter=StudentPassedOutStatusTest --env=testing`
- `php artisan test --filter=StudentPromotionNormalizationTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`
- `php artisan test --filter=AdminStudentClassNormalizationTest --env=testing`
- `php artisan test --filter=AdminStudentIndexFilterTest --env=testing`
- `php artisan test --filter=AdminStudentFormCanonicalIdTest --env=testing`
- `php artisan test --filter=StudentRouteAlignmentTest --env=testing`

## Test Result Summary

- `StudentPassedOutStatusTest`: 5 passed, 19 assertions.
- `StudentPromotionNormalizationTest`: 5 passed, 17 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.
- `AdminStudentClassNormalizationTest`: 6 passed, 18 assertions.
- `AdminStudentIndexFilterTest`: 5 passed, 15 assertions.
- `AdminStudentFormCanonicalIdTest`: 4 passed, 14 assertions.
- `StudentRouteAlignmentTest`: 2 passed, 4 assertions.

PHPUnit emitted existing doc-comment metadata deprecation warnings from older tests. They did not fail the targeted runs.

## Failures And Fixes

Initial `StudentPassedOutStatusTest` run failed because the isolated `academic_sessions` table did not include `deleted_at`, while `AcademicSession::current()` applies soft delete scope.

Fix applied:

- Added `softDeletes()` to the isolated test `academic_sessions` schema.

The first regression run for `StudentPromotionNormalizationTest` failed because its old safety assertion still expected the previous broken `students.status` write. The test was updated to assert the new boundary:

- promotion store still exists
- passed-out now uses `StudentStatus::create()`
- the old `students.status` plus class-null update pattern is absent

## No Full Suite Confirmation

- The full test suite was not run.
- Only targeted filters requested for this phase were executed.

## No Database Mutation Confirmation

- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was touched.
- No seeders, imports, promotion operations, or passed-out operations were run against real/local MySQL.
- All new test data lived only in isolated SQLite-memory tables.

## Remaining Risks

- Student promotion resource routes still expose unimplemented `show`, `edit`, `update`, and `destroy` methods.
- `AdvancedReportController` still appears to query `students.status`, which is incompatible with the confirmed status storage system.
- Student status show view may still reference a missing `currentClass` relationship.
- Existing historical data was not repaired or reconciled.

## Recommended Next Step

Phase 3N should be a read-only route/report stability audit for the student status area:

1. Decide whether to quarantine unimplemented `student-promotions` resource routes.
2. Audit `AdvancedReportController` status queries against `student_statuses`.
3. Audit `student-statuses/show.blade.php` for `currentClass` relationship safety.
