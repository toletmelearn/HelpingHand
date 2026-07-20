# Phase 3J - Student Promotion And Passed-Out Write Audit

## Files Inspected

- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Http/Controllers/Admin/StudentStatusController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `app/Models/StudentPromotionLog.php`
- `resources/views/admin/student-promotion/create.blade.php`
- `resources/views/admin/student-promotion/index.blade.php`
- `resources/views/admin/student-promotion/history.blade.php`
- `routes/web.php`
- `database/migrations/2026_01_29_090059_create_student_statuses_table.php`
- `database/migrations/2026_01_30_041845_create_student_promotion_logs_table.php`
- `docs/project-autopsy/PHASE_3B_CLASS_DATA_COMPATIBILITY_MAP.md`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`
- `docs/project-autopsy/PHASE_3D_ADMIN_STUDENT_WRITE_PATH_AUDIT.md`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`
- `docs/project-autopsy/PHASE_3I_ADMIN_STUDENT_INDEX_FILTER_ALIGNMENT.md`

## Commands Run

- `Get-Content app/Http/Controllers/Admin/StudentPromotionController.php`
- `Get-ChildItem resources/views/admin/student-promotion -Force`
- `Get-Content resources/views/admin/student-promotion/create.blade.php`
- `Get-Content resources/views/admin/student-promotion/index.blade.php`
- `Get-Content resources/views/admin/student-promotion/history.blade.php`
- `php -l app/Http/Controllers/Admin/StudentPromotionController.php`
- `php -l app/Models/Student.php`
- `php -l app/Models/SchoolClass.php`
- `php -l app/Models/Section.php`
- `php artisan route | Select-String "promotion"` - invalid in this Laravel app; no application mutation occurred.
- `php artisan route:list | Select-String "promotion"`
- `php artisan route:list | Select-String "passed"`
- `php artisan route:list --path=student-promotions`
- `php artisan route:list --path=student-promotions --json`
- `rg -n "student-promotions|markAsPassedOut|passed-out|passed_out|StudentPromotionController|promote|promotion" routes app resources/views/admin/student-promotion resources/views -g "*.php" -g "*.blade.php"`
- `rg -n "Student::|->update\\(|class_id|school_class_id|section_id|section|class'|class\\]" app/Http/Controllers/Admin/StudentPromotionController.php resources/views/admin/student-promotion -g "*.php" -g "*.blade.php"`
- `rg -n "student_promotion|promotion_logs|StudentPromotionLog|student_promotions|promoted_at|from_class|to_class" database app routes resources -g "*.php" -g "*.blade.php"`
- `rg -n "Schema::table\\('students'|Schema::create\\('students'|status" database/migrations -g "*.php"`
- `php artisan tinker --execute="..."` read-only guarded count checks for student class conflict and passed-out status risk.
- `php artisan tinker --execute="dump(['students_columns' => Schema::hasTable('students') ? Schema::getColumnListing('students') : []]);"`
- `php artisan tinker --execute="dump(['student_statuses_table' => Schema::hasTable('student_statuses'), 'student_statuses_count' => Schema::hasTable('student_statuses') ? DB::table('student_statuses')->count() : 'missing']);"`

No migrations, seeders, imports, promotions, pass-out operations, or database-changing tests were run.

## Promotion Route Map

All routes are under `web`, authenticated, and verified middleware.

| Method | URI | Route Name | Controller Method | Writes Student Class Fields |
|---|---|---|---|---|
| GET | `admin/student-promotions` | `admin.student-promotions.index` | `index` | No |
| GET | `admin/student-promotions/create` | `admin.student-promotions.create` | `create` | No |
| POST | `admin/student-promotions` | `admin.student-promotions.store` | `store` | Yes |
| GET | `admin/student-promotions/class/{class}/students` | `admin.student-promotions.get-students` | `getStudentsByClass` | No |
| GET | `admin/student-promotions/destination-classes/{class}` | `admin.student-promotions.get-destination-classes` | `getDestinationClasses` | No |
| GET | `admin/student-promotions/student/{studentId}/history` | `admin.student-promotions.history` | `studentHistory` | No |
| POST | `admin/student-promotions/student/{studentId}/passed-out` | `admin.student-promotions.passed-out` | `markAsPassedOut` | Yes |
| GET | `admin/student-promotions/{student_promotion}` | `admin.student-promotions.show` | `show` | No, but method missing |
| GET | `admin/student-promotions/{student_promotion}/edit` | `admin.student-promotions.edit` | `edit` | No, but method missing |
| PUT/PATCH | `admin/student-promotions/{student_promotion}` | `admin.student-promotions.update` | `update` | Route exists, method missing |
| DELETE | `admin/student-promotions/{student_promotion}` | `admin.student-promotions.destroy` | `destroy` | Route exists, method missing |

Route exposure note: `Route::resource('student-promotions', StudentPromotionController::class)` exposes `show`, `edit`, `update`, and `destroy`, but the controller currently does not implement those methods.

## Promotion Form Field Map

`resources/views/admin/student-promotion/create.blade.php` posts to `admin.student-promotions.store`.

| Field | Source | Submitted Value | Canonical Alignment |
|---|---|---|---|
| `academic_session_id` | `AcademicSession::current()` and all sessions queried in Blade | `academic_sessions.id` | Acceptable, though querying models in Blade is technical debt |
| `from_class` | `$currentClasses = SchoolClass::active()->orderByOrder()` | `school_classes.id` | Canonical |
| `to_class` | AJAX `getDestinationClasses()` from `SchoolClass` | `school_classes.id` | Canonical |
| `students[]` | AJAX `getStudentsByClass()` | `students.id` | Canonical student IDs, but source selection also includes legacy class string fallback |
| `remarks` | Free text | string | Not class-related |

The UI does not submit section fields. Promotion is class-only.

## Promotion Write Behavior

`StudentPromotionController@store`:

- Validates `from_class` as `exists:school_classes,id`.
- Validates `to_class` as `exists:school_classes,id`.
- Loads `$sourceClass` and `$destinationClass` from `SchoolClass`.
- Rejects destination classes whose `class_order` is not higher than source.
- Updates selected students with:
  - `class_id = $request->to_class`
  - `class = $destinationClass->name`
- Does not update:
  - `school_class_id`
  - `section_id`
  - `section`
- Creates one `StudentPromotionLog` per selected student after the bulk update.
- Does not wrap the student update and promotion log creation in a transaction.
- Uses `Auth::id()` for `promoted_by`.

Risk: this method can create `students.class_id != students.school_class_id` for every promoted student whose `school_class_id` previously matched the old class.

## Passed-Out Write Behavior

`StudentPromotionController@markAsPassedOut`:

- Validates only optional `remarks`.
- Loads the target `Student`.
- Loads the current `AcademicSession`.
- Calls `$student->update()` with:
  - `status = passed_out`
  - `class_id = null`
  - `class = Passed Out`
- Does not update:
  - `school_class_id`
  - `section_id`
  - `section`
- Creates a `StudentPromotionLog` after the student update.
- Does not wrap the student update and log creation in a transaction.
- Reads `$student->schoolClass->name ?? $student->class` after setting `class_id = null`, so the logged `from_class` can degrade to `Passed Out` instead of the original class.

Additional schema risk:

- The live `students` table inspected by read-only schema check does not contain a `status` column.
- A separate `student_statuses` table exists and currently has 1 row.
- Therefore `markAsPassedOut()` is likely to fail at runtime on the current schema before it can complete the class updates.

## Live Data Read-Only Risk Counts

Read-only checks only:

| Check | Result |
|---|---:|
| `students` table exists | yes |
| `students.status` column exists | no |
| `student_statuses` table exists | yes |
| `student_statuses` row count | 1 |
| Students with `class_id != school_class_id` | 1 |
| Passed-out total by `students.status` | not checkable because `students.status` is missing |
| Passed-out with `class_id` null and `school_class_id` not null | not checkable because `students.status` is missing |
| Passed-out with `section_id` not null | not checkable because `students.status` is missing |
| Passed-out with `section` not null | not checkable because `students.status` is missing |
| Students with `class = Passed Out` and class IDs still populated | 0 |

The first attempted passed-out count failed read-only with `Unknown column 'status' in 'where clause'`, confirming schema drift.

## Drift Risks

1. Promotion updates `class_id` but not `school_class_id`, creating FK disagreement.
2. Promotion updates string `class` but does not use the Phase 3E normalization pattern.
3. Promotion write and promotion log creation are not transactional.
4. Passed-out writes `students.status`, but the current live students table has no `status` column.
5. Passed-out nulls `class_id` but leaves `school_class_id`, `section_id`, and `section` unchanged if the status write is ever made schema-compatible.
6. Passed-out logging reads the student's prior class after the class has already been nulled/changed.
7. `Route::resource` exposes unimplemented `show`, `edit`, `update`, and `destroy` methods.
8. Promotion index groups by `class_id` and `class`, but then counts again by legacy `class` string in the Blade view.
9. `getStudentsByClass()` includes legacy `class` string and `like` fallback; useful for compatibility, but it can include drifted rows if names collide.
10. Promotion views query `AcademicSession` directly inside Blade.

## Recommended Promotion Normalization Behavior

Phase 3K should normalize only the promotion write path first:

- Resolve `$destinationClass` from `SchoolClass`.
- For promoted students, set:
  - `class_id = $destinationClass->id`
  - `school_class_id = $destinationClass->id`
  - `class = $destinationClass->name`
- Preserve current `section_id` and `section`, because the promotion UI does not currently change section.
- Wrap the selected student update and `StudentPromotionLog` creation in `DB::transaction()`.
- Capture the original class label before updating students.
- Do not bulk repair historical conflicts in the same phase.

## Recommended Passed-Out Behavior

Safer recommendation: use the existing `student_statuses` system for passed-out status rather than writing a missing `students.status` column.

For class compatibility fields, choose Option A after confirming the intended status storage:

- Record status in `student_statuses` or another confirmed active status mechanism.
- Set:
  - `class_id = null`
  - `school_class_id = null`
  - `class = Passed Out`
  - `section_id = null`
  - `section = null`
- Capture the original class and section values before mutating the student.
- Wrap the student update, status write, and promotion log creation in a transaction.

Reason: active student index/filter/class grouping already depend on class IDs. Leaving `school_class_id` or section fields populated for passed-out students would preserve misleading compatibility state.

## Safe Phase 3K Implementation Plan

1. Fix `StudentPromotionController@store` first.
2. Add a private normalization helper for promotion destination class fields, or inline the same narrow assignment:
   - `class_id`
   - `school_class_id`
   - `class`
3. Add transaction protection around student updates and `StudentPromotionLog` creation.
4. Preserve section fields during promotion.
5. Add isolated SQLite-memory tests for:
   - promotion sets `class_id`, `school_class_id`, and `class`
   - promotion preserves `section_id` and legacy `section`
   - promotion logs are created
   - destination class must be higher
6. Do not touch CSV/import, admin create/update, API student writes, or existing student data.
7. Handle `markAsPassedOut()` in a second step of Phase 3K or Phase 3L because it has a schema/status contract issue, not only class drift.
8. Consider replacing or limiting the resource route later to avoid exposed missing methods.

## Confirmation

- No application code was modified.
- No routes, models, controllers, migrations, or views were changed.
- No student records were promoted, passed out, imported, seeded, updated, or deleted.
- No migrations or database-changing test commands were run.
- The only file created in this phase is this report.
