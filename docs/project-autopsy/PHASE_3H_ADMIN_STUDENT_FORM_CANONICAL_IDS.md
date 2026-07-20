# Phase 3H - Admin Student Form Canonical IDs

## Files Inspected

- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `tests/Feature/Admin/StudentRouteAlignmentTest.php`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`
- `docs/project-autopsy/PHASE_3G_STUDENT_ROUTE_UI_ALIGNMENT.md`

## Files Changed

- `app/Http/Controllers/Admin/AdminStudentController.php`
- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `tests/Feature/Admin/AdminStudentFormCanonicalIdTest.php`
- `docs/project-autopsy/PHASE_3H_ADMIN_STUDENT_FORM_CANONICAL_IDS.md`

## Create Form Class/Section Field Changes

`resources/views/admin/students/create.blade.php` now submits canonical IDs:

- Class select uses `name="class_id"`.
- Class option values use `SchoolClass.id`.
- Class option display uses `SchoolClass.name`.
- Selection is preserved with `old('class_id')`.
- Section select uses `name="section_id"`.
- Section option values use `Section.id`.
- Section option display uses `Section.name`.
- Selection is preserved with `old('section_id')`.

To support this, `AdminStudentController@create` now passes:

- `$classList = SchoolClass::orderBy('class_order')->get()`
- `$sections = Section::orderBy('name')->get()`

## Edit Form Class/Section Field Changes

`resources/views/admin/students/edit.blade.php` now submits canonical IDs:

- Class select uses `name="class_id"`.
- Class option values use `SchoolClass.id`.
- Selected class prefers `old('class_id', $student->canonicalClassId())`.
- Section select uses `name="section_id"`.
- Section option values use `Section.id`.
- Selected section prefers `old('section_id', $student->section_id)`.

The edit form no longer relies on `$student->section` matching a section name, which protects existing numeric-string section data.

## Backend Validation Compatibility

The Phase 3E backend fallback remains intact.

`AdminStudentController@store` and `@update` still validate:

- `class_id` as nullable integer existing in `school_classes`
- `section_id` as nullable integer existing in `sections`
- legacy `class` as nullable string fallback required only when `class_id` is absent
- legacy `section` as nullable string fallback

The normalization helper still:

- prefers `class_id`
- falls back to string `class`
- prefers `section_id`
- falls back to numeric/string `section`

No API, CSV import/export, promotion, passed-out, or root `StudentController` write logic was changed.

## Tests Created / Updated

Created:

- `tests/Feature/Admin/AdminStudentFormCanonicalIdTest.php`

Tests added:

- `admin_create_form_contains_class_id_select`
- `admin_create_form_contains_section_id_select`
- `admin_edit_form_selects_student_canonical_class_id`
- `admin_edit_form_selects_student_section_id`

Existing tests still cover:

- `admin_store_with_class_id_sets_class_id_school_class_id_and_class_name`
- `admin_store_with_section_id_sets_section_id_and_section_string_id`
- `admin_update_keeps_class_fields_consistent`

The new test file uses an isolated SQLite-memory schema only. It does not use full project migrations or real/local MySQL.

## Commands Run

- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `php -l tests/Feature/Admin/StudentRouteAlignmentTest.php`
- `php -l tests/Feature/Admin/AdminStudentFormCanonicalIdTest.php`
- `php artisan test --filter=AdminStudentFormCanonicalIdTest --env=testing`
- `php artisan test --filter=AdminStudentClassNormalizationTest --env=testing`
- `php artisan test --filter=StudentRouteAlignmentTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`

## Test Result Summary

- Syntax check passed for `AdminStudentController.php`.
- Syntax check passed for `AdminStudentClassNormalizationTest.php`.
- Syntax check passed for `StudentRouteAlignmentTest.php`.
- Syntax check passed for `AdminStudentFormCanonicalIdTest.php`.
- `AdminStudentFormCanonicalIdTest`: 4 passed, 14 assertions.
- `AdminStudentClassNormalizationTest`: 6 passed, 18 assertions.
- `StudentRouteAlignmentTest`: 2 passed, 4 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated tests. They did not fail the targeted runs.

## Failures and Fixes

Initial `AdminStudentFormCanonicalIdTest` run failed because the isolated schema omitted `roles` and `role_user`, while app authorization checks call `User::hasRole('admin')`.

Fix:

- Added minimal `roles` and `role_user` tables to the isolated test schema.
- Attached the synthetic test user to an `admin` role.

Second run failed because disabled middleware meant the normal shared Blade `$errors` variable was absent during direct view rendering.

Fix:

- Added `$this->withViewErrors([])` in the test setup.

No application logic was changed to fix those test-harness issues.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was touched.
- No student data was bulk-updated, inserted, deleted, imported, seeded, promoted, or passed out.
- CSV import/export was not changed.
- API StudentController was not changed.
- Root StudentController resource routes were not reactivated.
- Phase 3E backend fallback remains in place.

## Remaining Risks

- CSV import still writes legacy class/section strings only.
- Root student create/edit views still contain legacy forms, though `/students/create` redirects to canonical admin create after Phase 3G.
- Admin index filters still use `class_id` plus legacy `section` string; a later phase should consider moving section filters to `section_id`.
- Existing student data was not repaired; known historical conflicts remain.

## Recommended Next Step

Phase 3I should audit and normalize admin student index/filter section behavior, or create a read-only import normalization plan before touching CSV import. The safer next code step is likely converting admin filters from legacy `section` string to `section_id` while preserving compatibility query fallback.
