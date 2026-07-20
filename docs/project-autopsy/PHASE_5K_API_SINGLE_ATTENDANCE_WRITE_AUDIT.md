# Phase 5K - API Single Attendance Write Audit

Date: 2026-06-05

Scope: Read-only audit of API single attendance write behavior after Phase 5J guarded API `bulkMark()`.

## Read-Only Confirmation

- No application code was modified.
- No routes were modified.
- No controllers, services, models, tests, or migrations were modified.
- No API attendance write route was executed.
- No attendance records were created, updated, deleted, marked, seeded, imported, exported, synced, or otherwise mutated.
- No biometric sync or device command was run.
- No migrations, database setup, composer setup, or full test suite were run.
- No real/local MySQL data was touched.
- Only this report file was created: `docs/project-autopsy/PHASE_5K_API_SINGLE_ATTENDANCE_WRITE_AUDIT.md`.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Http/Middleware/ApiAccessControl.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Models/User.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `docs/project-autopsy/PHASE_5H_ATTENDANCE_ROUTE_API_WRITE_AUDIT.md`
- `docs/project-autopsy/PHASE_5J_API_ATTENDANCE_BULK_MARK_GUARD.md`
- `docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md`

## Commands Run

```powershell
Get-Content routes/api.php
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content app/Http/Middleware/ApiAccessControl.php
Get-Content app/Models/Attendance.php
Get-Content app/Models/Student.php
Get-Content app/Models/StudentStatus.php
Get-Content app/Models/User.php
Get-Content app/Services/Attendance/AttendanceBulkPreflightService.php
Get-Content tests/Feature/Attendance/AttendanceApiBulkMarkGuardTest.php
Get-Content tests/Unit/Services/AttendanceBulkPreflightServiceTest.php
Get-Content docs/project-autopsy/PHASE_5H_ATTENDANCE_ROUTE_API_WRITE_AUDIT.md
Get-Content docs/project-autopsy/PHASE_5J_API_ATTENDANCE_BULK_MARK_GUARD.md
Get-Content docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md
rg -n "Route::apiResource\('attendance'|attendance/bulk-mark|public function store|public function update|public function destroy|marked_by|Attendance::create|->update\(|->delete\(|highRiskBlocklist|isAdmin|tokenAllows|studentSelfRoutes|teacherSelfRoutes|parentBlockedRoutes" routes/api.php app/Http/Controllers/API/AttendanceController.php app/Http/Middleware/ApiAccessControl.php app/Models/Attendance.php app/Services/Attendance/AttendanceBulkPreflightService.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l app/Http/Middleware/ApiAccessControl.php
php -l app/Services/Attendance/AttendanceBulkPreflightService.php
php artisan route --path=api/v1/attendance
```

Command results:

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l app/Http/Middleware/ApiAccessControl.php`: PASS
- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is a namespace, not a list command.

No live DB checks were run in this phase.

## API Route Map

All inspected API attendance routes are declared inside:

- Prefix: `/api/v1`
- Middleware: `auth:sanctum`, `throttle:60,1`, `App\Http\Middleware\ApiAccessControl`
- Route source: `Route::apiResource('attendance', AttendanceController::class)` plus custom attendance report/bulk routes

| Method | URI | Route name | Controller method | Auth guard / middleware | Current state | Risk |
| --- | --- | --- | --- | --- | --- | --- |
| POST | `/api/v1/attendance` | `attendance.store` | `API\AttendanceController@store` | `auth:sanctum`, throttle, `ApiAccessControl` | Write-enabled for admin mobile tokens | RED |
| PUT/PATCH | `/api/v1/attendance/{attendance}` | `attendance.update` | `API\AttendanceController@update` | `auth:sanctum`, throttle, `ApiAccessControl` | Write-enabled for admin mobile tokens | RED |
| DELETE | `/api/v1/attendance/{attendance}` | `attendance.destroy` | `API\AttendanceController@destroy` | `auth:sanctum`, throttle, `ApiAccessControl` | Delete-enabled for admin mobile tokens | RED |
| POST | `/api/v1/attendance/bulk-mark` | `api.attendance.bulk-mark` | `API\AttendanceController@bulkMark` | `auth:sanctum`, throttle, `ApiAccessControl` | Guarded by Phase 5J, returns HTTP 423 before validation/write | GREEN |

Route-name note:

- `apiResource('attendance', ...)` generates generic names such as `attendance.store`, `attendance.update`, and `attendance.destroy`, not `api.attendance.store`.
- This is ambiguous when compared with web attendance resource names and increases review risk.

## API `store()` Findings

Location: `app/Http/Controllers/API/AttendanceController.php`

### Input Fields

`store()` accepts:

- `student_id`
- `date`
- `status`
- `remarks`
- `period`
- `subject`
- `class`
- `session`
- `marked_by`

### Validation Rules

```php
'student_id' => 'required|exists:students,id',
'date' => 'required|date',
'status' => 'required|in:present,absent,late,half_day',
'remarks' => 'nullable|string|max:255',
'period' => 'nullable|string|max:50',
'subject' => 'nullable|string|max:100',
'class' => 'required|string|max:50',
'session' => 'nullable|string|max:20',
'marked_by' => 'required|exists:users,id'
```

### Findings

- `marked_by` is accepted from the request.
- `marked_by` is not derived from the authenticated Sanctum user.
- A caller with access to this route can submit another existing user id as `marked_by`.
- `student_id` existence is validated.
- Student terminal/inactive status is not checked.
- `StudentStatus` is not queried.
- Soft-deleted students are not explicitly filtered in the controller beyond the behavior of `exists:students,id`.
- Duplicate check exists before create:
  - `student_id`
  - `date`
  - `period`
- Duplicate response is controlled HTTP `409` with message:
  - `Attendance already marked for this student on this date and period.`
- Duplicate check is non-atomic.
- No transaction, lock, or atomic upsert is used.
- A race condition exists if concurrent requests pass the existence check before `Attendance::create()`.
- The controller may rely on the DB unique constraint indirectly if a race occurs, but the catch block returns a generic failure message.
- The route writes legacy `class` string.
- No canonical `class_id` is accepted or written by this controller path.
- Student class membership is not checked against the submitted legacy `class`.
- `Attendance::create($validated)` mass-assigns the validated payload.
- DB exceptions are caught as generic `\Exception` and returned through the API error helper.

Risk classification: RED.

## API `update()` Findings

Location: `app/Http/Controllers/API/AttendanceController.php`

### Updatable Fields

`update()` allows:

- `student_id`
- `date`
- `status`
- `remarks`
- `period`
- `subject`
- `class`
- `session`
- `marked_by`

### Validation Rules

```php
'student_id' => 'sometimes|required|exists:students,id',
'date' => 'sometimes|required|date',
'status' => 'sometimes|required|in:present,absent,late,half_day',
'remarks' => 'nullable|string|max:255',
'period' => 'nullable|string|max:50',
'subject' => 'nullable|string|max:100',
'class' => 'sometimes|required|string|max:50',
'session' => 'nullable|string|max:20',
'marked_by' => 'sometimes|required|exists:users,id'
```

### Findings

- `marked_by` can be changed by the API caller.
- `student_id` can be changed by the API caller.
- `class` can be changed by the API caller.
- `date` and `period` can also be changed.
- No terminal/inactive status check is performed when changing `student_id`.
- No class membership check is performed when changing `student_id` or `class`.
- No duplicate conflict pre-check is performed before update.
- Updating `student_id`, `date`, or `period` can create a duplicate conflict with another attendance row.
- No transaction is used.
- No row ownership or per-record authorization is checked in the controller beyond route middleware.
- No `updated_by` field is used.
- The method does not distinguish identity fields from editable attendance fields.
- Response is success with updated model if update succeeds.
- DB/model exceptions are caught and returned as generic API errors.

Risk classification: RED.

## API `destroy()` Findings

Location: `app/Http/Controllers/API/AttendanceController.php`

### Behavior

```php
$attendance = Attendance::findOrFail($id);
$attendance->delete();
return $this->success(null, 'Attendance record deleted successfully');
```

### Findings

- `Attendance` model does not use `SoftDeletes`.
- `destroy()` therefore performs a hard delete from the `attendances` table.
- No controller-level authorization is checked beyond `auth:sanctum` and `ApiAccessControl`.
- No audit-preserving status, void, or reversal mechanism is used.
- No `deleted_by` or reason field is captured.
- Hard delete is dangerous for attendance audit trail integrity.
- `ApiAccessControl` blocks non-admin access to `attendance.destroy`, but admin mobile tokens are allowed before the high-risk blocklist is evaluated.
- Parent/student/teacher roles should not reach this route through `ApiAccessControl`, but admin tokens can.
- The route should be guarded or replaced with an audit-preserving workflow before production use.

Risk classification: RED.

## ApiAccessControl Findings

Location: `app/Http/Middleware/ApiAccessControl.php`

### Middleware Order and Admin Behavior

The protected API route group uses:

- `auth:sanctum`
- `throttle:60,1`
- `ApiAccessControl`

Inside `authorizeRequest()`:

```php
if ($this->isAdmin($user) && $this->tokenAllows($user, 'mobile:admin')) {
    return true;
}
```

This admin allow happens before:

```php
if (in_array($routeName, $this->highRiskBlocklist(), true)) {
    return false;
}
```

Findings:

- Admin users with `mobile:admin` token ability are broadly allowed.
- The high-risk blocklist is primarily effective for non-admin users because admin is allowed first.
- Generic attendance write route names are present in `highRiskBlocklist`:
  - `attendance.index`
  - `attendance.store`
  - `attendance.show`
  - `attendance.update`
  - `attendance.destroy`
- `api.attendance.bulk-mark` is explicitly in `highRiskBlocklist`.
- Phase 5J also guards `bulkMark()` in the controller, so even admin access receives HTTP 423 if the method is reached.
- Student self routes include read-only attendance report routes, not generic attendance writes.
- Teacher self routes do not include generic attendance writes.
- Parent blocked routes do not explicitly mention attendance writes, but parent users are not otherwise allowed through the generic attendance write names.
- Ability checks apply through `currentAccessToken()->can(...)`.
- Route names from `apiResource('attendance', ...)` are generic and not prefixed with `api.`, which makes it easier to confuse API and web attendance route names during review.

Conclusion:

- Non-admin API users appear blocked from `attendance.store`, `attendance.update`, and `attendance.destroy`.
- Admin API users with `mobile:admin` can still access single attendance write/delete routes.
- Admin-token exposure is enough for a RED classification because `store()`, `update()`, and `destroy()` still allow spoofing, broad mutation, and hard delete.

## Live Read-Only Counts

No live read-only DB counts were checked in Phase 5K.

Prior Phase 5A read-only observations remain relevant but were not refreshed:

- Duplicate attendance groups by `student_id,date,period`: `0`
- Attendance rows where `attendances.class <> students.class`: `24`
- Attendance rows for students with `student_statuses.status = 'passed_out'`: `1`
- Distinct attendance status values observed then: `present`, `absent`

## RED/YELLOW/GREEN Risk Classification

### RED

- API `store()` allows `marked_by` spoofing.
- API `update()` allows changing `marked_by`.
- API `update()` allows changing `student_id`.
- API `update()` allows changing legacy `class`.
- API `destroy()` hard-deletes attendance records.
- Terminal/inactive students can still be written through API single `store()` and potentially reassigned through `update()`.
- API `store()` duplicate check is non-atomic.
- API `update()` can create duplicate conflicts without pre-check.
- Admin mobile tokens can access single write/delete routes before high-risk blocklist denial.

### YELLOW

- API `store()` and `update()` use legacy `class` string instead of canonical `class_id`.
- No FormRequest is used for API attendance writes.
- API resource route names are generic (`attendance.store`, `attendance.update`, `attendance.destroy`) instead of `api.attendance.*`.
- `store()` catches DB exceptions generically.
- `update()` mutates too many fields in one path.

### GREEN

- API `bulkMark()` is guarded by Phase 5J and returns HTTP 423 before validation or insert.
- API attendance routes are protected by `auth:sanctum`.
- API routes are throttled.
- `ApiAccessControl` blocks generic attendance write routes for non-admin users.
- `store()` has a duplicate pre-check, even though it is non-atomic.

## Top 10 API Single Attendance Write Risks

1. `store()` accepts caller-supplied `marked_by`, allowing marker identity spoofing.
2. `update()` accepts caller-supplied `marked_by`, allowing historical marker identity changes.
3. `update()` accepts `student_id`, allowing an existing attendance row to be reassigned to another student.
4. `update()` accepts legacy `class`, allowing class drift or false class attribution.
5. `destroy()` performs hard delete because `Attendance` does not use `SoftDeletes`.
6. `store()` does not exclude terminal/inactive students.
7. `update()` does not check terminal/inactive status when `student_id` changes.
8. `store()` duplicate check is non-atomic and race-prone.
9. `update()` can create duplicate `student_id,date,period` conflicts without a pre-check.
10. Admin mobile tokens bypass the high-risk blocklist before single attendance write/delete route denial.

## Recommended Phase 5L First Code Task

Recommended first implementation: Option A - make API single attendance writes derive `marked_by` from the authenticated user and ignore request-supplied `marked_by`.

Scope for Phase 5L:

- In API `store()`, remove `marked_by` from client-controlled validation requirements.
- Set `marked_by` from `$request->user()->id`.
- In API `update()`, prevent `marked_by` from being client-mutated.
- If marker changes need to be tracked later, add a separate safe policy for `updated_by`; do not overload `marked_by`.
- Preserve API `bulkMark()` guard.
- Do not change API `destroy()` yet in 5L unless explicitly scoped.

Rationale:

- This closes the clearest identity spoofing risk with the smallest behavior change.
- It does not require schema changes.
- It does not require a new preflight/apply contract.
- It keeps API single writes operational for admin users while preventing false attribution.

Recommended sequence after 5L:

- Phase 5M: guard API `destroy()` with a controlled disabled response until an audit-preserving delete/void policy exists.
- Phase 5N: add terminal/inactive student exclusion and safer duplicate/upsert policy for API single `store()` and `update()`.
- Later: normalize API attendance writes away from legacy `class` string toward canonical class identity.

## Final Confirmation

- No code was modified.
- No routes were modified.
- No controllers/services/models/tests/migrations were modified.
- No database data was touched.
- No API write route was executed.
- No attendance data was marked, created, updated, deleted, seeded, synced, imported, or exported.
- No biometric sync or device command was run.
