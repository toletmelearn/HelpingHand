# Phase 3E - Admin Student Class Normalization

## Files Inspected

- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `tests/Unit/Models/StudentClassCompatibilityTest.php`
- `docs/project-autopsy/PHASE_3B_CLASS_DATA_COMPATIBILITY_MAP.md`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`
- `docs/project-autopsy/PHASE_3D_ADMIN_STUDENT_WRITE_PATH_AUDIT.md`

## Files Changed

- `app/Http/Controllers/Admin/AdminStudentController.php`
- `tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`

## Normalization Helper Summary

Added `normalizeClassSectionPayload(array $validated, Request $request): array` to `AdminStudentController`.

Class behavior:

- Prefers submitted `class_id`.
- Falls back to resolving submitted string `class` by `SchoolClass::where('name', ...)`.
- If a `SchoolClass` is resolved, writes:
  - `class_id = school_classes.id`
  - `school_class_id = school_classes.id`
  - `class = school_classes.name`
- If no class can be resolved, preserves the existing legacy `class` string and does not crash.

Section behavior:

- Prefers submitted `section_id`.
- Falls back to numeric string `section` as a section ID.
- Falls back to resolving non-numeric string `section` by `Section::where('name', ...)`.
- If a `Section` is resolved, writes:
  - `section_id = sections.id`
  - `section = (string) sections.id`
- If no section can be resolved, preserves the existing legacy `section` string and does not crash.

## Store Method Change Summary

`AdminStudentController@store` now validates optional `class_id` and `section_id`, normalizes the validated payload, fills the model, explicitly assigns compatibility FK fields, and saves the student.

This applies only to admin student create writes.

## Update Method Change Summary

`AdminStudentController@update` now validates optional `class_id` and `section_id`, normalizes the validated payload, fills the existing student, explicitly assigns compatibility FK fields, and saves the student.

This applies only to admin student update writes.

## Student Fillable / Assignment Decision

`Student::$fillable` was not changed.

Reason: the safest narrow fix is to keep mass assignment surface unchanged and explicitly assign the compatibility fields in `AdminStudentController` only:

- `class_id`
- `school_class_id`
- `section_id`

This avoids broadening mass assignment behavior across import, promotion, API, passed-out, or other student write paths.

## Admin Create/Edit View Changes

No Blade view changes were made.

The helper supports the current form field names and also supports future `class_id` / `section_id` inputs.

## Tests Created / Updated

Updated:

- `tests/Feature/Admin/AdminStudentClassNormalizationTest.php`

The test uses an isolated SQLite-memory schema created inside the test lifecycle. It does not use full project migrations, `RefreshDatabase`, seeders, or real/local MySQL.

Tests covered:

- Admin store with `class_id` sets `class_id`, `school_class_id`, and canonical class name.
- Admin store with class string resolves `SchoolClass`.
- Admin store with `section_id` sets `section_id` and legacy section string ID.
- Admin update keeps class and section fields consistent.
- Unresolved legacy class string is preserved without crashing.
- Unresolved legacy section string is preserved without crashing.

## Commands Run

- `php -l app/Http/Controllers/Admin/AdminStudentController.php`
- `php -l app/Models/Student.php`
- `php -l tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `php artisan tinker --env=testing --execute="dump(app()->environment()); dump(config('database.default')); dump(config('database.connections.sqlite.database'));"`
- `php artisan test --filter=AdminStudentClassNormalizationTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`
- `php artisan test --filter=BellTimingTodayRouteTest --env=testing`
- `php artisan test --filter=ApiAccessControlAbilityTest --env=testing`
- `php artisan test --filter=SanctumTokenAbilityTest --env=testing`

## Test Result Summary

- Syntax check passed for `AdminStudentController.php`.
- Syntax check passed for `Student.php`.
- Syntax check passed for `AdminStudentClassNormalizationTest.php`.
- Test environment verified as:
  - environment: `testing`
  - database default: `sqlite`
  - SQLite database: `:memory:`
- `AdminStudentClassNormalizationTest`: 6 passed, 18 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.
- `BellTimingTodayRouteTest`: 2 passed, 18 assertions.
- `ApiAccessControlAbilityTest`: 10 passed, 10 assertions.
- `SanctumTokenAbilityTest`: 6 passed, 19 assertions.

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated tests. They did not fail the targeted runs.

## Failures and Fixes

Initial targeted normalization run had one test fixture issue:

- The unresolved legacy section sample used `Legacy Section Z`, which exceeded the controller's existing `section|max:10` validation.

Fix:

- Changed the test fixture to `Legacy Z`, which remains unresolved but valid under the existing validation contract.

No application logic change was required for that failure.

## Data / Migration Safety Confirmations

- Existing students were not bulk-mutated.
- No migrations were run.
- No schema files were changed.
- No full test suite was run.
- No real/local MySQL data was touched.
- Test schema changes were limited to isolated SQLite-memory tables inside targeted tests.

## Remaining Risks

- Existing conflicting student rows remain unchanged, including the known `class_id != school_class_id` conflict from Phase 3B.
- Admin create/edit views still primarily submit legacy class/section fields; this phase intentionally avoided UI redesign.
- Import, promotion, API student writes, passed-out flow, and root student controller writes remain outside this phase and may still create drift.
- Legacy `students.section` remains an ID string for compatibility, which is intentional for now but should eventually be replaced by canonical `section_id` reads.
- `Student::$fillable` still omits `school_class_id` and `section_id`; this is safe for this phase because admin writes explicitly assign them, but other future write paths must account for it.

## Recommended Next Step

Phase 3F should audit the admin create/edit UI and root `StudentController` write paths for class/section field alignment, without changing imports or promotions yet. After that, apply the same normalization pattern to one additional low-risk write path at a time.
