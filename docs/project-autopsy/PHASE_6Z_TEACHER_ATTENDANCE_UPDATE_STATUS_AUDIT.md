# Phase 6Z - Teacher Attendance Update / Status Vocabulary Audit

Date: 2026-06-07

Scope: Read-only audit of teacher attendance update behavior and teacher-specific status vocabulary before changing teacher attendance update behavior.

## Files Inspected

- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/AttendanceService.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/User.php`
- `app/Models/Teacher.php`
- `app/Policies/AttendancePolicy.php`
- `routes/web.php`
- `resources/views/teacher/attendance/dashboard.blade.php`
- `resources/views/teacher/attendance/mark.blade.php`
- `resources/views/teacher/attendance/edit.blade.php`
- `resources/views/teacher/attendance/reports.blade.php`
- `resources/views/teacher/attendance/student.blade.php`
- `database/migrations/2026_01_21_083000_create_attendances_table.php`
- `database/migrations/2026_01_21_084000_create_attendances_temp_table.php`
- `database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php`
- `database/migrations/2026_02_08_083517_create_teacher_attendances_table.php`
- `tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php`
- `docs/project-autopsy/PHASE_6X_TEACHER_ATTENDANCE_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md`
- `docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`
- `docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md`

## Commands Run

```powershell
Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content app/Services/AttendanceService.php
Get-Content app/Models/Attendance.php
Get-Content app/Policies/AttendancePolicy.php
Get-Content app/Models/Student.php
Get-Content app/Models/User.php
Get-Content app/Models/Teacher.php
Get-ChildItem database/migrations -Filter *attendances* | Select-Object -ExpandProperty FullName
Get-Content database/migrations/2026_01_21_083000_create_attendances_table.php
Get-Content database/migrations/2026_01_21_084000_create_attendances_temp_table.php
Get-Content database/migrations/2026_02_08_083517_create_teacher_attendances_table.php
rg -n "updated_by|status.*present|leave|half_day|late|attendances" database/migrations app/Models app/Http/Controllers app/Services resources/views/teacher routes/web.php tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php
Get-ChildItem resources/views/teacher/attendance -Force | Select-Object Name,FullName,Length
Get-Content resources/views/teacher/attendance/dashboard.blade.php
Get-Content resources/views/teacher/attendance/mark.blade.php
Get-Content resources/views/teacher/attendance/edit.blade.php
Get-Content resources/views/teacher/attendance/reports.blade.php
Get-Content resources/views/teacher/attendance/student.blade.php
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l app/Services/AttendanceService.php
php -l app/Models/Attendance.php
php -l app/Policies/AttendancePolicy.php
php artisan route:list | Select-String "teacher.attendance"
php artisan route:list | Select-String "attendance"
Get-Content database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php
Get-Content tests/Feature/Attendance/TeacherAttendanceStoreGuardTest.php
Get-Content docs/project-autopsy/PHASE_6Y_TEACHER_ATTENDANCE_STORE_GUARD.md
Get-Content docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md
Get-Content docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md
Get-Content docs/project-autopsy/PHASE_6X_TEACHER_ATTENDANCE_WRITE_AUDIT.md
rg -n "required\|in:present,absent,late,half_day|in:present,absent,late,half_day|in:present,absent,leave|status.*leave|leave_days|updated_by|function updateAttendance" app/Http/Controllers app/Services app/Models resources/views/teacher database/migrations docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md
```

Notes:

- No optional live database checks were run. The status vocabulary and `updated_by` risks are visible from source and migration inspection.
- No teacher attendance store, update, delete, export, web attendance write, API attendance write, biometric, migration, or full-suite command was executed.

## Teacher Update Route Findings

Teacher attendance routes are registered under the `teacher` prefix and protected by `App\Http\Middleware\TeacherAuth::class`.

Relevant update route:

- URI: `teacher/attendance/{id}`
- name: `teacher.attendance.update`
- HTTP method: `PUT`
- controller method: `Teacher\TeacherAttendanceController@updateAttendance`
- route parameter: raw `{id}`, not route model binding
- middleware: teacher auth group

Related edit route:

- URI: `teacher/attendance/{id}/edit`
- name: `teacher.attendance.edit`
- HTTP method: `GET`
- controller method: `Teacher\TeacherAttendanceController@editAttendance`
- route parameter: raw `{id}`, not route model binding

Current route state:

- Teacher store route remains registered but is guarded from Phase 6Y.
- Teacher update route remains active while teacher store is guarded.
- No teacher-side student-attendance delete route was found.
- Separate `admin/teacher-attendance` resource routes exist, but those target an admin teacher-attendance area and are not the teacher student-attendance update path audited here.

## Teacher Update Controller Findings

`TeacherAttendanceController@updateAttendance(Request $request, $attendanceId)` validates:

```php
'status' => 'required|in:present,absent,leave',
'remarks' => 'nullable|string'
```

Accepted status values:

- `present`
- `absent`
- `leave`

Fields written:

```php
$attendance->update([
    'status' => $request->status,
    'remarks' => $request->remarks,
    'updated_by' => $teacher->id
]);
```

Findings:

- `leave` is accepted.
- `status` is updated.
- `remarks` is updated.
- `updated_by` is attempted with `$teacher->id`.
- The method loads attendance by raw id with `Attendance::findOrFail($attendanceId)`.
- The method loads the current teacher through `Auth::guard('teacher')->user()?->teacher`.
- The method calls `$this->authorize('update', $attendance)`.
- The method does not mutate `class`.
- The method does not mutate `date`.
- The method does not mutate `period`.
- The method does not mutate `student_id`.
- The method does not mutate `marked_by`.
- No transaction exists.
- No duplicate check exists.
- No duplicate-key exception handling exists.
- No explicit exception handling exists around the update.
- The update is a direct overwrite of status/remarks rather than an audit-preserving correction workflow.

Authorization caution:

- `AttendancePolicy::update(User $user, Attendance $attendance)` type-hints `App\Models\User`.
- The teacher routes use the teacher guard and `TeacherAuth` middleware.
- This mismatch should be tested before assuming the policy behaves as intended for teacher-authenticated requests.

## Status Vocabulary Findings

Teacher update accepts:

- `present`
- `absent`
- `leave`

Teacher store's unreachable post-guard validation still also references:

- `present`
- `absent`
- `leave`

Main attendance status vocabulary from code/migrations:

- `present`
- `absent`
- `late`
- `half_day`

Evidence:

- `database/migrations/2026_01_21_083000_create_attendances_table.php` defines `status` enum as `present`, `absent`, `late`, `half_day`.
- `database/migrations/2026_01_21_084000_create_attendances_temp_table.php` uses the same enum values.
- `database/migrations/2026_02_08_083517_create_teacher_attendances_table.php` also uses `present`, `absent`, `late`, `half_day`.
- `app/Http/Controllers/API/AttendanceController.php` validates API attendance statuses as `present`, `absent`, `late`, `half_day`.
- `app/Http/Controllers/AttendanceController.php` validates web attendance statuses as `present`, `absent`, `late`, `half_day`.

`leave` support:

- `leave` is not supported by the main `attendances.status` enum migration.
- `leave` is counted in `AttendanceService::getStudentAttendanceStats()`.
- `leave` is counted in `AttendanceService::getClassAttendanceSummary()`.
- This creates split semantics: the teacher service expects `leave`, while the shared attendance schema/API/web policy do not.

Risk:

- If the database enum is enforced, teacher update with `leave` can fail at write time.
- If the local schema has drifted to a string-like status column, teacher update can pollute shared attendance rows with a status value not accepted by API/web update or main reports.
- `leave` should not be silently mapped until a product policy decides whether it means `absent`, `half_day`, or a separate approved leave workflow.

## `updated_by` / Identity Findings

Migration findings:

- `database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php` conditionally adds `attendances.updated_by`.
- The column is an unsigned big integer.
- The foreign key references `users.id`.

Model findings:

- `Attendance::$fillable` does not include `updated_by`.
- `Attendance` has `markedBy()` pointing `marked_by` to `User`.
- No `updatedBy()` relation exists on `Attendance`.

Controller findings:

- Teacher update attempts to mass-assign `updated_by => $teacher->id`.
- `$teacher->id` is a teacher id, not a user id.
- Because `updated_by` is not fillable, this assignment is likely ignored by mass assignment today.
- If `updated_by` is later added to `Attendance::$fillable`, the teacher update path could write the wrong identity type into a users foreign key.

Risk:

- Silent non-write creates false confidence that an audit field is recorded.
- Future fillable/schema changes could turn this into a wrong-foreign-key write.
- Correct identity should likely use the authenticated user id, not the teacher model id, if this shared `attendances.updated_by` column remains user-referenced.

## Teacher Edit View Findings

Files found under `resources/views/teacher/attendance`:

- `dashboard.blade.php`

Files referenced by `TeacherAttendanceController` but not found:

- `resources/views/teacher/attendance/mark.blade.php`
- `resources/views/teacher/attendance/edit.blade.php`
- `resources/views/teacher/attendance/reports.blade.php`
- `resources/views/teacher/attendance/student.blade.php`

Edit view findings:

- `teacher.attendance.edit` route is registered.
- `TeacherAttendanceController@editAttendance()` returns `view('teacher.attendance.edit', compact('attendance', 'student'))`.
- The expected edit view file was not found.
- Therefore the edit route may fail at runtime before users can submit the update form.
- Because the view is missing, submitted fields/status options could not be audited from Blade.
- Backend risk remains because the `PUT teacher/attendance/{id}` route is active and can receive crafted requests.

Dashboard findings:

- Dashboard contains a "Mark Attendance" button that navigates to `/teacher/attendance/mark/{classId}`.
- Dashboard has links to teacher attendance reports and export.
- Dashboard export copy still says Excel/CSV, while teacher export returns a JSON placeholder.

## Comparison With Hardened API / Web Update Policy

Hardened API update:

- blocks `marked_by`
- blocks `student_id`
- blocks `class`
- blocks `date`
- blocks `period`
- allows main status vocabulary only

Hardened ordinary web update:

- blocks `class`
- blocks `date`
- blocks `period`
- does not mutate `student_id`
- does not mutate `marked_by`
- keeps `status`, `subject`, and `remarks` editable
- uses main status vocabulary `present`, `absent`, `late`, `half_day`

Teacher update comparison:

- already does not mutate class/date/period/student_id/marked_by
- still accepts `leave`, unlike API/web/main schema
- attempts `updated_by` with teacher id, unlike user-referenced audit fields
- lacks a clear aligned policy for teacher-specific leave
- remains active while teacher store is guarded for class/status/schema misalignment

Conclusion:

- Teacher update is narrower than the old unsafe web update because it does not mutate identity/date/period fields.
- Teacher update is still unsafe because it can attempt incompatible status and ambiguous identity writes.
- Given teacher store is already guarded for policy mismatch, teacher update should likely be guarded too until the teacher attendance workflow is aligned.

## Risk Classification

RED:

- Teacher update route is active.
- Teacher update accepts `leave`, which is not part of the shared attendance enum/status policy.
- Teacher update can attempt to write shared attendance status values inconsistent with API/web/main migration.
- Teacher update writes `updated_by` using teacher id while the migration references users.

YELLOW:

- `updated_by` is not fillable, so the attempted audit write likely does nothing today.
- If fillable changes later, the same code could write the wrong identity type.
- Teacher edit view is missing, but crafted `PUT` requests can still target the active route.
- Teacher auth guard and `AttendancePolicy` user type-hint should be explicitly verified before relying on the authorization path.
- `AttendanceService` still counts `leave`, preserving old teacher semantics in reports despite schema mismatch.

GREEN:

- Teacher update does not mutate class.
- Teacher update does not mutate date.
- Teacher update does not mutate period.
- Teacher update does not mutate student id.
- Teacher update does not mutate marked_by.
- No teacher-side delete route was found.
- Teacher store is already guarded from Phase 6Y.

## Safe Implementation Options

### Option A - Temporarily guard teacher update

Return a warning from `updateAttendance()` before validation/write.

Pros:

- Smallest immediate safety change.
- Aligns teacher update with guarded teacher store.
- Prevents `leave` pollution and `updated_by` ambiguity.
- Keeps route registered.

Cons:

- Temporarily disables teacher attendance update until policy is aligned.

### Option B - Keep teacher update active but restrict status to main vocabulary

Allow only `present`, `absent`, `late`, `half_day`.

Pros:

- Fixes the immediate status vocabulary mismatch.

Cons:

- Still leaves `updated_by` teacher-id/user-id ambiguity.
- Does not answer what old teacher `leave` should mean.
- Could break teacher workflow unexpectedly.

### Option C - Remove `updated_by` write and restrict status

Update only `status` and `remarks` with main status vocabulary.

Pros:

- Avoids wrong audit identity writes.

Cons:

- Still lacks teacher leave policy.
- Still does not align teacher workflow with the guarded store path.

### Option D - Add proper leave workflow later

Design `leave` as approved leave, half-day, absence reason, or separate workflow.

Pros:

- Better domain modeling.

Cons:

- Larger design and migration/reporting question.

### Option E - Build full teacher attendance alignment service

Use `AttendanceClassResolver`, main statuses, correct user identity, duplicate policy, terminal/inactive checks, and safe status mapping.

Pros:

- Best long-term alignment.

Cons:

- Too broad for the immediate safety phase.

## Recommended Phase 7A First Code Task

Phase 7A should temporarily guard `TeacherAttendanceController@updateAttendance()` server-side.

Recommended behavior:

- Keep route registered.
- Return back with warning before validation or update.
- Warning:

```text
Teacher attendance updates are temporarily disabled until class/status/schema policy is aligned.
```

- Do not change teacher store, API, web attendance, preflight, or `AttendanceService`.
- Add isolated SQLite tests proving:
  - teacher update returns warning
  - status is not changed
  - remarks are not changed
  - `updated_by` is not written
  - teacher store guard still passes

Reasoning:

- Teacher store is already guarded for class/status/schema risk.
- Teacher update still has the same status vocabulary problem and an additional `updated_by` identity ambiguity.
- Guarding update prevents new unsafe writes while a later phase decides the correct teacher attendance status and identity policy.

## Confirmation

This phase was read-only except for creating this report.

No application code, controllers, routes, models, services, views, tests, migrations, schema, database data, attendance records, attendance writes, attendance deletes, exports, imports, seeds, sync jobs, biometric device commands, full test suite, or real/local MySQL data were modified or run.

