# Phase 6S - Web Attendance Update Mutation Audit

Date: 2026-06-06

Scope: Read-only audit of web `AttendanceController@update()` mutation behavior before changing web attendance update/correction semantics.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Policies/AttendancePolicy.php`
- `resources/views/attendance/edit.blade.php`
- `resources/views/attendance/show.blade.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `docs/project-autopsy/PHASE_6R_WEB_ATTENDANCE_STORE_CLASS_DERIVATION.md`
- `docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md`
- `docs/project-autopsy/PHASE_6N_API_ATTENDANCE_STORE_CLASS_DERIVATION.md`
- `docs/project-autopsy/PHASE_5N_API_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`
- `docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md`

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/AttendanceController.php
Get-Content -Path resources/views/attendance/edit.blade.php
Get-Content -Path resources/views/attendance/show.blade.php
Get-Content -Path routes/web.php
Get-Content -Path app/Models/Attendance.php
Get-Content -Path app/Models/Student.php
Get-Content -Path app/Services/Attendance/AttendanceClassResolver.php
Get-Content -Path tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php
Get-Content -Path docs/project-autopsy/PHASE_6R_WEB_ATTENDANCE_STORE_CLASS_DERIVATION.md
Get-Content -Path docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md
Get-Content -Path docs/project-autopsy/PHASE_6N_API_ATTENDANCE_STORE_CLASS_DERIVATION.md
Get-Content -Path docs/project-autopsy/PHASE_5N_API_ATTENDANCE_UPDATE_IDENTITY_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md
Get-Content -Path app/Policies/AttendancePolicy.php
rg -n "function update|function edit|attendance\.update|readonly|disabled|hidden|student_id|remarks|subject|status" app/Http/Controllers/AttendanceController.php resources/views/attendance/edit.blade.php resources/views/attendance/show.blade.php routes/web.php
rg -n "name=\"class\"|name=\"date\"|name=\"period\"|name=\"status\"|name=\"subject\"|name=\"remarks\"" resources/views/attendance/edit.blade.php
rg -n "Route::resource\('attendance'|attendance\.edit|attendance\.update|/attendance/\{attendance\}|admin/attendance|Route::resource\('attendance'" routes/web.php
php -l app/Http/Controllers/AttendanceController.php
php -l app/Models/Attendance.php
php -l app/Services/Attendance/AttendanceClassResolver.php
php artisan route:list | Select-String "attendance"
```

Notes:

- One broad `rg` command had a quoting/regex parse error and was rerun with simpler focused patterns.
- `php -l` checks passed for `AttendanceController.php`, `Attendance.php`, and `AttendanceClassResolver.php`.
- No optional live database checks were run. The mutation risks were clear from source inspection, and avoiding local MySQL contact kept the phase tightly read-only.

## Update Method Mutation Findings

`AttendanceController@update(Request $request, Attendance $attendance)` uses route model binding and calls:

```php
$this->authorize('update', $attendance);
```

Validation rules:

```php
'date' => 'required|date',
'class' => 'required|string',
'status' => 'required|in:present,absent,late,half_day',
'subject' => 'required|string',
'period' => 'nullable|string',
'remarks' => 'nullable|string|max:255',
```

Fields written:

```php
$attendance->update([
    'date' => $request->date,
    'class' => $request->class,
    'status' => $request->status,
    'subject' => $request->subject,
    'period' => $request->period,
    'remarks' => $request->remarks,
]);
```

Findings:

- `class` can be mutated server-side.
- `date` can be mutated server-side.
- `period` can be mutated server-side.
- `status` can be mutated server-side.
- `subject` can be mutated server-side.
- `remarks` can be mutated server-side.
- `student_id` is not validated and is not written by the current update method.
- `marked_by`, `ip_address`, `device_info`, and `session` are not written by the current update method.
- No duplicate conflict check exists before update.
- No transaction is used.
- No duplicate-key `QueryException` handling exists.
- The update directly overwrites the existing attendance record; it is not audit-preserving.
- `AttendanceClassResolver` is not used in update.

Because `Attendance::$fillable` includes `date`, `class`, `status`, `remarks`, `period`, and `subject`, these updates are mass-assignable and consistent with the current model.

## Edit View Findings

`resources/views/attendance/edit.blade.php` renders a `PUT` form to:

```blade
route('attendance.update', $attendance)
```

Visible fields:

- `date`
- `class`
- `status`
- student name display
- `subject`
- `period`
- `remarks`

Readonly fields:

- `class` is rendered as `readonly` but still has `name="class"`.
- student name is readonly display only and has no submitted `student_id` field.

Editable fields:

- `date`
- `status`
- `subject`
- `period`
- `remarks`

Hidden/disabled fields:

- No hidden `student_id` was found in the edit form.
- No disabled `class`, `date`, or `period` controls were found.

Findings:

- Readonly `class` is still submitted by the browser.
- A crafted request can mutate `class` because the controller validates and writes it.
- A crafted or normal request can mutate `date` and `period`.
- The UI looks like a simple edit screen, not an explicit audit-preserving correction workflow.
- There is no UI indication that changing date/period/class is a correction with duplicate or audit implications.

## Route / Auth Findings

Route inventory shows both admin-prefixed and unprefixed attendance resources:

- `GET admin/attendance/{attendance}/edit` -> `AttendanceController@edit`
- `PUT|PATCH admin/attendance/{attendance}` -> `AttendanceController@update`
- `GET attendance/{attendance}/edit` -> `AttendanceController@edit`
- `PUT|PATCH attendance/{attendance}` -> `AttendanceController@update`

The unprefixed route cluster is inside an authenticated route group. The admin route cluster is also route-list-visible and dispatches to the same controller method.

Route model binding is used through the `Attendance $attendance` method parameter.

Authorization:

- `edit()` calls `$this->authorize('update', $attendance)` inside a try/catch.
- `update()` calls `$this->authorize('update', $attendance)`.
- `AttendancePolicy::update()` allows admins to update all attendance.
- `AttendancePolicy::update()` also allows teachers to update attendance they marked within 24 hours, based on `marked_by` comparison and `created_at`.

Findings:

- Web update is not admin-only by policy.
- Teacher update access may be possible for recently marked rows if policy/user-role wiring is active.
- Authorization exists, but it controls who may update, not which fields may mutate.

## API Update Safety Comparison

API update hardening already completed:

- Phase 5L blocked `marked_by` mutation.
- Phase 5N blocked `student_id` and `class` mutation.
- Phase 5P blocked `date` and `period` mutation.

API update currently allows normal editable fields such as:

- `status`
- `remarks`
- `subject`
- `session`

Web update is currently less restrictive:

- web update still allows `class`
- web update still allows `date`
- web update still allows `period`
- web update does not expose `student_id` mutation in the audited form/controller
- web update allows `status`, `subject`, and `remarks`

Recommended alignment direction:

- Web update should follow the API's identity/date/period guard posture unless a dedicated attendance correction workflow is explicitly designed.
- `class`, `date`, and `period` should not be ordinary editable fields.
- A future correction workflow should be audit-preserving and duplicate-aware.

## Risk Classification

RED:

- Web update can mutate `class` server-side despite readonly UI.
- Web update can mutate `date` and `period`, which can create duplicate or conflicting attendance identity.
- No duplicate conflict check exists before update.
- Update directly overwrites the row and is not audit-preserving.
- Web update is not admin-only by policy; teachers may update eligible recently marked rows.

YELLOW:

- `subject` can be mutated, but subject is not currently part of uniqueness policy.
- `period` is nullable, so date/period mutation interacts with the known null-period policy risk.
- No transaction or DB duplicate exception handling exists.
- The UI does not clarify correction semantics.
- Destroy remains exposed in web show actions and is outside this phase.

GREEN:

- `student_id` is not accepted by the current web update method.
- `marked_by` is not accepted by the current web update method.
- Web individual store now derives class from student after Phase 6R.
- API update has already been guarded for identity/date/period fields.
- Route model binding and policy authorization exist.

## Safe Implementation Options

### Option A - Block class/date/period mutation now

Remove `class`, `date`, and `period` from web update validation/write payload and keep only normal editable fields:

- `status`
- `subject`
- `remarks`
- `session` if later present in the form/controller

Pros:

- Aligns web update with the already-hardened API update policy.
- Small, focused controller/view/test change.
- Avoids class spoofing and date/period duplicate risk.

Cons:

- If users currently rely on web update as a correction workflow for date/period, that workflow would need a replacement later.

### Option B - Derive class from attendance's student on update, keep date/period mutable

Pros:

- Fixes class spoofing only.
- Leaves date/period correction behavior available.

Cons:

- Date/period duplicate risk remains.
- Still treats update as a correction workflow without audit/duplicate protections.

### Option C - Leave update as correction workflow and add duplicate checks/audit logs

Pros:

- Preserves broad correction capability.
- Can be made safer with explicit policy.

Cons:

- Larger design and implementation phase.
- Needs audit trail, conflict handling, and likely UI changes.

### Option D - Disable identity/date/period edits until correction workflow exists

Pros:

- Strong safety posture.
- Clear separation between ordinary edits and corrections.

Cons:

- Similar to Option A but may require additional UI communication.

### Option E - Add audit-preserving correction workflow later

Pros:

- Best long-term domain model.

Cons:

- Not the smallest safe next task.

## Recommended Phase 6T First Code Task

Phase 6T should block web update from mutating `class`, `date`, and `period`.

Recommended behavior:

- Remove `class`, `date`, and `period` from the server-side update write payload.
- Keep editable:
  - `status`
  - `subject`
  - `remarks`
- Keep `student_id` and `marked_by` unaccepted.
- Do not change destroy/delete behavior in Phase 6T.
- Do not add a correction workflow yet.
- Add focused isolated tests proving crafted requests cannot mutate `class`, `date`, or `period`, while status/subject/remarks still update.

This is the smallest safe step because it aligns web update with the API update guard posture and avoids treating identity/date/period changes as ordinary edits.

## Confirmation

This phase was read-only except for creating this report.

No application code, controllers, routes, models, services, views, tests, migrations, database data, attendance records, exports, imports, seeds, sync jobs, biometric device commands, full test suite, or real/local MySQL data were modified or run.
