# Phase 2C - API Authorization Map

## 1. Files Inspected

- `routes/api.php`
- `app/Http/Middleware/ApiAccessControl.php`
- `app/Http/Controllers/API/*.php`
- `app/Models/User.php`
- `app/Models/Role.php`
- `app/Models/Student.php`
- `app/Models/Teacher.php`
- `app/Models/TeacherLogin.php`
- `app/Models/ParentModel.php`
- `app/Models/Guardian.php`
- `app/Models/Attendance.php`
- `app/Models/Result.php`
- `app/Models/FeeCollection.php`
- `app/Models/Fee.php`
- `app/Models/LessonPlan.php`
- `app/Models/ExamPaper.php`
- `config/auth.php`
- `config/sanctum.php`
- `tests/Feature/API/AuthControllerTest.php`
- `docs/project-autopsy/PHASE_2A_API_BASE_REPAIR_AUDIT.md`
- `docs/project-autopsy/PHASE_2B_API_BASE_ACTIVATION.md`

## 2. Commands Run

Safe commands only:

```powershell
php artisan route:list --path=api/v1 --json
php artisan route:list --path=api/v1
Get-Content routes\api.php
Get-Content app\Http\Middleware\ApiAccessControl.php
Get-ChildItem app\Http\Controllers\API -File
Get-ChildItem tests\Feature\API -Recurse -ErrorAction SilentlyContinue
Get-Content app\Models\User.php
Get-Content app\Models\Role.php
Get-Content app\Models\Student.php
Get-Content app\Models\Teacher.php
Get-Content app\Models\TeacherLogin.php
Get-Content app\Models\ParentModel.php
Get-Content app\Models\Guardian.php
Get-Content app\Models\Attendance.php
Get-Content app\Models\Result.php
Get-Content app\Models\FeeCollection.php
Get-Content app\Models\Fee.php
Get-Content app\Models\LessonPlan.php
Get-Content app\Models\ExamPaper.php
Get-Content config\auth.php
Get-Content config\sanctum.php
Get-Content app\Http\Controllers\API\StudentController.php
Get-Content app\Http\Controllers\API\TeacherController.php
Get-Content app\Http\Controllers\API\AttendanceController.php
Get-Content app\Http\Controllers\API\GuardianController.php
Get-Content app\Http\Controllers\API\ExamPaperController.php
Get-Content app\Http\Controllers\API\BellTimingController.php
rg -n "function (availableForClass|search|todaysSchedule)" app\Http\Controllers\API -g "*.php"
rg -n "Role::|roles|admin|teacher|student|parent|super" database app tests -g "*.php"
rg -n "function (classAssignments|subjectAssignments|lessonPlans|guardians|students\(|student\(|teacher\()" app\Models app\Http\Controllers\API -g "*.php"
php -l app\Http\Middleware\ApiAccessControl.php
php -l routes\api.php
```

No migrations, database reset, composer setup, database-changing tests, route edits, middleware edits, Sanctum config edits, or application-code edits were run.

## 3. Full API Authorization Route Map

Active `/api/v1/*` route count: 73.

Public or guest-throttled routes: 5.

Sanctum-protected routes: 68.

High or critical dangerous/unmapped routes recommended for initial blocking: 37.

| Method | URI | Route name | Controller@method | Current middleware | Public/protected | Recommended access rule | Required role(s) | Ownership rule | Risk |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `POST` | `api/v1/login` | `api.login` | `AuthController@login` | `api` | Public | Allow public | None | None | Low |
| `POST` | `api/v1/register` | `api.register` | `AuthController@register` | `api` | Public | Allow public because controller returns disabled 403 | None | None | Low |
| `GET` | `api/v1/exam-papers/available/{classSection}` | `api.exam-papers.available-for-class` | `ExamPaperController@availableForClass` | `api`, `throttle:10,1`, `ApiAccessControl` | Public | Temporarily block until method exists or route is repaired | None | Published/approved class-only after repair | Critical |
| `POST` | `api/v1/exam-papers/search` | `api.exam-papers.search` | `ExamPaperController@search` | `api`, `throttle:10,1`, `ApiAccessControl` | Public | Temporarily block until method exists or route is repaired | None | Published/approved search only after repair | Critical |
| `GET` | `api/v1/bell-timing/today/{classSection}` | `api.bell-timing.today` | `BellTimingController@todaysSchedule` | `api`, `throttle:10,1`, `ApiAccessControl` | Public | Temporarily block until method exists or route is repaired | None | Class-section read only after repair | Critical |
| `POST` | `api/v1/logout` | `api.logout` | `AuthController@logout` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Current token only | Low |
| `POST` | `api/v1/logout-all` | `api.logout-all` | `AuthController@logoutAll` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Own tokens only | Low |
| `GET` | `api/v1/me` | `api.me` | `AuthController@me` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Own user record | Low |
| `PUT` | `api/v1/profile` | `api.update-profile` | `AuthController@updateProfile` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Own user record | Low |
| `POST` | `api/v1/change-password` | `api.change-password` | `AuthController@changePassword` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Own user record | Low |
| `POST` | `api/v1/refresh-token` | `api.refresh-token` | `AuthController@refreshToken` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Current token only | Low |
| `GET` | `api/v1/dashboard/student` | `api.dashboard.student` | `DashboardController@studentDashboard` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Student self dashboard | `student`, `admin` | `Student::where('user_id', user.id)` | Medium |
| `GET` | `api/v1/dashboard/parent` | `api.dashboard.parent` | `DashboardController@parentDashboard` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Parent child dashboard, but map is unclear | `parent`, `admin` | Needs confirmed `User` to `Guardian` or `ParentModel` mapping | High |
| `GET` | `api/v1/dashboard/teacher` | `api.dashboard.teacher` | `DashboardController@teacherDashboard` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self dashboard | `teacher`, `admin` | `Teacher::where('user_id', user.id)` | Medium |
| `GET` | `api/v1/lesson-plans` | `api.lesson-plans.index` | `LessonPlanController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Scope by user role | `student`, `parent`, `teacher`, `admin` | Student/parent class only; teacher assigned class/own plans | Medium |
| `POST` | `api/v1/lesson-plans` | `api.lesson-plans.store` | `LessonPlanController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher own/assigned only | `teacher`, `admin` | Teacher must match `user->teacher->id` and assigned class/subject | High |
| `GET` | `api/v1/lesson-plans/my` | `api.lesson-plans.my` | `LessonPlanController@myLessonPlans` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self only | `teacher`, `admin` | `LessonPlan.teacher_id = user->teacher->id` | Medium |
| `GET` | `api/v1/lesson-plans/today` | `api.lesson-plans.today` | `LessonPlanController@todayLessons` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Class-scoped read | `student`, `parent`, `teacher`, `admin` | Requested class/section must match self child or teacher assignment | Medium |
| `GET` | `api/v1/lesson-plans/week` | `api.lesson-plans.week` | `LessonPlanController@weekLessons` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Class-scoped read | `student`, `parent`, `teacher`, `admin` | Requested class/section must match self child or teacher assignment | Medium |
| `GET` | `api/v1/lesson-plans/{id}` | `api.lesson-plans.show` | `LessonPlanController@show` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Scoped read | `student`, `parent`, `teacher`, `admin` | Published or teacher-owned/admin | Medium |
| `PUT` | `api/v1/lesson-plans/{id}` | `api.lesson-plans.update` | `LessonPlanController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher owner only | `teacher`, `admin` | `LessonPlan.teacher_id = user->teacher->id` | High |
| `GET` | `api/v1/students` | `students.index` | `StudentController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None | High |
| `POST` | `api/v1/students` | `students.store` | `StudentController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/students/{student}` | `students.show` | `StudentController@show` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Student self, parent child, teacher assigned, admin | `student`, `parent`, `teacher`, `admin` | `canAccessStudentRecord(student_id)` | Medium |
| `PUT/PATCH` | `api/v1/students/{student}` | `students.update` | `StudentController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None until field-level rules exist | High |
| `DELETE` | `api/v1/students/{student}` | `students.destroy` | `StudentController@destroy` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/students/{id}/attendance` | `api.students.attendance` | `StudentController@attendance` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Student self, parent child, teacher assigned, admin | `student`, `parent`, `teacher`, `admin` | `canAccessStudentRecord(id)` | Medium |
| `GET` | `api/v1/students/{id}/results` | `api.students.results` | `StudentController@results` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Student self, parent child, teacher assigned, admin | `student`, `parent`, `teacher`, `admin` | `canAccessStudentRecord(id)` | Medium |
| `GET` | `api/v1/students/{id}/fees` | `api.students.fees` | `StudentController@fees` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Student self, parent child, admin; teacher only if explicitly permitted | `student`, `parent`, `admin` | `canAccessStudentRecord(id)`, but fees should exclude teacher by default | Medium |
| `GET` | `api/v1/teachers` | `teachers.index` | `TeacherController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None | High |
| `POST` | `api/v1/teachers` | `teachers.store` | `TeacherController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/teachers/{teacher}` | `teachers.show` | `TeacherController@show` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self or admin | `teacher`, `admin` | `Teacher.user_id = user.id` or admin | Medium |
| `PUT/PATCH` | `api/v1/teachers/{teacher}` | `teachers.update` | `TeacherController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None until profile field rules exist | High |
| `DELETE` | `api/v1/teachers/{teacher}` | `teachers.destroy` | `TeacherController@destroy` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/teachers/{id}/classes` | `api.teachers.classes` | `TeacherController@classes` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self or admin | `teacher`, `admin` | `Teacher.user_id = user.id` | Medium |
| `GET` | `api/v1/teachers/{id}/papers` | `api.teachers.papers` | `TeacherController@examPapers` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self or admin | `teacher`, `admin` | `Teacher.user_id = user.id` | Medium |
| `GET` | `api/v1/teachers/{id}/subject-classes` | `api.teachers.subject-classes` | `TeacherController@subjectClasses` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self or admin | `teacher`, `admin` | `Teacher.user_id = user.id` | Medium |
| `GET` | `api/v1/teachers/{id}/attendance-data` | `api.teachers.attendance-data` | `TeacherController@attendanceData` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self or admin | `teacher`, `admin` | `Teacher.user_id = user.id` | Medium |
| `GET` | `api/v1/teachers/{id}/grading-data` | `api.teachers.grading-data` | `TeacherController@gradingData` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher self or admin | `teacher`, `admin` | `Teacher.user_id = user.id` | Medium |
| `GET` | `api/v1/attendance` | `attendance.index` | `AttendanceController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None | High |
| `POST` | `api/v1/attendance` | `attendance.store` | `AttendanceController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher assigned class or admin | `teacher`, `admin` | `marked_by` must equal current user; class/student assigned to teacher | High |
| `GET` | `api/v1/attendance/{attendance}` | `attendance.show` | `AttendanceController@show` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Student self, parent child, teacher assigned, admin | `student`, `parent`, `teacher`, `admin` | Check attendance student/class ownership | High |
| `PUT/PATCH` | `api/v1/attendance/{attendance}` | `attendance.update` | `AttendanceController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher assigned class or admin | `teacher`, `admin` | Existing record class/student assigned to teacher | High |
| `DELETE` | `api/v1/attendance/{attendance}` | `attendance.destroy` | `AttendanceController@destroy` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None | High |
| `GET` | `api/v1/attendance/student/{studentId}/monthly/{month}/{year}` | `api.attendance.student-monthly` | `AttendanceController@studentMonthlyReport` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Student self, parent child, teacher assigned, admin | `student`, `parent`, `teacher`, `admin` | `canAccessStudentRecord(studentId)` | Medium |
| `GET` | `api/v1/attendance/class/{classSection}/daily/{date}` | `api.attendance.daily-report` | `AttendanceController@dailyReport` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher assigned class or admin | `teacher`, `admin` | Teacher class assignment must match `classSection` | High |
| `POST` | `api/v1/attendance/bulk-mark` | `api.attendance.bulk-mark` | `AttendanceController@bulkMark` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher assigned class or admin | `teacher`, `admin` | All student IDs must belong to assigned class; `marked_by` current user | High |
| `GET` | `api/v1/exam-papers` | `exam-papers.index` | `ExamPaperController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin or teacher scoped; students/parents published only | `admin`, `teacher`, `student`, `parent` | Apply `ExamPaper::canBeAccessedBy()` or class scoping | High |
| `POST` | `api/v1/exam-papers` | `exam-papers.store` | `ExamPaperController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Teacher or admin only | `teacher`, `admin` | Teacher must own/teach class/subject; `created_by` current user | High |
| `GET` | `api/v1/exam-papers/{exam_paper}` | `exam-papers.show` | `ExamPaperController@show` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Model-level access | `admin`, `teacher`, `student`, `parent` | `ExamPaper::canBeAccessedBy(user)` plus parent class check | Medium |
| `PUT/PATCH` | `api/v1/exam-papers/{exam_paper}` | `exam-papers.update` | `ExamPaperController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Owner teacher or admin | `teacher`, `admin` | `created_by/uploaded_by = user.id` or admin | High |
| `DELETE` | `api/v1/exam-papers/{exam_paper}` | `exam-papers.destroy` | `ExamPaperController@destroy` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Owner teacher or admin | `teacher`, `admin` | `created_by/uploaded_by = user.id` or admin | High |
| `POST` | `api/v1/exam-papers/{id}/download` | `api.exam-papers.download` | `ExamPaperController@download` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Model-level access | `admin`, `teacher`, `student`, `parent` | `ExamPaper::canBeAccessedBy(user)` plus parent class check | Medium |
| `POST` | `api/v1/exam-papers/{id}/toggle-publish` | `api.exam-papers.toggle-publish` | `ExamPaperController@togglePublish` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None | High |
| `GET` | `api/v1/bell-timing` | `bell-timing.index` | `BellTimingController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated read scoped by role | `admin`, `teacher`, `student`, `parent` | Student/parent/teacher class section only | Medium |
| `POST` | `api/v1/bell-timing` | `bell-timing.store` | `BellTimingController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None | High |
| `GET` | `api/v1/bell-timing/{bell_timing}` | `bell-timing.show` | `BellTimingController@show` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated read scoped by role | `admin`, `teacher`, `student`, `parent` | Class section must be accessible | Medium |
| `PUT/PATCH` | `api/v1/bell-timing/{bell_timing}` | `bell-timing.update` | `BellTimingController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `DELETE` | `api/v1/bell-timing/{bell_timing}` | `bell-timing.destroy` | `BellTimingController@destroy` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/bell-timing/weekly/{classSection}` | `api.bell-timing.weekly` | `BellTimingController@weeklyTimetable` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Class-scoped read | `admin`, `teacher`, `student`, `parent` | Class section must be accessible | Medium |
| `GET` | `api/v1/bell-timing/current-period` | `api.bell-timing.current-period` | `BellTimingController@currentPeriod` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated any user | Any authenticated user | None | Low |
| `POST` | `api/v1/bell-timing/bulk-create` | `api.bell-timing.bulk-create` | `BellTimingController@bulkCreate` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/notifications` | `api.notifications.index` | `NotificationController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Uses `$request->user()->notifications()` | Low |
| `PUT` | `api/v1/notifications/{id}/read` | `api.notifications.mark-as-read` | `NotificationController@markAsRead` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Notification must belong to current user | Low |
| `PUT` | `api/v1/notifications/mark-all-read` | `api.notifications.mark-all-read` | `NotificationController@markAllAsRead` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Current user's unread notifications | Low |
| `GET` | `api/v1/notifications/unread-count` | `api.notifications.unread-count` | `NotificationController@unreadCount` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Authenticated current user | Any authenticated user | Current user's unread notifications | Low |
| `GET` | `api/v1/guardians` | `guardians.index` | `GuardianController@index` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | None until guardian-user ownership is fixed | High |
| `POST` | `api/v1/guardians` | `guardians.store` | `GuardianController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/guardians/{guardian}` | `guardians.show` | `GuardianController@show` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only initially | `admin` | Parent ownership unclear | High |
| `PUT/PATCH` | `api/v1/guardians/{guardian}` | `guardians.update` | `GuardianController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `DELETE` | `api/v1/guardians/{guardian}` | `guardians.destroy` | `GuardianController@destroy` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Admin only | `admin` | None | High |
| `GET` | `api/v1/guardians/{id}/children` | `api.guardians.children` | `GuardianController@children` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Block initially except admin | `admin` | Parent-to-guardian user ownership unclear | High |
| `GET` | `api/v1/guardians/{id}/notifications` | `api.guardians.notifications` | `GuardianController@notifications` | `auth:sanctum`, throttle, `ApiAccessControl` | Protected | Block initially except admin | `admin` | Parent-to-guardian user ownership unclear | High |

## 4. Role and RBAC Helper Findings

Role checks:

- `User::roles()` is a many-to-many relationship through `role_user`.
- `User::hasRole($roleName)` checks `roles.name`.
- `User::hasAnyRole($roles)` and `User::hasAllRoles($roles)` exist.
- `User::hasPermission($permissionName)` loops through assigned roles and checks role permissions.
- `User::hasPermissionTo($permissionName)` aliases `hasPermission()`.
- `Role::permissions()` uses pivot table `role_permissions`.

Role naming:

- Seeders define lowercase role names: `admin`, `teacher`, `student`, `parent`.
- Other seeders also reference `class-teacher` and `accountant`.
- `AuthServiceProvider` grants broad permissions to `hasRole('admin')`.

API identity model:

- `AuthController@login()` authenticates against `App\Models\User`.
- `User` uses Sanctum `HasApiTokens`.
- `config/auth.php` has separate session guards/providers for `parent` and `teacher`, but the active API login does not use those providers.
- `teacher` guard provider uses `TeacherLogin`, not `User`.
- `parent` guard provider uses `ParentModel`, not `User`.

Conclusion:

Phase 2D should implement API authorization around `User` plus `User::roles()`, then bridge to `Student`, `Teacher`, or `Guardian` records where a `user_id` relationship exists. Do not assume `ParentModel` session users are Sanctum API users.

## 5. Ownership Data Map

### Student

- `User::student()` is `hasOne(Student::class)`.
- `Student::user()` is `belongsTo(User::class)`.
- Student self rule: `Student::where('id', $studentId)->where('user_id', $user->id)->exists()`.

### Parent

- `ParentModel` is a separate authenticatable model using table `parents`.
- `ParentModel::student()` belongs to one `Student` via `student_id`.
- `Guardian::students()` belongs to many `Student` through `student_guardian`.
- `User::guardians()` exists as `hasMany(Guardian::class)`, but inspected `Guardian::$fillable` did not include `user_id`, and `Guardian` did not define `user()`.
- `DashboardController@parentDashboard()` attempts a guardian relationship using `guardian_id = $user->id`, which appears suspicious because `guardian_id` is likely a guardian model ID, not a user ID.

Parent child-access rule cannot be trusted until the user-to-guardian/parent mapping is repaired or confirmed.

### Teacher

- `User::teacher()` is `hasOne(Teacher::class)`.
- `Teacher::user()` is `belongsTo(User::class)`.
- `Teacher::classSubjectAssignments()` has many `TeacherClassSubjectAssignment`.
- `Teacher::assignedClasses()` and `Teacher::assignedSubjects()` derive from `teacher_class_subject_assignments`.
- `TeacherLogin` is separate from `User` and powers the session teacher guard; it is not the current Sanctum identity.

Teacher self rule: `Teacher::where('id', $teacherId)->where('user_id', $user->id)->exists()`.

Teacher assigned-class rule: resolve `user->teacher->id`, then check `teacher_class_subject_assignments` for matching `class_id`, `section_id`, or class/section representation used by the route.

### Fees

- Legacy `Fee` belongs to `Student`.
- Canonical `FeeCollection` belongs to `Student`.
- `Student::fees()` has many legacy `Fee`.
- `Student::feeCollections()` has many `FeeCollection`.
- Current `StudentController@fees()` uses legacy `fees`, not canonical `feeCollections`.

Fee access rule:

- Student self can read own fee summary.
- Parent can read child's fee summary only after parent-child mapping is reliable.
- Admin can read all.
- Teacher should not read student fee records by default unless a specific permission exists.

### Results

- `Result::student()` belongs to `Student`.
- `Student::results()` has many `Result`.
- `Result::uploadedByTeacher()` belongs to `Teacher`.

Result access rule:

- Student self can read own results.
- Parent can read child's results after parent-child mapping is reliable.
- Teacher can read results for assigned students/classes or results uploaded by that teacher.
- Admin can read all.

### Lesson Plans

- `LessonPlan::teacher()` belongs to `Teacher`.
- `LessonPlan::class()` belongs to `SchoolClass`.
- `LessonPlan::section()` belongs to `Section`.
- `LessonPlan::createdBy()` and `modifiedBy()` belong to `User`.
- `LessonPlan::scopeForParents()` filters `show_to_parents`.

Lesson plan ownership rule:

- Teacher can create/update own assigned class/subject plans.
- Students/parents should see only plans for their class/child's class and only parent/student-visible plans.
- Admin can manage all.

### Exam Papers

- `ExamPaper::uploadedBy()` belongs to `User` via `uploaded_by`.
- `ExamPaper::createdBy()` belongs to `User` via `created_by`.
- `ExamPaper::class()` belongs to `SchoolClass`.
- `ExamPaper::canBeAccessedBy($user)` checks `access_level`, roles, and private owner/admin.

Exam paper access rule:

- Public routes should return only published, approved, non-expired, class-scoped records.
- Authenticated students/parents should be class-scoped and access-level scoped.
- Teachers can manage papers they created/uploaded or papers for assigned class/subject.
- Admin can manage all.

### Attendance

- `Attendance::student()` belongs to `Student`.
- `Attendance::teacher()` belongs to `Teacher`.
- `Attendance::markedBy()` belongs to `User` through `marked_by`.
- Attendance records also store string fields: `class`, `subject`, `period`.

Attendance access rule:

- Student self can read own attendance.
- Parent can read child attendance after parent-child mapping is reliable.
- Teacher can read/write attendance only for assigned classes/students.
- Admin can read/write all.
- Writes must ensure `marked_by` equals the authenticated user unless admin override is explicit.

## 6. Public Route Allowlist Recommendation

Allowlist by route name only:

- `api.login`
- `api.register`

Temporarily block or repair before allowing:

- `api.exam-papers.available-for-class`
- `api.exam-papers.search`
- `api.bell-timing.today`

Reason: route-list exposes these public/guest routes, but `rg` did not find matching methods `availableForClass`, `search`, or `todaysSchedule` in the inspected API controllers.

## 7. Admin Route Rule Recommendation

Implement:

```php
private function isAdmin(User $user): bool
{
    return $user->hasAnyRole(['admin', 'super-admin', 'super_admin']);
}
```

Recommended admin-only initial routes:

- Student index/store/update/delete
- Teacher index/store/update/delete
- Guardian CRUD and guardian child/notification routes until parent mapping is fixed
- Attendance index/delete and broad administration
- Bell timing writes
- Exam paper publish toggle and broad administrative operations

Admin should bypass ownership checks only after public route allowlist and authentication checks have run.

## 8. Student Route Rule Recommendation

Student access should be self-only:

- `api.dashboard.student`
- `students.show`
- `api.students.attendance`
- `api.students.results`
- `api.students.fees`
- `api.attendance.student-monthly`
- Read-only class-scoped lesson plan and bell timing routes
- Exam papers only when access level and class match

Core query:

```php
Student::where('id', $studentId)
    ->where('user_id', $user->id)
    ->exists();
```

## 9. Parent Route Rule Recommendation

Parent access should be child-only, but this is not ready for broad enablement.

Potential queries after ownership is fixed:

```php
Guardian::where('user_id', $user->id)
    ->whereHas('students', fn ($q) => $q->where('students.id', $studentId))
    ->exists();
```

or, if using `ParentModel` in API later:

```php
ParentModel::where('user_id', $user->id)
    ->where('student_id', $studentId)
    ->exists();
```

Current blocker:

- `Guardian` does not visibly expose `user_id`.
- `ParentModel` is a separate auth provider, not the current Sanctum `User`.

Phase 2D should block parent child-data routes unless a reliable `User` to `Guardian` or `User` to `ParentModel` bridge is confirmed.

## 10. Teacher Route Rule Recommendation

Teacher self:

```php
Teacher::where('id', $teacherId)
    ->where('user_id', $user->id)
    ->exists();
```

Teacher assigned class/subject:

```php
$teacher = $user->teacher;

TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
    ->where('class_id', $classId)
    ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
    ->exists();
```

Teacher should initially be allowed:

- Own dashboard
- Own teacher profile read
- Own classes/papers/subject classes/attendance/grading data
- Own lesson plans
- Attendance write only when assigned class/student verification is implemented

Teacher should not initially be allowed:

- Full student index
- Student mutations
- Teacher mutations
- Fee access
- Guardian access
- Bell timing writes

## 11. Dangerous Routes To Block First

Block first in Phase 2D:

- Public routes pointing to missing methods:
  - `api.exam-papers.available-for-class`
  - `api.exam-papers.search`
  - `api.bell-timing.today`
- Full resource list/write/delete routes:
  - `students.index`, `students.store`, `students.update`, `students.destroy`
  - `teachers.index`, `teachers.store`, `teachers.update`, `teachers.destroy`
  - all `guardians.*` and `api.guardians.*` until parent ownership is fixed
  - `attendance.index`, `attendance.store`, `attendance.update`, `attendance.destroy`, `api.attendance.bulk-mark`, `api.attendance.daily-report`
  - `bell-timing.store`, `bell-timing.update`, `bell-timing.destroy`, `api.bell-timing.bulk-create`
  - `exam-papers.index`, `exam-papers.store`, `exam-papers.update`, `exam-papers.destroy`, `api.exam-papers.toggle-publish`
  - `api.lesson-plans.store`, `api.lesson-plans.update` until teacher assignment checks are implemented

## 12. Minimal ApiAccessControl Implementation Design

Do not implement in this phase. Recommended Phase 2D design:

1. Resolve the route name:

```php
$routeName = $request->route()?->getName();
```

2. Explicit public allowlist:

```php
if (in_array($routeName, ['api.login', 'api.register'], true)) {
    return true;
}
```

3. Explicit temporary blocklist for broken or dangerous routes:

```php
if (in_array($routeName, [...], true)) {
    return false;
}
```

4. Require authenticated user:

```php
$user = $request->user();
if (!$user) {
    return false;
}
```

5. Admin bypass:

```php
if ($this->isAdmin($user)) {
    return true;
}
```

6. Route-specific allow rules:

- Auth self routes: allow authenticated.
- Notification routes: allow authenticated because controller scopes through `$request->user()`.
- Student read routes: call `canAccessStudentRecord($request, $user)`.
- Teacher self routes: call `isTeacherSelf($request, $user)`.
- Lesson plan routes: call `canAccessLessonPlan($request, $user)`.
- Exam paper routes: call `canAccessExamPaper($request, $user)`.
- Attendance read routes: call `canAccessAttendance($request, $user)`.

7. Deny by default:

```php
return false;
```

Helper methods needed:

- `isAdmin(User $user): bool`
- `isStudentSelf(User $user, int $studentId): bool`
- `isParentOfStudent(User $user, int $studentId): bool`
- `isTeacherSelf(User $user, int $teacherId): bool`
- `isTeacherAssignedToStudent(User $user, int $studentId): bool`
- `isTeacherAssignedToClassSection(User $user, $classIdOrClassSection, $sectionId = null): bool`
- `canAccessStudentRecord(Request $request, User $user): bool`
- `canAccessLessonPlan(Request $request, User $user): bool`
- `canAccessExamPaper(Request $request, User $user): bool`
- `canAccessAttendance(Request $request, User $user): bool`

Model queries needed:

- `Student::where('id', $studentId)->where('user_id', $user->id)->exists()`
- `Teacher::where('id', $teacherId)->where('user_id', $user->id)->exists()`
- `TeacherClassSubjectAssignment::where('teacher_id', $user->teacher->id)->...->exists()`
- `Guardian::where('user_id', $user->id)->whereHas('students', ...)->exists()` after `user_id` is confirmed
- `Attendance::with('student')->find($attendanceId)` then pass `student_id` to student/parent/teacher checks
- `LessonPlan::find($lessonPlanId)` then check `teacher_id`, class, section, visibility
- `ExamPaper::find($paperId)` then use `canBeAccessedBy($user)` plus class/parent restrictions

## 13. Safe Phase 2D Implementation Plan

1. Add a public route allowlist for `api.login` and `api.register`.
2. Add a temporary blocklist for broken public methods and high-risk broad CRUD/write routes.
3. Add `isAdmin()` and allow admins broadly after authentication.
4. Allow self auth routes:
   - `api.logout`
   - `api.logout-all`
   - `api.me`
   - `api.update-profile`
   - `api.change-password`
   - `api.refresh-token`
5. Allow notification routes because controllers scope to `$request->user()`.
6. Add student self helper and allow student self read routes.
7. Add teacher self helper and allow teacher self dashboard/profile-related routes.
8. Keep parent child routes blocked until the `User` to `Guardian` or `ParentModel` bridge is repaired.
9. Keep attendance writes, student mutations, guardian routes, teacher mutations, bell timing writes, and exam paper writes blocked except admin.
10. Run safe verification only:
    - `php -l app/Http/Middleware/ApiAccessControl.php`
    - `php -l routes/api.php`
    - `php artisan route:list --path=api/v1`
11. Avoid database-reset tests because `tests/Feature/API/AuthControllerTest.php` uses `RefreshDatabase`.
12. Do not change Sanctum expiration or token abilities in Phase 2D.

## 14. Confirmation

No application code was modified in Phase 2C.

No routes, middleware, controllers, models, config files, migrations, or database state were changed.

Only this report file was created.
