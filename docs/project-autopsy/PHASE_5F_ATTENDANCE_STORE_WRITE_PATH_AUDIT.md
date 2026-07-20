# Phase 5F - Attendance Store Write Path Decision Audit

Date: 2026-06-05

Scope: Read-only focused audit of `AttendanceController@store()` and related attendance write paths before changing attendance write behavior.

## Read-Only Confirmation

- No application code, routes, views, controllers, services, tests, migrations, seeders, imports, or sync logic were modified.
- No attendance write route was executed.
- No biometric sync, device command, import, export, migration, seed, or full test suite was run.
- Only this report file was created: `docs/project-autopsy/PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT.md`.
- Database access was limited to read-only schema/count/select checks.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/AttendanceService.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `resources/views/attendance/bulk_mark.blade.php`
- `resources/views/attendance/create.blade.php`
- `resources/views/attendance/preflight-result.blade.php`
- `routes/web.php`
- `routes/api.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md`
- `docs/project-autopsy/PHASE_5B_ATTENDANCE_BULK_PREFLIGHT_SERVICE.md`
- `docs/project-autopsy/PHASE_5D_ATTENDANCE_PREFLIGHT_UI.md`
- `docs/project-autopsy/PHASE_5E_ATTENDANCE_CREATE_READ_ONLY_FIX.md`

## Commands Run

- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Http/Controllers/API/AttendanceController.php`
- `Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `Get-Content app/Services/AttendanceService.php`
- `Get-Content app/Services/Attendance/AttendanceBulkPreflightService.php`
- `Get-Content app/Models/Attendance.php`
- `Get-Content app/Models/Student.php`
- `Get-Content app/Models/StudentStatus.php`
- `Get-Content resources/views/attendance/bulk_mark.blade.php`
- `Get-Content resources/views/attendance/create.blade.php`
- `Get-Content resources/views/attendance/preflight-result.blade.php`
- `Get-Content routes/web.php`
- `Get-Content routes/api.php`
- `Get-Content tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `Get-Content tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `Get-Content tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `Get-Content docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md`
- `Get-Content docs/project-autopsy/PHASE_5B_ATTENDANCE_BULK_PREFLIGHT_SERVICE.md`
- `Get-Content docs/project-autopsy/PHASE_5D_ATTENDANCE_PREFLIGHT_UI.md`
- `Get-Content docs/project-autopsy/PHASE_5E_ATTENDANCE_CREATE_READ_ONLY_FIX.md`
- `Get-Content routes/web.php | Select-Object -Skip 440 -First 30`
- `Get-ChildItem docs/project-autopsy | Select-String "PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT"`
- `rg -n "attendance\.(store|preflight|bulk-mark)|preflight-view|Route::.*attendance|AttendanceController|bulkMark|storeAttendance|markAttendance" routes app resources tests docs/project-autopsy/PHASE_5*.md`
- `rg -n "public function store|filled\('classes'\)|Handle individual|Attendance::insert|function preflight|function preflightView|function isMarked|ensureAllStudentsPresent" app/Http/Controllers/AttendanceController.php app/Models/Attendance.php`
- `rg -n "function store|function bulkMark|Attendance::create|Attendance::insert|where\('student_id'|where\('date'" app/Http/Controllers/API/AttendanceController.php`
- `rg -n "storeAttendance|attendance\.|markAttendance|updateOrCreate|DB::beginTransaction|class_id" app/Http/Controllers/Teacher/TeacherAttendanceController.php app/Services/AttendanceService.php resources/views/attendance/*.blade.php routes/web.php routes/api.php`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l app/Http/Controllers/API/AttendanceController.php`
- `php -l app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `php -l app/Services/AttendanceService.php`
- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php`
- `php artisan route | Select-String "attendance"` (failed because this Laravel app exposes `route:list`, not a bare `route` list command)
- `php artisan route:list | Select-String "attendance"`
- Read-only tinker query for attendance schema/counts/distincts/duplicate groups/terminal rows/class mismatch rows.

## Live Read-Only Counts

From read-only SELECT/schema checks:

- `attendances` columns: `id`, `student_id`, `teacher_id`, `date`, `status`, `remarks`, `period`, `subject`, `class`, `session`, `marked_by`, `updated_by`, `ip_address`, `device_info`, `created_at`, `updated_at`
- Total `attendances` rows: 104
- Duplicate groups by `student_id,date,period`: 0
- Rows linked to latest/any terminal status set checked (`passed_out`, `left_school`, `tc_issued`, `inactive`): 2
- Rows where `attendances.class` differs from `students.class`: 24
- Distinct attendance class values: `Class 1`, `Class 2`, `Class 3`, `Nursery`
- Distinct attendance status values: `present`, `absent`

## Web `AttendanceController@store()` Branch Map

### Branch 1: Bulk `classes[]` + `default_status`

Location: `app/Http/Controllers/AttendanceController.php`, `store()`, bulk branch around line 170.

- Trigger: `$request->filled('classes') && $request->filled('default_status')`
- Expected inputs:
  - `date`
  - `subject`
  - `period` optional
  - `classes[]`
  - `default_status`
- Validation:
  - `date`: required date
  - `subject`: required string
  - `period`: nullable string
  - `classes`: required array
  - `classes.*`: string
  - `default_status`: required in `present,absent,late,half_day`
- Student selection:
  - Loops each legacy class string from `classes[]`.
  - Uses `Student::where('class', $class)->get()`.
- Class identity:
  - Uses legacy `students.class` and writes legacy `attendances.class`.
  - Does not use `class_id` or `section_id`.
- Terminal/inactive exclusion:
  - None.
  - Does not inspect `student_statuses`.
  - Does not apply a terminal/inactive scope.
- Duplicate handling:
  - Checks `Attendance::isMarked($class, $date, $period)` once per class before inserting.
  - `isMarked()` checks existing rows by legacy `class`, `date`, and optionally `period`.
  - Does not pre-check duplicates per student.
  - DB-level unique constraint may still protect exact `student_id,date,period` duplicates, but controller does not catch that specifically.
- Transaction:
  - No explicit DB transaction.
  - One raw insert per class.
  - Multi-class request can partially write: earlier classes may already be inserted if a later class fails.
- Write method:
  - Builds array and calls `Attendance::insert($attendances)`.
- Calls `ensureAllStudentsPresent()`:
  - No.
- Audit/user fields:
  - Writes `marked_by` from `Auth::id()`.
  - Writes `ip_address` from `$request->ip()`.
  - Writes `device_info` from `$request->userAgent()`.
  - Writes `session`, `created_at`, `updated_at`.
  - Does not write `updated_by`.
- DB exception handling:
  - No branch-local try/catch.
- Return:
  - Redirects to `attendance.index` with success message, appending any class-level "already marked" errors.

Classification: RED.

Reason: direct bulk raw insert, legacy class expansion, no terminal exclusion, no transaction around the multi-class batch, no per-student duplicate preflight, and no required preview/approval gate.

### Branch 2: Individual `student_ids` + `statuses`

Location: `app/Http/Controllers/AttendanceController.php`, `store()`, individual branch around line 228.

- Trigger:
  - Fallback when bulk `classes[] + default_status` condition is not met.
- Expected inputs:
  - `class`
  - `date`
  - `subject`
  - `period` optional
  - `student_ids[]`
  - `statuses[]`
  - `remarks[]` optional
- Validation:
  - `class`: required string
  - `date`: required date
  - `subject`: required string
  - `period`: nullable string
  - `student_ids`: required array
  - `statuses`: required array
  - `remarks.*`: nullable string max 255
- Missing validation details:
  - No `student_ids.*` `exists:students,id`.
  - No `statuses.*` `in:present,absent,late,half_day`.
  - No assertion that `statuses` indexes align with `student_ids`.
  - No assertion that each `student_id` belongs to submitted `class`.
- Student selection:
  - Uses submitted `student_ids` directly.
  - The create page populates these from `Student::where('class', $class)`.
- Class identity:
  - Uses and writes legacy `class` string.
  - Does not use `class_id`.
- Terminal/inactive exclusion:
  - None.
- Duplicate handling:
  - Checks `Attendance::isMarked($request->class, $date, $period)` at class level.
  - Does not pre-check duplicates per submitted student.
- Transaction:
  - No explicit transaction.
  - One raw insert for all submitted students.
- Write method:
  - `Attendance::insert($attendances)`.
- Calls `ensureAllStudentsPresent()`:
  - No.
- Audit/user fields:
  - Writes `marked_by`, `ip_address`, `device_info`, `session`, timestamps.
  - Does not write `updated_by`.
- DB exception handling:
  - No branch-local try/catch.
- Return:
  - On class/date/period already marked: `back()` with error.
  - On write success: redirect to `attendance.index` with success.

Classification: YELLOW/RED.

Reason: row set is visible to the user before save, so it is less risky than blind class expansion. However it still raw-inserts, uses legacy class string, lacks row-level validation, has no transaction wrapper, and does not exclude terminal students.

### Branch 3: Fallback

There is no separate fallback write behavior. If the bulk condition is false, the method enters the individual branch and validates for individual inputs. Invalid/missing individual fields fail validation and return Laravel validation errors.

## UI Direct-Write Map

### `resources/views/attendance/bulk_mark.blade.php`

- Primary form action: `route('attendance.store')`.
- Direct write button still exists:
  - Button text: `Mark Attendance`
  - Type: `submit`
  - Uses the form default action, so it posts directly to `attendance.store`.
- Read-only preview button exists:
  - Button text: `Preview`
  - Uses `formaction="{{ route('attendance.preflight-view') }}"` and `formmethod="post"`.
  - Posts same bulk form inputs to the read-only preflight view.
- Inputs:
  - `date`
  - `subject`
  - `period`
  - `classes[]`
  - `default_status`
- Class identity:
  - Uses legacy class strings in `classes[]`.
- Conclusion:
  - Users can still bypass preflight by clicking `Mark Attendance`.

### `resources/views/attendance/create.blade.php`

- Form action: `route('attendance.store')`.
- Direct write button:
  - Button text: `Save Attendance`
  - Posts directly to `attendance.store`.
- Inputs:
  - Hidden `class`
  - Hidden `date`
  - `subject`
  - `period`
  - `student_ids[index]`
  - `statuses[index]`
  - `remarks[index]`
- Per-student marking UI:
  - Radio buttons per student for `present`, `absent`, `late`, `half_day`.
  - Default checked status is `absent`.
- Preview:
  - No preflight button in this per-student view.

### `resources/views/attendance/preflight-result.blade.php`

- Read-only result page.
- Shows summary, create/update/skip lists, errors, and warnings.
- Contains only Back links.
- Does not render Apply, Confirm, Save, or Mark Attendance buttons.

## Preflight Coverage Gap

### What `AttendanceBulkPreflightService` supports

- Input payload:
  - `date`
  - `period`
  - optional `class`
  - optional `class_id`
  - optional `section_id`
  - `attendance_rows[]` containing `student_id`, `status`, optional remarks
- Detects:
  - Missing/invalid date
  - Missing/invalid student id
  - Invalid status
  - Duplicate rows in payload by `student_id|date|period`
  - Existing attendance by `student_id,date,period`
  - Terminal/inactive latest status from `student_statuses`
  - Soft-deleted student warning
  - Payload class/class_id/section_id mismatch
  - Existing attendance legacy class mismatch
- Output:
  - Read-only structured summary and per-row normalized actions: `create`, `update`, `skip`, `error`.

### What store inputs are covered by preflight

- Per-student row shape can be represented as `attendance_rows[]`.
- Bulk class/default-status form can be mapped by `preflightView()` into `attendance_rows[]`.
- `date` and `period` are supported.
- Existing duplicates before store can be detected per student/date/period.
- Terminal/inactive students can be detected after rows are expanded to student ids.

### What store inputs are not fully covered

- `subject` is required by `store()` but not validated or normalized by `AttendanceBulkPreflightService`.
- `marked_by`, `ip_address`, `device_info`, `session`, and timestamp write metadata are not represented in preflight output.
- Preflight returns `update` for existing rows, but current web `store()` only inserts and refuses/blocks by class-level `isMarked()`; there is no safe apply path matching preflight's `update` recommendation.
- Individual `create.blade.php` does not currently post to preflight.
- Bulk `classes[]` are not natively expanded inside the service; expansion happens in `AttendanceController@preflightView()`.

### Legacy expansion risk

- `preflightView()` expands `classes[]` via `Student::where('class', $cls)->get()`.
- This matches current bulk `store()` behavior, which is useful for previewing the same risky selection logic.
- It does not solve canonical `class_id` drift and may omit students whose canonical `class_id` is correct but legacy `class` string is stale.

## API Attendance Write Findings

### API `store()`

Location: `app/Http/Controllers/API/AttendanceController.php`, around line 28.

- Validates:
  - `student_id` exists
  - `date`
  - `status`
  - `remarks`
  - `period`
  - `subject`
  - `class`
  - `session`
  - `marked_by` exists
- Duplicate handling:
  - Checks existing row by `student_id`, `date`, and `period`.
  - Uses `Attendance::create($validated)` after the existence check.
- Transaction:
  - None.
- Terminal/inactive exclusion:
  - None.
- Class identity:
  - Requires legacy `class` string.
  - Does not use `class_id`.
- Exception handling:
  - Catches generic `Exception` and returns error response.
- Risk:
  - YELLOW. Single-row duplicate check exists, but race conditions remain and terminal/class drift risks remain.

### API `bulkMark()`

Location: `app/Http/Controllers/API/AttendanceController.php`, around line 145.

- Validates:
  - `date`
  - `class`
  - `subject`
  - `period`
  - `student_ids[]`
  - `statuses[]`
  - `marked_by`
- Missing/weak validation:
  - No `remarks.*` validation despite reading remarks.
  - No index alignment validation between `student_ids` and `statuses`.
  - No class membership validation for each student id.
- Duplicate handling:
  - None before insert.
  - Relies on DB unique constraint to reject exact duplicates.
- Transaction:
  - None.
- Terminal/inactive exclusion:
  - None.
- Write method:
  - `Attendance::insert($attendances)`.
- Exception handling:
  - Catches generic `Exception` and returns error response.
- Risk:
  - RED. API bulk-mark still has duplicate risk, terminal inclusion risk, and direct raw insert behavior.

Recommendation for API sequencing:

- Web bulk store is the first user-facing bypass to guard because the UI now presents a Preview path next to an unsafe direct write path.
- API bulk-mark should be guarded soon after web bulk store, because it has the same or greater duplicate risk and no preview concept.

## Teacher Attendance Write Findings

### `TeacherAttendanceController@storeAttendance()`

- Uses `class_id` in request validation: `required|exists:school_classes,id`.
- Builds attendance rows with:
  - `student_id`
  - `date`
  - `status`
  - `remarks`
  - `class_id`
- Accepted statuses:
  - `present`, `absent`, `leave`
- Risk:
  - Status vocabulary differs from `attendances.status`, which uses `present`, `absent`, `late`, `half_day` elsewhere. `leave` is not in the main Attendance model's documented status set and may conflict with database enum constraints depending on active schema.

### `AttendanceService::markAttendance()`

- Wraps writes in `DB::beginTransaction()` / `commit()` / `rollback()`.
- Uses `Attendance::updateOrCreate()` with lookup:
  - `student_id`
  - `date`
- Updates:
  - `status`
  - `remarks`
  - `marked_by`
  - `class_id`
- Duplicate safety:
  - Better than raw insert because it uses update-or-create and transaction.
  - However lookup does not include `period`, so it may collapse period-wise attendance if teacher flow later supports periods.
- Schema mismatch:
  - Live `attendances` columns do not include `class_id`.
  - `Attendance::$fillable` also does not include `class_id`.
  - Service is writing `class_id` to `Attendance`, not to `student_attendance`.
  - This is a schema/path mismatch around canonical class writes.
- Table target:
  - Writes to `attendances` through `App\Models\Attendance`.
  - Does not write to `student_attendance`.
- Terminal/inactive exclusion:
  - None in controller or service.
- Recommendation:
  - Treat teacher path as Phase 5G+ or later unless changing it is unavoidable.
  - It is more transactional than web/API bulk, but has schema/status vocabulary mismatch risks that need their own focused fix.

## Risk Classification

### RED

- Web bulk `classes[] + default_status` path: direct raw insert, no mandatory preflight/approval, legacy class expansion, no transaction around multi-class loop.
- Web bulk UI: route can write without explicit preview because `Mark Attendance` still posts to `attendance.store`.
- API `bulkMark()`: raw insert with no duplicate pre-check, no terminal exclusion, no transaction.
- Terminal/inactive students can be included in web/API/teacher writes; read-only DB check found 2 attendance rows linked to terminal/inactive statuses checked.
- Teacher service attempts to write `class_id` to `attendances` even though live schema lacks `class_id`.

### YELLOW

- Web individual `student_ids/statuses` branch: direct raw insert, no row-level `exists`/status validation, no terminal exclusion, legacy class string.
- API `store()`: duplicate check exists, but no transaction/upsert, no terminal exclusion, legacy class string.
- `Attendance::isMarked()` checks by legacy class string rather than canonical class id.
- Preflight expansion currently mirrors legacy class string behavior.
- Duplicate risk is handled partly by DB unique constraint instead of a controller/service-level safe apply path.
- No FormRequest for attendance store payloads.
- Teacher path uses `updateOrCreate`, but status vocabulary and schema alignment are weak.

### GREEN

- `AttendanceController@create()` is now read-only and no longer calls `ensureAllStudentsPresent()`.
- `preflight()` JSON endpoint is read-only.
- `preflightView()` is read-only and renders no apply/confirm button.
- `AttendanceBulkPreflightService` can detect duplicate payload rows, existing attendance, terminal/inactive status, and class/section mismatch without writing.
- Teacher service uses transaction and `updateOrCreate`, although schema mismatch keeps the overall path out of fully green status.

## Top 10 Attendance Write Risks

1. Web bulk `Mark Attendance` can still bypass preflight and write directly.
2. Web bulk store uses `Attendance::insert()` with no transaction around the multi-class request.
3. Web bulk store expands legacy `classes[]` with `Student::where('class', ...)`, not canonical `class_id`.
4. Terminal/inactive students are not excluded from web/API/teacher writes.
5. API bulk-mark uses raw `Attendance::insert()` with no duplicate pre-check.
6. Individual web store lacks `student_ids.*` existence validation and `statuses.*` allowed-value validation.
7. `Attendance::isMarked()` is class-level and legacy-string based, not row-level canonical duplicate protection.
8. Preflight can recommend `update`, but current store paths only insert and have no safe apply path.
9. Teacher attendance service writes `class_id` to the `attendances` model/table despite live schema lacking `class_id`.
10. Teacher path accepts `leave`, while main attendance statuses elsewhere use `present`, `absent`, `late`, `half_day`.

## Phase 5G Decision

Recommended safest first code task: Option B, with a narrow scope.

Phase 5G should guard only the web bulk `classes[] + default_status` branch by requiring a session-backed preflight approval token before allowing the direct bulk store write. If the token is missing or stale, redirect the user to the read-only preflight flow and do not write. Leave the existing individual/per-student store behavior untouched in the first guard.

Rationale:

- It closes the current UI bypass where the direct `Mark Attendance` button can write without preview.
- It targets the riskiest web branch without entangling the per-student branch.
- It builds on the existing read-only preflight UI and service.
- It avoids redesigning the full apply/upsert behavior before policy decisions are made.
- It can be tested narrowly: bulk post without token must not write; preview creates token; matching token allows current behavior or a later transactional apply.

Fallback if session token work is too entangled:

- Option D: disable/remove the direct bulk `Mark Attendance` button and leave only `Preview` until a safe apply path exists.

Not recommended as first Phase 5G task:

- Option C is directionally best long-term, but converting store to transactional preflight-backed upsert changes write semantics and should come after the guard.
- Option E should follow web guard. API bulk-mark is risky, but the immediate user-visible bypass exists in the bulk web UI.

## Final Confirmation

- Web bulk store can still bypass preflight: YES.
- Direct `Mark Attendance` button still exists in `bulk_mark.blade.php`: YES.
- API bulk-mark still has duplicate risk: YES.
- No application code was modified.
- No attendance database data was created, updated, deleted, seeded, imported, or synced.
- No biometric device sync or device command was run.
