# Phase 3L - Passed-Out / Student Status System Audit

## Files Inspected

- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Http/Controllers/Admin/StudentStatusController.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/StudentPromotionLog.php`
- `database/migrations/2026_01_17_070802_create_students_table.php`
- `database/migrations/2026_01_29_090059_create_student_statuses_table.php`
- `database/migrations/2026_01_30_041845_create_student_promotion_logs_table.php`
- `resources/views/admin/student-promotion/create.blade.php`
- `resources/views/admin/student-promotion/index.blade.php`
- `resources/views/admin/student-promotion/history.blade.php`
- `resources/views/admin/student-statuses/index.blade.php`
- `resources/views/admin/student-statuses/create.blade.php`
- `resources/views/admin/student-statuses/edit.blade.php`
- `resources/views/admin/student-statuses/show.blade.php`
- `routes/web.php`
- `docs/project-autopsy/PHASE_3J_STUDENT_PROMOTION_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_3K_STUDENT_PROMOTION_NORMALIZATION.md`

## Commands Run

- `Get-Content app/Http/Controllers/Admin/StudentPromotionController.php`
- `Get-Content app/Http/Controllers/Admin/StudentStatusController.php`
- `Get-Content app/Models/StudentStatus.php`
- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/StudentPromotionLog.php`
- `Get-ChildItem resources/views/admin/student-statuses -Force`
- `Get-Content resources/views/admin/student-statuses/index.blade.php`
- `Get-Content resources/views/admin/student-statuses/create.blade.php`
- `Get-Content resources/views/admin/student-statuses/edit.blade.php`
- `Get-Content resources/views/admin/student-statuses/show.blade.php`
- `Get-Content database/migrations/2026_01_17_070802_create_students_table.php`
- `Get-Content database/migrations/2026_01_29_090059_create_student_statuses_table.php`
- `Get-Content database/migrations/2026_01_30_041845_create_student_promotion_logs_table.php`
- `php -l app/Http/Controllers/Admin/StudentPromotionController.php`
- `php -l app/Http/Controllers/Admin/StudentStatusController.php`
- `php -l app/Models/Student.php`
- `php -l app/Models/StudentPromotionLog.php`
- `php artisan route:list | Select-String "student-status"`
- `php artisan route:list | Select-String "passed"`
- `php artisan route:list | Select-String "promotion"`
- `php artisan route:list --path=student-statuses --json`
- `php artisan route:list --path=student-promotions --json`
- `rg -n "student-status|student_status|StudentStatus|passed_out|Passed Out|markAsPassedOut|status' => 'passed_out|currentClass|student_statuses" app routes resources database docs -g "*.php" -g "*.blade.php" -g "*.md"`
- Read-only `php artisan tinker --execute="..."` schema/count checks for `students`, `student_statuses`, passed-out strings, null class/section fields, and class FK conflicts.

Note: the prompt listed `php artisan route`; this Laravel 12 app exposes `route:list`, so route inspection was run with `php artisan route:list`.

## Status Storage Map

| Evidence | Finding |
|---|---|
| Live schema check | `students.status` does not exist. |
| `2026_01_17_070802_create_students_table.php` | Initial `students` migration has no `status` column. |
| `2026_01_29_090059_create_student_statuses_table.php` | Creates `student_statuses` with `student_id`, `status`, `status_date`, `reason`, `remarks`, document fields, timestamps. |
| `app/Models/StudentStatus.php` | Model exists and is fillable for `student_statuses` columns. |
| `StudentStatusController` | Full CRUD writes `StudentStatus::create()` / `update()`. |
| Student status views | UI supports `active`, `inactive`, `passed_out`, `tc_issued`, `left_school`. |
| Routes | `Route::resource('student-statuses', StudentStatusController::class)` is active under admin auth. |
| Admin dashboard/sidebar references | Student status management is linked from dashboard/sidebar. |

Decision: the current status system is `student_statuses`, not `students.status`.

## Passed-Out Route / Flow Map

| Item | Current Behavior |
|---|---|
| Route | `POST admin/student-promotions/student/{studentId}/passed-out` |
| Route name | `admin.student-promotions.passed-out` |
| Controller | `StudentPromotionController@markAsPassedOut` |
| Middleware | `web`, authenticated, verified |
| Request fields | `remarks` only |
| Validation | `remarks` nullable string |
| Student lookup | `Student::findOrFail($studentId)` |
| Current session lookup | `AcademicSession::current()->first()` |
| Student update | writes `status = passed_out`, `class_id = null`, `class = Passed Out` |
| Student fields not cleared | `school_class_id`, `section_id`, `section` |
| Status table write | none |
| Promotion log write | creates `StudentPromotionLog` |
| Transaction | none |
| Runtime risk | likely fails because `students.status` does not exist |
| Logging risk | `from_class` is read after mutation, so original class can be lost |

## StudentStatusController Findings

- Manages `student_statuses` records.
- Supports `passed_out`.
- Validates:
  - `student_id`
  - `status`
  - `status_date`
  - `reason`
  - `remarks`
  - document metadata
- Uses the right table/model for status persistence.
- Is linked from admin dashboard/sidebar.
- Does not modify student class compatibility fields.
- `resources/views/admin/student-statuses/show.blade.php` references `$studentStatus->student->currentClass->name`, but `Student` inspection did not show a `currentClass()` relation. This view may fail independently and should be checked in a later UI stability phase.

Conclusion: `markAsPassedOut()` should use `StudentStatus` or a shared status-recording pattern, but it must also handle class/section compatibility fields because `StudentStatusController` currently does not.

## Live Data Read-Only Risk Counts

| Check | Result |
|---|---:|
| `students.status` exists | no |
| `student_statuses` table exists | yes |
| `student_statuses` columns | `id`, `student_id`, `status`, `status_date`, `reason`, `remarks`, `document_number`, `document_issue_date`, `issued_by`, timestamps |
| `student_statuses` total rows | 1 |
| `student_statuses` rows with `passed_out` | 1 |
| Students with `class = Passed Out` | 0 |
| Students with `class_id` null and `school_class_id` non-null | 0 |
| Students with `class_id` null and `section_id` non-null | 0 |
| Students with `class_id` null and `section` non-null | 0 |
| Students with `class_id != school_class_id` | 1 |

No data was changed by these checks.

## Passed-Out Behavior Recommendation

Recommended Phase 3M design:

1. Do not write `students.status`.
2. Record the passed-out status in `student_statuses`.
3. Wrap the full operation in `DB::transaction()`.
4. Capture original class/section values before mutation.
5. Update student class compatibility fields consistently:
   - `class_id = null`
   - `school_class_id = null`
   - `class = Passed Out`
   - `section_id = null`
   - `section = null`
6. Create `StudentPromotionLog` using the original class label, not mutated values.
7. Preserve `Auth::id()` for `promoted_by`.
8. Do not bulk repair existing rows in the same phase.

Suggested `StudentStatus` payload:

- `student_id = $student->id`
- `status = passed_out`
- `status_date = now()->toDateString()`
- `reason = null` or a short fixed reason such as `Passed out`
- `remarks = request remarks or default`
- `issued_by = auth user name/id if safe, otherwise null`

## Resource Route Risk Findings

Student promotion resource routes remain active:

- `admin.student-promotions.show`
- `admin.student-promotions.edit`
- `admin.student-promotions.update`
- `admin.student-promotions.destroy`

`StudentPromotionController` still does not implement `show`, `edit`, `update`, or `destroy`. These routes should be quarantined or narrowed in a later route-safety phase. Do not mix that change into Phase 3M unless the passed-out route fix requires it.

Student status resource routes are active and backed by implemented controller methods.

## Additional Status Drift Findings

- `AdvancedReportController` still queries `Student` by `status`:
  - `where('status', 'passed_out')`
  - `where('status', 'left_school')`
  - `where('status', 'active')`
- Against the current live schema, those report queries are likely to fail.
- This should be handled in a later reporting/status alignment phase, not inside the passed-out route fix.

## Safe Phase 3M Implementation Plan

1. Modify only `StudentPromotionController@markAsPassedOut`.
2. Import/use `App\Models\StudentStatus`.
3. Capture original class label before modifying the student.
4. Use `DB::transaction()` for:
   - student compatibility field update
   - `StudentStatus::create()`
   - `StudentPromotionLog::create()`
5. Do not write `students.status`.
6. Clear `school_class_id`, `section_id`, and `section` together with `class_id`.
7. Add isolated SQLite-memory tests for:
   - passed-out creates a `student_statuses` row
   - passed-out does not require/write `students.status`
   - passed-out clears class and section compatibility fields
   - promotion log uses original class label
   - passed-out operation is transaction-safe enough for the happy path
8. Do not touch promotion store again unless a regression appears.
9. Do not touch CSV/import/API student writes.
10. Do not bulk repair existing live data.

## Confirmation

- No application code was modified.
- No routes, controllers, models, views, migrations, or tests were changed.
- No student data was updated, inserted, deleted, promoted, passed out, seeded, or imported.
- No migrations, database resets, or database-changing tests were run.
- The only file created in this phase is this report.
