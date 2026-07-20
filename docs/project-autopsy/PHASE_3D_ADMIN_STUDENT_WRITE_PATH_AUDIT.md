# Phase 3D - Admin Student Class/Section Write Path Audit

Date: 2026-06-04  
Project: HelpingHand  
Mode: Read-only audit with report creation only

## Files Inspected

Models:

- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`

Controllers:

- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Http/Controllers/Admin/ClassTeacherController.php`
- `app/Http/Controllers/Admin/StudentStatusController.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/API/StudentController.php`

Views:

- `resources/views/admin/students/create.blade.php`
- `resources/views/admin/students/edit.blade.php`
- `resources/views/admin/students/index.blade.php`
- `resources/views/admin/student-promotion/create.blade.php`
- `resources/views/admin/student-promotion/index.blade.php`
- `resources/views/admin/class-teacher-control/edit-student.blade.php`
- `resources/views/admin/class-teacher-control/student-records.blade.php`
- `resources/views/students/create.blade.php`
- `resources/views/students/edit.blade.php`
- `resources/views/students/index.blade.php`

Routes and docs:

- `routes/web.php`
- `routes/api.php`
- `docs/project-autopsy/PHASE_3B_CLASS_DATA_COMPATIBILITY_MAP.md`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`

Requests/services:

- `app/Http/Requests` was searched for student/promotion/import/transfer requests.
- `app/Services` was searched for student class/section write paths.

## Commands Run

```powershell
Get-Content app/Models/Student.php
Get-Content app/Models/SchoolClass.php
Get-Content app/Models/Section.php
Get-Content docs/project-autopsy/PHASE_3B_CLASS_DATA_COMPATIBILITY_MAP.md
Get-Content docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md
Get-Content app/Http/Controllers/Admin/AdminStudentController.php
Get-Content app/Http/Controllers/Admin/StudentPromotionController.php
Get-Content app/Http/Controllers/Admin/StudentStatusController.php
Get-Content app/Http/Controllers/Admin/ClassTeacherController.php
Get-Content app/Http/Controllers/StudentController.php
Get-Content app/Http/Controllers/API/StudentController.php
Get-Content resources/views/admin/students/create.blade.php
Get-Content resources/views/admin/students/edit.blade.php
Get-Content resources/views/admin/students/index.blade.php
Get-Content resources/views/admin/student-promotion/create.blade.php
Get-Content resources/views/admin/student-promotion/index.blade.php
Get-Content resources/views/admin/class-teacher-control/edit-student.blade.php
Get-Content resources/views/admin/class-teacher-control/student-records.blade.php
Get-Content resources/views/students/create.blade.php
Get-Content resources/views/students/edit.blade.php
Get-Content resources/views/students/index.blade.php
Get-ChildItem app/Http/Controllers -Recurse | Where-Object { $_.Name -match 'Student|Promotion|Import|Transfer' } | Select-Object FullName
Get-ChildItem app -Recurse | Where-Object { $_.Name -match 'Student|Promotion|Import|Transfer' -and $_.Extension -eq '.php' } | Select-Object FullName
Get-ChildItem app/Http/Requests -Recurse -ErrorAction SilentlyContinue | Where-Object { $_.Name -match 'Student|Promotion|Import|Transfer' } | Select-Object FullName
Get-ChildItem resources/views/admin -Recurse | Where-Object { $_.Name -match 'student|promotion|import|transfer' } | Select-Object FullName
rg -n "Student::create|Student::update|Student::where|->update\(|->fill\(|forceFill\(|->save\(" app routes resources tests database -g "*.php" -g "*.blade.php"
rg -n "import|promote|promotion|transfer|bulk" app routes resources tests database -g "*.php" -g "*.blade.php"
rg -n "class_id|school_class_id|section_id" app/Http/Controllers resources/views routes -g "*.php" -g "*.blade.php"
rg -n "Student::create|Student::whereIn|student->update|Student::where\(|updateStudent|importCSV|markAsPassedOut" app/Http/Controllers app/Services routes resources -g "*.php" -g "*.blade.php"
Select-String -Path routes/web.php,routes/api.php -Pattern "student"
Select-String -Path routes/web.php,routes/api.php -Pattern "promotion"
php artisan route | Select-String "student"
php artisan route | Select-String "promotion"
php -l app/Http/Controllers/Admin/AdminStudentController.php
php -l app/Http/Controllers/Admin/StudentPromotionController.php
php -l app/Http/Controllers/StudentController.php
php -l app/Http/Controllers/Admin/ClassTeacherController.php
php -l app/Http/Controllers/API/StudentController.php
php -l app/Models/Student.php
php -l app/Models/SchoolClass.php
php -l app/Models/Section.php
```

Notes:

- `php artisan route` is not a valid command in this Laravel 12 project and returned Artisan route namespace help. `Select-String` over `routes/web.php` and `routes/api.php` was used for route mapping.
- One broad `rg` command and one mixed-quote `rg` command failed due shell quoting/regex parsing. They performed no data or code changes; the audit was completed with simpler successful searches.
- No database SELECT checks were needed in this phase because Phase 3B already captured the relevant live data compatibility facts.

## Student Write Path Inventory

Total relevant student write paths found: **10**

| # | File | Method | Route / exposure | Writes class fields? | Fields written | Risk |
|---:|---|---|---|---|---|---|
| 1 | `app/Http/Controllers/Admin/AdminStudentController.php` | `store()` | `POST admin/students`, `POST admin/students-crud` | Yes | `class`, `section` via `$validated` | High |
| 2 | `app/Http/Controllers/Admin/AdminStudentController.php` | `update()` | `PUT admin/students/{student}`, `PUT admin/students-crud/{student}` | Yes | `class`, `section` via `$validated` | High |
| 3 | `app/Http/Controllers/Admin/StudentPromotionController.php` | `store()` | `POST admin/student-promotions` | Yes | bulk `class_id`, `class` | High |
| 4 | `app/Http/Controllers/Admin/StudentPromotionController.php` | `markAsPassedOut()` | `POST admin/student-promotions/student/{studentId}/passed-out` | Yes | `status`, `class_id = null`, `class = Passed Out` | High |
| 5 | `app/Http/Controllers/StudentController.php` | `store()` | root `students.create` form, route conflict-prone | Yes | `class`, `section` via `$validated` | High |
| 6 | `app/Http/Controllers/StudentController.php` | `update()` | root `students.update` if route is active | Yes | `class`, `section` via `$validated` | Medium |
| 7 | `app/Http/Controllers/StudentController.php` | `importCSV()` | `POST students/import/csv` | Yes | imported string `class`, `section` only | High |
| 8 | `app/Http/Controllers/API/StudentController.php` | `store()` | `POST /api/v1/students`, admin-only after API access control | Yes | `class`, `section` via `$validated` | Medium |
| 9 | `app/Http/Controllers/API/StudentController.php` | `update()` | `PUT/PATCH /api/v1/students/{student}`, admin-only after API access control | Yes | `class`, `section` via `$validated` | Medium |
| 10 | `app/Http/Controllers/Admin/ClassTeacherController.php` | `updateStudent()` | class-teacher control update route if wired | Conditional | any field allowed by `FieldPermission`, possibly `class`, `class_id`, `section`, `section_id` | Medium |

Excluded as non-class write paths:

- `Admin\StudentStatusController` writes `StudentStatus`, not `students`.
- `StudentVerificationController` updates `is_verified` only.
- Result/import services read student identity/class for results but do not write student class/section fields.
- Seeders/migrations write student data but are not active user-facing write paths and must not be run in this migration-inconsistent state.

## Create/Edit Form Field Map

| View | Class source | Submitted class field | Section source | Submitted section field | Alignment |
|---|---|---|---|---|---|
| `admin/students/create.blade.php` | Free text input | `class` string | Free text input | `section` string | Not aligned with canonical IDs |
| `admin/students/edit.blade.php` | `$classList = SchoolClass::orderBy('class_order')` | `class` = `SchoolClass.name` | `$sections = Section::orderBy('name')` | `section` = `Section.name` | Class name aligned; section string conflicts with current numeric `students.section` convention |
| `admin/students/index.blade.php` | `$classList` IDs for filter | `class_id` query only | `Section.name` for filter | `section` query only | Read path mixes canonical class ID with string section name |
| `admin/student-promotion/create.blade.php` | `SchoolClass` IDs | `from_class`, `to_class` IDs | Not handled | none | Promotion class source is canonical, but output only updates `class_id` and `class` |
| `admin/class-teacher-control/edit-student.blade.php` | `ClassManagement` IDs in a disabled select | `class_id`, disabled | `Section` IDs in a disabled select | `section_id`, disabled | Display is ID-based but disabled; if field permissions allow posted fields, controller can still accept allowed class/section keys |
| `students/create.blade.php` | hardcoded string list | `class` string | hardcoded A-D list | `section` string | Not canonical; lacks Class 11/12 stream names |
| `students/edit.blade.php` | no class control | none | no section control | none | Update validates class/section required, but form does not submit them |
| `students/index.blade.php` import card | CSV/Excel columns | CSV `Class` column -> `class` | CSV `Section` column -> `section` | Import only strings |

## Validation Rule Map

| Controller method | Class validation | Section validation | Missing canonical validation |
|---|---|---|---|
| `AdminStudentController@store` | `class` required string max 50 | `section` nullable string max 10 | No `class_id`, `school_class_id`, `section_id` validation |
| `AdminStudentController@update` | `class` required string max 50 | `section` nullable string max 10 | No `class_id`, `school_class_id`, `section_id` validation |
| `StudentPromotionController@store` | `from_class` and `to_class` required integers, exist in `school_classes` | none | Does not update `school_class_id`; does not handle section |
| `StudentPromotionController@markAsPassedOut` | none beyond route student lookup | none | Nulls `class_id` only; leaves `school_class_id`, `section_id`, `section` untouched |
| `StudentController@store` | `class` required string max 50 | `section` nullable string max 10 | No canonical FK validation |
| `StudentController@update` | `class` required string max 50 | `section` nullable string max 10 | Form does not submit class/section; update likely validation-fails unless fields are supplied elsewhere |
| `StudentController@importCSV` | file only; row class not validated against `school_classes` | row section not validated against `sections` | No canonical FK resolution |
| `API\StudentController@store` | `class` required string max 50 | `section` nullable string max 10 | No canonical FK validation |
| `API\StudentController@update` | `class` required string max 50 | `section` nullable string max 10 | No canonical FK validation |
| `ClassTeacherController@updateStudent` | dynamic default `string|nullable` if allowed by field permissions | dynamic default `string|nullable` if allowed by field permissions | No `exists:school_classes,id` / `exists:sections,id` for class/section keys |

No student-specific FormRequest classes were found.

## Current Class/Section Write Behavior

### Admin Create/Update

`AdminStudentController@store` and `@update` write only:

- `class`
- `section`

They do not write:

- `class_id`
- `school_class_id`
- `section_id`

Because `Student::$fillable` includes `class_id` but not `school_class_id` or `section_id`, even adding validated FK fields to `$validated` would not write the compatibility fields unless explicitly assigned or fillable is changed later.

### Promotion

`StudentPromotionController@store` validates against `school_classes` and updates:

```php
'class_id' => $request->to_class,
'class' => $destinationClass->name
```

It does not update:

- `school_class_id`
- `section_id`
- `section`

This can create the exact drift Phase 3C is meant to detect.

`markAsPassedOut()` updates:

```php
'status' => 'passed_out',
'class_id' => null,
'class' => 'Passed Out'
```

It leaves `school_class_id` populated, which means a passed-out student can still appear in queries using `school_class_id`.

### Root Student Controller

The root `StudentController` is still routed for import/export and at least the create form. Its create/update/import flows write only string `class` and `section`.

This is an important legacy path because `resources/views/admin/class-teacher-control/student-records.blade.php` links to `route('students.create')`, which resolves outside the admin CRUD naming pattern.

### API Student Controller

API create/update also validate and write string `class` and `section` only. These routes are currently high-risk and generally admin-only after the API access control work, but they remain capable of creating canonical drift if used by an admin token.

### Class Teacher Control

`ClassTeacherController@updateStudent` writes `$allowedInput` based on `FieldPermission`.

The edit view disables `class_id` and `section_id`, but the controller itself does not hard-block those fields. If field permissions mark class/section fields editable, it may accept:

- `class`
- `section`
- `class_id`
- `section_id`

without canonical normalization or `exists` validation.

## Drift Risks

1. Admin student create writes only string `class`/`section`, leaving canonical FKs null for new records.
2. Admin student edit posts class names and section names, but current live `students.section` stores numeric strings `"1".."4"`.
3. Student promotion updates `class_id` and `class` but not `school_class_id`, creating FK disagreement.
4. Passed-out flow nulls `class_id` but leaves `school_class_id` and section fields untouched.
5. Root `StudentController@importCSV` can import arbitrary class/section strings with no canonical mapping.
6. Root `StudentController@store` uses a hardcoded class list that does not include `Class 11 Science`, `Class 11 Commerce`, etc.
7. Root `StudentController@update` requires class/section validation but its edit view does not submit class/section controls.
8. API student create/update use legacy string fields, bypassing canonical IDs.
9. `ClassTeacherController@updateStudent` has dynamic field-permission writes with weak default validation.
10. `Student::$fillable` excludes `school_class_id` and `section_id`, so mass-assignment will silently ignore those fields.
11. Read paths are split: some query `class_id`, some query `school_class_id`, and some query string `class`.
12. Section filters in admin views use `Section.name`, while current student rows store numeric section strings.

## Recommended Normalization Behavior

Do not implement in Phase 3D. For Phase 3E, use one small normalization method for create/update payloads:

1. Accept `class_id` as the preferred submitted field.
2. If only string `class` is present, resolve it against `SchoolClass::where('name', $class)->first()`.
3. Load the selected `SchoolClass`.
4. Set:
   - `class_id = $schoolClass->id`
   - `school_class_id = $schoolClass->id`
   - `class = $schoolClass->name`
5. Accept `section_id` as the preferred submitted field.
6. If only string `section` is present:
   - First treat numeric strings as section IDs for backward compatibility.
   - Else resolve by `Section::where('name', $section)->first()`.
7. Load the selected `Section`.
8. Set:
   - `section_id = $section->id`
   - `section = (string) $section->id` temporarily

## Section String Decision

Recommendation: **temporarily keep `students.section` as the section ID string, not the section name.**

Reason:

- Phase 3B found current live values are `"1"`, `"2"`, `"3"`, `"4"`.
- Switching future writes to `A`, `B`, `C`, `D` would immediately create mixed section string semantics.
- The canonical display should come from `section_id -> sections.name`.
- The legacy `section` string should stay compatible until a later migration/backfill phase can intentionally change all rows together.

## Phase 3E Implementation Plan

1. Add a private helper to `AdminStudentController`, for example:
   - `normalizeClassSectionPayload(array $validated, Request $request): array`
2. Use it only in:
   - `AdminStudentController@store`
   - `AdminStudentController@update`
3. Prefer updated admin forms to submit:
   - `class_id`
   - `section_id`
4. In the helper, derive compatibility fields:
   - `class_id`
   - `school_class_id`
   - `class`
   - `section_id`
   - `section`
5. Use explicit assignment or add carefully scoped fillable support, because `school_class_id` and `section_id` are currently not fillable.
6. Add isolated SQLite-memory tests for the helper behavior.
7. Do not bulk repair existing students yet.
8. Do not touch CSV import, API student writes, promotions, or passed-out flow until admin create/update is stable.
9. After create/update normalization passes, handle promotion:
   - update `school_class_id` alongside `class_id`
   - decide whether passed-out should null both class FKs or use a separate `status`-only query rule.
10. Add a read-only conflict warning/report for rows where `hasClassIdConflict()` is true; do not mutate those rows in Phase 3E.

## Confirmation

- No application code was modified.
- No routes, models, controllers, migrations, or views were modified.
- No database data was created, updated, deleted, imported, promoted, seeded, or migrated.
- No full test suite was run.
- The only file created by this phase is this report.
