# Phase 6N - API Attendance Store Class Derivation

Date: 2026-06-06

Scope: Stop trusting client-supplied `class` in API attendance `store()` and derive stored `attendances.class` from the target student.

## Files Inspected

- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/API/BaseApiController.php`
- `app/Models/Attendance.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `routes/api.php`
- `tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiPeriodDisplayResponseTest.php`
- `docs/project-autopsy/PHASE_6M_API_ATTENDANCE_STORE_LEGACY_CLASS_AUDIT.md`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`
- `docs/project-autopsy/PHASE_5R_API_ATTENDANCE_STORE_TERMINAL_STATUS_GUARD.md`
- `docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md`

## Files Changed

- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php`
- `docs/project-autopsy/PHASE_6N_API_ATTENDANCE_STORE_CLASS_DERIVATION.md`

## Previous Class Spoofing Risk Summary

Phase 6M found that API `store()`:

- required client-supplied `class`
- wrote the submitted class into `attendances.class`
- did not compare submitted class to `students.class`
- did not compare submitted class to canonical `students.class_id`
- could store attendance rows whose class conflicted with the target student's real class

This mattered because existing attendance reports and API daily report paths still filter by legacy `attendances.class`.

## New Class Derivation Behavior

API `AttendanceController@store()` now treats request `class` as optional/backward-compatible input and does not trust it for storage.

Validation changed from:

```php
'class' => 'required|string|max:50',
```

to:

```php
'class' => 'nullable|string|max:50',
```

After the student is loaded and after the terminal/inactive status guard, the controller derives the stored class from the student and assigns it into `$validated['class']` before the duplicate pre-check and `Attendance::create()`.

Comment added near the derivation:

```php
// Phase 6N: derive attendance class from student; client class is not trusted.
```

## Class Source Precedence

The stored attendance class is resolved in this order:

1. If `Student::hasClassIdConflict()` is true, return a controlled conflict response.
2. If `Student::resolveCanonicalSchoolClass()` returns a `SchoolClass`, use `$schoolClass->name`.
3. Else if `$student->class` is present, use the legacy student class string.
4. Else return a controlled unresolved-class response.

No `class_id` column was added to `attendances`. The existing legacy `attendances.class` string remains populated for current reports.

## Conflict Behavior

If the student has conflicting class ids, API `store()` returns:

- HTTP status: `409`
- Message: `Student class data has a conflict. Attendance cannot be marked until class data is resolved.`

No attendance row is created for this case.

## Unresolved Class Behavior

If neither canonical class nor legacy student class can be resolved, API `store()` returns:

- HTTP status: `422`
- Message: `Student class could not be resolved. Attendance cannot be marked.`

No attendance row is created for this case.

## Client Class Trust Confirmation

Client-supplied class is no longer trusted for storage.

Old clients can still send `class`, but the stored `attendances.class` comes from:

- canonical `SchoolClass::name`, or
- fallback `students.class`

Tests confirm spoofed payload class values are ignored.

## Existing Store Protections Confirmation

Preserved protections:

- authenticated API user still sets `marked_by`
- terminal/inactive status guard remains
- app-level duplicate pre-check remains
- duplicate-key `QueryException` still returns controlled HTTP `409`
- successful store responses still include `period_display`

Unchanged behavior:

- duplicate/race behavior was not changed
- nullable `period` behavior was not changed
- API `update()` was not changed
- API `destroy()` remains guarded
- API `bulkMark()` remains guarded
- web attendance behavior was not changed

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php`

Coverage:

- `api_store_ignores_spoofed_client_class_when_student_has_canonical_class`
- `api_store_derives_class_from_canonical_school_class`
- `api_store_falls_back_to_student_legacy_class_when_no_canonical_class_exists`
- `api_store_returns_conflict_when_student_class_ids_conflict`
- `api_store_returns_controlled_error_when_student_class_cannot_be_resolved`
- `api_store_still_accepts_old_payload_with_class_but_does_not_trust_it`
- `api_store_marked_by_guard_still_uses_authenticated_user`
- `api_store_terminal_status_guard_still_blocks_before_create`
- `api_store_duplicate_handling_still_returns_409`
- `api_store_response_still_includes_period_display`

The test uses isolated SQLite-memory schema only and does not use project migrations or real/local MySQL.

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/API/AttendanceController.php
Get-Content -Path app/Models/Student.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiPeriodDisplayResponseTest.php
Get-Content -Path app/Models/Attendance.php
Get-Content -Path app/Models/SchoolClass.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l app/Models/Student.php
php -l tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php
php -l routes/api.php
php artisan test --filter=AttendanceApiStoreClassDerivationTest --env=testing
php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing
php artisan test --filter=AttendanceApiStoreDuplicateHandlingTest --env=testing
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
php artisan test --filter=AttendanceApiPeriodDisplayResponseTest --env=testing
```

## Test Result Summary

- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l app/Models/Student.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php`: PASS
- `php -l routes/api.php`: PASS
- `php artisan test --filter=AttendanceApiStoreClassDerivationTest --env=testing`: PASS, 10 tests / 26 assertions
- `php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing`: PASS, 10 tests / 26 assertions
- `php artisan test --filter=AttendanceApiStoreDuplicateHandlingTest --env=testing`: PASS, 5 tests / 13 assertions
- `php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing`: PASS, 6 tests / 13 assertions
- `php artisan test --filter=AttendanceApiPeriodDisplayResponseTest --env=testing`: PASS, 7 tests / 22 assertions

Targeted test runs emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests. No targeted test failed.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No database schema was changed.
- No real/local MySQL data was touched.
- No attendance writes were performed against real/local MySQL.
- No attendance deletes were performed against real/local MySQL.
- No biometric sync was changed or triggered.
- No biometric device command was run.
- No web attendance behavior was changed.
- No API `update()` behavior was changed.
- No API `destroy()` guard behavior was changed.
- No API `bulkMark()` guard behavior was changed.
- No API preflight/apply behavior was added.

## Remaining Risks

1. API `store()` duplicate pre-check remains non-atomic.
2. Nullable `period` still weakens MySQL duplicate protection for full-day/no-period attendance.
3. API `store()` still stores only legacy `attendances.class`, not canonical `class_id`.
4. Existing historical attendance rows may still have class drift.
5. Web attendance write paths still use legacy class strings.
6. Preflight class mismatch logic still uses legacy warning rules rather than canonical derivation.
7. No audit-preserving attendance correction workflow exists.
8. No safe API bulk apply contract exists.

## Recommended Phase 6O Next Step

Phase 6O should perform a read-only audit of web attendance store class derivation and preflight class mismatch behavior.

Recommended focus:

- whether web individual/per-student store still trusts class strings
- whether guarded web bulk/preflight paths should derive class from student before any future safe apply
- whether `AttendanceBulkPreflightService` should switch from legacy class warnings to canonical class helper checks
- whether existing attendance class drift needs a read-only reconciliation report before any data repair
