# Phase 3N - Student Status Route / Report Stability Audit

## Files Inspected

- `routes/web.php`
- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Http/Controllers/Admin/StudentStatusController.php`
- `app/Http/Controllers/Admin/AdvancedReportController.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/StudentPromotionLog.php`
- `resources/views/admin/student-statuses/index.blade.php`
- `resources/views/admin/student-statuses/create.blade.php`
- `resources/views/admin/student-statuses/edit.blade.php`
- `resources/views/admin/student-statuses/show.blade.php`
- `resources/views/admin/student-promotion/index.blade.php`
- `resources/views/admin/student-promotion/create.blade.php`
- `resources/views/admin/student-promotion/history.blade.php`
- `docs/project-autopsy/PHASE_3L_PASSED_OUT_STATUS_SYSTEM_AUDIT.md`
- `docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`

## Commands Run

- `Get-Content routes/web.php`
- `Get-Content app/Http/Controllers/Admin/StudentPromotionController.php`
- `Get-Content app/Http/Controllers/Admin/StudentStatusController.php`
- `Get-Content app/Http/Controllers/Admin/AdvancedReportController.php`
- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/StudentStatus.php`
- `Get-Content app/Models/StudentPromotionLog.php`
- `Get-ChildItem resources/views/admin/student-statuses`
- `Get-ChildItem resources/views/admin/student-promotion`
- `Get-Content resources/views/admin/student-statuses/index.blade.php`
- `Get-Content resources/views/admin/student-statuses/create.blade.php`
- `Get-Content resources/views/admin/student-statuses/edit.blade.php`
- `Get-Content resources/views/admin/student-statuses/show.blade.php`
- `Get-Content resources/views/admin/student-promotion/index.blade.php`
- `Get-Content resources/views/admin/student-promotion/create.blade.php`
- `Get-Content resources/views/admin/student-promotion/history.blade.php`
- `Get-Content docs/project-autopsy/PHASE_3L_PASSED_OUT_STATUS_SYSTEM_AUDIT.md`
- `Get-Content docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`
- `php -l app/Http/Controllers/Admin/StudentPromotionController.php`
- `php -l app/Http/Controllers/Admin/StudentStatusController.php`
- `php -l app/Http/Controllers/Admin/AdvancedReportController.php`
- `php -l app/Models/Student.php`
- `php -l app/Models/StudentStatus.php`
- `php artisan route:list | Select-String "student-promotions"`
- `php artisan route:list | Select-String "student-status"`
- `php artisan route:list | Select-String "report"`
- `php artisan route:list --path=admin/student-promotions --json`
- `php artisan route:list --path=admin/student-statuses --json`
- `php artisan route:list --path=admin/advanced-reports --json`
- `rg -n "currentClass|schoolClass|student-status|student-promotions|passed_out|Passed Out|where\('status'|where\(\"status\"|student_statuses|status" routes app resources database docs/project-autopsy/PHASE_3L_PASSED_OUT_STATUS_SYSTEM_AUDIT.md docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md -g "*.php" -g "*.blade.php" -g "*.md"`
- `rg -n "student-promotions\.show|student-promotions\.edit|student-promotions\.update|student-promotions\.destroy|student-promotions\.passed-out|student-statuses|advanced-reports\.dashboard|currentClass|StudentStatus|student_statuses" routes app resources tests docs/project-autopsy -g "*.php" -g "*.blade.php" -g "*.md"`
- `rg -n "Route::resource\('student-promotions'|Route::resource\('student-statuses'|passed-out|student-promotions|student-statuses|advanced-reports" routes/web.php`
- `rg -n -F "where('status'" app/Http/Controllers/Admin/AdvancedReportController.php app/Models/Student.php app/Http/Controllers/Admin/StudentStatusController.php app/Http/Controllers/Admin/StudentPromotionController.php resources/views/admin/student-statuses`
- `rg -n -F "students.status" app/Http/Controllers/Admin/AdvancedReportController.php app/Models/Student.php app/Http/Controllers/Admin/StudentStatusController.php app/Http/Controllers/Admin/StudentPromotionController.php resources/views/admin/student-statuses`
- `rg -n -F "status" app/Http/Controllers/Admin/AdvancedReportController.php`
- Read-only `php artisan tinker --execute="..."` schema/count check for `students.status`, `student_statuses`, `student_promotion_logs`, `Passed Out` strings, and class FK conflicts.

Notes:

- The prompt listed `php artisan route`; this Laravel app uses `php artisan route:list`, so route inspections used `route:list`.
- One exploratory `rg` command with a grouped regex failed due a regex parse issue and was replaced with fixed-string/literal searches. No files or data were changed.

## Student Promotion Resource Route Findings

Active routes under `web`, authenticated, verified middleware:

| Method | URI | Route name | Controller method | Method exists | Finding |
|---|---|---|---|---|---|
| GET/HEAD | `admin/student-promotions` | `admin.student-promotions.index` | `index` | yes | Active promotion dashboard. |
| POST | `admin/student-promotions` | `admin.student-promotions.store` | `store` | yes | Fixed in Phase 3K; writes normalized class fields. |
| GET/HEAD | `admin/student-promotions/create` | `admin.student-promotions.create` | `create` | yes | Active promotion form. |
| GET/HEAD | `admin/student-promotions/class/{class}/students` | `admin.student-promotions.get-students` | `getStudentsByClass` | yes | AJAX student loader. |
| GET/HEAD | `admin/student-promotions/destination-classes/{class}` | `admin.student-promotions.get-destination-classes` | `getDestinationClasses` | yes | AJAX destination-class loader. |
| GET/HEAD | `admin/student-promotions/student/{studentId}/history` | `admin.student-promotions.history` | `studentHistory` | yes | Active history view. |
| POST | `admin/student-promotions/student/{studentId}/passed-out` | `admin.student-promotions.passed-out` | `markAsPassedOut` | yes | Fixed in Phase 3M. |
| GET/HEAD | `admin/student-promotions/{student_promotion}` | `admin.student-promotions.show` | `show` | no | Active route points to missing method. Quarantine later. |
| GET/HEAD | `admin/student-promotions/{student_promotion}/edit` | `admin.student-promotions.edit` | `edit` | no | Active route points to missing method. Quarantine later. |
| PUT/PATCH | `admin/student-promotions/{student_promotion}` | `admin.student-promotions.update` | `update` | no | Active route points to missing method. Quarantine later. |
| DELETE | `admin/student-promotions/{student_promotion}` | `admin.student-promotions.destroy` | `destroy` | no | Active route points to missing method. Quarantine later. |

The inspected promotion views link only to:

- `admin.student-promotions.index`
- `admin.student-promotions.create`
- `admin.student-promotions.store`
- AJAX paths for `class/{class}/students` and `destination-classes/{class}`

No inspected promotion view referenced `admin.student-promotions.show`, `edit`, `update`, or `destroy`.

## StudentStatusController Findings

Active routes under `web`, authenticated, verified middleware:

| Method | URI | Route name | Controller method | Method exists | Finding |
|---|---|---|---|---|---|
| GET/HEAD | `admin/student-statuses` | `admin.student-statuses.index` | `index` | yes | Lists all status records with student relation. |
| GET/HEAD | `admin/student-statuses/create` | `admin.student-statuses.create` | `create` | yes | Loads all students for manual status creation. |
| POST | `admin/student-statuses` | `admin.student-statuses.store` | `store` | yes | Validates and writes `student_statuses`. |
| GET/HEAD | `admin/student-statuses/{student_status}` | `admin.student-statuses.show` | `show` | yes | Likely view risk due missing `currentClass`. |
| GET/HEAD | `admin/student-statuses/{student_status}/edit` | `admin.student-statuses.edit` | `edit` | yes | Loads editable status record and students. |
| PUT/PATCH | `admin/student-statuses/{student_status}` | `admin.student-statuses.update` | `update` | yes | Updates `student_statuses`. |
| DELETE | `admin/student-statuses/{student_status}` | `admin.student-statuses.destroy` | `destroy` | yes | Deletes a status record. |

Controller behavior:

- Uses the confirmed `student_statuses` table through `StudentStatus`.
- Supports `passed_out`, `tc_issued`, `left_school`, `active`, and `inactive`.
- Does not write the missing `students.status` column.
- Does not update student class/section compatibility fields when a `passed_out`, `tc_issued`, or `left_school` status is manually created.
- Does not enforce uniqueness or latest-status semantics, so duplicate status records per student are possible.
- `index()` and `show()` eager load only `student`, not student class/section relationships.

UI exposure:

- `resources/views/layouts/sidebar.blade.php` links to `admin.student-statuses.index`.
- `resources/views/admin-dashboard.blade.php` links to `admin.student-statuses.index`.
- The status UI is active and reachable from admin-facing navigation.

Compatibility with Phase 3M:

- Phase 3M passed-out flow uses `StudentStatus::create()` and clears class/section fields in the promotion controller.
- Manual status CRUD uses the same status table but does not share the passed-out cleanup behavior. This is acceptable as current generic CRUD, but it is a drift risk if admins use it as an alternate passed-out workflow.

## AdvancedReportController Status Query Findings

`AdvancedReportController@dashboard()` calls `getStudentAnalytics()`.

`getStudentAnalytics()` builds `Student::query()` and then queries:

- line 127: `where('status', 'passed_out')`
- line 128: `where('status', 'left_school')`
- line 129: `where('status', 'active')`

Live schema check confirms `students.status` does not exist. These student analytics queries are likely to fail when the advanced report dashboard or export path reaches `getStudentAnalytics()`.

Additional reporting note:

- `getStudentAnalytics()` reuses the same mutable `$query` instance for `total_students`, `new_admissions`, `passed_out`, `left_school`, and `active_students`.
- Even after replacing `students.status`, each metric should use cloned/base queries or isolated query builders, otherwise earlier filters like `whereBetween('created_at', ...)` may leak into later counts.

Future correct approach:

- Use `student_statuses` as the status source.
- Decide "latest status per student" semantics.
- For passed-out/left-school/active counts, prefer a latest-status subquery or relationship rather than any `students.status` filter.
- Fall back safely for students with no status record, likely treating them as active until a dedicated status backfill/decision exists.

## Student Status View Findings

| View | Finding |
|---|---|
| `index.blade.php` | Mostly safe. Uses `$studentStatus->student->name`, status fields, and CRUD route helpers. Potential null-date risk if `status_date` is null, though validation requires it for CRUD-created records. |
| `create.blade.php` | Uses `student_id`, `status`, `status_date`, reason, document fields, `issued_by`, and remarks. Compatible with `StudentStatusController@store`. |
| `edit.blade.php` | Uses the same status table fields. Compatible with `StudentStatusController@update`. Potential null-date risk if old records have null date. |
| `show.blade.php` | References `$studentStatus->student->currentClass->name`, but `Student` has no `currentClass()` relationship. This is a runtime stability risk. |

Student model relationship evidence:

- Existing class relation: `schoolClass()` belongs to `SchoolClass` through `class_id`.
- Existing alias relation: `class()` belongs to `SchoolClass` through `class_id`.
- Existing section relation: `section()` belongs to `Section` through `section_id`.
- Missing relation: `currentClass()`.

Recommended view fix later:

- Replace `currentClass` usage with a safe canonical class display:
  - `$studentStatus->student?->resolveCanonicalSchoolClass()?->name`
  - or `$studentStatus->student?->schoolClass?->name ?? $studentStatus->student?->class ?? 'N/A'`
- Keep this out of Phase 3N because this phase is read-only.

## Live Data Read-Only Status Counts

| Check | Result |
|---|---:|
| `students.status` exists | no |
| `student_statuses` exists | yes |
| `student_statuses` columns | `id`, `student_id`, `status`, `status_date`, `reason`, `remarks`, `document_number`, `document_issue_date`, `issued_by`, `created_at`, `updated_at` |
| `student_statuses` grouped by status | `passed_out`: 1 |
| Students with `class = Passed Out` | 0 |
| Student promotion logs with `to_class = Passed Out` | 0 |
| Students with `class_id != school_class_id` | 1 |

Interpretation:

- The schema confirms reports must not query `students.status`.
- There is at least one passed-out status record.
- Current live data does not show a matching `StudentPromotionLog` with `to_class = Passed Out`, so historical status/log alignment should not be assumed.
- Existing class FK conflict count remains 1; Phase 3N did not repair data.

## Top 10 Route / Report / Status Risks

1. `admin.student-promotions.show` is active but `StudentPromotionController@show` is missing.
2. `admin.student-promotions.edit` is active but `StudentPromotionController@edit` is missing.
3. `admin.student-promotions.update` is active but `StudentPromotionController@update` is missing.
4. `admin.student-promotions.destroy` is active but `StudentPromotionController@destroy` is missing.
5. `AdvancedReportController@getStudentAnalytics()` queries missing `students.status`.
6. `AdvancedReportController@getStudentAnalytics()` reuses a mutable query object across multiple metric counts.
7. `student-statuses/show.blade.php` references missing `Student::currentClass()`.
8. Manual `StudentStatusController` CRUD can create `passed_out` records without applying Phase 3M class/section cleanup.
9. Student status CRUD allows multiple status rows per student without "latest status" or uniqueness semantics.
10. Live data currently has one `passed_out` status record but zero `StudentPromotionLog` rows with `to_class = Passed Out`, so reporting may find inconsistent historical evidence.

## Recommended Phase 3O First Code Task

Safest first code task: quarantine the unimplemented student-promotion resource member routes.

Recommended Phase 3O sequence:

1. Change `Route::resource('student-promotions', StudentPromotionController::class)` to expose only implemented methods, likely `only(['index', 'create', 'store'])`, while keeping the existing custom AJAX/history/passed-out routes.
2. Run route-list verification to prove `show`, `edit`, `update`, and `destroy` are no longer active.
3. Add a small route-alignment test or static route assertion if feasible.
4. Do not change passed-out logic in Phase 3O unless a direct regression appears.

Recommended Phase 3P follow-up:

1. Fix `AdvancedReportController@getStudentAnalytics()` to use `student_statuses`.
2. Define latest-status behavior.
3. Use cloned/base queries for each metric.
4. Add isolated report analytics tests.

Recommended Phase 3Q follow-up:

1. Fix `student-statuses/show.blade.php` to use `schoolClass`, `resolveCanonicalSchoolClass()`, or another safe canonical class display.
2. Consider whether manual status CRUD should disable `passed_out` or route users through the Phase 3M passed-out workflow.

## Confirmation

- No application code was modified.
- No routes, controllers, models, views, migrations, or tests were changed.
- No student data was created, updated, deleted, promoted, passed out, seeded, or imported.
- No migrations were run.
- No full test suite or database-changing tests were run.
- No real/local MySQL data was touched.
- The only file created in this phase is this report.
