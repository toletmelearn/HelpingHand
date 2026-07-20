# Phase 2O - API Controller Stability Fixes

## 1. Files Inspected

- `app/Http/Controllers/API/DashboardController.php`
- `app/Http/Controllers/API/TeacherController.php`
- `app/Http/Controllers/API/NotificationController.php`
- `app/Http/Controllers/API/TeacherController_backup.php`
- `app/Models/User.php`
- `app/Models/Student.php`
- `app/Models/Teacher.php`
- `app/Models/LessonPlan.php`
- `app/Models/ExamPaper.php`
- `app/Models/TeacherClassSubjectAssignment.php`
- `routes/api.php`
- `tests/Feature/API/ApiAccessControlAbilityTest.php`
- `docs/project-autopsy/PHASE_2N_API_CONTROLLER_STABILITY_AUDIT.md`
- `docs/project-autopsy/PHASE_2M_SANCTUM_ABILITY_ENFORCEMENT_IMPLEMENTATION.md`

## 2. Files Changed

- `app/Http/Controllers/API/DashboardController.php`
- `app/Http/Controllers/API/TeacherController.php`
- `app/Http/Controllers/API/NotificationController.php`
- `docs/project-autopsy/quarantined-code/TeacherController_backup.php.txt`
- Removed/moved from autoloaded controller namespace:
  - `app/Http/Controllers/API/TeacherController_backup.php`
- `docs/project-autopsy/PHASE_2O_API_CONTROLLER_STABILITY_FIXES.md`

No routes, middleware, Sanctum config, migrations, schema files, token logic, or parent route access rules were changed.

## 3. Dashboard Notification Fix Summary

Problem:

- `DashboardController` imported `App\Models\Notification`.
- No `app/Models/Notification.php` exists.
- Allowed dashboard routes could fatal when calling `Notification::where(...)`.

Fix:

- Removed the missing `App\Models\Notification` import.
- Replaced notification counts with the existing authenticated user notification relationship:
  - `$user->unreadNotifications()->count()`

Affected response keys preserved:

- `unread_notifications`

Also guarded teacher/student dashboard lesson-plan status filtering with `Schema::hasColumn('lesson_plans', 'status')` so dashboards avoid crashing if the inconsistent migration state lacks the `status` column.

## 4. TeacherController Relationship Fixes

Problem:

- `TeacherController` used relationships that are not defined on the current `Teacher` model:
  - `subjectAssignments`
  - `classAssignments`
  - `lessonPlans`

Fixes:

- `subjectClasses()` now uses:
  - `classSubjectAssignments.schoolClass`
  - `classSubjectAssignments.section`
  - `classSubjectAssignments.subject`
  - direct `LessonPlan::where('teacher_id', $teacher->id)->get()`
- `attendanceData()` now uses:
  - `classSubjectAssignments.schoolClass.students.attendances`
  - `classSubjectAssignments.section`
  - `classSubjectAssignments.subject`
- `gradingData()` now uses:
  - `classSubjectAssignments.schoolClass`
  - `classSubjectAssignments.section`
  - `classSubjectAssignments.subject`
  - `examPapers`
  - `uploadedResults.student`

Response shapes were preserved as much as possible:

- `subjects`
- `classes`
- `lesson_plans`
- `attendance_records`
- `exam_papers`
- `results`

Fields that previously depended on missing relationships are now sourced from existing relationships or returned as safe conservative values, such as `graded_count => 0` and `total_students => 0` for exam-paper grading summaries.

## 5. NotificationController Namespace Fix

Problem:

- File path and route imports use `API`.
- Controller namespace was `App\Http\Controllers\Api`.

Fix:

- Changed namespace to:

```php
namespace App\Http\Controllers\API;
```

No notification controller logic was changed.

## 6. TeacherController_backup Quarantine Action

Problem:

- `app/Http/Controllers/API/TeacherController_backup.php` declared the same class name as the active controller:
  - `App\Http\Controllers\API\TeacherController`

Fix:

- Created quarantine folder:
  - `docs/project-autopsy/quarantined-code/`
- Moved backup PHP contents to:
  - `docs/project-autopsy/quarantined-code/TeacherController_backup.php.txt`
- Removed the original `.php` file from `app/Http/Controllers/API/` so it can no longer define a duplicate controller class.

## 7. Public Broken Routes Status

These public routes remain registered and were not implemented in Phase 2O:

- `api.exam-papers.available-for-class` -> `ExamPaperController@availableForClass`
- `api.exam-papers.search` -> `ExamPaperController@search`
- `api.bell-timing.today` -> `BellTimingController@todaysSchedule`

They remain blocked by `ApiAccessControl` and were intentionally not opened or implemented.

## 8. Commands Run

```powershell
Get-Content app/Http/Controllers/API/DashboardController.php
Get-Content app/Http/Controllers/API/TeacherController.php
Get-Content app/Http/Controllers/API/NotificationController.php
Get-Content app/Http/Controllers/API/TeacherController_backup.php
Get-Content app/Models/Teacher.php
Get-Content app/Models/LessonPlan.php
Get-Content app/Models/ExamPaper.php
Get-Content docs/project-autopsy/PHASE_2N_API_CONTROLLER_STABILITY_AUDIT.md
Get-Content app/Models/TeacherClassSubjectAssignment.php
Get-ChildItem database/migrations -Filter '*teacher_class_subject_assignments*' | ForEach-Object { $_.FullName; Get-Content $_.FullName }
Select-String -Path app/Models/SchoolClass.php,app/Models/ClassManagement.php,app/Models/Section.php,app/Models/Subject.php -Pattern "function students|class_name|name|section" -Context 0,5
Select-String -Path app/Models/Result.php -Pattern "function student|function exam|function exam_paper|function examPaper|uploaded" -Context 0,8
Select-String -Path app/Models/TeacherClassSubjectAssignment.php -Pattern "function" -Context 0,8
New-Item -ItemType Directory -Force docs/project-autopsy/quarantined-code | Out-Null; Move-Item -LiteralPath app/Http/Controllers/API/TeacherController_backup.php -Destination docs/project-autopsy/quarantined-code/TeacherController_backup.php.txt
php -l app/Http/Controllers/API/DashboardController.php
php -l app/Http/Controllers/API/TeacherController.php
php -l app/Http/Controllers/API/NotificationController.php
php artisan route:list --path=api/v1
php artisan test --filter=ApiAccessControlAbilityTest --env=testing
php artisan test --filter=SanctumTokenAbilityTest --env=testing
Test-Path app/Http/Controllers/API/TeacherController_backup.php; Test-Path docs/project-autopsy/quarantined-code/TeacherController_backup.php.txt
git diff -- app/Http/Controllers/API/DashboardController.php app/Http/Controllers/API/TeacherController.php app/Http/Controllers/API/NotificationController.php -- docs/project-autopsy/quarantined-code/TeacherController_backup.php.txt
Select-String -Path routes/api.php -Pattern "availableForClass|search|todaysSchedule" -Context 0,2
```

## 9. Test Result Summary

Syntax checks:

- `php -l app/Http/Controllers/API/DashboardController.php`: passed
- `php -l app/Http/Controllers/API/TeacherController.php`: passed
- `php -l app/Http/Controllers/API/NotificationController.php`: passed

Route list:

- `php artisan route:list --path=api/v1`: passed, 73 routes shown

Targeted tests:

- `php artisan test --filter=ApiAccessControlAbilityTest --env=testing`
  - Passed: 10 tests, 10 assertions
- `php artisan test --filter=SanctumTokenAbilityTest --env=testing`
  - Passed: 6 tests, 19 assertions

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated test files during test discovery. These warnings were not introduced by Phase 2O.

## 10. Failures and Fixes

No targeted verification command failed after the Phase 2O changes.

## 11. Full Suite / Database Safety Confirmation

- Full test suite was not run.
- Migrations were not run.
- `migrate`, `migrate:fresh`, `db:wipe`, and composer setup were not run.
- No database schema files were changed.
- Real `.env` was not edited.
- Real/local MySQL data was not touched.
- Only targeted tests were run under `--env=testing`.

## 12. Remaining Risks

- Broken public routes remain registered but blocked:
  - `ExamPaperController@availableForClass`
  - `ExamPaperController@search`
  - `BellTimingController@todaysSchedule`
- Lesson plan API field contract is still inconsistent with the current `LessonPlan` model and migration history.
- Teacher grading summaries now avoid crashing, but counts that depended on missing relationships are conservative placeholders.
- Parent/guardian ownership remains blocked for non-admin and still needs a dedicated ownership repair phase.
- Existing unrelated PHPUnit doc-comment metadata warnings remain.

## 13. Recommended Next Step

Phase 2P should address the broken public API routes while keeping them blocked until complete:

1. Decide whether to quarantine or implement `ExamPaperController@availableForClass`.
2. Decide whether to quarantine or implement `ExamPaperController@search`.
3. Decide whether to quarantine or implement `BellTimingController@todaysSchedule`.
4. Add isolated route/controller tests before opening any public route.
5. Keep parent routes blocked.
