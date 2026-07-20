# Phase 7B - Teacher Attendance Dashboard / Export / Missing Views Audit

Date: 2026-06-07

Scope: Read-only audit of teacher attendance dashboard links, controller view returns, active routes, export behavior, and missing teacher attendance Blade views.

## Files Inspected

- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/AttendanceService.php`
- `app/Models/Attendance.php`
- `app/Models/Teacher.php`
- `app/Models/Student.php`
- `routes/web.php`
- `resources/views/teacher/attendance/dashboard.blade.php`
- `resources/views/teacher/attendance/mark.blade.php`
- `resources/views/teacher/attendance/edit.blade.php`
- `resources/views/teacher/attendance/reports.blade.php`
- `resources/views/teacher/attendance/student.blade.php`
- `resources/views/teacher/**/*.blade.php`
- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`
- `tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php`
- `docs/project-autopsy/PHASE_6X_TEACHER_ATTENDANCE_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md`
- `docs/project-autopsy/PHASE_7A_TEACHER_ATTENDANCE_UPDATE_GUARD.md`

## Commands Run

```powershell
Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content app/Services/AttendanceService.php
Get-Content resources/views/teacher/attendance/dashboard.blade.php
Get-ChildItem resources/views/teacher/attendance -Force | Select-Object Name,FullName,Length
Get-ChildItem resources/views/teacher -Recurse -Filter *.blade.php | Select-Object -ExpandProperty FullName
rg -n "teacher\.attendance|attendance\.dashboard|attendance\.mark|attendance\.reports|attendance\.student|attendance\.edit|attendance\.export|Mark Attendance|Export attendance|Excel|CSV|View Reports|View Details" resources/views/teacher app/Http/Controllers/Teacher/TeacherAttendanceController.php routes/web.php
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l app/Services/AttendanceService.php
php artisan route:list | Select-String "teacher.attendance"
php artisan route:list | Select-String "attendance"
Get-Content tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php
Get-Content tests/Feature/Attendance/TeacherAttendanceUpdateGuardTest.php
Get-Content resources/views/teacher/attendance/mark.blade.php
Get-Content resources/views/teacher/attendance/edit.blade.php
Get-Content resources/views/teacher/attendance/reports.blade.php
Get-Content resources/views/teacher/attendance/student.blade.php
```

Notes:

- The missing view `Get-Content` checks returned explicit missing-file results.
- No teacher attendance store, update, export, web attendance write, API attendance write, biometric, migration, or full-suite command was executed.

## Teacher Dashboard Findings

Dashboard route:

- URI: `teacher/attendance/dashboard`
- route name: `teacher.attendance.dashboard`
- method: `GET`
- controller method: `TeacherAttendanceController@dashboard`
- returned view: `teacher.attendance.dashboard`
- Blade file exists: yes, `resources/views/teacher/attendance/dashboard.blade.php`

Dashboard content:

- shows "Attendance Dashboard"
- says "Manage and track student attendance for your classes"
- shows today's summary cards
- shows class-wise attendance sections
- shows a "Mark Attendance" button for each class
- shows low attendance alerts with "View Details"
- shows quick action link "View Reports"
- shows quick action link "Export Attendance"
- shows export copy: "Export attendance records to Excel/CSV"

Dashboard link findings:

- "Mark Attendance" uses JavaScript to navigate to `/teacher/attendance/mark/{classId}`.
- That route is active, but `teacher.attendance.mark` view is missing.
- "View Details" links to `teacher.attendance.student`.
- That route is active, but `teacher.attendance.student` view is missing.
- "View Reports" links to `teacher.attendance.reports`.
- That route is active, but `teacher.attendance.reports` view is missing.
- "Export Attendance" links to `teacher.attendance.export`.
- That route is active, but the controller returns placeholder JSON instead of an export.

Temporary-disable messaging:

- The dashboard does not indicate that teacher attendance marking is temporarily disabled.
- The dashboard does not indicate that teacher attendance updates are temporarily disabled.
- The dashboard still presents mark/report/export actions as active user-facing workflows.

Risk:

- Dashboard links may lead to missing-view runtime errors.
- Export copy promises Excel/CSV behavior that is not implemented.
- Marking UI appears available even though teacher store is guarded.

## Controller View Return Findings

`dashboard()`:

- returns `teacher.attendance.dashboard`
- file exists
- read route remains usable if service dependencies/data are valid

`markAttendance($classId)`:

- returns `teacher.attendance.mark`
- file missing
- route may fail at runtime after loading teacher, authorization, class, students, and existing attendance
- teacher store behind this flow is guarded, so the UI should not invite marking until aligned

`reports(Request $request)`:

- returns `teacher.attendance.reports`
- file missing
- route may fail at runtime after loading teacher, class data, and optional report

`studentAttendance($studentId)`:

- returns `teacher.attendance.student`
- file missing
- route may fail at runtime after student lookup, teacher lookup, assignment access check, and stats/trends loading

`editAttendance($attendanceId)`:

- returns `teacher.attendance.edit`
- file missing
- route may fail at runtime before any user can use an edit UI
- update route itself is guarded from Phase 7A

`exportAttendance(Request $request)`:

- authorizes export on `Attendance::class`
- reads `class_id`, `start_date`, and `end_date`
- does not generate CSV, Excel, or PDF
- returns JSON placeholder:

```php
return response()->json(['message' => 'Export functionality to be implemented']);
```

Guarded write methods:

- `storeAttendance()` is guarded from Phase 6Y.
- `updateAttendance()` is guarded from Phase 7A.
- The guarded write/update methods leave read routes registered, but several read routes return missing views.

## Route Findings

Teacher attendance routes found:

| Method | URI | Name | Controller method | Type | View / behavior |
| --- | --- | --- | --- | --- | --- |
| GET | `teacher/attendance/dashboard` | `teacher.attendance.dashboard` | `dashboard` | read/dashboard | view exists |
| GET | `teacher/attendance/mark/{classId}` | `teacher.attendance.mark` | `markAttendance` | read/form | view missing |
| POST | `teacher/attendance/store` | `teacher.attendance.store` | `storeAttendance` | write | guarded |
| GET | `teacher/attendance/reports` | `teacher.attendance.reports` | `reports` | read/report | view missing |
| GET | `teacher/attendance/student/{studentId}` | `teacher.attendance.student` | `studentAttendance` | read/detail | view missing |
| GET | `teacher/attendance/{id}/edit` | `teacher.attendance.edit` | `editAttendance` | read/edit form | view missing |
| PUT | `teacher/attendance/{id}` | `teacher.attendance.update` | `updateAttendance` | update | guarded |
| GET | `teacher/attendance/export` | `teacher.attendance.export` | `exportAttendance` | export | placeholder JSON |

Additional route notes:

- No teacher-side student attendance delete route was found.
- Route parameters use raw ids, not route model binding.
- Routes are under the teacher auth route group.
- Store/update routes are now guarded server-side.
- Read/export routes remain active even where backing views or export behavior are incomplete.

## Existing / Missing View Findings

Existing teacher attendance Blade files:

- `resources/views/teacher/attendance/dashboard.blade.php`

Referenced but missing:

- `resources/views/teacher/attendance/mark.blade.php`
- `resources/views/teacher/attendance/edit.blade.php`
- `resources/views/teacher/attendance/reports.blade.php`
- `resources/views/teacher/attendance/student.blade.php`

Broader teacher view scan:

- Many teacher feature views exist under `resources/views/teacher`, including dashboard, auth, classes, exams, exam papers, homework, lesson plans, marks, profile, and results.
- No alternate `mark`, `edit`, `reports`, or `student` attendance views were found under the teacher attendance folder.

Finding:

- The dashboard is the only present teacher attendance view.
- The missing file names match the controller view names exactly.
- Route UX should be cleaned up before teacher attendance marking/update is re-enabled.

## Export Contract Findings

Export route:

- URI: `teacher/attendance/export`
- route name: `teacher.attendance.export`
- method: `GET`
- controller method: `exportAttendance`

Controller behavior:

- Does not return CSV.
- Does not return Excel.
- Does not return PDF.
- Does not stream or download a file.
- Returns placeholder JSON:

```json
{"message":"Export functionality to be implemented"}
```

Dashboard behavior:

- Displays "Export Data"
- Says "Export attendance records to Excel/CSV"
- Shows active "Export Attendance" link

Risk:

- Dashboard export UI is misleading because no teacher attendance export is implemented.
- The export route should be hidden, disabled, or clearly labeled unavailable until implemented.
- This is separate from the hardened main attendance CSV export workflow.

## Risk Classification

RED:

- Active dashboard links target missing views.
- Active export link promises Excel/CSV but returns JSON placeholder.
- Marking appears available from the dashboard while teacher store is guarded.

YELLOW:

- Teacher reports/student detail/edit routes remain active but may fail due to missing views.
- Dashboard does not communicate temporary disablement of teacher attendance marking/update/export.
- Teacher dashboard data still depends on `AttendanceService` logic that includes old `leave` semantics.
- Export route authorization may be reached before placeholder JSON, but no actual export contract exists.

GREEN:

- Teacher store is guarded.
- Teacher update is guarded.
- No teacher attendance delete route was found.
- Dashboard Blade file exists.
- Dashboard route remains registered.

## Safe Implementation Options

### Option A - Disable or hide teacher attendance action links

Disable/hide dashboard links to mark, reports, student detail, edit, and export until views/workflow are aligned.

Pros:

- Prevents users from navigating into missing or unavailable flows.
- Keeps the dashboard route working.
- Does not change controller write guards.

Cons:

- Reduces visible teacher attendance functionality temporarily.

### Option B - Keep read-only dashboard and show disabled-state message

Add a clear dashboard alert such as "Teacher attendance marking, updates, reports, and export are temporarily disabled until class/status/schema policy is aligned."

Pros:

- Honest UX.
- Smallest UI-only correction.

Cons:

- Still needs links disabled/hidden to avoid broken routes.

### Option C - Add placeholder views

Create placeholder views for mark/edit/reports/student.

Pros:

- Avoids runtime missing-view errors.

Cons:

- Can expose unfinished flows and increase maintenance surface.
- Does not solve export placeholder or teacher attendance policy.

### Option D - Build proper teacher attendance workflow later

Rebuild teacher attendance using `AttendanceClassResolver`, main status policy, correct user identity, terminal/inactive checks, duplicate-safe writes, and complete views.

Pros:

- Correct long-term direction.

Cons:

- Too broad for the immediate UI safety phase.

## Recommended Phase 7C First Code Task

Phase 7C should update teacher attendance dashboard UI only.

Recommended behavior:

- Show a clear temporary-disabled message on `resources/views/teacher/attendance/dashboard.blade.php`.
- Disable or hide "Mark Attendance" buttons because store is guarded and mark view is missing.
- Disable or hide "View Reports" because report view is missing.
- Disable or hide "View Details" low-attendance student links if they target missing `teacher.attendance.student`.
- Disable or hide "Export Attendance" and remove Excel/CSV promise because export is placeholder JSON.
- Keep dashboard route working.
- Do not change controller write guards.
- Do not implement export.
- Do not add missing workflow views yet.

Why this is safest:

- It aligns UI promises with the guarded backend.
- It avoids leading teachers into broken/missing pages.
- It is a small display-only change after the server-side guards are already in place.

## Confirmation

This phase was read-only except for creating this report.

No application code, controllers, routes, models, services, views, tests, migrations, schema, database data, attendance records, attendance writes, attendance deletes, exports, imports, seeds, sync jobs, biometric device commands, full test suite, or real/local MySQL data were modified or run.

