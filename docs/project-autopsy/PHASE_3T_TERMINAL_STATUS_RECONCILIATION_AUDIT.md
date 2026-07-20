# Phase 3T - Terminal Status Reconciliation Audit

## Files Inspected

- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/StudentPromotionLog.php`
- `app/Http/Controllers/Admin/StudentStatusController.php`
- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Http/Controllers/Admin/AdvancedReportController.php`
- `resources/views/admin/student-statuses/index.blade.php`
- `resources/views/admin/student-statuses/show.blade.php`
- `resources/views/admin/student-promotion/history.blade.php`
- `docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`
- `docs/project-autopsy/PHASE_3R_MANUAL_STUDENT_STATUS_WORKFLOW_AUDIT.md`
- `docs/project-autopsy/PHASE_3S_STUDENT_STATUS_CRUD_RESTRICTION.md`

## Commands Run

- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/StudentStatus.php`
- `Get-Content app/Models/StudentPromotionLog.php`
- `Get-Content app/Http/Controllers/Admin/StudentStatusController.php`
- `Get-Content app/Http/Controllers/Admin/StudentPromotionController.php`
- `Get-Content app/Http/Controllers/Admin/AdvancedReportController.php`
- `Get-Content resources/views/admin/student-statuses/index.blade.php`
- `Get-Content resources/views/admin/student-statuses/show.blade.php`
- `Get-Content resources/views/admin/student-promotion/history.blade.php`
- `php -l app/Models/Student.php`
- `php -l app/Models/StudentStatus.php`
- `php -l app/Models/StudentPromotionLog.php`
- `php -l app/Http/Controllers/Admin/StudentStatusController.php`
- `php -l app/Http/Controllers/Admin/StudentPromotionController.php`
- `php -l app/Http/Controllers/Admin/AdvancedReportController.php`
- `rg -n "passed_out|left_school|tc_issued|StudentStatus::create|StudentPromotionLog|DB::transaction|class_id|school_class_id|section_id|Passed Out" app/Models/Student.php app/Models/StudentStatus.php app/Models/StudentPromotionLog.php app/Http/Controllers/Admin/StudentStatusController.php app/Http/Controllers/Admin/StudentPromotionController.php app/Http/Controllers/Admin/AdvancedReportController.php resources/views/admin/student-statuses/index.blade.php resources/views/admin/student-statuses/show.blade.php resources/views/admin/student-promotion/history.blade.php docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md docs/project-autopsy/PHASE_3R_MANUAL_STUDENT_STATUS_WORKFLOW_AUDIT.md docs/project-autopsy/PHASE_3S_STUDENT_STATUS_CRUD_RESTRICTION.md -g "*.php" -g "*.blade.php" -g "*.md"`

Read-only database checks:

- `php artisan tinker --execute="dump(['total_student_statuses' => DB::table('student_statuses')->count(), 'students_class_passed_out' => DB::table('students')->where('class', 'Passed Out')->count(), 'passed_out_logs' => DB::table('student_promotion_logs')->where('to_class', 'Passed Out')->count()]);"`
- `php artisan tinker --execute="dump(DB::table('student_statuses')->select('status', DB::raw('count(*) as total'))->groupBy('status')->get());"`
- `php artisan tinker --execute="dump(DB::select('select ss.status, count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id group by ss.status'));"`
- `php artisan tinker --execute="dump(DB::table('student_statuses')->select('student_id')->groupBy('student_id')->havingRaw('COUNT(*) > 1')->count());"`
- `php artisan tinker --execute="dump(DB::select('select ss.status, count(*) as latest_terminal, sum(case when s.class_id is not null then 1 else 0 end) as has_class_id, sum(case when s.school_class_id is not null then 1 else 0 end) as has_school_class_id, sum(case when s.section_id is not null then 1 else 0 end) as has_section_id, sum(case when s.section is not null then 1 else 0 end) as has_section, sum(case when ss.status = \'passed_out\' and (s.class is null or s.class <> \'Passed Out\') then 1 else 0 end) as passed_out_class_string_not_passed_out from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id join students s on s.id = ss.student_id where ss.status in (\'passed_out\', \'left_school\', \'tc_issued\') group by ss.status'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id where ss.status in (\'passed_out\', \'left_school\', \'tc_issued\')'));"`
- `php artisan tinker --execute="dump(DB::select('select s.id, s.name, ss.status, s.class_id, s.school_class_id, s.class, s.section_id, s.section from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id join students s on s.id = ss.student_id where ss.status in (\'passed_out\', \'left_school\', \'tc_issued\') and (s.class_id is not null or s.school_class_id is not null or s.section_id is not null or s.section is not null or (ss.status = \'passed_out\' and (s.class is null or s.class <> \'Passed Out\'))) order by s.id limit 50'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from students where class_id is not null and school_class_id is not null and class_id <> school_class_id')); dump(DB::select('select id, name, class_id, school_class_id, class from students where class_id is not null and school_class_id is not null and class_id <> school_class_id order by id limit 50'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id where ss.status = \'passed_out\' and not exists (select 1 from student_promotion_logs spl where spl.student_id = ss.student_id and spl.to_class = \'Passed Out\')')); dump(DB::select('select ss.student_id, ss.status from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id where ss.status = \'passed_out\' and not exists (select 1 from student_promotion_logs spl where spl.student_id = ss.student_id and spl.to_class = \'Passed Out\') order by ss.student_id limit 50'));"`
- `php artisan tinker --execute="dump(DB::select('select count(distinct spl.student_id) as total from student_promotion_logs spl where spl.to_class = \'Passed Out\' and not exists (select 1 from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id where ss.student_id = spl.student_id and ss.status = \'passed_out\')'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from student_promotion_logs where to_class = \'Passed Out\' and (from_class is null or trim(from_class) = \'\' or from_class in (\'Unknown\', \'Passed Out\'))')); dump(DB::select('select id, student_id, from_class, to_class from student_promotion_logs where to_class = \'Passed Out\' and (from_class is null or trim(from_class) = \'\' or from_class in (\'Unknown\', \'Passed Out\')) order by id limit 50'));"`
- `php artisan tinker --execute="dump(['class_id_null_school_class_id_populated' => DB::table('students')->whereNull('class_id')->whereNotNull('school_class_id')->count(), 'class_id_populated_school_class_id_null' => DB::table('students')->whereNotNull('class_id')->whereNull('school_class_id')->count(), 'class_id_null_no_latest_terminal' => DB::select('select count(*) as total from students s where s.class_id is null and not exists (select 1 from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id where ss.student_id = s.id and ss.status in (\'passed_out\', \'left_school\', \'tc_issued\'))')[0]->total]);"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from students s where s.class = \'Passed Out\' and not exists (select 1 from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id where ss.student_id = s.id and ss.status = \'passed_out\')'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from student_statuses ss join (select student_id, max(id) as id from student_statuses group by student_id) latest on latest.id = ss.id join students s on s.id = ss.student_id where ss.status in (\'passed_out\', \'left_school\', \'tc_issued\') and (s.class_id is not null or s.school_class_id is not null or s.section_id is not null or s.section is not null or (ss.status = \'passed_out\' and (s.class is null or s.class <> \'Passed Out\')))'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from students s join school_classes sc on sc.id = s.class_id where s.class is not null and s.class <> sc.name')); dump(DB::select('select s.id, s.name, s.class_id, sc.name as canonical_class, s.class from students s join school_classes sc on sc.id = s.class_id where s.class is not null and s.class <> sc.name order by s.id limit 50'));"`
- `php artisan tinker --execute="dump(DB::select('select count(*) as total from students where class_id is not null and school_class_id is not null and class_id <> school_class_id')); dump(DB::select('select count(*) as total from students where class_id is null and school_class_id is not null')); dump(DB::select('select count(*) as total from students where class_id is not null and school_class_id is null'));"`

All database commands were read-only SELECT/count inspections.

## Status Data Snapshot

| Metric | Count |
| --- | ---: |
| Total `student_statuses` rows | 1 |
| `student_statuses.status = passed_out` | 1 |
| `student_statuses.status = left_school` | 0 |
| `student_statuses.status = tc_issued` | 0 |
| Latest statuses per student | 1 |
| Latest `passed_out` statuses | 1 |
| Latest `left_school` statuses | 0 |
| Latest `tc_issued` statuses | 0 |
| Latest terminal statuses total | 1 |
| Duplicate status rows per student | 0 |
| Students with `class = Passed Out` | 0 |
| Promotion logs with `to_class = Passed Out` | 0 |

## Terminal Drift Map

| Latest Terminal Status | Latest Records | Has `class_id` | Has `school_class_id` | Has `section_id` | Has `section` | Legacy Class Inconsistent | Risk |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| `passed_out` | 1 | 1 | 1 | 1 | 1 | 1 | High |
| `left_school` | 0 | 0 | 0 | 0 | 0 | 0 | No current data |
| `tc_issued` | 0 | 0 | 0 | 0 | 0 | 0 | No current data |

Latest terminal status drift count: 1.

Affected terminal drift row:

| Student ID | Name | Latest Status | `class_id` | `school_class_id` | `class` | `section_id` | `section` |
| ---: | --- | --- | ---: | ---: | --- | ---: | --- |
| 6 | Demo Student 116 | `passed_out` | 1 | 1 | Nursery | 1 | 1 |

This row is active-looking from class placement even though latest status is terminal.

## Passed-Out Log Alignment Findings

| Check | Count | Affected IDs |
| --- | ---: | --- |
| Latest `passed_out` statuses with no matching `to_class = Passed Out` promotion log | 1 | student 6 |
| `to_class = Passed Out` logs with no latest `passed_out` status | 0 | none |
| `to_class = Passed Out` logs with suspicious `from_class` (`null`, blank, `Unknown`, `Passed Out`) | 0 | none |

Phase 3M behavior does not appear reflected in the historical row for student 6. That record appears to predate the dedicated passed-out cleanup/log workflow or was created outside it.

## Class Compatibility Conflict Findings

| Check | Count | Affected IDs |
| --- | ---: | --- |
| `class_id != school_class_id` | 1 | student 301 |
| `class_id is null` and `school_class_id is not null` | 0 | none |
| `class_id is not null` and `school_class_id is null` | 0 | none |
| `class_id is null` with no latest terminal status | 0 | none |
| `class = Passed Out` without latest `passed_out` status | 0 | none |
| Student legacy `class` mismatches canonical `school_classes.name` through `class_id` | 0 | none |

Known historical class FK conflict still exists:

| Student ID | Name | `class_id` | `school_class_id` | `class` |
| ---: | --- | ---: | ---: | --- |
| 301 | Demo Student 831 | 11 | 8 | Class 8 |

This conflict is separate from the terminal-status drift row.

## Affected Student IDs

- Terminal status / class-section drift: student 6
- Latest `passed_out` without matching `Passed Out` promotion log: student 6
- `class_id` / `school_class_id` conflict: student 301

## Recommended Repair Strategy

Recommended strategy: Option B - dedicated dry-run reconciliation command later, with manual review before any apply mode.

Do not auto-repair historical data yet. The current affected set is small, but the repair semantics are still important:

- student 6 needs review because latest `passed_out` conflicts with populated class/section placement and has no `Passed Out` promotion log.
- student 301 needs review because `class_id` and `school_class_id` disagree, but this is not terminal-status drift.

The safest next move is a detector that can be run repeatedly and reviewed before any mutation exists:

```bash
php artisan helpinghand:reconcile-terminal-statuses --dry-run
```

The dry-run output should list affected student IDs, names, latest status, current class/section fields, matching promotion-log status, and recommended action. An `--apply` mode should not be added until the dry-run output has been reviewed and tested.

## Safe Phase 3U Implementation Plan

1. Add a read-only reconciliation detector command or service for terminal status drift.
2. Keep the initial command dry-run only; do not implement automatic repair yet.
3. Detect and report:
   - latest terminal statuses with populated `class_id`, `school_class_id`, `section_id`, or `section`
   - latest `passed_out` where `class != Passed Out`
   - latest `passed_out` without matching `student_promotion_logs.to_class = Passed Out`
   - `Passed Out` promotion logs without latest `passed_out`
   - suspicious `from_class` values in `Passed Out` logs
   - `class_id != school_class_id`
4. Add isolated tests for the detector using SQLite-memory minimal schema.
5. Do not touch CSV/import/API student writes.
6. Do not bulk repair data.
7. Do not add an apply/repair mode until the dry-run report is reviewed.

## Confirmation

No application code, routes, views, controllers, models, migrations, tests, or database data were modified in this phase. Only this report was created. No migrations, seeders, imports, promotions, passed-out actions, full test suite, or database-changing commands were run.
