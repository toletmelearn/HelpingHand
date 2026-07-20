# Phase 6X - Teacher Attendance Write / Update / Delete Audit

Date: 2026-06-07

Scope: Read-only audit of teacher attendance write, update, and delete behavior before changing teacher attendance flows.

## Files Inspected

- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/AttendanceService.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `app/Models/User.php`
- `app/Policies/AttendancePolicy.php`
- `routes/web.php`
- `resources/views/teacher/attendance/dashboard.blade.php`
- `database/migrations/2026_01_21_083000_create_attendances_table.php`
- `docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md`
- `docs/project-autopsy/PHASE_6R_WEB_ATTENDANCE_STORE_CLASS_DERIVATION.md`
- `docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`
- `docs/project-autopsy/PHASE_6V_WEB_ATTENDANCE_DESTROY_GUARD.md`
- `docs/project-autopsy/PHASE_6W_ATTENDANCE_DELETE_UI_DISABLED.md`

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content -Path app/Services/AttendanceService.php
Get-Content -Path app/Services/Attendance/AttendanceClassResolver.php
Get-Content -Path app/Models/Attendance.php
Get-Content -Path app/Models/Student.php
Get-Content -Path routes/web.php
Get-ChildItem -Path resources/views/teacher -Recurse -Filter *.blade.php | Select-Object -ExpandProperty FullName
rg -n "attendance|class_id|status|leave|present|absent|delete|destroy|method\('DELETE'|@method\('DELETE'|teacher\.attendance" resources/views/teacher app/Http/Controllers/Teacher/TeacherAttendanceController.php app/Services/AttendanceService.php routes/web.php
rg -n "Schema::create\('attendances'|table\('attendances'|class_id|updated_by|teacher_id|period|subject|session|unique" database/migrations app/Models/Attendance.php app/Services/AttendanceService.php
Get-Content -Path app/Models/SchoolClass.php
Get-Content -Path app/Models/Section.php
Get-Content -Path app/Models/User.php
Get-Content -Path resources/views/teacher/attendance/dashboard.blade.php
Get-Content -Path resources/views/teacher/attendance/mark.blade.php -ErrorAction SilentlyContinue
Get-Content -Path resources/views/teacher/attendance/edit.blade.php -ErrorAction SilentlyContinue
Get-Content -Path resources/views/teacher/attendance/reports.blade.php -ErrorAction SilentlyContinue
Get-Content -Path resources/views/teacher/attendance/student.blade.php -ErrorAction SilentlyContinue
Get-Content -Path database/migrations/2026_01_21_083000_create_attendances_table.php
rg -n "TeacherAttendance|teacher\.attendance|AttendanceService|markAttendance\(|updateAttendance\(|storeAttendance" tests app/Policies app/Providers
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l app/Services/AttendanceService.php
php -l app/Models/Attendance.php
php -l app/Models/Student.php
php -l app/Services/Attendance/AttendanceClassResolver.php
php artisan route:list | Select-String "teacher"
php artisan route:list | Select-String "attendance"
Get-Content -Path app/Policies/AttendancePolicy.php
Get-ChildItem -Path docs/project-autopsy -Filter "PHASE_6*.md" | Select-Object -ExpandProperty Name
Get-Content -Path docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md
Get-Content -Path docs/project-autopsy/PHASE_6R_WEB_ATTENDANCE_STORE_CLASS_DERIVATION.md
Get-Content -Path docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_6V_WEB_ATTENDANCE_DESTROY_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_6W_ATTENDANCE_DELETE_UI_DISABLED.md
```

Notes:

- `Get-Content` for `resources/views/teacher/attendance/mark.blade.php`, `edit.blade.php`, `reports.blade.php`, and `student.blade.php` returned no content because those files were not found.
- No optional live database checks were run. The schema/model/service risks were clear from source and migration inspection, and avoiding local MySQL contact kept this audit strictly read-only.
- No teacher attendance write, update, delete, export, biometric, migration, or full-suite command was run.

## Teacher Route Findings

Teacher attendance routes are registered inside the `teacher` prefix and protected by `App\Http\Middleware\TeacherAuth::class`.

Routes found:

- `GET teacher/attendance/dashboard`
  - name: `teacher.attendance.dashboard`
  - action: `Teacher\TeacherAttendanceController@dashboard`
- `GET teacher/attendance/mark/{classId}`
  - name: `teacher.attendance.mark`
  - action: `Teacher\TeacherAttendanceController@markAttendance`
  - route model binding: no, raw `{classId}`
- `POST teacher/attendance/store`
  - name: `teacher.attendance.store`
  - action: `Teacher\TeacherAttendanceController@storeAttendance`
  - active write route: yes
- `GET teacher/attendance/reports`
  - name: `teacher.attendance.reports`
  - action: `Teacher\TeacherAttendanceController@reports`
- `GET teacher/attendance/student/{studentId}`
  - name: `teacher.attendance.student`
  - action: `Teacher\TeacherAttendanceController@studentAttendance`
  - route model binding: no, raw `{studentId}`
- `GET teacher/attendance/{id}/edit`
  - name: `teacher.attendance.edit`
  - action: `Teacher\TeacherAttendanceController@editAttendance`
  - route model binding: no, raw `{id}`
- `PUT teacher/attendance/{id}`
  - name: `teacher.attendance.update`
  - action: `Teacher\TeacherAttendanceController@updateAttendance`
  - active update route: yes
- `GET teacher/attendance/export`
  - name: `teacher.attendance.export`
  - action: `Teacher\TeacherAttendanceController@exportAttendance`

Findings:

- Teacher attendance write and update routes are active.
- No `DELETE teacher/attendance/{id}` route was found.
- Teacher attendance delete does not appear exposed in the teacher namespace.
- Routes use raw ids instead of route model binding.
- The teacher routes are teacher-authenticated, not broad unauthenticated routes.
- Separate admin `admin/teacher-attendance` resource routes also exist, but those appear to manage teacher attendance records through `Admin\TeacherAttendanceController`, not the student attendance `Attendance` model path audited here.

## Teacher Controller Write Findings

### Display / Read Methods

`dashboard()`:

- loads the current teacher from `Auth::guard('teacher')->user()?->teacher`
- calls `AttendanceService::getTeacherClassAttendance()`
- calls `AttendanceService::getClassAttendanceSummary()`
- calls `AttendanceService::getLowAttendanceAlerts()`
- renders `teacher.attendance.dashboard`

`markAttendance($classId)`:

- loads the current teacher from the teacher guard
- calls `$this->authorize('markAttendance', [null, $classId])`
- loads `SchoolClass::findOrFail($classId)`
- loads students with `Student::where('class_id', $classId)->get()`
- renders `teacher.attendance.mark`

Risk:

- The referenced `teacher.attendance.mark` view file was not found during inspection, so this page may fail at runtime unless the view exists through a nonstandard path or is generated elsewhere.
- The authorization call is unusual because it passes `[null, $classId]`; `AttendancePolicy::markAttendance(User $user, $classId)` exists, but there is no model class anchor in the call. Policy resolution may not be using the intended `AttendancePolicy`.

### Store Method

`storeAttendance(Request $request)` validates:

```php
'class_id' => 'required|exists:school_classes,id',
'date' => 'required|date',
'attendance' => 'required|array',
'attendance.*.student_id' => 'required|exists:students,id',
'attendance.*.status' => 'required|in:present,absent,leave'
```

It builds rows:

```php
[
    'student_id' => $record['student_id'],
    'date' => $request->date,
    'status' => $record['status'],
    'remarks' => $record['remarks'] ?? null,
    'class_id' => $request->class_id,
]
```

Then it calls:

```php
$markedRecords = $this->attendanceService->markAttendance($attendanceData, $teacher->id);
```

Findings:

- Teacher attendance write path is active.
- `class_id` is request-supplied.
- Each row inherits the request `class_id`.
- Student ids are request-supplied.
- The controller validates that each student id exists, but does not verify each student belongs to the submitted `class_id`.
- The controller does not use `AttendanceClassResolver`.
- The controller does not derive legacy `attendances.class` from the student.
- The controller does not check `Student::hasClassIdConflict()`.
- The controller does not check terminal/inactive student statuses.
- The controller uses `teacher->id` as `marked_by` through the service, while `attendances.marked_by` is defined as a user id foreign key in the main attendance migration.
- The controller sends notifications after the service write using the same submitted attendance payload.
- The controller permits status `leave`, which is not part of the main attendance enum/status vocabulary.
- The controller does not explicitly verify the teacher can mark all submitted students beyond the class-level request/route authorization.

### Update Method

`updateAttendance(Request $request, $attendanceId)` validates:

```php
'status' => 'required|in:present,absent,leave',
'remarks' => 'nullable|string'
```

It loads attendance by raw id, then:

```php
$this->authorize('update', $attendance);

$attendance->update([
    'status' => $request->status,
    'remarks' => $request->remarks,
    'updated_by' => $teacher->id
]);
```

Findings:

- Teacher update route is active.
- Teacher update does not mutate `class`, `date`, `period`, `student_id`, or `marked_by`.
- It does mutate `status` and `remarks`.
- It attempts to write `updated_by` with `$teacher->id`.
- The migration adds `updated_by` as a user foreign key, while this code uses teacher id.
- `Attendance::$fillable` does not include `updated_by`, so this mass-assigned field is likely discarded unless model behavior changes elsewhere.
- It permits `leave`, which conflicts with the main attendance status enum.
- It does not use a transaction.
- It does not catch duplicate or DB exceptions.

### Delete Method

No delete method was found in `TeacherAttendanceController`.

No teacher attendance delete route was found under the teacher attendance route group.

## AttendanceService Findings

`AttendanceService::markAttendance($attendanceData, $teacherId = null)` writes to `App\Models\Attendance`.

Write behavior:

- Starts a DB transaction.
- Loops each submitted row.
- Calls `Attendance::updateOrCreate()`.
- Lookup keys:
  - `student_id`
  - `date`
- Updated fields:
  - `status`
  - `remarks`
  - `marked_by`
  - `class_id`
- Commits on success.
- Rolls back and logs on exception.

Findings:

- It writes to the shared `attendances` table.
- It uses `updateOrCreate()`, so it may overwrite an existing attendance row for the same `student_id/date`.
- It ignores `period` in the identity key.
- It ignores `subject` in the identity key.
- It ignores `class` in the identity key.
- It does not write `period`.
- It does not write `subject`.
- It does not write `session`.
- It does not write legacy `class`.
- It attempts to write `class_id`.
- `Attendance::$fillable` does not include `class_id`.
- The main `attendances` migration does not define `class_id`.
- Therefore teacher write has a strong model/schema compatibility risk.
- It uses `marked_by => $teacherId`, but `marked_by` is documented and constrained as a user id in the attendance migration.
- It uses status values expected by the teacher path, including `leave`.
- It does not normalize `leave` into `half_day`, `absent`, or another main-module value.

Other service findings:

- `getStudentAttendanceStats()` counts `leave`.
- `getClassAttendanceSummary()` counts `leave`.
- `getTeacherClassAttendance()` uses teacher class-subject assignments and fetches students by `class_id`, then finds attendance by student/date.
- `generateAttendanceReport()` reports per student stats using the same `leave` vocabulary.

## Model / Schema Compatibility Findings

### Attendance Model

`Attendance::$fillable` includes:

- `student_id`
- `teacher_id`
- `date`
- `status`
- `remarks`
- `period`
- `subject`
- `class`
- `session`
- `marked_by`
- `ip_address`
- `device_info`

It does not include:

- `class_id`
- `updated_by`

### Attendance Migration

`2026_01_21_083000_create_attendances_table.php` defines:

- `student_id`
- `teacher_id`
- `date`
- `status` enum: `present`, `absent`, `late`, `half_day`
- `remarks`
- `period`
- `subject`
- `class`
- `session`
- `marked_by`
- `ip_address`
- `device_info`

It does not define `class_id`.

It defines unique key:

```php
$table->unique(['student_id', 'date', 'period']);
```

Additional migration `2026_01_21_120001_add_relationships_to_existing_tables.php` conditionally adds `updated_by` to `attendances`, but the model does not list it as fillable.

Compatibility findings:

- Teacher service writes `class_id`, but the table/model are legacy `class` based.
- Teacher update writes `updated_by`, but the model does not allow mass assignment for `updated_by`.
- Teacher path status `leave` is incompatible with the main attendance enum migration.
- If the database enum is enforced, `leave` can fail at write time.
- If the local schema drifted away from enum enforcement, `leave` can still pollute status vocabulary used by reports/API/web.
- `AttendanceService::updateOrCreate()` identity only uses `student_id/date`, while the table unique key includes `period`.
- Because teacher path omits `period`, it may update a full-day row or create a null-period row depending on existing data and DB behavior.
- It does not use `AttendancePeriodPresenter` or null-period policy helpers.

## Teacher View Findings

Files present under `resources/views/teacher/attendance`:

- `dashboard.blade.php`

Files referenced by `TeacherAttendanceController` but not found:

- `teacher.attendance.mark`
- `teacher.attendance.edit`
- `teacher.attendance.reports`
- `teacher.attendance.student`

Dashboard findings:

- Shows today's class attendance.
- Shows a "Mark Attendance" button that routes via JavaScript to `/teacher/attendance/mark/{classId}`.
- Shows reports link.
- Shows export link.
- Mentions "Export attendance records to Excel/CSV", while controller returns JSON placeholder: `Export functionality to be implemented`.
- Does not show delete controls for student attendance.

Because the mark/edit/report/student views were not found:

- Submitted form fields for teacher attendance marking could not be audited from Blade source.
- Status options in the missing mark/edit views could not be confirmed.
- Hidden `class_id` and posted `student_id` behavior could not be verified from the UI.
- The active controller routes may fail at view rendering unless those files exist under another convention not found by `Get-ChildItem`.

## Comparison With Hardened API / Web Policy

Hardened API/web attendance behavior now includes:

- API store derives `attendances.class` from the target student through `AttendanceClassResolver`.
- Web individual store derives `attendances.class` from the target student through `AttendanceClassResolver`.
- Preflight uses `AttendanceClassResolver` read-only.
- API store rejects terminal/inactive students.
- API store has duplicate pre-check plus duplicate-key handling.
- API update blocks `marked_by`, `student_id`, `class`, `date`, and `period`.
- Web update blocks `class`, `date`, and `period`.
- API destroy and web destroy are guarded.
- Web delete UI is disabled/removed.

Teacher attendance currently differs:

- Uses request `class_id`, not resolver-derived legacy class.
- Does not write legacy `attendances.class`.
- Does not use `AttendanceClassResolver`.
- Does not reject terminal/inactive students.
- Uses `leave`, not the main `late` / `half_day` vocabulary.
- Uses `updateOrCreate()` and can overwrite existing rows for `student_id/date`.
- Omits `period`, `subject`, and `session`.
- Uses teacher id for `marked_by` / `updated_by` where the attendance schema expects user ids.
- Has active write/update routes but missing referenced mark/edit/report/student views in the inspected tree.

Teacher path should eventually align to:

- derive class per student through `AttendanceClassResolver`
- write legacy `class` unless/until a schema migration adds `class_id`
- verify each student belongs to the teacher's assigned class
- reject terminal/inactive students
- use the main attendance status vocabulary or explicitly map teacher-specific values
- avoid unsafe update/overwrite behavior
- keep delete unavailable or guarded

## Risk Classification

RED:

- Teacher attendance write route is active.
- Teacher write path uses request-supplied `class_id`.
- Teacher service writes `class_id`, but `Attendance` model and main migration are legacy `class` based.
- Teacher path accepts `leave`, which is not in the main attendance enum/status policy.
- Teacher service uses `updateOrCreate()` keyed only by `student_id/date`, so it can overwrite existing attendance rows unexpectedly.
- Teacher path does not verify each posted student belongs to the submitted class.
- Teacher path does not block terminal/inactive students.

YELLOW:

- `marked_by` and `updated_by` use teacher id values, while attendance schema documents user id foreign keys.
- Teacher update can write `leave` into shared attendance records if schema permits it.
- Teacher path omits period, subject, and session, making it incompatible with current period/report semantics.
- Teacher mark/edit/report/student views referenced by the controller were not found.
- Authorization uses teacher guard context but policy methods type-hint `App\Models\User`, so policy behavior may not match intention without further verification.
- Dashboard export UI promises Excel/CSV, but controller returns a JSON placeholder.

GREEN:

- Teacher attendance delete route was not found.
- Teacher update does not mutate class/date/period/student_id/marked_by.
- Teacher service uses a transaction around bulk write/update operations.
- Teacher student detail uses assignment-based access check for student class.
- Teacher routes are behind teacher authentication middleware.

## Safe Implementation Options

### Option A - Guard teacher attendance writes temporarily

Return a clear warning from `storeAttendance()` before calling `AttendanceService::markAttendance()`.

Pros:

- Smallest safety move.
- Prevents schema/status/class mismatch writes immediately.
- Mirrors the recent posture for unsafe bulk/delete routes.

Cons:

- Temporarily disables teacher attendance marking until policy is aligned.

### Option B - Use AttendanceClassResolver in TeacherAttendanceController

Load each submitted student, verify class assignment, resolve class through `AttendanceClassResolver`, and write legacy `class`.

Pros:

- Aligns teacher write with API/web store class policy.

Cons:

- Still must address status `leave`, period null policy, `marked_by` id mismatch, and `AttendanceService` schema mismatch.

### Option C - Update AttendanceService to write legacy `class`

Stop writing unsupported `class_id` and write `class` derived from student instead.

Pros:

- Fixes immediate model/schema mismatch.

Cons:

- Requires controller/service contract change.
- Does not alone fix terminal/inactive/status vocabulary/overwrite risks.

### Option D - Normalize teacher status vocabulary

Map or replace `leave` with a main-module status policy.

Pros:

- Avoids enum/runtime failure and reporting drift.

Cons:

- Needs product decision: whether leave maps to absent, half_day, or a separate approved-leave workflow.

### Option E - Build teacher preflight before write

Make teacher marking preview-only first, then later add safe apply.

Pros:

- Matches the hardened bulk/preflight approach.

Cons:

- Larger UI/service change.

### Option F - Separate teacher attendance workflow/table

If teacher attendance semantics differ materially from main attendance, design a separate model/table.

Pros:

- Clean long-term boundary if teacher marking is subject/session based.

Cons:

- Larger design and migration project.

## Recommended Phase 6Y First Code Task

Phase 6Y should guard teacher attendance writes temporarily.

Recommended first step:

- In `TeacherAttendanceController@storeAttendance()`, return a clear warning before validation/service write:
  - `Teacher attendance marking is temporarily disabled until class/status/schema policy is aligned.`
- Do not change teacher update yet.
- Do not change `AttendanceService` yet.
- Keep routes registered.
- Add isolated tests proving:
  - teacher store route returns warning/redirect
  - `AttendanceService::markAttendance()` is not called
  - no attendance row is written in isolated SQLite
  - teacher update remains unchanged for now

Why this is safest:

- The active teacher write path combines several high-risk mismatches at once: request `class_id`, unsupported `class_id` storage, `leave` status, no terminal filter, no student/class membership check, and overwrite-prone `updateOrCreate()`.
- A temporary guard prevents new bad writes while the next phases design the resolver/status/schema alignment carefully.

After the guard, a later phase can refactor teacher attendance to use `AttendanceClassResolver`, a safe status mapping, user-correct `marked_by`, and duplicate-aware write behavior.

## Confirmation

This phase was read-only except for creating this report.

No application code, controllers, routes, models, services, views, tests, migrations, schema, database data, attendance records, attendance writes, attendance deletes, exports, imports, seeds, sync jobs, biometric device commands, full test suite, or real/local MySQL data were modified or run.
