# PHASE 4I - Student Export Surface Audit

## Files inspected

- `app/Http/Controllers/StudentController.php`
- `app/Exports/StudentsExport.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `resources/views/students/index.blade.php`
- `routes/web.php`
- `tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `tests/Feature/Students/StudentImportApplyTest.php`
- `docs/project-autopsy/PHASE_4H_STUDENT_CSV_EXPORT_TEMPLATE_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_4G_STUDENT_CSV_EXPORT_TEMPLATE_AUDIT.md`

## Commands run

- `Get-Content app/Http/Controllers/StudentController.php`
- `Get-Content app/Exports/StudentsExport.php`
- `Get-Content app/Models/Student.php`
- `Get-Content resources/views/students/index.blade.php`
- `Get-Content tests/Feature/Students/StudentCsvExportTemplateTest.php`
- `Get-ChildItem app/Exports`
- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Exports/StudentsExport.php`
- `php -l app/Models/Student.php`
- `rg -n "StudentsExport|exportCSV|exportCsv|exportExcel|students\\.export|students/export|Excel::download|Download CSV|downloadSampleCSV|students\\.export\\.csv" app resources routes tests docs -g "*.php" -g "*.blade.php" -g "*.md"`
- `php artisan route | Select-String "students/export"`
- `php artisan route | Select-String "export"`
- `php artisan route | Select-String "excel"`
- `php artisan route | Select-String "csv"`
- `php artisan route:list | Select-String "students/export|export|excel|csv"`
- `php artisan tinker --execute="dump(Schema::getColumnListing('students'));"`
- `php artisan tinker --execute="dump(['total' => DB::table('students')->count(), 'null_class_id' => DB::table('students')->whereNull('class_id')->count(), 'null_school_class_id' => DB::table('students')->whereNull('school_class_id')->count(), 'null_section_id' => DB::table('students')->whereNull('section_id')->count(), 'class_id_conflicts' => DB::table('students')->whereNotNull('class_id')->whereNotNull('school_class_id')->whereColumn('class_id', '<>', 'school_class_id')->count()]);"`

Notes:

- `php artisan route ...` is not available in this Laravel 12 app. It returned Artisan route namespace help.
- Equivalent route inspection was completed with `php artisan route:list`.
- No export/download route was executed against real/local MySQL.

## Export surface map

| Surface | URI / reference | Route name | Controller / class | Active? | Output contract | Risk |
| --- | --- | --- | --- | --- | --- | --- |
| Student CSV export route | `GET students/export/csv` | `students.export.csv` | `StudentController@exportCSV` / `exportCsv` | Yes | Phase 4H normalized CSV headers and mappings | Low |
| Student Excel controller method | No active route found | None found | `StudentController@exportExcel` anonymous export class | Method exists, not routed in inspected routes | Phase 4H normalized headers and mappings | Low while unused, medium if later routed without tests |
| `StudentsExport.php` | `app/Exports/StudentsExport.php` | None found | `App\Exports\StudentsExport` | No active reference found | Raw `Student::all()` collection, no headings/mapping | Medium if wired later |
| Student export UI button | `resources/views/students/index.blade.php` | `students.export.csv` | Link to CSV route | Yes | Points to normalized CSV route | Low |
| Sample CSV template | `downloadSampleCSV()` in `resources/views/students/index.blade.php` | None | JavaScript-generated CSV | Yes | Phase 4H normalized headers/sample values | Low |

## StudentController export findings

### `exportCSV()`

Confirmed Phase 4H state:

- Exports `Class ID`.
- Exports `Section ID`.
- Exports `Mobile`.
- Exports `Admission No`.
- Exports canonical class display with `$student->schoolClass?->name ?? $student->class`.
- Exports canonical section display through eager-loaded `section` relation when available, falling back to the legacy `section` attribute.
- Uses `Student::with(['schoolClass', 'section'])->get()` to avoid N+1 lookups for class/section display names.
- Streams a raw CSV response directly from the controller.
- Is active through `GET students/export/csv`.
- Is inside the authenticated web route group.

Current headers:

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

### `exportExcel()`

Confirmed Phase 4H state:

- Method exists in `StudentController`.
- Uses `Excel::download()` with an anonymous export class.
- Uses normalized headings aligned with CSV export.
- Maps `Class ID`, `Class`, `Section ID`, `Section`, `Mobile`, and `Admission No`.
- Eager-loads `schoolClass` and `section`.
- No active route reference was found for this method in `routes/web.php`.

Because no active route was found, Excel export appears currently dormant from the web UI.

## StudentsExport.php findings

`app/Exports/StudentsExport.php` contents:

```php
class StudentsExport implements FromCollection
{
    public function collection()
    {
        return Student::all();
    }
}
```

Findings:

- No active route/controller/view reference to `StudentsExport` was found by `rg`.
- It has no headings.
- It has no field mapping.
- It does not eager-load `schoolClass` or `section`.
- It returns raw `Student::all()`.
- It does not define the Phase 4H normalized export contract.
- If wired later, it could expose raw/inconsistent columns and bypass the normalized CSV/Excel controller mapping.

Recommended status: unused but risky if reintroduced.

## UI export link findings

`resources/views/students/index.blade.php` has one visible student export button:

- label: `Download CSV`
- route: `route('students.export.csv')`
- target: normalized CSV route

No visible Excel export link was found in the inspected student index view.

No UI link to `StudentsExport.php` was found.

The import/sample area includes Phase 4H helper text:

`Class ID and Section ID are preferred for import accuracy. ID is for reference only; import creates new students and does not update existing rows.`

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

The one known historical class FK conflict remains a data issue outside this export-surface audit.

## Export risks

1. `StudentsExport.php` can bypass the normalized export contract if a future route wires it in.
2. `StudentsExport.php` has no headings, so consumers may receive raw model attributes rather than a stable template.
3. `StudentsExport.php` returns raw `Student::all()` and does not eager-load canonical class/section relations.
4. `exportExcel()` is normalized but currently untested directly and appears unrouted.
5. The active CSV export still includes `ID`, which is reference-only but can be misunderstood as an update key if the helper text is missed.

## Recommended decision

Safest recommendation: **normalize `StudentsExport.php` in Phase 4J rather than delete it immediately.**

Reasoning:

- It is unused now, but it is a conventional Laravel export class name and may be wired in later by accident.
- Normalizing it makes future reuse safer.
- Deleting/quarantining may be safe later, but should happen only after confirming no planned Excel/CSV export path needs it.
- Leaving it raw creates a latent regression path.

Recommended future behavior for `StudentsExport.php`:

- Implement `WithHeadings`.
- Implement mapped rows or `FromCollection` with normalized arrays.
- Eager-load `schoolClass` and `section`.
- Match Phase 4H CSV/Excel headers exactly.
- Add tests proving it does not return raw model columns.

## Safe Phase 4J implementation plan

1. Update `app/Exports/StudentsExport.php` to match the Phase 4H export contract.
2. Add `WithHeadings`.
3. Add normalized row mapping for:
   - `Class ID`
   - `Class`
   - `Section ID`
   - `Section`
   - `Mobile`
   - `Admission No`
4. Eager-load `schoolClass` and `section`.
5. Add isolated tests for `StudentsExport` headings and row mapping.
6. Add a route/reference guard test confirming no route uses raw student export behavior.
7. Do not change import apply.
8. Do not run real exports against real/local MySQL.

## Confirmation

- No application code was modified.
- No routes were modified.
- No views, controllers, export classes, services, models, or migrations were modified.
- No export/download action was run against real/local MySQL.
- No import/apply action was run.
- No full test suite was run.
- No migrations, schema changes, or composer setup were run.
- Real/local MySQL data was inspected only with read-only schema/count queries.

## Recommended next step

Phase 4J should normalize `StudentsExport.php` to the Phase 4H contract and add isolated tests so it cannot reintroduce raw student export behavior if used later.
