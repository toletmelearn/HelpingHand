# Phase 2N - API Controller Stability Audit

## 1. Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/*.php`
- `app/Http/Middleware/ApiAccessControl.php`
- `app/Models/User.php`
- `app/Models/Student.php`
- `app/Models/Teacher.php`
- `app/Models/Attendance.php`
- `app/Models/Result.php`
- `app/Models/Fee.php`
- `app/Models/FeeCollection.php`
- `app/Models/LessonPlan.php`
- `app/Models/ExamPaper.php`
- `app/Models/BellTiming.php`
- `app/Models/Guardian.php`
- `database/migrations/*attendances*`
- `database/migrations/*bell_timings*`
- `database/migrations/*exam_papers*`
- `database/migrations/*lesson_plans*`
- `database/migrations/*guardians*`
- `database/migrations/*student_guardian*`
- `tests/Feature/API/SanctumTokenAbilityTest.php`
- `tests/Feature/API/ApiAccessControlAbilityTest.php`
- `docs/project-autopsy/PHASE_2M_SANCTUM_ABILITY_ENFORCEMENT_IMPLEMENTATION.md`

## 2. Commands Run

```powershell
Get-ChildItem app/Http/Controllers/API -File
php artisan route:list --path=api/v1
Get-Content routes/api.php
Get-ChildItem app/Http/Controllers/API -Filter *.php | ForEach-Object { php -l $_.FullName }
rg -n "^use App\\Models\\|App\\Models\\|new [A-Z][A-Za-z0-9_]+|extends" app/Http/Controllers/API -g "*.php"
Get-ChildItem app/Models -File | Select-Object -ExpandProperty Name
rg -n -- "->\w+\(|::with\(|whereHas\(|with\(\[|with\('|classAssignments|assignedClasses|lessonPlans|notifications|student\(|teacher\(|guardians\(|fees|feeCollections" app/Http/Controllers/API app/Models/User.php app/Models/Student.php app/Models/Teacher.php -g "*.php"
rg -n "function (student|teacher|guardians|fees|feeCollections|classAssignments|assignedClasses|lessonPlans|subjectAssignments|classes|examPapers|notifications|unreadNotifications)" app/Models/User.php app/Models/Student.php app/Models/Teacher.php app/Models/Guardian.php app/Models/LessonPlan.php app/Models/ExamPaper.php -g "*.php"
rg -n "public function" app/Http/Controllers/API -g "*.php"
Get-Content app/Http/Controllers/API/TeacherController.php
Get-Content app/Http/Controllers/API/LessonPlanController.php
Get-Content app/Http/Controllers/API/StudentController.php
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content app/Http/Controllers/API/GuardianController.php
Get-Content app/Http/Controllers/API/ExamPaperController.php
Get-Content app/Http/Controllers/API/BellTimingController.php
Get-Content app/Http/Controllers/API/NotificationController.php
Get-Content app/Http/Controllers/API/AuthController.php
Get-Content app/Models/Attendance.php
Get-Content app/Models/BellTiming.php
Get-Content app/Models/ExamPaper.php
Get-Content app/Models/LessonPlan.php
Get-Content app/Models/Guardian.php
rg -n "create_.*(attendances|bell_timings|lesson_plans|exam_papers|guardians|student_guardian|fees)_table|Schema::create\('(attendances|bell_timings|lesson_plans|exam_papers|guardians|student_guardian|fees)'|table\('(attendances|bell_timings|lesson_plans|exam_papers|guardians|fees)'" database/migrations -g "*.php"
Get-Content database/migrations/2026_01_21_083000_create_attendances_table.php
Get-Content database/migrations/2026_01_21_090000_create_bell_timings_table.php
Get-Content database/migrations/2026_01_21_093000_create_exam_papers_table.php
Get-Content database/migrations/2026_02_15_100247_create_lesson_plans_table.php
Get-Content database/migrations/2026_02_15_100536_add_missing_columns_to_lesson_plans_table.php
Get-Content database/migrations/2026_02_16_093000_add_date_column_to_lesson_plans_table.php
Get-Content database/migrations/2026_02_16_094500_add_audit_columns_to_lesson_plans_table.php
Get-Content database/migrations/2026_02_19_092822_update_exam_papers_table_add_missing_columns.php
Get-Content database/migrations/2026_02_19_093906_update_exam_papers_table_for_professional_workflow.php
```

One attempted PowerShell `rg` command for field names was malformed by quote parsing and produced no useful inspection output. It did not modify anything and was replaced by direct migration reads.

## 3. API Controller Inventory

### Routed API Controllers

| Controller | Routed methods | Route count | Public/protected | Phase 2M reachability |
| --- | --- | ---: | --- | --- |
| `AuthController` | `login`, `register`, `logout`, `logoutAll`, `me`, `updateProfile`, `changePassword`, `refreshToken` | 8 | 2 public, 6 protected | Public auth routes reachable; protected auth-self routes reachable with token rules |
| `DashboardController` | `studentDashboard`, `parentDashboard`, `teacherDashboard` | 3 | Protected | Student/teacher reachable for matching role + ability; parent blocked for non-admin |
| `LessonPlanController` | `index`, `show`, `todayLessons`, `weekLessons`, `myLessonPlans`, `store`, `update` | 7 | Protected | `myLessonPlans` reachable for teacher; others admin-only or denied by default/high-risk |
| `StudentController` | resource CRUD, `attendance`, `results`, `fees` | 8 | Protected | Student self reads reachable; CRUD admin-only |
| `TeacherController` | resource CRUD, `classes`, `examPapers`, `subjectClasses`, `attendanceData`, `gradingData` | 10 | Protected | Teacher self reads reachable; CRUD admin-only |
| `AttendanceController` | resource CRUD, `studentMonthlyReport`, `dailyReport`, `bulkMark` | 8 | Protected | Student monthly reachable for own student; others admin-only/high-risk |
| `ExamPaperController` | resource CRUD, `download`, `togglePublish`, public available/search routes | 9 | 2 public + protected | Public available/search blocked; most protected routes admin-only or denied by default |
| `BellTimingController` | resource CRUD, `weeklyTimetable`, `currentPeriod`, `bulkCreate`, public today route | 8 | 1 public + protected | Public today blocked; protected routes admin-only unless denied by default |
| `NotificationController` | `index`, `markAsRead`, `markAllAsRead`, `unreadCount` | 4 | Protected | Reachable for authenticated token with `mobile:user` |
| `GuardianController` | resource CRUD, `children`, `notifications` | 7 | Protected | Blocked for non-admin; admin can reach |

Total active `/api/v1/*` routes from route list: 73.

### Unrouted API Folder Files

| File | Finding |
| --- | --- |
| `BiometricController.php` | Not referenced by `routes/api.php`; imports `BiometricDevice` and depends on `BiometricSyncService`. |
| `SelfServiceController.php` | Not referenced by `routes/api.php`; separate token flow and direct JSON responses. |
| `TeacherController_backup.php` | Backup file declares `class TeacherController` in the same namespace as the real controller. It linted, but it is a dangerous backup file if classmap/autoload optimization ever scans it. |

## 4. Routed Method Map

| Route group | Current non-admin access | Admin access | Stability summary |
| --- | --- | --- | --- |
| Auth public | Allowed | Allowed | Mostly stable |
| Auth self | Allowed with `mobile:user`, except logout/logout-all/refresh-token old-token recovery | Allowed with `mobile:admin` | Mostly stable |
| Notifications | Allowed with `mobile:user` | Allowed | Namespace casing risk but route list currently resolves |
| Student self reads | Allowed with student role + ownership + `mobile:student` | Allowed | Mostly stable for `show`, `attendance`, `results`, `fees`; dashboard has RED issue |
| Teacher self reads | Allowed with teacher role + ownership + `mobile:teacher` | Allowed | Several teacher-specific routes are RED due missing relationships |
| Parent/guardian | Blocked for non-admin | Allowed | Still unstable; ownership remains unrepaired |
| High-risk writes | Blocked for non-admin | Allowed | Controller/schema validation inconsistent; avoid broad use |
| Default protected read routes | Denied for non-admin unless explicit branch | Allowed for admin | Stability varies by controller |
| Public temporary blocklist | Blocked for everyone by middleware | Blocked before admin branch | Registered methods include missing handlers |

## 5. Missing Model / Class Findings

| Severity | File | Finding |
| --- | --- | --- |
| RED | `DashboardController.php` | Imports `App\Models\Notification`, but no `app/Models/Notification.php` exists. `studentDashboard()` and `parentDashboard()` call `Notification::where(...)` and can fatal once reached. |
| RED | `routes/api.php` + `ExamPaperController.php` | `api.exam-papers.available-for-class` points to `ExamPaperController@availableForClass`, but that method does not exist. Route is currently blocked by middleware. |
| RED | `routes/api.php` + `ExamPaperController.php` | `api.exam-papers.search` points to `ExamPaperController@search`, but that method does not exist. Route is currently blocked by middleware. |
| RED | `routes/api.php` + `BellTimingController.php` | `api.bell-timing.today` points to `BellTimingController@todaysSchedule`, but that method does not exist. Route is currently blocked by middleware. |
| YELLOW | `NotificationController.php` | File path is `API/NotificationController.php`, but namespace declares `App\Http\Controllers\Api`. Route list resolves on this Windows environment, but this is fragile on case-sensitive deployment/autoload flows. |
| YELLOW | `TeacherController_backup.php` | Backup file declares the same `App\Http\Controllers\API\TeacherController` class name as `TeacherController.php`. It is not routed, but can break optimized autoload/classmap discovery. |

## 6. Relationship / Method Mismatch Findings

| Severity | File | Controller assumption | Model reality |
| --- | --- | --- | --- |
| RED | `TeacherController@subjectClasses` | `Teacher::with('subjectAssignments.subject', 'classAssignments.schoolClass', 'lessonPlans')` | Current `Teacher` model has `classSubjectAssignments()`, `classes()`, `examPapers()`, `assignedClasses()`, but not `subjectAssignments`, `classAssignments`, or `lessonPlans`. |
| RED | `TeacherController@attendanceData` | Uses `classAssignments.schoolClass.students.attendances` and `subjectAssignments.attendances` | `Teacher` lacks `classAssignments` and `subjectAssignments`. |
| RED | `TeacherController@gradingData` | Uses `subjectAssignments.examPapers`, `subjectAssignments.results`, `lessonPlans`, plus nested `enrolledStudents` on papers | These relationships are not present on `Teacher`; `ExamPaper` also does not define `results` or `enrolledStudents`. |
| RED | `DashboardController@teacherDashboard` | Calls `$teacher->classAssignments()->with(['class', 'section', 'subject'])` | `Teacher` model does not define `classAssignments()`. |
| YELLOW | `DashboardController@parentDashboard` | Uses `Student::whereHas('guardians', where guardian_id = $user->id)` | `Student::guardians()` exists, but Phase 2E found User -> Guardian ownership is unreliable; `guardian_id = user.id` is not proven safe. |
| GREEN | `StudentController` | Uses `user`, `attendances`, `fees`, `results` | Corresponding `Student` relationships exist. |
| GREEN | `AttendanceController` | Uses `student`, `teacher`, `markedBy` | Corresponding `Attendance` relationships exist. |
| GREEN | `GuardianController` | Uses `students.*` relationships | `Guardian::students()` and `Student` relationships exist. Parent ownership remains blocked for non-admin. |
| GREEN | `NotificationController` | Uses `$user->notifications()` and `$user->unreadNotifications` | `User` defines `notifications()` and `unreadNotifications()`. |
| GREEN | `LessonPlanController@myLessonPlans` | Uses `$request->user()->teacher` | `User::teacher()` exists. |

## 7. Schema / Field Mismatch Findings

| Severity | Area | Finding |
| --- | --- | --- |
| RED | `LessonPlanController@store` / `update` | Controller writes `period_number`, `learning_objectives`, `teaching_methodology`, `resources_required`, `homework_assignment`, and `status`. Current `LessonPlan::$fillable` uses `teaching_method`, `homework_classwork`, `books_notebooks_required`, etc., and does not include these API field names or `status`/`period_number`. Writes can silently drop data or fail depending on DB columns. |
| RED | `LessonPlanController@index/show/today/week/my` | Controller orders by `period_number` and filters/statuses by `status`. Current inspected lesson migrations do not clearly establish `period_number` or `status` in the canonical model generation. |
| YELLOW | `ExamPaperController@store/update` | Validation accepts lowercase values such as `midterm`, `question`, but model constants/comment examples use title-case values such as `Mid-term`, `Question Paper`. Existing data/UI may not align. |
| YELLOW | `ExamPaper` schema | Multiple overlapping migrations define and later repair `exam_papers`; some repair migrations use raw MySQL enum alteration. Runtime fields are likely environment-dependent. |
| YELLOW | `AttendanceController@store/update/bulkMark` | Accepts client-provided `marked_by` instead of deriving it from authenticated user. This is blocked for non-admin, but remains a stability/audit issue for admin API writes. |
| YELLOW | `AttendanceController@dailyReport` | Queries `attendances.class`, while other domains increasingly use `class_id`/`section_id`. This may work only for legacy attendance data. |
| YELLOW | `DashboardController@getStudentFeeStatus` | Uses legacy `Fee` model/status values; canonical fee domain is `StudentFeeAssignment` + `FeeCollection`. Dashboard fee data may be stale/wrong even if it does not crash. |
| YELLOW | `GuardianController` fee helpers | Filters `payment_status === 'Paid'`, while other fee code uses mixed casing/status fields. May return incorrect paid/outstanding totals. |
| YELLOW | `BellTimingController` | Mostly matches `BellTiming` model/migration, but public `todaysSchedule` route is wired to a missing method. |

## 8. Runtime Risk Classification Table

Classification is based on controller stability after a request passes middleware. `BLOCKED` means non-admin is blocked by `ApiAccessControl`, but admin may still reach the controller.

| Route / method group | Count | Classification | Reason |
| --- | ---: | --- | --- |
| Auth routes | 8 | GREEN | Methods exist; token flow already covered by targeted tests. |
| Notification routes | 4 | GREEN/YELLOW | Methods and user relationships exist; namespace casing should be normalized later. |
| Student self detail/attendance/results/fees | 4 | GREEN | Relationships exist and are allowed for student self. |
| Student dashboard | 1 | RED | Reaches missing `App\Models\Notification`. |
| Teacher `show`, `classes`, `examPapers` | 3 | GREEN/YELLOW | Methods/relationships exist; route parameter handling works for custom routes. |
| Teacher dashboard, subject/classes, attendance-data, grading-data | 4 | RED | Undefined teacher relationships. |
| Lesson plan read/write routes | 7 | YELLOW/RED | Relationship base exists, but API fields/status/period assumptions diverge from model/migrations. |
| Attendance routes | 8 | YELLOW/BLOCKED | Relationships exist; writes and daily report use legacy/client-supplied fields. |
| Exam paper regular routes | 7 | YELLOW/BLOCKED | Methods exist, but schema/status/storage assumptions are broad and migration history is unstable. |
| Exam paper public available/search | 2 | RED/BLOCKED | Registered to missing controller methods; middleware currently blocks. |
| Bell timing protected routes | 7 | YELLOW/BLOCKED | Core model exists; admin-only for most paths; schema mostly aligned. |
| Bell timing public today | 1 | RED/BLOCKED | Registered to missing controller method. |
| Guardian routes | 7 | YELLOW/BLOCKED | Relationships exist, but parent ownership and fee status assumptions remain unsafe. |
| Unrouted backup/self-service/biometric controllers | 3 files | YELLOW | Not active in API route map, but backup duplicate class is dangerous. |

### Count Summary

- GREEN: 23 routes
- YELLOW: 31 routes
- RED: 12 routes
- BLOCKED for non-admin but still potentially reachable by admin: 25 routes

The GREEN/YELLOW/RED counts classify controller runtime stability. The BLOCKED count overlaps with YELLOW/RED because it describes middleware exposure, not code health.

## 9. Top 10 Controller Stability Risks

1. `DashboardController` imports missing `App\Models\Notification`; student dashboard can fatal after Phase 2M authorization allows it.
2. `BellTimingController@todaysSchedule` route exists but method is missing.
3. `ExamPaperController@availableForClass` route exists but method is missing.
4. `ExamPaperController@search` route exists but method is missing.
5. `TeacherController@subjectClasses` calls missing `Teacher::subjectAssignments`, `Teacher::classAssignments`, and `Teacher::lessonPlans`.
6. `TeacherController@attendanceData` calls missing teacher relationships and nested assumptions.
7. `TeacherController@gradingData` calls missing teacher relationships and missing exam-paper/result relationships.
8. `DashboardController@teacherDashboard` calls missing `Teacher::classAssignments()`.
9. `LessonPlanController` API field contract does not match `LessonPlan::$fillable` or the stable migration shape.
10. `TeacherController_backup.php` declares a duplicate `TeacherController` class and should not live in an autoloaded controller namespace.

## 10. Safe Phase 2O Fix Plan

Recommended minimal order:

1. Fix the notification fatal:
   - Replace `App\Models\Notification` usage in `DashboardController` with the existing user notification relation or `Illuminate\Notifications\DatabaseNotification`.
   - Keep response shape unchanged.
2. Quarantine or implement safe no-op responses for broken public routes:
   - `api.exam-papers.available-for-class`
   - `api.exam-papers.search`
   - `api.bell-timing.today`
   - Since middleware blocks them today, prefer route quarantine/documentation unless UI/mobile clients need them.
3. Repair teacher controller relationship assumptions:
   - Replace `classAssignments` / `subjectAssignments` / `lessonPlans` calls with actual model relationships, or return controlled 501/empty structured responses until the domain contract is repaired.
4. Normalize `NotificationController` namespace casing to match `App\Http\Controllers\API`.
5. Remove or move `TeacherController_backup.php` out of the autoloaded namespace.
6. Add isolated controller stability tests for routes already allowed by `ApiAccessControl`:
   - `/api/v1/dashboard/student`
   - `/api/v1/dashboard/teacher`
   - `/api/v1/teachers/{id}/classes`
   - `/api/v1/teachers/{id}/subject-classes`
   - notification routes
7. Audit `LessonPlanController` separately before enabling writes:
   - Align API fields to `LessonPlan::$fillable`.
   - Avoid migrations in Phase 2O unless a later schema phase explicitly approves it.
8. Keep parent/guardian child routes blocked until ownership repair is complete.

## 11. Read-Only Confirmation

- No application code was modified.
- No database data was read through tinker or modified.
- No migrations were run.
- No tests were run.
- No routes, middleware, controllers, config, migrations, or models were changed.
- Only this report file was created.
