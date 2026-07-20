# PHASE 4G - Student CSV Export / Template Alignment Audit

## Files inspected

- `app/Http/Controllers/StudentController.php`
- `app/Exports/StudentsExport.php`
- `app/Services/Students/StudentImportNormalizer.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `resources/views/students/index.blade.php`
- `resources/views/students/import-preview.blade.php`
- `routes/web.php`
- `tests/Feature/Students/StudentImportApplyTest.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `tests/Unit/Services/StudentImportNormalizerTest.php`
- `docs/project-autopsy/PHASE_4F_STUDENT_IMPORT_APPLY_FLOW.md`
- `docs/project-autopsy/PHASE_4A_STUDENT_CSV_IMPORT_AUDIT.md`

## Commands run

- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content app/Exports/StudentsExport.php`
- `Get-Content app/Services/Students/StudentImportNormalizer.php`
- `Get-Content resources/views/students/index.blade.php`
- `Get-Content resources/views/students/import-preview.blade.php`
- `Get-Content app/Models/SchoolClass.php`
- `Get-Content app/Models/Section.php`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Exports/StudentsExport.php`
- `php -l app/Services/Students/StudentImportNormalizer.php`
- `php -l app/Models/Student.php`
- `rg -n "students/export|students/import|exportCsv|exportCSV|exportExcel|StudentsExport|sample CSV|downloadSampleCSV|Class ID|Section ID|school_class_id|section_id" app resources routes tests docs -g "*.php" -g "*.blade.php" -g "*.md"`
- `php artisan route | Select-String "students/export"`
- `php artisan route | Select-String "students/import"`
- `php artisan route:list | Select-String "students/export"`
- `php artisan route:list | Select-String "students/import"`
- `php artisan tinker --execute="dump(Schema::getColumnListing('students'));"`
- `php artisan tinker --execute="dump(['total' => DB::table('students')->count(), 'null_class_id' => DB::table('students')->whereNull('class_id')->count(), 'null_school_class_id' => DB::table('students')->whereNull('school_class_id')->count(), 'null_section_id' => DB::table('students')->whereNull('section_id')->count(), 'class_id_conflicts' => DB::table('students')->whereNotNull('class_id')->whereNotNull('school_class_id')->whereColumn('class_id', '<>', 'school_class_id')->count()]);"`
- `php artisan tinker --execute="dump(['distinct_classes' => DB::table('students')->whereNotNull('class')->distinct()->limit(20)->pluck('class'), 'distinct_sections' => DB::table('students')->whereNotNull('section')->distinct()->limit(20)->pluck('section')]);"`

Notes:

- `php artisan route ...` is not available in this Laravel 12 app. It returned Artisan route namespace help.
- Equivalent route inspection was completed with `php artisan route:list`.
- Initial read-only tinker attempts had PowerShell quoting errors. They did not write data and were rerun successfully with corrected quoting.

## Export route map

| Method | URI | Route name | Controller method | Middleware/group | Active | Export mechanism |
| --- | --- | --- | --- | --- | --- | --- |
| `GET` / `HEAD` | `students/export/csv` | `students.export.csv` | `StudentController@exportCsv` | Inside authenticated web route group | Yes | Raw streamed CSV response from controller |

Related import routes currently active:

| Method | URI | Route name | Controller method | Status |
| --- | --- | --- | --- | --- |
| `POST` | `students/import/csv/preview` | `students.import.csv.preview` | `StudentController@previewImportCsv` | Preview-only |
| `POST` | `students/import/csv/apply` | `students.import.csv.apply` | `StudentController@applyImportCsv` | Session-backed safe apply |
| `POST` | `students/import/csv` | `students.import.csv` | `StudentController@importCsv` | Guarded, writes zero students |

## Export method findings

`StudentController@exportCSV()` streams a CSV directly from the controller.

Current exported headers:

- `ID`
- `Name`
- `Father Name`
- `Mother Name`
- `Date of Birth`
- `Aadhar Number`
- `Phone`
- `Gender`
- `Category`
- `Class`
- `Section`
- `Roll Number`
- `Religion`
- `Caste`
- `Blood Group`
- `Address`

Current exported values:

- `ID` uses `$student->id`.
- `Class` uses `$student->class`.
- `Section` uses `$student->section`.
- No `Mobile` column is exported.
- No `Admission No` column is exported.
- No `Class ID` / `class_id` column is exported.
- No `school_class_id` column is exported.
- No `Section ID` / `section_id` column is exported.
- `Section` exports the legacy stored section value, which currently appears to be numeric strings such as `1`, `2`, `3`, `4`.

The current export does not match the Phase 4F safe import apply contract because Phase 4F writes and prefers canonical IDs:

- `class_id`
- `school_class_id`
- `class`
- `section_id`
- `section`

The export includes a student `ID`, but Phase 4F import/apply creates new students and does not treat exported `ID` as an update key. Re-importing exported rows can therefore create duplicate student records unless duplicate warnings block apply.

## Export class findings

`app/Exports/StudentsExport.php` exists, but it is not used by the inspected CSV route.

It returns:

- `Student::all()`

It does not define headings, mapping, canonical class/section IDs, or a normalized export contract. If used later for Excel export or another route, it may expose raw model columns rather than a curated import template.

`StudentController@exportExcel()` uses an anonymous export class, not `StudentsExport`. Its headings match the legacy CSV export and also omit canonical class/section ID columns.

## Sample/template findings

The visible sample CSV is generated in JavaScript inside `resources/views/students/index.blade.php` by `downloadSampleCSV()`.

Current sample headers:

- `ID`
- `Name`
- `Father Name`
- `Mother Name`
- `Date of Birth`
- `Aadhar Number`
- `Phone`
- `Gender`
- `Category`
- `Class`
- `Section`
- `Roll Number`
- `Religion`
- `Caste`
- `Blood Group`
- `Address`

Current sample row uses:

- `Class 5` for `Class`
- `A` for `Section`

Findings:

- Sample does not include `Class ID`.
- Sample does not include `school_class_id`.
- Sample does not include `Section ID`.
- Sample does not include `Mobile`.
- Sample does not include `Admission No`.
- Sample uses section display name `A`, while current live legacy `students.section` values are numeric strings `1`, `2`, `3`, `4`.
- The normalizer can resolve section name `A` to `section_id`, so the sample can preview/apply if the section exists, but it still does not teach admins that `Section ID` is the primary stable import field.

## Read-only data snapshot

Observed `students` columns:

- `id`
- `user_id`
- `guardian_id`
- `name`
- `photo`
- `father_name`
- `mother_name`
- `guardian_name`
- `date_of_birth`
- `aadhar_number`
- `admission_no`
- `address`
- `phone`
- `mobile`
- `gender`
- `category`
- `class`
- `class_id`
- `school_class_id`
- `section_id`
- `section`
- `roll_number`
- `religion`
- `caste`
- `blood_group`
- `nationality`
- `medical_history`
- `previous_school`
- `created_at`
- `updated_at`
- `deleted_at`
- `is_verified`

Read-only counts:

| Check | Count |
| --- | ---: |
| Total students | 760 |
| Students with null `class_id` | 0 |
| Students with null `school_class_id` | 0 |
| Students with null `section_id` | 0 |
| Students where `class_id != school_class_id` | 1 |

Distinct legacy class patterns include canonical-looking names such as:

- `Nursery`
- `LKG`
- `UKG`
- `Class 1` through `Class 12 ...`

Distinct legacy section values:

- `1`
- `2`
- `3`
- `4`

This confirms the current internal legacy `section` compatibility field stores section ID strings, not section display names.

## Export/import contract mismatch

| Contract area | Phase 4F import/apply | Current export/sample | Mismatch |
| --- | --- | --- | --- |
| Class identity | Prefers `Class ID` / `class_id`, falls back to `Class` | Exports/samples only `Class` | Yes |
| Compatibility class FK | Writes `school_class_id = class_id` | Not exported/sampled | Yes |
| Section identity | Prefers `Section ID` / `section_id`, falls back to `Section` | Exports/samples only `Section` | Yes |
| Legacy section storage | Writes section ID string internally | Export emits numeric legacy section; sample emits `A` | Yes |
| Mobile | Apply can write `mobile` if present | Not exported/sampled | Yes |
| Admission No | Apply can write `admission_no` if present | Not exported/sampled | Yes |
| Student ID | Apply does not update by ID | Export includes `ID` | Re-import risk |

## Recommended normalized export/template design

Recommended future headers:

- `ID`
- `Name`
- `Father Name`
- `Mother Name`
- `Date of Birth`
- `Aadhar Number`
- `Phone`
- `Mobile`
- `Gender`
- `Category`
- `Class ID`
- `Class`
- `Section ID`
- `Section`
- `Roll Number`
- `Religion`
- `Caste`
- `Blood Group`
- `Address`
- `Admission No`

Recommended field rules:

- `Class ID` should be `students.class_id`, which maps to `school_classes.id`.
- `Class` should be the canonical display name from `schoolClass.name` when available, falling back to `students.class`.
- `Section ID` should be `students.section_id`, which maps to `sections.id`.
- `Section` should be the section display name from `section.name` when available, falling back to `students.section`.
- Keep `ID` for human reference, but clearly warn that import apply creates new students and does not update existing rows by `ID`.
- Keep `Class` and `Section` for readability, but make ID columns the primary import fields.
- Add `Mobile` and `Admission No` so export aligns with actual student columns and the Phase 4F apply payload.

For import compatibility, internal `students.section` should continue to be written as the section ID string by apply. The export/template can show section display name as long as `Section ID` is present and primary.

## Safe Phase 4H implementation plan

1. Update `StudentController@exportCSV()` headers to include `Mobile`, `Class ID`, `Section ID`, and `Admission No`.
2. Export canonical IDs and display fallback names:
   - `Class ID` from `class_id`
   - `Class` from `schoolClass.name` fallback to `class`
   - `Section ID` from `section_id`
   - `Section` from `section.name` fallback to `section`
3. Use eager loading for `schoolClass` and `section` to avoid N+1 queries.
4. Update `StudentController@exportExcel()` or explicitly document if Excel remains out of scope.
5. Update JavaScript sample CSV in `resources/views/students/index.blade.php` to include the same normalized headers.
6. Add helper text near the sample link that `Class ID` and `Section ID` are preferred and `ID` is not used for updates.
7. Keep `StudentImportNormalizer` legacy fallbacks unchanged.
8. Add isolated tests for export headers, sample CSV headers, and import compatibility with the new template.
9. Do not alter Phase 4F apply transaction/guard behavior unless a test proves a direct mismatch.

## Top export/template risks

1. Legacy-only export encourages admins to keep importing by class/section strings instead of stable IDs.
2. Sample CSV currently uses section name `A`, while live legacy `students.section` stores numeric section ID strings.
3. Exported `ID` may make admins assume re-import updates existing students, but Phase 4F apply creates new students.
4. Missing `Class ID` and `Section ID` weakens the safety of the normalized import contract.
5. `StudentsExport.php` returns raw `Student::all()` and could expose an inconsistent raw model export if wired into a route later.

## Confirmation

- No application code was modified.
- No routes were modified.
- No views, controllers, exports, services, models, or migrations were modified.
- No export/download action was executed.
- No import/apply action was executed.
- No full test suite was run.
- No migrations, schema changes, or composer setup were run.
- Real/local MySQL data was inspected only with read-only schema/count/distinct queries.

## Recommended next step

Phase 4H should update CSV export and the sample CSV template to include `Class ID` and `Section ID` as primary import fields while keeping human-readable `Class` and `Section` columns as display/fallback fields.
