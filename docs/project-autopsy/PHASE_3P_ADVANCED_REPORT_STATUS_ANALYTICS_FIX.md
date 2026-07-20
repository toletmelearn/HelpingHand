# Phase 3P - Advanced Report Student Status Analytics Fix

## Files Inspected

- `app/Http/Controllers/Admin/AdvancedReportController.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `database/migrations/2026_01_29_090059_create_student_statuses_table.php`
- `tests/Feature/Admin/StudentPassedOutStatusTest.php`
- `docs/project-autopsy/PHASE_3N_STUDENT_STATUS_ROUTE_REPORT_AUDIT.md`
- `docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`
- `docs/project-autopsy/PHASE_3O_STUDENT_PROMOTION_ROUTE_SAFETY.md`

## Files Changed

- `app/Http/Controllers/Admin/AdvancedReportController.php`
- `tests/Feature/Admin/AdvancedReportStudentStatusAnalyticsTest.php`
- `docs/project-autopsy/PHASE_3P_ADVANCED_REPORT_STATUS_ANALYTICS_FIX.md`

Note: `AdvancedReportController.php` already had unrelated uncommitted export-method changes in the working tree. Phase 3P did not alter that export code; this phase changed only student analytics status logic and private helper methods.

## Previous Broken Status Query Summary

`AdvancedReportController@getStudentAnalytics()` previously:

- Built one mutable `Student::query()` instance.
- Counted `total_students` from that query.
- Added `whereBetween('created_at', ...)` to the same query for `new_admissions`.
- Then queried missing `students.status` values:
  - `passed_out`
  - `left_school`
  - `active`

Because `students.status` does not exist, the dashboard/export analytics path could fail at runtime. Because the same query builder was reused, later metrics could also inherit earlier metric filters.

## New Student_Statuses Query Behavior

`getStudentAnalytics()` now builds one reusable base student filter through:

- `baseStudentAnalyticsQuery($sessionId, $classId, $sectionId)`

Each metric then uses a fresh cloned query:

- `total_students`: cloned base query
- `new_admissions`: cloned base query plus date range
- `passed_out`: latest student status row with `status = passed_out`
- `left_school`: latest student status row with `status = left_school`
- `active_students`: students whose latest status is missing or not one of the inactive terminal statuses

The report no longer queries `students.status` for student analytics.

## Latest-Status Behavior Decision

Phase 3P uses latest-status semantics based on the highest `student_statuses.id` per student:

```php
DB::table('student_statuses')
    ->selectRaw('MAX(id)')
    ->groupBy('student_id');
```

This is a small, schema-preserving rule that works with the current table. It assumes newer status records receive larger IDs.

Active student rule:

- Include students with no status row.
- Include students whose latest status is `active`.
- Exclude students whose latest status is:
  - `passed_out`
  - `left_school`
  - `inactive`
  - `tc_issued`

## Mutable Query Reuse Fix Summary

The old `$query` mutation chain was replaced with:

- a base query method
- `(clone $baseQuery)` for independent metrics
- private status-count helpers that also clone the base query

This prevents `new_admissions` date filters or status filters from leaking into unrelated counts.

## Tests Created / Updated

Created:

- `tests/Feature/Admin/AdvancedReportStudentStatusAnalyticsTest.php`

Tests added:

- `student_analytics_does_not_require_students_status_column`
- `student_analytics_counts_passed_out_from_student_statuses`
- `student_analytics_counts_left_school_from_student_statuses`
- `student_analytics_counts_active_students_using_latest_status`
- `student_analytics_uses_independent_queries_for_metrics`

The test uses isolated SQLite-memory tables only:

- `students`
- `student_statuses`

The isolated `students` table intentionally omits a `status` column.

## Commands Run

- `Get-Content app/Http/Controllers/Admin/AdvancedReportController.php`
- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/StudentStatus.php`
- `Get-Content database/migrations/2026_01_29_090059_create_student_statuses_table.php`
- `Get-Content docs/project-autopsy/PHASE_3N_STUDENT_STATUS_ROUTE_REPORT_AUDIT.md`
- `Get-Content docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`
- `php -l app/Http/Controllers/Admin/AdvancedReportController.php`
- `php -l tests/Feature/Admin/AdvancedReportStudentStatusAnalyticsTest.php`
- `php artisan test --filter=AdvancedReportStudentStatusAnalyticsTest --env=testing`
- `php artisan test --filter=StudentPassedOutStatusTest --env=testing`
- `php artisan test --filter=StudentPromotionRouteSafetyTest --env=testing`
- `php artisan test --filter=StudentPromotionNormalizationTest --env=testing`
- `php artisan test --filter=StudentClassCompatibilityTest --env=testing`
- `rg -n "where\('status'|students\.status|student_statuses|latestStudentStatusIdsSubquery|countStudentsWithLatestStatus|countActiveStudentsByLatestStatus" app/Http/Controllers/Admin/AdvancedReportController.php tests/Feature/Admin/AdvancedReportStudentStatusAnalyticsTest.php`
- `git diff -- app/Http/Controllers/Admin/AdvancedReportController.php tests/Feature/Admin/AdvancedReportStudentStatusAnalyticsTest.php`
- `rg -n "Student::query\(\)|where\('status', 'passed_out'|where\('status', 'left_school'|where\('status', 'active'|student_statuses|latestStudentStatusIdsSubquery" app/Http/Controllers/Admin/AdvancedReportController.php`

## Test Result Summary

- `AdvancedReportStudentStatusAnalyticsTest`: 5 passed, 10 assertions.
- `StudentPassedOutStatusTest`: 5 passed, 19 assertions.
- `StudentPromotionRouteSafetyTest`: 3 passed, 11 assertions.
- `StudentPromotionNormalizationTest`: 5 passed, 17 assertions.
- `StudentClassCompatibilityTest`: 6 passed, 8 assertions.

PHPUnit emitted existing doc-comment metadata deprecation warnings from older tests. They did not fail the targeted runs.

## Failures And Fixes

No test failures occurred.

No promotion, passed-out, route, status CRUD, migration, or schema changes were needed.

## No Full Suite Confirmation

- The full test suite was not run.
- Only targeted test filters requested for Phase 3P were executed.

## No Database Mutation Confirmation

- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was touched.
- No student data was bulk updated.
- No promotion, passed-out, import, seed, or status CRUD operation was run against real/local MySQL.
- New test data lived only in isolated SQLite-memory tables.

## Remaining Risks

- `student-statuses/show.blade.php` still references missing `Student::currentClass()`.
- Manual `StudentStatusController` CRUD can still create a `passed_out` status without applying Phase 3M class/section cleanup.
- Latest status is currently inferred by highest `student_statuses.id`; a later phase may want an explicit latest-status relation ordered by `status_date`, `created_at`, and `id`.
- Other analytics methods in `AdvancedReportController` still reuse mutable queries for non-student domains. This phase fixed only student status analytics.

## Recommended Next Step

Phase 3Q should fix `resources/views/admin/student-statuses/show.blade.php` to use the canonical student class compatibility layer or the existing `schoolClass` relationship instead of the missing `currentClass` relationship.
