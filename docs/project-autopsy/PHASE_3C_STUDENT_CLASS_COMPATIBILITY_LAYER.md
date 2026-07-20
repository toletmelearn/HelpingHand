# Phase 3C - Student Class Compatibility Layer

Date: 2026-06-04  
Project: HelpingHand  
Mode: Small code change plus isolated SQLite-memory tests

## Files Inspected

- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `docs/project-autopsy/PHASE_3A_CLASS_SYSTEM_HARMONIZATION_AUDIT.md`
- `docs/project-autopsy/PHASE_3B_CLASS_DATA_COMPATIBILITY_MAP.md`
- `tests/Feature/API/SanctumTokenAbilityTest.php`
- `tests/Feature/API/ApiAccessControlAbilityTest.php`
- `tests/Feature/API/BellTimingTodayRouteTest.php`
- `.env.testing`
- `phpunit.xml`

## Files Changed

- `app/Models/Student.php`
- `tests/Unit/Models/StudentClassCompatibilityTest.php`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`

Note: `Student.php` already had broader uncommitted model changes before this phase. Phase 3C only added the non-mutating class compatibility methods described below.

## Student Compatibility Methods Added

Added to `App\Models\Student`:

- `canonicalClassId(): ?int`
- `hasClassIdConflict(): bool`
- `resolveCanonicalSchoolClass(): ?SchoolClass`
- `classCompatibilityStatus(): array`
- private helper `classCompatibilitySource(): string`

No write, backfill, save, update, insert, delete, or mutation logic was added.

## Canonical FK Behavior Summary

`canonicalClassId()` behavior:

1. If `students.class_id` is present, return it.
2. Else if `students.school_class_id` is present, return it.
3. Else return `null`.

This matches the Phase 3B recommendation:

- Prefer `students.class_id` as the canonical class FK.
- Keep `students.school_class_id` as a fallback/compatibility value for now.
- Do not mutate or normalize live data yet.

## Conflict Detection Behavior Summary

`hasClassIdConflict()` returns `true` only when:

- `class_id` is present,
- `school_class_id` is present,
- and the two integer values differ.

Otherwise it returns `false`.

This is designed to flag the known Phase 3B conflict:

- Student `id=301`
- `class_id=11`
- `school_class_id=8`
- string `class=Class 8`

## Compatibility Status Shape

`classCompatibilityStatus()` returns:

```php
[
    'canonical_class_id' => ...,
    'class_id' => ...,
    'school_class_id' => ...,
    'string_class' => ...,
    'has_conflict' => ...,
    'source' => ...,
]
```

Source values:

- `class_id`
- `school_class_id_fallback`
- `none`

## Existing Student Relationships Preserved

Existing relationships were not removed:

- `schoolClass()` remains `belongsTo(SchoolClass::class, 'class_id')`.
- `class()` remains `belongsTo(SchoolClass::class, 'class_id')`.
- `section()` remains `belongsTo(Section::class, 'section_id')`.

`resolveCanonicalSchoolClass()` was added as a safe resolver method instead of replacing existing relationship methods. It uses `SchoolClass::find($this->canonicalClassId())` and therefore supports the fallback behavior without changing Eloquent relationship semantics.

## Test File Created

Created:

- `tests/Unit/Models/StudentClassCompatibilityTest.php`

The test does not use `RefreshDatabase` and does not run Laravel migrations. It creates and drops only the minimal SQLite-memory test tables inside the test lifecycle:

- `students`
- `school_classes`

## Tests Added

Added six targeted unit tests:

- `canonical_class_id_prefers_class_id_when_both_exist`
- `canonical_class_id_falls_back_to_school_class_id_when_class_id_missing`
- `class_id_conflict_is_detected_when_ids_differ`
- `class_id_conflict_is_false_when_ids_match`
- `class_compatibility_status_reports_source_and_conflict`
- `canonical_school_class_resolves_using_preferred_class_id`

Implementation note:

- Test Student instances use `forceFill()` because `school_class_id` is intentionally not mass assignable today.
- `school_classes` rows needed for resolver tests are inserted into the isolated SQLite-memory schema only.

## Commands Run

```powershell
Get-Content app/Models/Student.php
Get-Content app/Models/SchoolClass.php
Get-Content app/Models/Section.php
Get-Content tests/Feature/API/SanctumTokenAbilityTest.php
Get-Content tests/Feature/API/ApiAccessControlAbilityTest.php
Get-Content tests/Feature/API/BellTimingTodayRouteTest.php
Get-Content .env.testing
Get-Content phpunit.xml
Test-Path tests/Unit/Models; if ($?) { Get-ChildItem tests/Unit -Recurse | Select-Object FullName }
Get-Content app/Traits/Auditable.php
php -l app/Models/Student.php
php -l tests/Unit/Models/StudentClassCompatibilityTest.php
php artisan tinker --env=testing --execute="dump(app()->environment()); dump(config('database.default')); dump(config('database.connections.sqlite.database'));"
php artisan test --filter=StudentClassCompatibilityTest --env=testing
php artisan test --filter=SanctumTokenAbilityTest --env=testing
php artisan test --filter=ApiAccessControlAbilityTest --env=testing
php artisan test --filter=BellTimingTodayRouteTest --env=testing
git diff -- app/Models/Student.php tests/Unit/Models/StudentClassCompatibilityTest.php
git status --short app/Models/Student.php tests/Unit/Models/StudentClassCompatibilityTest.php docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md
```

## Test Environment Verification

The targeted test environment check returned:

- `app()->environment()`: `testing`
- `config('database.default')`: `sqlite`
- `config('database.connections.sqlite.database')`: `:memory:`

## Test Result Summary

Syntax checks:

- `php -l app/Models/Student.php`: passed
- `php -l tests/Unit/Models/StudentClassCompatibilityTest.php`: passed

Targeted tests:

- `StudentClassCompatibilityTest`: 6 passed, 8 assertions
- `SanctumTokenAbilityTest`: 6 passed, 19 assertions
- `ApiAccessControlAbilityTest`: 10 passed, 10 assertions
- `BellTimingTodayRouteTest`: 2 passed, 18 assertions

PHPUnit emitted existing doc-comment metadata deprecation warnings from older test files. These warnings were not introduced by Phase 3C and did not fail the targeted tests.

## Failures and Fixes

Initial `StudentClassCompatibilityTest` run failed because test fixtures used normal mass assignment:

- `school_class_id` is not currently fillable on `Student`.
- `SchoolClass::create(['id' => ...])` does not preserve explicit IDs because `id` is not fillable.

Fix applied only to the isolated test harness:

- Student fixtures now use `(new Student())->forceFill(...)`.
- Resolver test rows are inserted directly into the isolated SQLite-memory `school_classes` table.

No production fillable arrays were changed.

## Full Suite / Migration / Data Safety Confirmation

- Full test suite was not run.
- Laravel migrations were not run.
- No migration files were modified.
- No schema changes were made to real/local MySQL.
- No real/local MySQL data was inserted, updated, deleted, truncated, seeded, or backfilled.
- Only isolated SQLite-memory test schemas were created/dropped during filtered tests.

## Remaining Risks

1. Live database still contains the known class FK conflict for student `id=301`; this phase detects but does not repair it.
2. Future controller writes can still create drift unless create/update flows normalize `class_id`, `school_class_id`, `class`, `section_id`, and `section`.
3. `school_class_id` remains outside `Student::$fillable`, so mass-assignment based write paths will not update it unless handled explicitly.
4. Existing relationship methods still use `class_id`; this is intentional but should be documented in future developer-facing model notes.
5. `students.section` remains a legacy string that currently stores numeric strings, not section names.

## Recommended Next Step

Phase 3D should audit `AdminStudentController` create/update write paths and document exactly how student class and section values are written today. After that audit, add a small normalization step so future student writes keep:

- `class_id`
- `school_class_id`
- `class`
- `section_id`
- `section`

consistent without mutating existing rows in bulk.
