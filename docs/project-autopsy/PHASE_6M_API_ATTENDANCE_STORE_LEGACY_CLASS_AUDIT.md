# Phase 6M - API Attendance Store Legacy Class Validation Audit

Date: 2026-06-06

Scope: Read-only audit of API `AttendanceController@store()` legacy class validation and canonical student class compatibility before changing attendance write behavior.

## Files Inspected

- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `routes/api.php`
- `tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiPeriodDisplayResponseTest.php`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`
- `docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md`
- `docs/project-autopsy/PHASE_5Q_API_ATTENDANCE_STORE_TERMINAL_DUPLICATE_AUDIT.md`
- `docs/project-autopsy/PHASE_5R_API_ATTENDANCE_STORE_TERMINAL_STATUS_GUARD.md`
- `docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md`
- `docs/project-autopsy/PHASE_6A_API_ATTENDANCE_PERIOD_DISPLAY_FIELD.md`

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/API/AttendanceController.php
Get-Content -Path app/Http/Controllers/API/BaseApiController.php
Get-Content -Path app/Models/Attendance.php
Get-Content -Path app/Models/Student.php
Get-Content -Path app/Models/SchoolClass.php
Get-Content -Path app/Models/Section.php
Get-Content -Path app/Services/Attendance/AttendanceBulkPreflightService.php
Get-Content -Path routes/api.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiPeriodDisplayResponseTest.php
Get-Content -Path docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md
Get-Content -Path docs/project-autopsy/PHASE_3E_ADMIN_STUDENT_CLASS_NORMALIZATION.md
Get-Content -Path docs/project-autopsy/PHASE_5Q_API_ATTENDANCE_STORE_TERMINAL_DUPLICATE_AUDIT.md
Get-Content -Path docs/project-autopsy/PHASE_5R_API_ATTENDANCE_STORE_TERMINAL_STATUS_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_6A_API_ATTENDANCE_PERIOD_DISPLAY_FIELD.md
rg -n "function store|class'|marked_by|transformAttendanceForApi|isDuplicateAttendanceException|resolveCanonicalSchoolClass|canonicalClassId|payload_legacy_class_mismatch|payload_class_id_mismatch" app/Http/Controllers/API/AttendanceController.php app/Models/Student.php app/Services/Attendance/AttendanceBulkPreflightService.php app/Models/Attendance.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l app/Models/Student.php
php -l app/Services/Attendance/AttendanceBulkPreflightService.php
php artisan route --path=api/v1/attendance
```

Command results:

- `php -l app/Http/Controllers/API/AttendanceController.php`: passed.
- `php -l app/Models/Student.php`: passed.
- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php`: passed.
- `php artisan route --path=api/v1/attendance`: did not list routes because this Laravel app exposes `route:list`; `route` is only an Artisan namespace. No alternate route command was run because this phase had a fixed safe-command list.

No live database SELECT/count/schema checks were run. The class-trust risk is clear from code, and avoiding local MySQL contact was the safest read-only choice for this phase.

## API Store Class Input Findings

`API\AttendanceController@store()` currently validates `class` as:

```php
'class' => 'required|string|max:50',
```

Findings:

- `class` is required in the client payload.
- `class` is client-supplied.
- `store()` loads the target `Student` for terminal/inactive checks, but does not use that loaded student to derive class.
- Submitted `class` is not checked against `students.class`.
- Submitted `class` is not checked against the student's canonical class id.
- Submitted `class` is not checked against `Student::resolveCanonicalSchoolClass()?->name`.
- A mismatched class can be written into `attendances.class`.
- `Attendance::create($validated)` persists the client-controlled class after marked_by derivation, terminal/inactive rejection, and duplicate pre-check.
- The attendance table/model currently uses legacy `class`; `Attendance::$fillable` includes `class` and does not expose a canonical `class_id`.
- Current attendance report helpers and API daily report logic still filter by `attendances.class`, so class drift can hide or misplace records in class-scoped reports.

Current safe behavior around API `store()` remains intact:

- `marked_by` is derived from the authenticated API user.
- terminal/inactive students are rejected.
- existing duplicate pre-check remains.
- duplicate-key `QueryException` handling returns controlled HTTP `409`.
- successful responses include `period_display`.

Current risk: API `store()` still trusts client-supplied legacy class.

## Student Canonical Class Findings

`Student` has compatibility helpers from Phase 3C:

- `canonicalClassId(): ?int`
- `hasClassIdConflict(): bool`
- `resolveCanonicalSchoolClass(): ?SchoolClass`
- `classCompatibilityStatus(): array`

Canonical behavior from Phase 3C:

1. Prefer `students.class_id`.
2. Fall back to `students.school_class_id`.
3. Return `null` when neither exists.

Conflict behavior:

- `hasClassIdConflict()` returns true when both `class_id` and `school_class_id` are present and differ.
- `classCompatibilityStatus()` exposes the canonical id, raw ids, legacy string class, conflict flag, and source.

Phase 3E admin student normalization already writes future admin student creates/updates consistently:

- `class_id = school_classes.id`
- `school_class_id = school_classes.id`
- `class = school_classes.name`

API store can safely use these helpers in a future phase because:

- API `store()` already loads the `Student`.
- `resolveCanonicalSchoolClass()` provides the preferred class name without schema changes.
- fallback to `$student->class` keeps transitional compatibility when no canonical class id exists.

Recommended policy for edge cases:

- If `hasClassIdConflict()` is true, return a controlled conflict/error rather than guessing.
- If canonical class exists, derive `attendances.class` from `resolveCanonicalSchoolClass()->name`.
- If no canonical class exists but legacy `$student->class` exists, fall back to the legacy string.
- If neither canonical nor legacy class exists, return a controlled validation/eligibility error.

## Preflight Service Class Findings

`AttendanceBulkPreflightService` currently inspects class information in read-only preflight rows:

- reads payload `class`
- reads payload `class_id`
- includes student `legacy_class`
- includes student `class_id`
- reports `payload_class_id_mismatch`
- reports `payload_legacy_class_mismatch`
- reports `existing_attendance_legacy_class_mismatch`

Findings:

- Preflight can detect class mismatch as warnings.
- Preflight compares payload legacy class to `students.class`.
- Preflight compares payload class id to `students.class_id`.
- Preflight does not use `Student::resolveCanonicalSchoolClass()`.
- Preflight does not currently derive a write class for API store.
- Preflight warning shape is useful for future user-facing preview, but API store needs a direct storage policy.
- Reusing the full preflight service in single API `store()` would be a larger response-shape and policy change than necessary for the next phase.

Recommended use:

- Use preflight's mismatch concepts as supporting evidence.
- Implement a small local API store class derivation guard first.
- Later align preflight class mismatch checks with canonical class helpers.

## Existing Data Risk Findings

Live database class drift checks were not run in this phase.

Reason:

- The task allowed read-only SELECT/count/schema checks, but did not require them.
- Code inspection already proves API store can write mismatched client-supplied class.
- Avoiding local MySQL contact keeps this phase strictly limited to source inspection and the required report.

Potential future read-only data checks:

- count attendance rows where `attendances.class` differs from `students.class`
- count attendance rows where `attendances.class` differs from canonical `school_classes.name`
- count students with `class_id` / `school_class_id` conflicts
- list distinct attendance class values
- list distinct student class values

## Safe Implementation Options

### Option A - Require client class and reject mismatch

Keep `class` required from the client, then reject when it does not match the target student's canonical class name.

Pros:

- Small conceptual change.
- Makes spoofing fail.

Risks:

- String formatting differences can reject otherwise valid requests.
- Still treats the client as a source of truth.
- Requires careful comparison rules around legacy names, spacing, aliases, and class id conflicts.

### Option B - Ignore client class and derive from student

Derive `attendances.class` from the target student.

Preferred derivation:

1. `Student::resolveCanonicalSchoolClass()?->name`
2. fallback to `$student->class`
3. controlled error if neither exists

Pros:

- Removes class spoofing from API store.
- Uses Phase 3C canonical helper and Phase 3E normalization direction.
- No schema change.
- Does not require adding `class_id` to attendances.
- Keeps current reports working because `attendances.class` still receives a class string.

Risks:

- Client payload behavior changes because submitted class no longer controls storage.
- Existing tests that include class as required payload will need focused updates.
- Class conflict handling must be explicit to avoid hiding student data drift.

### Option C - Accept client class only when no canonical class exists

Use canonical class when available, but allow client class for students without canonical or legacy class.

Pros:

- Transitional.

Risks:

- More complicated than necessary.
- Keeps class spoofing possible for incomplete student records.
- Makes API behavior harder to reason about.

### Option D - Add `class_id` to attendances

Add canonical class id to attendance records.

Pros:

- Long-term relationally cleaner.

Risks:

- Requires migration/schema/data/report changes.
- Out of scope for the next small safety phase.

## Risk Classification

RED:

- API `store()` writes `attendances.class` from client input.
- API caller can submit a class that conflicts with the student's actual class.
- Class drift can affect class-scoped reports because attendance reads still filter by `attendances.class`.

YELLOW:

- `AttendanceBulkPreflightService` detects legacy class mismatches as warnings, but API `store()` does not reuse or enforce them.
- `Attendance` has only legacy `class`, not canonical `class_id`.
- Student compatibility still includes transitional `school_class_id` fallback and possible conflict cases.
- Existing API store tests still submit class as a normal required payload.
- String class comparison can be brittle if used as a rejection rule.

GREEN:

- Student canonical class helpers exist.
- Admin student writes already normalize class id and legacy class string.
- API `store()` already loads the `Student`, so class derivation can be added without another lookup boundary.
- API `bulkMark()` and `destroy()` remain guarded.
- API `update()` cannot mutate class.
- API `store()` already protects marked_by, terminal/inactive students, and duplicate-key exceptions.

## Recommended Phase 6N First Code Task

Phase 6N should derive the stored attendance class from the target student in `API\AttendanceController@store()`.

Recommended behavior:

1. Keep existing API store protections:
   - authenticated `marked_by`
   - terminal/inactive rejection
   - app-level duplicate pre-check
   - duplicate-key `QueryException` handling
   - `period_display` response transformation
2. Stop trusting client `class` for storage.
3. Make request `class` optional or ignore it after validation.
4. If `$student->hasClassIdConflict()` is true, return a controlled conflict/validation response.
5. Set `$validated['class']` from:
   - `$student->resolveCanonicalSchoolClass()?->name`
   - fallback `$student->class`
6. If neither source is available, return a controlled error before `Attendance::create()`.
7. Do not add `class_id` to `attendances`.
8. Do not change web attendance writes yet.
9. Add isolated SQLite tests proving spoofed class is ignored and canonical/fallback class behavior is stable.

Suggested Phase 6N test coverage:

- API store ignores spoofed client class when student has canonical class.
- API store derives class from `resolveCanonicalSchoolClass()?->name`.
- API store falls back to legacy `students.class` when no canonical class exists.
- API store returns a controlled error when student has no canonical or legacy class.
- API store returns a controlled error when student class ids conflict, if the isolated schema supports the compatibility field.
- Existing marked_by, terminal status, duplicate handling, and period display tests still pass.

## Confirmation

This phase was read-only except for creating this report.

No application code, controllers, routes, models, services, views, tests, migrations, database data, attendance records, exports, imports, seeds, sync jobs, biometric device commands, or full test suite were modified or run.
