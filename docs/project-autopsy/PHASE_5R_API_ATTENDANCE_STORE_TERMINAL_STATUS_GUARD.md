# Phase 5R - API Attendance Store Terminal Status Guard

Date: 2026-06-05

Scope: Add terminal/inactive student rejection to `API\AttendanceController@store()` only.

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
- `docs/project-autopsy/PHASE_5Q_API_ATTENDANCE_STORE_TERMINAL_DUPLICATE_AUDIT.md`
- `docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `docs/project-autopsy/PHASE_5R_API_ATTENDANCE_STORE_TERMINAL_STATUS_GUARD.md`

## Previous Terminal / Inactive Store Risk Summary

Phase 5Q found that API `store()`:

- validated `student_id` only with `exists:students,id`
- did not load the `Student` model
- did not inspect latest `student_statuses`
- did not block `passed_out`, `left_school`, `tc_issued`, or `inactive`
- still had non-atomic duplicate checking
- still accepted caller-supplied legacy `class`

## New API Store Terminal / Inactive Behavior

After validation and before the existing duplicate check, API `store()` now:

- derives `marked_by` from the authenticated API user, preserving the Phase 5L behavior
- loads the student with normal `Student::find($validated['student_id'])`
- returns a controlled `422` response if the student cannot be loaded
- checks the student's latest status row
- returns a controlled `409` response for blocked terminal/inactive statuses
- does not create an attendance row for blocked students

Disabled response for terminal/inactive students:

```text
Attendance cannot be marked for terminal or inactive student.
```

## Latest Status Rule Used

The latest status is selected by highest `student_statuses.id`, matching the preflight service rule:

```php
StudentStatus::where('student_id', $student->id)
    ->orderByDesc('id')
    ->value('status');
```

## Blocked Statuses

API `store()` now blocks:

- `passed_out`
- `left_school`
- `tc_issued`
- `inactive`

## No-Status Student Behavior

Students with no `student_statuses` row are treated as active for now.

The new test `api_store_allows_student_with_no_status` confirms that such students can still be marked in isolated SQLite.

## Soft-Deleted Student Behavior

`Student` uses `SoftDeletes`.

This phase intentionally uses normal `Student::find()`, so soft-deleted students are not returned by default. If table-level validation were ever to pass for a soft-deleted row, the subsequent `Student::find()` eligibility load returns null and API `store()` responds with:

```text
Student is not eligible for attendance marking.
```

Status code: `422`.

## Behavior Intentionally Not Changed

Duplicate behavior was not changed:

- the existing `student_id/date/period` pre-write duplicate check remains
- the duplicate check remains non-atomic
- no transaction, lock, upsert, or duplicate DB exception handling was added
- nullable `period` behavior was not changed

Legacy class behavior was not changed:

- `class` remains required
- `class` remains client-supplied
- class mismatch/canonical class normalization was not added

Marked-by behavior remains:

- API `store()` still derives `marked_by` from `$request->user()->id`
- spoofed request `marked_by` remains ignored

Update/destroy/bulkMark guards remain:

- API `update()` still cannot mutate `marked_by`, `student_id`, `class`, `date`, or `period`
- API `destroy()` still returns HTTP `423`
- API `bulkMark()` still returns HTTP `423`

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`

Coverage:

- `api_store_rejects_passed_out_student`
- `api_store_rejects_left_school_student`
- `api_store_rejects_tc_issued_student`
- `api_store_rejects_inactive_student`
- `api_store_allows_student_with_no_status`
- `api_store_allows_latest_active_status_after_old_inactive_status`
- `api_store_uses_highest_student_status_id_as_latest`
- `api_store_marked_by_guard_still_uses_authenticated_user`
- `api_update_date_period_guard_still_passes`
- `api_bulk_mark_and_destroy_guards_still_return_423`

Updated:

- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`

Update reason:

- Added the minimal isolated `student_statuses` table to the existing SQLite-memory test harness so the Phase 5L marked_by store tests continue to exercise API `store()` after the new Phase 5R status lookup.

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiUpdateDatePeriodGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiDestroyGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content app/Models/Student.php
Get-Content app/Models/StudentStatus.php
Get-Content docs/project-autopsy/PHASE_5Q_API_ATTENDANCE_STORE_TERMINAL_DUPLICATE_AUDIT.md
Get-Content docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php
php -l routes/api.php
php artisan route --path=api/v1/attendance
php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing
php artisan test --filter=AttendanceApiUpdateDatePeriodGuardTest --env=testing
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing
php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is only an Artisan namespace.
- `php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing`: PASS, 10 tests / 26 assertions
- `php artisan test --filter=AttendanceApiUpdateDatePeriodGuardTest --env=testing`: PASS, 6 tests / 20 assertions
- `php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing`: PASS, 6 tests / 13 assertions
- `php artisan test --filter=AttendanceApiDestroyGuardTest --env=testing`: PASS, 5 tests / 10 assertions
- `php artisan test --filter=AttendanceApiBulkMarkGuardTest --env=testing`: PASS, 4 tests / 9 assertions

PHPUnit emitted unrelated existing doc-comment metadata deprecation warnings during targeted test runs. No targeted test failed in the final sequential run.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No database schema was changed.
- No real/local MySQL data was touched.
- No attendance records were written in real data.
- No attendance records were deleted in real data.
- No biometric sync was changed or triggered.
- No biometric device command was run.
- No web attendance behavior was changed.
- No API `update()` behavior was changed in this phase.
- No API `destroy()` guard behavior was changed.
- No API `bulkMark()` guard behavior was changed.
- No API preflight/apply behavior was added.
- No duplicate/race behavior was changed.
- No legacy class normalization was added.

## Remaining Risks

1. API `store()` duplicate check remains non-atomic.
2. Nullable `period` still weakens MySQL unique protection for full-day/no-period attendance.
3. Duplicate DB exceptions are still not returned as controlled `409` responses.
4. API `store()` still requires and trusts caller-supplied legacy `class`.
5. API `store()` still does not validate class against the student's class.
6. API `store()` still does not derive canonical `class_id`.
7. Existing historical attendance for terminal/inactive students remains editable through allowed update fields.
8. API single-write routes still do not use a dedicated FormRequest.
9. API correction workflow for date/period/student/class changes does not exist.
10. Safe API bulk apply contract does not exist.

## Recommended Phase 5S Next Step

Phase 5S should address API `store()` duplicate/race and nullable-period behavior.

Recommended focus:

- convert duplicate failure into a controlled conflict response
- decide whether `period = null` needs an explicit application-level lock, normalized sentinel, or different uniqueness policy
- avoid changing legacy class normalization until the duplicate/race policy is stable
