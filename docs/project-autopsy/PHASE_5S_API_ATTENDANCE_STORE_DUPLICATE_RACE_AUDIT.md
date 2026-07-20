# Phase 5S - API Attendance Store Duplicate / Race Audit

Date: 2026-06-05

Scope: Read-only audit of API `AttendanceController@store()` duplicate/race/null-period behavior.

## Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/StudentStatus.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `database/migrations/2026_01_21_083000_create_attendances_table.php`
- `database/migrations/2026_01_21_084000_create_attendances_temp_table.php`
- `database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php`
- `tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `docs/project-autopsy/PHASE_5R_API_ATTENDANCE_STORE_TERMINAL_STATUS_GUARD.md`
- `docs/project-autopsy/PHASE_5Q_API_ATTENDANCE_STORE_TERMINAL_DUPLICATE_AUDIT.md`
- `docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md`

## Commands Run

```powershell
Get-Content app/Http/Controllers/API/AttendanceController.php
Get-Content app/Http/Controllers/API/BaseApiController.php
Get-Content routes/api.php
Get-Content app/Models/Attendance.php
Get-Content app/Services/Attendance/AttendanceBulkPreflightService.php
Get-ChildItem database/migrations
rg -n "create_attendances_table|Schema::create\('attendances'|table\('attendances'|unique\(|student_id.*date.*period|period|class|subject" database/migrations app/Models/Attendance.php app/Http/Controllers/API/AttendanceController.php app/Services/Attendance/AttendanceBulkPreflightService.php
Get-Content tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php
Get-Content tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
Get-Content docs/project-autopsy/PHASE_5R_API_ATTENDANCE_STORE_TERMINAL_STATUS_GUARD.md
Get-Content database/migrations/2026_01_21_083000_create_attendances_table.php
Get-Content database/migrations/2026_01_21_084000_create_attendances_temp_table.php
Get-Content database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php
Get-Content docs/project-autopsy/PHASE_5Q_API_ATTENDANCE_STORE_TERMINAL_DUPLICATE_AUDIT.md
Get-Content docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md
php -l app/Http/Controllers/API/AttendanceController.php
php -l routes/api.php
php -l app/Services/Attendance/AttendanceBulkPreflightService.php
php artisan route --path=api/v1/attendance
```

Command results:

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l routes/api.php`: PASS
- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php`: PASS
- `php artisan route --path=api/v1/attendance`: failed because this Laravel app exposes `route:list`; `route` is only an Artisan namespace.

No optional live database checks were run.

## Current API Store Duplicate Flow

`API\AttendanceController@store()` currently performs this sequence:

1. Resolve authenticated API user.
2. Validate request fields.
3. Derive `marked_by` from `$request->user()->id`.
4. Load `Student::find($validated['student_id'])`.
5. Reject missing/ineligible student.
6. Check latest `student_statuses.id` and reject terminal/inactive statuses.
7. Run application-level duplicate pre-check.
8. Call `Attendance::create($validated)`.
9. Catch generic `\Exception`.

Validation rules before duplicate check:

```php
'student_id' => 'required|exists:students,id',
'date' => 'required|date',
'status' => 'required|in:present,absent,late,half_day',
'remarks' => 'nullable|string|max:255',
'period' => 'nullable|string|max:50',
'subject' => 'nullable|string|max:100',
'class' => 'required|string|max:50',
'session' => 'nullable|string|max:20'
```

Phase 5R terminal/inactive guard location:

- It runs after validation and `marked_by` derivation.
- It runs before duplicate check and before `Attendance::create()`.

Duplicate check fields:

- `student_id`
- `date`
- `period`

Current duplicate query:

```php
Attendance::where('student_id', $validated['student_id'])
    ->where('date', $validated['date'])
    ->where('period', $validated['period'] ?? null)
    ->exists()
```

Findings:

- The duplicate check uses `where('period', $validated['period'] ?? null)`, not an explicit `whereNull('period')`.
- Laravel generally compiles `where('column', null)` as `IS NULL`, so the single-request null-period query should detect existing null-period rows.
- The duplicate check happens before `Attendance::create()`.
- No transaction is used.
- No `lockForUpdate()` is used.
- No `upsert()`, `firstOrCreate()`, or `updateOrCreate()` is used.
- No `Illuminate\Database\QueryException` catch exists.
- Duplicate DB exceptions are not handled separately.
- App-level duplicate response is already controlled: HTTP `409`, message `Attendance already marked for this student on this date and period.`
- DB-level duplicate response is generic through the catch-all exception handler: `Failed to mark attendance: ...`

Current answer: API store duplicate check is not atomic.

## Schema / Unique Constraint Findings

Primary attendance table migration:

`database/migrations/2026_01_21_083000_create_attendances_table.php`

Columns created:

- `id`
- `student_id` nullable unsigned big integer
- `teacher_id` nullable unsigned big integer
- `date` non-null date
- `status` enum: `present`, `absent`, `late`, `half_day`
- `remarks` nullable text
- `period` nullable string
- `subject` nullable string
- `class` nullable string
- `session` nullable string
- `marked_by` nullable unsigned big integer
- `ip_address` nullable string
- `device_info` nullable string
- timestamps

Additional migration:

- `2026_01_21_120001_add_relationships_to_existing_tables.php` adds nullable `updated_by` to `attendances`.

Indexes and constraints:

- foreign key `student_id` references `students.id` with cascade delete
- foreign key `teacher_id` references `teachers.id` with cascade delete
- foreign key `marked_by` references `users.id` with set null
- index on `date,class`
- index on `student_id,date`
- index on `teacher_id,date`
- index on `status`
- index on `period`
- unique constraint on `student_id,date,period`

Null-period finding:

- `period` is nullable.
- In MySQL, unique indexes allow multiple `NULL` values because `NULL` is not considered equal to another `NULL`.
- Therefore, multiple rows with the same `student_id` and `date` and `period = NULL` can exist despite the unique index.
- The current app-level pre-check can compensate for a single request by querying for `period IS NULL`.
- That app-level check is not race-safe; concurrent requests can both pass the check before either insert commits.

Subject/class uniqueness:

- `subject` is not part of the attendance unique identity.
- `class` is not part of the attendance unique identity.
- Current design appears to define one attendance row per student/date/period, regardless of subject or class.
- That policy is coherent for full-day or period-level attendance.
- If subject-level attendance is required later, uniqueness may need a separate design using canonical subject identifiers rather than the current free-text `subject`.
- `class` should not be part of duplicate identity if the attendance row belongs to a student; class should be derived or validated separately.

Recommended uniqueness policy based on current design:

- Keep `student_id,date,period` as the conceptual identity for now.
- Treat null period as full-day attendance at the application layer until a schema/data policy is designed.
- Do not add `class` or free-text `subject` to uniqueness in the immediate next phase.

## Null-Period Findings

Current period behavior:

- API `store()` validates `period` as nullable.
- If omitted, `$validated['period'] ?? null` becomes null for duplicate checking.
- `Attendance::create($validated)` will create a row without `period` in the payload, leaving it null.

What works today:

- The app-level duplicate pre-check can detect an already-existing null-period row in a normal single-request path.
- `AttendanceBulkPreflightService` uses an explicit `whereNull('period')` when period is null.

What remains unsafe:

- The DB unique constraint does not reliably protect null-period duplicates in MySQL.
- The app-level pre-check is non-atomic.
- A race can create duplicate full-day rows with `period = NULL`.
- A DB duplicate exception may never occur for null-period duplicates in MySQL, because the unique index may allow the duplicates.

Null-period policy recommendation:

- Do not normalize null period in Phase 5T.
- Defer sentinel normalization, such as `full_day`, because it requires schema/data/API compatibility policy.
- First add controlled duplicate exception handling for non-null-period races.
- Then decide whether null period needs an application lock, generated column, sentinel, or schema-level policy.

## Preflight Service Reuse Findings

`AttendanceBulkPreflightService` duplicate behavior:

- It detects duplicate rows inside the submitted payload using `student_id|date|period`.
- It detects existing attendance by `student_id`, `date`, and `period`.
- It uses explicit `whereNull('period')` when `$period === null`.
- It can support a single-row payload through `attendance_rows`.

Limitations:

- It is read-only and does not solve atomic write safety.
- It cannot prevent concurrent inserts after preflight.
- Its response shape is bulk/preflight-oriented, not the existing single API `store()` response shape.
- It is useful for preview/user-facing diagnostics, but not sufficient as the final authority for write safety.

Recommendation:

- Do not use preflight service as the Phase 5T atomic duplicate fix.
- Keep it available for future API preflight/preview flows.
- For `store()`, let the DB unique constraint be final authority where it can, and normalize duplicate-key errors into controlled API responses.

## Safe Implementation Options

### Option A: Keep Pre-Check, Add QueryException Duplicate Catch

Behavior:

- Preserve existing friendly pre-check.
- Add `QueryException` handling around `Attendance::create()`.
- Detect duplicate-key errors and return controlled HTTP `409`.

Pros:

- Smallest code change.
- Preserves current response shape.
- Does not change duplicate semantics.
- Protects non-null-period race collisions when DB unique constraint fires.

Cons:

- Does not fix MySQL null-period duplicate allowance.
- Requires careful duplicate-key detection across drivers.

### Option B: Transaction with Lock

Behavior:

- Wrap duplicate check and create in `DB::transaction()`.
- Use `lockForUpdate()` on the matching duplicate query.

Pros:

- Can reduce races when matching rows exist.

Cons:

- Locking an absent row may not prevent concurrent insert in all DBs/isolation modes.
- More invasive than Option A.
- Still does not fully solve nullable unique semantics.

### Option C: `updateOrCreate()` / `firstOrCreate()`

Pros:

- Simple Laravel API.

Cons:

- `updateOrCreate()` may silently update and hide conflicts.
- `firstOrCreate()` can still race and then throw a DB exception.
- Could change API semantics from "reject duplicate" to "reuse/update duplicate."

### Option D: DB Unique Constraint as Final Authority, Normalize Duplicate Exception

Behavior:

- Keep app-level pre-check for friendly early response.
- Treat DB unique constraint as the final authority.
- Catch duplicate-key `QueryException` and return controlled `409`.

Pros:

- Best small next step.
- Preserves existing API behavior.
- Handles races for non-null-period rows.
- Avoids silent update semantics.

Cons:

- Still does not solve nullable-period duplicate allowance in MySQL.

### Option E: Normalize Null Period to Sentinel

Behavior:

- Convert omitted/null period to a sentinel such as `full_day`.

Pros:

- Makes DB uniqueness enforce full-day attendance if applied consistently.

Cons:

- Requires schema/data/API policy.
- Could break existing clients and reports expecting null.
- Needs migration/backfill strategy.
- Too large for Phase 5T.

## RED / YELLOW / GREEN Risk Classification

RED:

- Duplicate check remains non-atomic.
- MySQL can allow multiple `NULL` periods under the current unique index.
- DB duplicate exceptions are not returned as controlled `409`.
- Null-period duplicate races may not throw DB duplicate exceptions at all.

YELLOW:

- Duplicate pre-check uses `where('period', null)` instead of explicit `whereNull`.
- `subject` uniqueness policy is implicit.
- `class` remains client-supplied but is not part of uniqueness.
- No FormRequest or dedicated duplicate policy object exists.
- `attendances_temp` has similar columns without the attendance unique key.

GREEN:

- API `store()` has a friendly app-level duplicate pre-check.
- API `store()` now rejects terminal/inactive students before duplicate check and create.
- API `store()` derives `marked_by` from authenticated user.
- API `update()` can no longer mutate date or period.
- API `bulkMark()` and `destroy()` remain guarded.

## Top 10 Duplicate / Race / Null-Period Risks

1. Concurrent API store requests can both pass the pre-check.
2. DB duplicate exception handling is generic, not a controlled `409`.
3. MySQL unique indexes can allow duplicate rows when `period` is `NULL`.
4. Null-period duplicate races may succeed silently at the DB level.
5. There is no transaction around duplicate check plus create.
6. There is no lock or upsert policy.
7. `where('period', null)` relies on Laravel null translation rather than explicit `whereNull`.
8. `firstOrCreate()` or `updateOrCreate()` would risk changing duplicate semantics if chosen casually.
9. `subject` is not part of uniqueness, which is likely correct but still undocumented policy.
10. Legacy `class` remains trusted and can create misleading class-based reports even when duplicate identity is student/date/period.

## Recommended Phase 5T First Code Task

Phase 5T should keep the existing application-level duplicate pre-check and add specific `QueryException` handling for duplicate-key conflicts around `Attendance::create()`.

Recommended constraints:

- Keep Phase 5R terminal/inactive guard unchanged.
- Keep existing duplicate pre-check and friendly 409 response.
- Add `Illuminate\Database\QueryException` catch before the generic catch.
- Detect duplicate-key errors and return controlled HTTP `409`.
- Keep nullable period policy unchanged in Phase 5T.
- Do not normalize null period yet.
- Do not use `updateOrCreate()` yet.
- Do not change legacy class behavior yet.

Recommended later sequencing:

- Phase 5T: controlled duplicate-key `QueryException` response.
- Phase 5U: audit/design null-period sentinel or locking policy.
- Phase 5V: legacy class validation/normalization.

## Confirmation

This phase was read-only except for creating this report.

No application code, routes, controllers, services, models, tests, migrations, database data, attendance records, imports, seeds, sync jobs, biometric sync, or biometric device commands were modified or run.
