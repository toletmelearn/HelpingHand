# Phase 3R - Manual Student Status Workflow Audit

## Files Inspected

- `app/Http/Controllers/Admin/StudentStatusController.php`
- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/StudentPromotionLog.php`
- `resources/views/admin/student-statuses/create.blade.php`
- `resources/views/admin/student-statuses/edit.blade.php`
- `resources/views/admin/student-statuses/index.blade.php`
- `resources/views/admin/student-statuses/show.blade.php`
- `resources/views/admin/student-promotion/index.blade.php`
- `resources/views/admin/student-promotion/create.blade.php`
- `routes/web.php`
- `tests/Feature/Admin/StudentPassedOutStatusTest.php`
- `tests/Feature/Admin/StudentStatusShowViewTest.php`
- `docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`
- `docs/project-autopsy/PHASE_3Q_STUDENT_STATUS_SHOW_VIEW_FIX.md`
- `docs/project-autopsy/PHASE_3N_STUDENT_STATUS_ROUTE_REPORT_AUDIT.md`

## Commands Run

- `php -l app/Http/Controllers/Admin/StudentStatusController.php`
- `php -l app/Http/Controllers/Admin/StudentPromotionController.php`
- `php -l app/Models/Student.php`
- `php -l app/Models/StudentStatus.php`
- `php artisan route:list | Select-String "student-status"`
- `php artisan route:list | Select-String "passed"`
- `php artisan route:list | Select-String "promotion"`
- `php artisan route:list --path=admin/student-statuses --json`
- `php artisan route:list --path=admin/student-promotions --json`
- `rg -n "passed_out|left_school|tc_issued|active|inactive|StudentStatus::create|student_statuses|class_id|school_class_id|section_id|StudentPromotionLog|DB::transaction" app/Http/Controllers/Admin/StudentStatusController.php app/Http/Controllers/Admin/StudentPromotionController.php resources/views/admin/student-statuses resources/views/admin/student-promotion routes/web.php tests/Feature/Admin/StudentPassedOutStatusTest.php tests/Feature/Admin/StudentStatusShowViewTest.php docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md docs/project-autopsy/PHASE_3Q_STUDENT_STATUS_SHOW_VIEW_FIX.md docs/project-autopsy/PHASE_3N_STUDENT_STATUS_ROUTE_REPORT_AUDIT.md -g "*.php" -g "*.blade.php" -g "*.md"`
- `php artisan tinker --execute="dump(DB::table('student_statuses')->select('status', DB::raw('count(*) as total'))->groupBy('status')->get());"`
- `php artisan tinker --execute="dump(DB::table('students')->where('class', 'Passed Out')->count());"`
- `php artisan tinker --execute="dump(DB::table('student_statuses')->select('student_id')->groupBy('student_id')->havingRaw('COUNT(*) > 1')->count());"`
- `php artisan tinker --execute="dump(DB::select('select ss.status, count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id group by ss.status'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id join students s on s.id = ss.student_id where ss.status in (\'passed_out\', \'left_school\', \'tc_issued\') and s.class_id is not null'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id join students s on s.id = ss.student_id where ss.status in (\'passed_out\', \'left_school\', \'tc_issued\') and s.school_class_id is not null'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id join students s on s.id = ss.student_id where ss.status in (\'passed_out\', \'left_school\', \'tc_issued\') and (s.section_id is not null or s.section is not null)'));"`

All database checks were read-only SELECT/count queries.

## StudentStatusController CRUD Map

| Method | Route | Request Fields | Allowed Statuses | Writes StudentStatus | Mutates Student Class/Section | Creates Promotion Log | Risk |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `index` | `admin.student-statuses.index` / `GET admin/student-statuses` | none | n/a | no | no | no | Low |
| `create` | `admin.student-statuses.create` / `GET admin/student-statuses/create` | none | n/a | no | no | no | Medium, because form exposes terminal statuses |
| `store` | `admin.student-statuses.store` / `POST admin/student-statuses` | `student_id`, `status`, `status_date`, `reason`, `remarks`, `document_number`, `document_issue_date`, `issued_by` | `passed_out`, `tc_issued`, `left_school`, `active`, `inactive` | yes | no | no | High |
| `show` | `admin.student-statuses.show` / `GET admin/student-statuses/{student_status}` | route model/id | n/a | no | no | no | Low after Phase 3Q |
| `edit` | `admin.student-statuses.edit` / `GET admin/student-statuses/{student_status}/edit` | route model/id | n/a | no | no | no | Medium, because form exposes terminal statuses |
| `update` | `admin.student-statuses.update` / `PUT/PATCH admin/student-statuses/{student_status}` | same as store | `passed_out`, `tc_issued`, `left_school`, `active`, `inactive` | yes | no | no | High |
| `destroy` | `admin.student-statuses.destroy` / `DELETE admin/student-statuses/{student_status}` | route model/id | n/a | deletes status row | no | no | Medium, can erase terminal status history |

## Status Form / Dropdown Findings

- `create.blade.php` exposes `active`, `inactive`, `passed_out`, `tc_issued`, and `left_school`.
- `edit.blade.php` exposes the same statuses, so an existing normal status can be changed into a terminal status.
- Neither form warns that terminal statuses should go through dedicated workflows.
- Neither form triggers class/section compatibility cleanup.
- Neither form creates promotion logs for terminal transitions.
- The generic CRUD can create duplicate status history rows for the same student.

## Terminal Status Risk Classification

| Status | Classification | Generic CRUD Recommendation | Reason |
| --- | --- | --- | --- |
| `active` | Normal/general | Keep allowed | Does not require class/section cleanup. |
| `inactive` | Normal/general | Keep allowed | Does not necessarily imply terminal academic movement. |
| `passed_out` | Terminal/workflow-sensitive | Remove/reject in generic CRUD | Phase 3M dedicated workflow clears class/section fields and creates promotion log. Generic CRUD does not. |
| `left_school` | Terminal/workflow-sensitive | Remove/reject in generic CRUD until dedicated workflow exists | Likely needs class/section/status cleanup and audit trail. |
| `tc_issued` | Terminal/workflow-sensitive | Remove/reject in generic CRUD until dedicated workflow exists | Likely needs document workflow and audit trail. |

## Live Data Read-Only Counts

- `student_statuses` grouped by status:
  - `passed_out`: 1
- Latest status grouped by status:
  - `passed_out`: 1
- Students with `class = 'Passed Out'`: 0
- Students with duplicate status records: 0
- Latest terminal status with `students.class_id` still populated: 1
- Latest terminal status with `students.school_class_id` still populated: 1
- Latest terminal status with `students.section_id` or `students.section` still populated: 1

These counts confirm the manual/generic status path can represent terminal status without the Phase 3M cleanup state.

## Recommended Terminal Status Policy

Use a dedicated workflow for terminal statuses. Generic `StudentStatusController` CRUD should be restricted to normal statuses only:

- Allow generic create/update for `active` and `inactive`.
- Remove `passed_out`, `left_school`, and `tc_issued` from generic create/edit dropdowns.
- Add controller-side validation/rejection for terminal statuses in generic `store()` and `update()`.
- Keep terminal status rows viewable for audit/history.
- Do not bulk repair historical rows in the same phase.

This is safer than duplicating Phase 3M cleanup logic in generic CRUD because each terminal transition may require different audit trail, document, and class/section behavior.

## Safe Phase 3S Implementation Plan

1. Update `StudentStatusController@store()` and `update()` to reject `passed_out`, `left_school`, and `tc_issued` from generic manual CRUD with a clear validation message.
2. Update `student-statuses/create.blade.php` and `edit.blade.php` dropdowns to show only `active` and `inactive`.
3. Add small helper text directing admins to the dedicated passed-out workflow for passed-out students, and note that left-school/TC workflows are not yet enabled.
4. Add isolated SQLite-memory tests for:
   - generic CRUD accepts `active`
   - generic CRUD accepts `inactive`
   - generic CRUD rejects `passed_out`
   - generic CRUD rejects `left_school`
   - generic CRUD rejects `tc_issued`
   - dropdowns no longer expose terminal statuses
5. Do not touch Phase 3M passed-out workflow.
6. Do not repair existing terminal drift rows yet; create a later read-only reconciliation/report phase first.

## Confirmation

No application code, routes, views, models, migrations, or database data were modified in this phase. Only this report was created. No migrations, seeders, imports, promotions, passed-out actions, tests, or database-changing commands were run.
