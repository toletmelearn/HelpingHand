# Phase 6O - Web Attendance Class / Preflight Class Audit

Date: 2026-06-06

Scope: Read-only audit of web attendance store class handling, preflight class mismatch behavior, and teacher attendance class handling after Phase 6N fixed API store class spoofing.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `app/Services/AttendanceService.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `resources/views/attendance/create.blade.php`
- `resources/views/attendance/edit.blade.php`
- `resources/views/attendance/bulk_mark.blade.php`
- `resources/views/attendance/preflight-result.blade.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php`
- `docs/project-autopsy/PHASE_6N_API_ATTENDANCE_STORE_CLASS_DERIVATION.md`
- `docs/project-autopsy/PHASE_5G_ATTENDANCE_BULK_DIRECT_WRITE_GUARD.md`
- `docs/project-autopsy/PHASE_5B_ATTENDANCE_BULK_PREFLIGHT_SERVICE.md`
- `docs/project-autopsy/PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT.md`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/AttendanceController.php
Get-Content -Path app/Http/Controllers/Teacher/TeacherAttendanceController.php
Get-Content -Path app/Services/Attendance/AttendanceBulkPreflightService.php
Get-Content -Path app/Services/AttendanceService.php
Get-Content -Path resources/views/attendance/create.blade.php
Get-Content -Path resources/views/attendance/edit.blade.php
Get-Content -Path resources/views/attendance/bulk_mark.blade.php
Get-Content -Path resources/views/attendance/preflight-result.blade.php
Get-Content -Path routes/web.php
Get-Content -Path tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php
Get-Content -Path tests/Unit/Services/AttendanceBulkPreflightServiceTest.php
Get-Content -Path docs/project-autopsy/PHASE_6N_API_ATTENDANCE_STORE_CLASS_DERIVATION.md
Get-Content -Path docs/project-autopsy/PHASE_5G_ATTENDANCE_BULK_DIRECT_WRITE_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_5B_ATTENDANCE_BULK_PREFLIGHT_SERVICE.md
Get-Content -Path docs/project-autopsy/PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT.md
rg -n "store|update|bulkMark|preflight|class_id|class|hasClassIdConflict|resolveCanonicalSchoolClass|payload_legacy_class_mismatch|payload_class_id_mismatch|markAttendance|Attendance::insert|Attendance::updateOrCreate|updateOrCreate" app/Http/Controllers/AttendanceController.php app/Http/Controllers/Teacher/TeacherAttendanceController.php app/Services/AttendanceService.php app/Services/Attendance/AttendanceBulkPreflightService.php app/Models/Attendance.php app/Models/Student.php routes/web.php
php -l app/Http/Controllers/AttendanceController.php
php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php
php -l app/Services/Attendance/AttendanceBulkPreflightService.php
php -l app/Services/AttendanceService.php
php -l app/Models/Student.php
php artisan route | Select-String "attendance"
```

Notes:

- One initial `rg` search had a PowerShell quoting error and was rerun successfully with the command shown above.
- `php -l` checks passed for `AttendanceController.php`, `TeacherAttendanceController.php`, `AttendanceBulkPreflightService.php`, `AttendanceService.php`, and `Student.php`.
- `php artisan route | Select-String "attendance"` did not list routes because this Laravel app exposes `route:list`; `route` is only an Artisan namespace. No alternate route command was run because this phase had a fixed safe-command list.
- No optional live database checks were run. The class risks were clear from source inspection, and avoiding local MySQL contact kept this phase tightly read-only.

## Web Individual Store Class Findings

`AttendanceController@store()` has two write branches:

1. bulk `classes[] + default_status`
2. individual/per-student `student_ids[] + statuses[]`

### Individual / Per-Student Branch

The individual branch validates:

```php
'class' => 'required|string',
'date' => 'required|date',
'subject' => 'required|string',
'period' => 'nullable|string',
'student_ids' => 'required|array',
'statuses' => 'required|array',
'remarks.*' => 'nullable|string|max:255',
```

Findings:

- The branch writes attendance rows with `Attendance::insert($attendances)`.
- `class` is required from the request.
- `class` is client/form-supplied through a hidden field in `resources/views/attendance/create.blade.php`.
- `create()` selects students with `Student::where('class', $class)`.
- The posted `student_ids[]` are written with the same request `class` string.
- No student model is loaded in `store()` for each submitted row.
- No `Student::canonicalClassId()`, `Student::hasClassIdConflict()`, or `Student::resolveCanonicalSchoolClass()` helper is used.
- Submitted class is not compared to `students.class`.
- Submitted class is not compared to canonical `students.class_id`.
- A mismatch can be written if the hidden class value or submitted student ids do not match the student's actual class.
- Terminal/inactive students are not blocked in this branch.
- Duplicate logic uses `Attendance::isMarked($request->class, $request->date, $request->period)`, which is class-level and legacy-string based.
- Duplicate logic does not include per-student canonical class derivation.
- The branch uses raw insert, not transaction/upsert.

### Web Update Path

`AttendanceController@update()` validates and writes:

```php
'class' => 'required|string',
...
$attendance->update([
    'date' => $request->date,
    'class' => $request->class,
    ...
]);
```

The edit view renders the class input as `readonly`, but it still submits `name="class"` and the controller accepts it. Since readonly is a UI property, not a server-side protection, the web update path can mutate legacy class if a request is crafted.

Current state: web individual store and web update still trust legacy request/form class values.

## Web Bulk / Preflight Class Findings

### Direct Bulk Store

The bulk `classes[] + default_status` branch remains guarded from Phase 5G:

```php
if ($request->filled('classes') && $request->filled('default_status')) {
    return back()->with('warning', ...);
}
```

This return occurs before validation, student expansion, or `Attendance::insert()`.

The dead code after the guard still documents the old risky behavior:

- validates legacy `classes.*` as strings
- loops submitted class strings
- selects students using `Student::where('class', $class)`
- writes `class => $class`
- calls `Attendance::insert($attendances)`

Because the guard returns first, this direct bulk write path is not currently reachable through normal execution.

### Bulk UI

`resources/views/attendance/bulk_mark.blade.php`:

- uses legacy `classes[]` checkboxes from distinct `students.class`
- keeps only a `Preview` submit button active
- posts preview to `attendance.preflight-view`
- states direct bulk marking is disabled
- does not render Apply, Confirm, Save, or direct Mark Attendance controls

### Preflight View Path

`AttendanceController@preflightView()`:

- reads `class`, `classes[]`, `class_id`, and `section_id` from the request
- expands `classes[]` by querying `Student::where('class', $cls)->get()`
- expands single `class` similarly with `Student::where('class', $providedClass)->get()`
- passes payload-level `class` and `class_id` hints to `AttendanceBulkPreflightService`

Findings:

- Preflight expansion uses legacy class strings.
- Preflight rows do not include a derived class value for future writes.
- Preflight result page remains read-only and has no apply button.
- Future safe apply should derive class from each student, not from the payload class string.
- Preflight should be aligned with Phase 6N before any safe apply flow is enabled.

## AttendanceBulkPreflightService Class Findings

`AttendanceBulkPreflightService` currently reads:

- payload `class`
- payload `class_id`
- payload `section_id`
- student legacy `$student->class`
- student `$student->class_id`
- student `$student->section_id`

Normalized row currently includes:

```php
'class_id' => $student->class_id ?? null,
'school_class_id' => $student->class_id ?? null,
'legacy_class' => $student->class ?? null,
```

Findings:

- It does not call `canonicalClassId()`.
- It does not call `hasClassIdConflict()`.
- It does not call `resolveCanonicalSchoolClass()`.
- `school_class_id` is populated from `$student->class_id`, not `$student->school_class_id`.
- Payload class id mismatch is a warning: `payload_class_id_mismatch`.
- Payload legacy class mismatch is a warning: `payload_legacy_class_mismatch`.
- Existing attendance legacy class mismatch is a warning: `existing_attendance_legacy_class_mismatch`.
- Class mismatch does not make a row invalid.
- Terminal/inactive statuses are warnings plus `action = skip`, not hard errors.
- The service does not output a `derived_class` or `resolved_class` field.
- It can warn about drift, but it does not encode the stronger Phase 6N storage policy.

Future preflight/apply implication:

- Mismatch warnings should remain safe for preview, but a future write apply must use a resolver-derived class.
- Student class id conflicts should likely become controlled errors/blockers for future apply.
- Preflight should expose a derived class value and class source so users can see what would be stored.

## Teacher Attendance Class Findings

### TeacherAttendanceController

`TeacherAttendanceController@storeAttendance()` validates:

```php
'class_id' => 'required|exists:school_classes,id',
'attendance.*.student_id' => 'required|exists:students,id',
'attendance.*.status' => 'required|in:present,absent,leave',
```

It builds rows with:

```php
'student_id' => $record['student_id'],
'date' => $request->date,
'status' => $record['status'],
'remarks' => $record['remarks'] ?? null,
'class_id' => $request->class_id,
```

Findings:

- Teacher flow is separate from web admin `AttendanceController@store()`.
- Teacher flow uses request `class_id`.
- It does not derive class from each student.
- It does not verify each submitted student belongs to the submitted class id.
- It does not use `Student::hasClassIdConflict()` or `resolveCanonicalSchoolClass()`.
- It does not inspect terminal/inactive status.
- It passes rows to `AttendanceService::markAttendance()`.

### AttendanceService

`AttendanceService::markAttendance()`:

- wraps writes in a transaction
- uses `Attendance::updateOrCreate()`
- lookup keys are `student_id` and `date`
- updates `status`, `remarks`, `marked_by`, and `class_id`

Findings:

- This writes through `App\Models\Attendance`, so it targets the `attendances` table.
- `Attendance::$fillable` does not include `class_id`.
- Prior Phase 5F live schema findings showed `attendances` did not include `class_id`.
- That means teacher path has a schema/model mismatch around class identity.
- It does not write legacy `attendances.class`.
- It may silently drop `class_id` because it is not fillable, or fail depending on runtime schema/mass assignment behavior.
- Duplicate logic is better than raw insert because it uses `updateOrCreate()` in a transaction, but its identity is only `student_id/date` and ignores `period`.
- Teacher status vocabulary includes `leave`, while the main attendance status set uses `present`, `absent`, `late`, `half_day`.

Teacher path has a separate class risk and should be handled in its own focused phase.

## Existing Data Risk Findings

Live database class drift checks were not run in this phase.

Reason:

- The task allowed optional read-only checks but did not require them.
- Source inspection already showed web store/preflight/teacher class risks.
- Avoiding local MySQL contact kept this phase strictly to code inspection and the required report.

Potential future read-only checks:

- count attendance rows where `attendances.class` differs from `students.class`
- count attendance rows where `attendances.class` differs from canonical `school_classes.name`
- count students where `class_id` and `school_class_id` conflict
- list distinct attendance class values
- list distinct student class values

Prior Phase 5F did run live read-only checks and found:

- total attendance rows: 104
- rows where `attendances.class` differs from `students.class`: 24
- terminal/inactive-linked attendance rows checked: 2

Those historical counts should be refreshed in a dedicated read-only reconciliation phase before any repair or normalization.

## Safe Implementation Options

### Option A - Apply Phase 6N-style derivation to web individual store first

Derive stored class per submitted student before insert, using canonical `SchoolClass::name` then legacy `students.class`, with controlled conflict/unresolved handling.

Pros:

- Directly reduces the web individual write risk.
- Mirrors the new API policy.

Risks:

- Current web individual branch bulk-inserts one row per student, so introducing per-row errors requires a clear UX policy.
- Class-level duplicate check currently uses the request class before rows are loaded.
- Terminal/inactive and row validation gaps remain.

### Option B - Update preflight service to canonical class policy first

Add canonical class helper usage to preflight:

- detect `hasClassIdConflict()`
- output derived class value/source
- warn or error on payload mismatch against derived class
- fix `school_class_id` normalized output

Pros:

- Read-only behavior can be tested safely.
- Aligns future safe apply with Phase 6N before enabling writes.
- Does not change web writes yet.

Risks:

- Does not immediately fix the web individual store write branch.
- Requires deciding which class mismatches are warnings versus blockers.

### Option C - Fix teacher attendance class derivation first

Make teacher path derive or store a compatible class value.

Pros:

- Teacher path already uses transaction/updateOrCreate.
- Teacher path has a clear `class_id` request context.

Risks:

- Teacher path has separate schema/status vocabulary problems.
- Fixing it first may entangle class derivation with table mismatch and `leave` status policy.

### Option D - Build a shared AttendanceClassResolver service/helper

Create a small resolver encapsulating Phase 6N policy:

- detect class id conflicts
- resolve canonical class name
- fallback to legacy class
- return controlled unresolved result

Use it first in API store without behavior change, then web store/preflight.

Pros:

- Reduces duplicated class logic.
- Makes API, web, and preflight alignment easier.
- Allows focused unit tests around class resolution.

Risks:

- Slightly broader than a one-controller patch.
- Requires careful migration of API store to preserve exact Phase 6N messages/statuses.

## Risk Classification

RED:

- Web individual store writes request `class` directly into all attendance rows.
- Web update accepts `class` server-side despite readonly UI.
- Teacher attendance path attempts to write `class_id` through `Attendance`, while `attendances` is legacy-class based.
- Future preflight apply would be unsafe if it wrote payload/legacy class instead of student-derived class.

YELLOW:

- Direct web bulk store is guarded but dead code still shows legacy class expansion.
- Preflight class mismatch is warning-only and legacy-string based.
- Preflight does not use canonical student class helpers.
- `Attendance::isMarked()` duplicate guard remains legacy-class based.
- Existing historical class drift likely exists based on Phase 5F counts.
- Teacher path lacks terminal/inactive exclusion and uses `leave`.

GREEN:

- API store now derives class from student.
- Direct web bulk write remains guarded.
- Bulk UI is preview-only.
- Preflight result page remains read-only.
- Student canonical class helpers exist.
- Admin student writes already normalize class fields.

## Recommended Phase 6P First Code Task

Phase 6P should create a small shared `AttendanceClassResolver` service/helper that encapsulates the Phase 6N derivation policy, then migrate API `store()` to use it without changing behavior.

Recommended resolver behavior:

- accept a `Student`
- if `hasClassIdConflict()` is true, return conflict result/message/status
- else if `resolveCanonicalSchoolClass()` exists, return its name and source `canonical`
- else if legacy `$student->class` exists, return it and source `legacy`
- else return unresolved result/message/status

Why this should come before changing web writes:

- It prevents copy-pasting the Phase 6N policy into multiple controllers.
- It gives a stable, unit-tested class-resolution contract.
- API store can be migrated first as a no-behavior-change safety check.
- Web individual store and preflight can then adopt the same resolver in later phases.

Suggested sequence:

1. Phase 6P: create resolver and refactor API store to use it, no behavior change.
2. Phase 6Q: update preflight service to expose derived class/source and class conflict blockers.
3. Phase 6R: apply resolver to web individual store with focused UX/error handling.
4. Phase 6S: audit/fix teacher attendance class/schema/status behavior.
5. Later: run read-only class drift reconciliation before any data repair.

## Confirmation

This phase was read-only except for creating this report.

No application code, controllers, routes, models, services, views, tests, migrations, database data, attendance records, export route, imports, seeds, sync jobs, biometric device commands, full suite, or real/local MySQL data were modified or run.
