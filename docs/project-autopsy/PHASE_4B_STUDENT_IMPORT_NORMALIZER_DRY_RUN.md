# Phase 4B - Student Import Normalizer Dry Run

## Files Inspected

- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `app/Exports/StudentsExport.php`
- `resources/views/students/index.blade.php`
- `resources/views/admin/students/index.blade.php`
- `docs/project-autopsy/PHASE_4A_STUDENT_CSV_IMPORT_AUDIT.md`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`
- `docs/project-autopsy/PHASE_3H_ADMIN_STUDENT_FORM_CANONICAL_IDS.md`
- `tests/Unit/Models/StudentClassCompatibilityTest.php`
- `tests/Feature/Admin/AdminStudentClassNormalizationTest.php`

## Files Changed

- `app/Services/Students/StudentImportNormalizer.php`
- `tests/Unit/Services/StudentImportNormalizerTest.php`
- `docs/project-autopsy/PHASE_4B_STUDENT_IMPORT_NORMALIZER_DRY_RUN.md`

## Normalizer Service Summary

Created:

```php
App\Services\Students\StudentImportNormalizer
```

Public method:

```php
public function normalizeRow(array $row, ?int $rowNumber = null): array
```

The service is read-only. It does not call:

- `save()`
- `update()`
- `delete()`
- `insert()`
- `create()`
- `truncate()`

It returns structured dry-run data:

```php
[
    'row_number' => $rowNumber,
    'original' => $row,
    'normalized' => [
        'class_id' => ...,
        'school_class_id' => ...,
        'class' => ...,
        'section_id' => ...,
        'section' => ...,
    ],
    'errors' => [...],
    'warnings' => [...],
    'is_valid' => true|false,
]
```

Supported input keys include header-style and positional legacy values:

- `class_id`
- `Class ID`
- `school_class_id`
- `School Class ID`
- `class`
- `Class`
- positional class index `9`
- `section_id`
- `Section ID`
- `section`
- `Section`
- positional section index `10`

It also supports student identity fields for dry-run validation:

- `name`
- `Name`
- positional name index `1`
- `aadhar_number`
- `Aadhar Number`
- positional aadhar index `5`
- `roll_number`
- `Roll Number`
- positional roll number index `11`
- `phone`
- `Phone`
- positional phone index `6`
- `mobile`
- `Mobile`

## Class Resolution Behavior

Resolution order:

1. `class_id`
2. `Class ID`
3. `school_class_id`
4. `School Class ID`
5. `class`
6. `Class`
7. positional legacy class index `9`

If a `SchoolClass` is resolved, dry-run output sets:

- `class_id = school_classes.id`
- `school_class_id = school_classes.id`
- `class = school_classes.name`

If no class can be resolved, the row gets this error:

```text
Class could not be resolved.
```

No data is written either way.

## Section Resolution Behavior

Resolution order:

1. `section_id`
2. `Section ID`
3. `section`
4. `Section`
5. positional legacy section index `10`

If the section value is numeric, it is treated as a section ID and validated against `sections.id`.

If it is non-numeric, it is resolved by exact `sections.name`.

If a `Section` is resolved, dry-run output sets:

- `section_id = sections.id`
- `section = (string) sections.id`

This preserves the current compatibility decision that legacy `students.section` stores the section ID string for now.

If no section can be resolved, the row gets this error:

```text
Section could not be resolved.
```

No data is written either way.

## Duplicate / Validation Behavior

Lightweight dry-run checks added:

- Blank provided student name returns error:
  - `Student name is required.`
- Existing `aadhar_number` returns warning:
  - `Duplicate aadhar_number found.`
- Existing `roll_number` returns warning:
  - `Duplicate roll_number found.`
- Existing `phone` or `mobile` match returns warning:
  - `Duplicate phone/mobile found.`

These checks are intentionally dry-run only. They do not block active import writes yet because the active import flow is unchanged.

The service does not add broad validation for fields that the current import does not explicitly require beyond class/section normalization and provided-name sanity checking.

## Active Import Write Behavior

`StudentController@importCsv` was inspected but not changed.

It still currently:

- reads CSV/TXT/XLSX/XLS files
- loops over rows
- creates `Student` rows through `Student::create([...])`
- writes legacy `class`
- writes legacy `section`
- does not write `class_id`
- does not write `school_class_id`
- does not write `section_id`

The new service is not wired into active import writes in this phase.

## Tests Created / Updated

Created:

- `tests/Unit/Services/StudentImportNormalizerTest.php`

The test uses an isolated SQLite-memory schema created inside the test lifecycle. It does not use full project migrations, seeders, or real/local MySQL.

Minimal test tables:

- `students`
- `school_classes`
- `sections`

Tests added:

- `normalizer_resolves_class_by_class_id`
- `normalizer_resolves_class_by_class_name`
- `normalizer_sets_school_class_id_equal_to_class_id`
- `normalizer_resolves_section_by_section_id`
- `normalizer_resolves_section_by_section_name`
- `normalizer_sets_legacy_section_to_section_id_string`
- `normalizer_reports_error_for_unresolved_class`
- `normalizer_reports_error_for_unresolved_section`
- `normalizer_detects_duplicate_aadhar_number_if_present`
- `normalizer_does_not_modify_database`

## Commands Run

- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content app/Http/Controllers/Admin/AdminStudentController.php`
- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/SchoolClass.php`
- `Get-Content app/Models/Section.php`
- `Get-Content docs/project-autopsy/PHASE_4A_STUDENT_CSV_IMPORT_AUDIT.md`
- `Get-Content tests/Unit/Models/StudentClassCompatibilityTest.php`
- `Get-Content tests/Feature/Admin/AdminStudentClassNormalizationTest.php`
- `Get-ChildItem tests/Unit -Recurse`
- `Get-ChildItem app/Services`
- `php -l app/Services/Students/StudentImportNormalizer.php`
- `php -l tests/Unit/Services/StudentImportNormalizerTest.php`
- `php -l app/Http/Controllers/StudentController.php`
- `php artisan test --filter=StudentImportNormalizerTest --env=testing`
- `php artisan test --filter=AdminStudentClassNormalizationTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`

## Test Result Summary

- Syntax check passed for `StudentImportNormalizer.php`.
- Syntax check passed for `StudentImportNormalizerTest.php`.
- Syntax check passed for `StudentController.php`.
- `StudentImportNormalizerTest`: 10 passed, 21 assertions.
- `AdminStudentClassNormalizationTest`: 6 passed, 18 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.

PHPUnit emitted existing unrelated doc-comment metadata deprecation warnings during test discovery. No targeted test failed.

## Safety Confirmations

- Active import writes were not changed.
- Export behavior was not changed.
- Routes were not changed.
- Admin student create/update normalization was not changed.
- No full test suite was run.
- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was touched.
- No student data was imported, created, updated, deleted, seeded, promoted, passed out, or otherwise mutated.
- The only schema changes occurred inside isolated SQLite-memory test setup.
- No Artisan import/dry-run command was added.

## Remaining Risks

- The active `students.import.csv` route can still create legacy-only class/section rows until a later phase wires in dry-run validation.
- Current export/sample CSV still omits `class_id`, `school_class_id`, and `section_id`.
- Duplicate checks are currently warnings in the dry-run service, not active import blockers.
- The service resolves class and section by exact names only; fuzzy/case-insensitive matching is intentionally deferred to avoid accidental mapping.
- `Student::$fillable` still omits `school_class_id` and `section_id`; future import writes must explicitly assign those fields or use a controlled service.

## Recommended Next Step

Phase 4C should add a dry-run import preview flow without applying writes:

1. Parse uploaded CSV/Excel rows.
2. Feed each row through `StudentImportNormalizer::normalizeRow()`.
3. Show row-level normalized values, errors, and warnings.
4. Do not import any rows from the preview endpoint.
5. Only after preview behavior is verified, add a separate apply phase that refuses to run unless the dry-run result is clean.
