# Phase 5O - API Attendance Date/Period Duplicate Audit

Date: 2026-06-05

Scope: Read-only audit of API attendance `update()` date/period mutation and duplicate-conflict behavior after Phase 5N blocked `student_id`, legacy `class`, and `marked_by` mutation.

## Read-Only Confirmation

- No application code was modified.
- No routes were modified.
- No controllers, services, models, tests, or migrations were modified.
- No API attendance write route was executed.
- No attendance records were created, updated, deleted, marked, seeded, imported, exported, synced, or otherwise mutated.
- No biometric sync or device command was run.
- No migrations, database setup, composer setup, or full test suite were run.
- No real/local MySQL data was touched.
- Only this report file was created: `docs/project-autopsy/PHASE_5O_API_ATTENDANCE_DATE_PERIOD_DUPLICATE_AUDIT.md`.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`
- `docs/project-autopsy/PHASE_5N_API_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`
- `docs/project-autopsy/PHASE_5M_API_ATTENDANCE_DESTROY_GUARD.md`
- `docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md`
- `database/migrations/2026_01_21_083000_create_attendances_table.php` via `rg` evidence
- `database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php` via `rg` evidence

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content routes/api.php
Get-Content app/Models/Attendance.php
Get-Content app/Models/Student.php
Get-Content app/Models/StudentStatus.php
Get-Content tests/Feature/Attendance/AttendanceApiUpdateIdentityGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php
rg -n "attendances|unique|student_id.*date.*period|period|date" database/migrations app/Models/Attendance.php app/Http/Controllers/API/AttendanceController.php
Get-Content docs/project-autopsy/PHASE_5N_API_ATTENDANCE_UPDATE_IDENTITY_GUARD.md
Get-Content docs/project-autopsy/PHASE_5M_API_ATTENDANCE_DESTROY_GUARD.md
Get-Content docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md
php -l app/Http/Controllers/API/AttendanceController.php
php -l routes/api.php
php artisan route --path=api/v1/attendance
```

Command results:

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is an Artisan namespace, not a route listing command.

No live DB checks were run in this phase.

## API Update Date/Period Findings

Location: `app/Http/Controllers/API/AttendanceController.php`

Current `update()` validation allows:

```php
'date' => 'sometimes|required|date',
'status' => 'sometimes|required|in:present,absent,late,half_day',
'remarks' => 'nullable|string|max:255',
'period' => 'nullable|string|max:50',
'subject' => 'nullable|string|max:100',
'session' => 'nullable|string|max:20'
```

### Date Mutation

- `date` can still be changed.
- It is validated only as a date.
- No duplicate conflict check runs before updating `date`.
- No correction/audit workflow wraps date changes.

### Period Mutation

- `period` can still be changed.
- It is nullable and validated as a string with max length 50.
- No duplicate conflict check runs before updating `period`.
- `period` is part of the table's unique identity policy.

### Fields Still Editable

The following fields remain editable:

- `status`
- `remarks`
- `subject`
- `session`
- `date`
- `period`

Phase 5N test coverage confirms status/remarks/subject/session remain editable.

### Fields Now Blocked

The following fields are blocked from `update()` mutation:

- `student_id`
- `class`
- `marked_by`

This reduces reassignment/class drift risk but does not remove date/period duplicate risk.

### Duplicate Conflict Behavior

- No pre-update duplicate query exists.
- No transaction is used.
- No lock is used.
- No atomic update/upsert policy is used.
- If an attendance row is updated to match another row's same `student_id,date,period`, the controller relies on the database to reject it if the unique constraint applies.
- The catch block catches generic `\Exception` and returns:
  - `Failed to update attendance: ...`
- There is no controlled `409 Conflict` response for update-time duplicate conflicts.
- The current update behavior is not safe enough for production if date/period correction is allowed through this endpoint.

## API Store Duplicate Findings

Location: `app/Http/Controllers/API/AttendanceController.php`

`store()` duplicate check:

```php
Attendance::where('student_id', $validated['student_id'])
    ->where('date', $validated['date'])
    ->where('period', $validated['period'] ?? null)
    ->exists()
```

Findings:

- Duplicate check fields are `student_id`, `date`, and `period`.
- Duplicate response is controlled HTTP `409`.
- The check is not atomic.
- There is no transaction around the check and create.
- There is no lock.
- There is no `updateOrCreate`, `upsert`, or database-level conflict handling path with a controlled response.
- Concurrent requests can pass the pre-check and race into `Attendance::create()`.
- DB unique constraint may catch exact duplicates, but the controller returns a generic exception response in that case.
- `store()` still does not check terminal/inactive student status.
- `store()` still writes legacy `class` string.
- Future conversion to a transactional normalized write policy or guarded correction flow remains advisable.

## Schema / Unique Constraint Findings

Evidence from `database/migrations/2026_01_21_083000_create_attendances_table.php` via `rg`:

- `date` column exists and is not nullable in the migration:
  - `$table->date('date');`
- `period` column exists and is nullable:
  - `$table->string('period')->nullable();`
- indexes include:
  - `['date', 'class']`
  - `['student_id', 'date']`
  - `['teacher_id', 'date']`
  - `period`
- unique constraint exists:
  - `$table->unique(['student_id', 'date', 'period']);`

### Nullable Period Risk

The unique constraint includes nullable `period`.

Risk:

- In MySQL, unique indexes generally allow multiple rows where one indexed column is `NULL`.
- That means multiple rows with the same `student_id` and `date` and `period = NULL` may be allowed by the database, depending on the engine/settings.
- The app-level store duplicate check uses `where('period', null)`, which Laravel generally translates to `IS NULL`; that can catch existing null-period rows before create, but the check is still non-atomic.
- Update has no equivalent duplicate check.

### Identity Policy Scope

The unique policy is `student_id,date,period`.

Not part of duplicate identity:

- `class`
- `subject`
- `session`
- `marked_by`

Implications:

- `subject` is not part of the uniqueness policy, so the schema does not support separate same-period attendance per subject for the same student/date/period.
- `class` is not part of uniqueness, which is good for preventing class drift from creating separate attendance rows, but class remains legacy metadata.
- The current policy appears to treat attendance identity as one record per student/date/period.
- If the school needs per-subject attendance, the current unique key does not match that need.

## Terminal/Inactive Sequencing Recommendation

Terminal/inactive exclusion is still pending.

Current state:

- `store()` validates that `student_id` exists but does not inspect latest `student_statuses`.
- `update()` can no longer change `student_id`, so update-time terminal reassignment risk is lower after Phase 5N.
- Existing rows for terminal/inactive students can still be modified through `update()`.
- New rows for terminal/inactive students can still be created through `store()`.

Sequencing recommendation:

- First block date/period mutation in `update()` because it is a small, local guard that prevents duplicate-conflict corrections from happening through the wrong endpoint.
- Then audit/fix terminal/inactive exclusion for `store()` and any remaining update behavior.
- Terminal/inactive policy should be designed around whether existing historical rows for terminal students may be corrected, while new attendance rows should likely be blocked.

## RED/YELLOW/GREEN Risk Classification

### RED

- API `update()` still allows `date` mutation.
- API `update()` still allows `period` mutation.
- API `update()` has no duplicate pre-check for the resulting `student_id,date,period`.
- API `update()` has no transaction or lock.
- DB unique constraint may not protect null-period duplicates in MySQL the way the application assumes.
- API `store()` duplicate check is non-atomic.

### YELLOW

- API `update()` catches duplicate DB exceptions generically instead of returning controlled `409 Conflict`.
- `subject` is not part of the uniqueness policy even though subject is stored.
- `class` is not part of uniqueness and remains legacy metadata in `store()`.
- API single writes still do not exclude terminal/inactive students.
- API resource route names are generic (`attendance.update`, `attendance.store`) rather than `api.attendance.*`.

### GREEN

- API `bulkMark()` is guarded and returns HTTP `423`.
- API `destroy()` is guarded and returns HTTP `423`.
- API `store()` derives `marked_by` from authenticated user.
- API `update()` cannot mutate `marked_by`.
- API `update()` cannot mutate `student_id`.
- API `update()` cannot mutate legacy `class`.
- `store()` has an application-level duplicate pre-check, even though it is non-atomic.

## Top 10 Date/Period Duplicate Risks

1. API `update()` can change `date` without checking for an existing row with the same `student_id,date,period`.
2. API `update()` can change `period` without checking for an existing row with the same `student_id,date,period`.
3. API `update()` relies on DB constraint failure rather than returning a controlled duplicate conflict response.
4. API `update()` catches DB exceptions generically, so duplicate failures may be exposed as generic failure text.
5. API `update()` does not use a transaction.
6. API `store()` duplicate check is non-atomic and race-prone.
7. Nullable `period` can weaken database uniqueness in MySQL for full-day/no-period attendance.
8. `subject` is stored but not included in duplicate identity, which may conflict with per-subject attendance requirements.
9. Existing terminal/inactive student rows can still be modified.
10. New terminal/inactive student rows can still be created via API `store()`.

## Recommended Phase 5P First Code Task

Recommended option: C - convert update toward a correction workflow later and block identity/date/period now.

Smallest safe Phase 5P implementation:

- Remove `date` from API `update()` validation.
- Remove `period` from API `update()` validation.
- Explicitly unset `date` and `period` before mass assignment.
- Keep `status`, `remarks`, `subject`, and `session` editable.
- Keep `store()` unchanged in Phase 5P.
- Keep API `bulkMark()` and `destroy()` guards unchanged.
- Add isolated tests proving date/period cannot be changed and normal editable fields still work.

Rationale:

- Date/period changes are identity/correction operations, not ordinary field edits.
- Blocking them prevents update-time duplicate conflicts without needing a larger correction workflow yet.
- A proper correction workflow can later handle date/period changes with preflight, duplicate conflict checks, audit reasons, and controlled response semantics.

## Final Confirmation

- No code was modified.
- No routes were modified.
- No controllers/services/models/tests/migrations were modified.
- No database data was touched.
- No API write route was executed.
- No attendance data was marked, created, updated, deleted, seeded, synced, imported, or exported.
- No biometric sync or device command was run.
