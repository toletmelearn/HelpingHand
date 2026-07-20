# Phase 3K - Student Promotion Normalization

## Files Inspected

- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/StudentPromotionLog.php`
- `tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `tests/Unit/Models/StudentClassCompatibilityTest.php`
- `docs/project-autopsy/PHASE_3J_STUDENT_PROMOTION_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`

## Files Changed

- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `tests/Feature/Admin/StudentPromotionNormalizationTest.php`
- `docs/project-autopsy/PHASE_3K_STUDENT_PROMOTION_NORMALIZATION.md`

## Promotion Normalization Summary

`StudentPromotionController@store` now promotes selected students by explicitly assigning:

- `class_id = $destinationClass->id`
- `school_class_id = $destinationClass->id`
- `class = $destinationClass->name`

The assignment is explicit rather than mass-assigned because `Student::$fillable` does not include `school_class_id`. This keeps the broader mass-assignment surface unchanged while still ensuring promotion writes keep compatibility class fields synchronized.

## Transaction Safety Summary

The selected student updates and `StudentPromotionLog` creation now run inside `DB::transaction()`.

This means promotion class updates and log creation succeed or fail together for the selected students.

## Log Behavior Summary

Promotion logs still use the existing schema and fields:

- `student_id`
- `academic_session_id`
- `from_class`
- `to_class`
- `promoted_by`
- `promoted_at`
- `remarks`

`from_class` remains the source `SchoolClass` name captured before mutation. `to_class` remains the destination `SchoolClass` name. Existing `Auth::id()` behavior for `promoted_by` was preserved.

## Section Fields Preserved

Promotion does not alter section placement because the promotion UI does not currently collect a destination section.

Preserved fields:

- `section_id`
- `section`

## Passed-Out Flow Not Changed

`markAsPassedOut()` was intentionally not changed in this phase.

Known remaining passed-out risks from Phase 3J still apply:

- The live `students` table appeared to lack a `status` column.
- `markAsPassedOut()` still writes `status = passed_out`.
- It still does not clear `school_class_id`, `section_id`, or `section`.

## Tests Created / Updated

Created:

- `tests/Feature/Admin/StudentPromotionNormalizationTest.php`

Tests added:

- `promotion_sets_class_id_school_class_id_and_class_name`
- `promotion_preserves_section_id_and_legacy_section`
- `promotion_creates_promotion_log`
- `promotion_rejects_destination_class_not_higher_than_source`
- `promotion_does_not_touch_passed_out_flow`

The test uses an isolated SQLite-memory schema and does not run project migrations, seeders, or real/local MySQL.

## Commands Run

- `Get-Content app/Http/Controllers/Admin/StudentPromotionController.php`
- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/SchoolClass.php`
- `Get-Content app/Models/StudentPromotionLog.php`
- `Get-Content docs/project-autopsy/PHASE_3J_STUDENT_PROMOTION_WRITE_AUDIT.md`
- `php -l app/Http/Controllers/Admin/StudentPromotionController.php`
- `php -l tests/Feature/Admin/StudentPromotionNormalizationTest.php`
- `php artisan test --filter=StudentPromotionNormalizationTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`
- `php artisan test --filter=AdminStudentClassNormalizationTest --env=testing`
- `php artisan test --filter=AdminStudentIndexFilterTest --env=testing`
- `php artisan test --filter=AdminStudentFormCanonicalIdTest --env=testing`
- `php artisan test --filter=StudentRouteAlignmentTest --env=testing`

## Test Result Summary

- `StudentPromotionNormalizationTest`: 5 passed, 16 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.
- `AdminStudentClassNormalizationTest`: 6 passed, 18 assertions.
- `AdminStudentIndexFilterTest`: 5 passed, 15 assertions.
- `AdminStudentFormCanonicalIdTest`: 4 passed, 14 assertions.
- `StudentRouteAlignmentTest`: 2 passed, 4 assertions.

PHPUnit emitted existing doc-comment metadata deprecation warnings from older tests. They did not fail the targeted runs.

## Failures And Fixes

Initial `StudentPromotionNormalizationTest` run failed because the isolated test schema did not include `roles` and `role_user`, which the existing student audit logging service reads during `Student` updates.

Fix applied:

- Added minimal `roles` and `role_user` tables to the isolated test schema.

No application production schema or migration files were changed.

## No Full Suite Confirmation

- The full test suite was not run.
- Only targeted test filters requested for this phase were executed.

## No Database Mutation Confirmation

- No migrations were run.
- No schema files were changed.
- No seeders, imports, promotions, or passed-out actions were run against real/local MySQL.
- All new test data lived only in isolated SQLite-memory tables.

## Remaining Risks

- Historical class FK conflicts were not bulk repaired.
- `markAsPassedOut()` remains risky and likely schema-incompatible until a dedicated passed-out/status phase.
- `Route::resource('student-promotions', ...)` still exposes unimplemented resource methods noted in Phase 3J.
- Promotion still preserves section fields; if the school requires destination section changes during promotion, that needs a separate UI and controller design phase.

## Recommended Next Step

Phase 3L should address passed-out/status safely:

1. Reconfirm whether active status should live in `student_statuses` instead of `students.status`.
2. Fix `markAsPassedOut()` without adding schema drift.
3. Decide whether passed-out should clear `class_id`, `school_class_id`, `section_id`, and `section`.
4. Add isolated tests for passed-out behavior.
