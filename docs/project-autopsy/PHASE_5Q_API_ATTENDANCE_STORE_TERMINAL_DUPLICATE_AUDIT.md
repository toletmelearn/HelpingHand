# Phase 5Q - API Attendance Store Terminal / Duplicate Audit

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md`
- `docs/project-autopsy/PHASE_5O_API_ATTENDANCE_DATE_PERIOD_DUPLICATE_AUDIT.md`
- `docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md`
- Attendance migrations found by read-only `rg` search

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content routes/api.php
Get-Content app/Models/Attendance.php
Get-Content app/Models/Student.php
Get-Content app/Models/StudentStatus.php
Get-Content app/Services/Attendance/AttendanceBulkPreflightService.php
Get-Content tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
Get-Content docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md
Get-Content docs/project-autopsy/PHASE_5O_API_ATTENDANCE_DATE_PERIOD_DUPLICATE_AUDIT.md
rg -n "public function store|student_id|student_statuses|passed_out|left_school|tc_issued|inactive|where\('period'|unique\(\['student_id', 'date', 'period'\]|class_id|canonicalClassId|payload_legacy_class_mismatch|existing_attendance" app/Http/Controllers/API/AttendanceController.php app/Services/Attendance/AttendanceBulkPreflightService.php app/Models/Student.php app/Models/Attendance.php database/migrations
php -l app/Http/Controllers/API/AttendanceController.php
php -l routes/api.php
php -l app/Services/Attendance/AttendanceBulkPreflightService.php
php artisan route --path=api/v1/attendance
```

Command results:

- `php -l app/Http/Controllers/API/AttendanceController.php`: no syntax errors detected.
- `php -l routes/api.php`: no syntax errors detected.
- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php`: no syntax errors detected.
- `php artisan route --path=api/v1/attendance`: did not list routes because this Laravel application exposes `route` as an Artisan namespace rather than a route-list command alias. No alternate route-list command was run because this was a read-only audit with a fixed safe-command list.

## API Store Terminal / Inactive Findings

`API\AttendanceController@store()` validates `student_id` using:

```php
'student_id' => 'required|exists:students,id'
```

Findings:

- The student row is validated for existence, but the `Student` model is not loaded by `store()`.
- No latest `student_statuses` row is checked before writing attendance.
- The Phase 5B/5D terminal-status rule, based on the highest `student_statuses.id`, is not applied in API `store()`.
- `passed_out`, `left_school`, `tc_issued`, and `inactive` are not blocked.
- Students with no status are not explicitly treated as active; the API store path simply does not inspect statuses.
- Because `Student` uses `SoftDeletes` and the validator uses a table-level `exists:students,id` rule, soft-deleted student rows may pass validation if still present in the database. This should be verified in a targeted future test before changing behavior.
- Existing historical attendance for terminal or inactive students should remain readable. New writes for terminal/inactive students should be blocked before insert.

Current state: API `store()` does not block terminal/inactive students today.

## API Store Duplicate Findings

`store()` performs a pre-write duplicate check:

```php
Attendance::where('student_id', $validated['student_id'])
    ->where('date', $validated['date'])
    ->where('period', $validated['period'] ?? null)
    ->exists()
```

Findings:

- Duplicate check fields are `student_id`, `date`, and `period`.
- The check is non-atomic. A concurrent request can pass the `exists()` check and then race into `Attendance::create()`.
- There is no transaction, lock, `updateOrCreate`, `upsert`, or controlled insert exception path.
- The attendance migration includes a unique constraint on `student_id`, `date`, and `period`.
- `period` is nullable. In MySQL, nullable columns inside a unique index can allow multiple rows with `NULL` period for the same student/date, weakening the DB-level duplicate guarantee for full-day/no-period attendance.
- Laravel's `where('period', null)` should compile to `IS NULL`, so the application-level check can detect existing null-period rows for a single request, but it still does not solve race conditions.
- Duplicate DB exceptions are not converted into a controlled `409` response. They fall into the generic exception handler and return a general failure response.
- `subject` is not part of the duplicate identity. Current policy appears to be one attendance row per `student_id/date/period`, regardless of subject.
- `class` is not part of the duplicate identity, which is consistent with using student/date/period as the unique attendance event, but it increases the importance of deriving class correctly.

Current state: API `store()` has a duplicate precheck, but it is not atomic.

## API Store Legacy Class Findings

`store()` requires:

```php
'class' => 'required|string|max:50'
```

Findings:

- `class` is required.
- `class` is client-supplied.
- Submitted `class` is not checked against `students.class`.
- Submitted `class` is not checked against canonical `class_id`.
- `Student` has `class_id`, `school_class_id`, and canonical class helper methods such as `canonicalClassId()`.
- `Attendance` still stores legacy `class` and does not expose a `class_id` fillable field.
- A future phase should derive class data from the student record or canonical class relationship rather than trusting the API caller.
- A future mismatch policy should reject or flag submitted class values that disagree with the student's current class.

Current state: API `store()` still accepts client-supplied legacy class.

## AttendanceBulkPreflightService Reuse Findings

`AttendanceBulkPreflightService` already provides read-only checks that overlap with API `store()` risks:

- It detects latest terminal/inactive status using the highest `student_statuses.id`.
- It treats `passed_out`, `left_school`, `tc_issued`, and `inactive` as terminal or skipped statuses.
- It detects existing attendance duplicates by `student_id`, `date`, and `period`.
- It handles `period === null` with `whereNull('period')`.
- It detects duplicate rows within the submitted payload.
- It detects legacy class mismatches.
- It supports single-row payloads through `attendance_rows`, so it could technically preflight one API store request.

Reuse caveats:

- The service currently returns a bulk preflight result shape, not the existing single-store API response shape.
- Terminal/inactive findings are represented as warnings with `action = skip`, not necessarily as hard API errors.
- Using the service directly in API `store()` could be safe later, but Phase 5R can be smaller by adding only terminal/inactive rejection in `store()`.
- A later duplicate/race phase could reuse the service for preflight visibility, but still needs an atomic write policy.

## Live Read-Only Counts

No live database checks were run in this phase.

Reason: the audit evidence needed for Phase 5Q was available from code and migrations, and the task prohibited touching real/local MySQL except optional read-only checks.

## RED / YELLOW / GREEN Risk Classification

RED:

- API `store()` can create attendance for terminal/inactive students.
- API `store()` duplicate protection is non-atomic.
- Nullable `period` weakens DB unique protection in MySQL for full-day/no-period attendance.
- Duplicate DB exceptions are not caught as controlled `409` responses.
- API `store()` trusts caller-supplied legacy `class`.

YELLOW:

- `student_id` validation checks existence but does not load the student or apply attendance eligibility rules.
- Soft-deleted students may pass table-level `exists` validation.
- No FormRequest isolates API attendance store rules.
- `subject` is not part of uniqueness; this may be correct, but the policy should be explicit.
- `class_id`/canonical class is available on `Student` but not used by `Attendance`.

GREEN:

- API `bulkMark()` is guarded with HTTP `423`.
- API `destroy()` is guarded with HTTP `423`.
- API `store()` derives `marked_by` from the authenticated API user.
- API `update()` no longer mutates `marked_by`, `student_id`, `class`, `date`, or `period`.
- API `store()` has at least a pre-write duplicate check, even though it is not atomic.

## Top 10 API Store Risks

1. Terminal/inactive students can still receive new API attendance rows.
2. Passed-out, left-school, TC-issued, and inactive statuses are not checked.
3. Duplicate check is non-atomic and can race under concurrent API requests.
4. Nullable `period` can allow duplicate full-day rows despite a MySQL unique index.
5. Duplicate DB exceptions are not returned as controlled conflict responses.
6. API caller can submit arbitrary legacy `class`.
7. Submitted `class` is not verified against the student's class.
8. Canonical `class_id` exists on `Student` but is not used for attendance writes.
9. Soft-deleted students may pass `exists:students,id` validation.
10. The current single-write API path does not reuse the richer preflight checks.

## Recommended Phase 5R First Code Task

Phase 5R should add terminal/inactive rejection to `API\AttendanceController@store()` only.

Recommended behavior:

- After validation and before duplicate check or `Attendance::create()`, load the target student/status eligibility.
- Check the latest `student_statuses` row by highest `id`, matching `AttendanceBulkPreflightService`.
- Reject `passed_out`, `left_school`, `tc_issued`, and `inactive`.
- Treat students with no status as active for now.
- Return a controlled API error response.
- Do not change duplicate semantics in Phase 5R.
- Do not normalize legacy class in Phase 5R.

Suggested sequencing:

- Phase 5R: terminal/inactive rejection in API `store()`.
- Phase 5S: duplicate/race/null-period policy and controlled duplicate exception handling.
- Phase 5T: legacy class normalization or class mismatch rejection.

## Confirmation

This phase was read-only except for creating this report.

No application code, routes, controllers, services, models, tests, migrations, database data, attendance records, imports, seeds, sync jobs, or biometric device commands were modified or run.
