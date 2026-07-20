# Phase 3I - Admin Student Index Filter Alignment

## Files Inspected

- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `resources/views/admin/students/index.blade.php`
- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `tests/Feature/Admin/AdminStudentFormCanonicalIdTest.php`
- `tests/Feature/Admin/StudentRouteAlignmentTest.php`
- `docs/project-autopsy/PHASE_3H_ADMIN_STUDENT_FORM_CANONICAL_IDS.md`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`

## Files Changed

- `app/Http/Controllers/Admin/AdminStudentController.php`
- `resources/views/admin/students/index.blade.php`
- `tests/Feature/Admin/AdminStudentIndexFilterTest.php`
- `docs/project-autopsy/PHASE_3I_ADMIN_STUDENT_INDEX_FILTER_ALIGNMENT.md`

## Previous Filter Behavior

- `AdminStudentController@index` already supported canonical class filtering through `class_id`.
- Section filtering used only the legacy `students.section` string field.
- The admin index view submitted the section filter as `section`.
- Existing live data stores `students.section` as numeric strings such as `"1"`, `"2"`, `"3"`, and `"4"`.
- The grouped class/section cards linked back with legacy `section`, not canonical `section_id`.

## New Section ID Filter Behavior

- `AdminStudentController@index` now reads `section_id` from the request.
- If `section_id` is present, the query filters by `students.section_id`.
- The admin index section filter now submits `section_id`.
- Section options use `sections.id` as values and `sections.name` as display text.
- Grouped class/section card links now prefer `section_id` when available.

## Legacy Section Fallback Behavior

Legacy `section` request values remain supported:

- Numeric legacy section values filter by `section_id = value OR section = value`.
- Non-numeric legacy section values attempt to resolve `Section::where('name', value)`.
- Resolved section names filter by `section_id = resolved id OR section = resolved id string OR section = original value`.
- Unresolved legacy section names fall back to `section = original value`.

This preserves compatibility for existing links and old data while moving the active admin UI toward canonical `section_id`.

## Class Filter Confirmation

- Existing `class_id` filtering remains unchanged.
- The class filter still uses `students.class_id`.
- No class create/update normalization logic was changed in this phase.

## Index View Changes

- The section select changed from `name="section"` to `name="section_id"`.
- Section option values now use `Section.id`.
- The JavaScript filter builder now appends `section_id`.
- Grouped card links now include `section_id` when available and only fall back to legacy `section` if `section_id` is missing.

## Tests Created/Updated

Created:

- `tests/Feature/Admin/AdminStudentIndexFilterTest.php`

Tests added:

- `admin_index_section_filter_uses_section_id`
- `admin_index_legacy_numeric_section_filter_still_matches_section_id`
- `admin_index_legacy_section_name_filter_resolves_to_section_id`
- `admin_index_class_id_filter_still_works`
- `admin_index_view_contains_section_id_filter_select`

The test file uses an isolated SQLite-memory schema and does not run project migrations.

## Commands Run

- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l tests/Feature/Admin/AdminStudentIndexFilterTest.php`
- `php artisan test --filter=AdminStudentIndexFilterTest --env=testing`
- `php artisan test --filter=AdminStudentFormCanonicalIdTest --env=testing`
- `php artisan test --filter=AdminStudentClassNormalizationTest --env=testing`
- `php artisan test --filter=StudentRouteAlignmentTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`

## Test Result Summary

- `AdminStudentIndexFilterTest`: 5 passed, 15 assertions.
- `AdminStudentFormCanonicalIdTest`: 4 passed, 14 assertions.
- `AdminStudentClassNormalizationTest`: 6 passed, 18 assertions.
- `StudentRouteAlignmentTest`: 2 passed, 4 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.

PHPUnit emitted existing metadata deprecation warnings from older doc-comment based tests. No targeted test failed.

## Failures And Fixes

- No failures occurred in the targeted Phase 3I verification commands.

## No Full Suite Confirmation

- The full test suite was not run.
- Only targeted test filters were executed.

## No Database Mutation Confirmation

- No migrations were run.
- No schema changes were made to application migrations.
- No real/local MySQL data was touched.
- The new test uses isolated SQLite-memory tables created inside the test lifecycle.

## Remaining Risks

- Existing live students with missing `section_id` still rely on the legacy fallback path.
- CSV/import, promotion, API student writes, and passed-out flows remain outside this phase.
- Some non-index views may still display legacy `students.section` instead of resolving `sections.name`.
- Existing historical data has not been backfilled or repaired.

## Recommended Next Step

Phase 3J should audit and normalize the next active student read/display surface, preferably class-section grouped display and detail pages, so UI labels can consistently show canonical section names while preserving legacy fallback behavior.
