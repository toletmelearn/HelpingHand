# Phase 6P - Attendance Class Resolver Service

Date: 2026-06-06

Scope: Create a shared attendance class resolver and refactor API attendance `store()` to use it while preserving Phase 6N behavior.

## Files Inspected

- `app/Http/Controllers/API/AttendanceController.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Attendance.php`
- `routes/api.php`
- `tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php`
- `tests/Feature/Attendance/AttendanceApiStoreTerminalStatusGuardTest.php`
- `tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`
- `tests/Feature/Attendance/AttendanceApiMarkedByGuardTest.php`
- `docs/project-autopsy/PHASE_6N_API_ATTENDANCE_STORE_CLASS_DERIVATION.md`
- `docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md`
- `docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md`

## Files Changed

- `app/Services/Attendance/AttendanceClassResolver.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `tests/Unit/Services/AttendanceClassResolverTest.php`
- `docs/project-autopsy/PHASE_6P_ATTENDANCE_CLASS_RESOLVER_SERVICE.md`

## Resolver Class Summary

Created:

- `App\Services\Attendance\AttendanceClassResolver`

Public method:

```php
public function resolveForStudent(Student $student): array
```

The resolver encapsulates the Phase 6N class derivation policy so API, web, preflight, and later teacher paths can adopt one shared rule instead of copying controller-local logic.

## Resolver Return Structure

Successful canonical result:

```php
[
    'ok' => true,
    'class' => 'Class 10',
    'source' => 'canonical',
    'status' => 200,
    'message' => null,
]
```

Successful legacy fallback result:

```php
[
    'ok' => true,
    'class' => 'Legacy 10',
    'source' => 'legacy',
    'status' => 200,
    'message' => null,
]
```

Failure results use the same shape with `ok = false`, `class = null`, and a controlled status/message.

## Class Source Precedence

The resolver matches Phase 6N exactly:

1. If `$student->hasClassIdConflict()` is true, return conflict.
2. Else if `$student->resolveCanonicalSchoolClass()` returns a class, return its `name` with source `canonical`.
3. Else if `$student->class` is present, return it with source `legacy`.
4. Else return unresolved.

## Conflict Behavior

Conflict response:

```php
[
    'ok' => false,
    'class' => null,
    'source' => 'conflict',
    'status' => 409,
    'message' => 'Student class data has a conflict. Attendance cannot be marked until class data is resolved.',
]
```

This preserves the Phase 6N API `store()` status and message.

## Unresolved Behavior

Unresolved response:

```php
[
    'ok' => false,
    'class' => null,
    'source' => 'unresolved',
    'status' => 422,
    'message' => 'Student class could not be resolved. Attendance cannot be marked.',
]
```

This preserves the Phase 6N API `store()` status and message.

## API Store Refactor Summary

`API\AttendanceController@store()` now imports and uses `AttendanceClassResolver`.

The inline Phase 6N block was replaced with:

```php
$classResolution = app(AttendanceClassResolver::class)->resolveForStudent($student);

if (!$classResolution['ok']) {
    return $this->error($classResolution['message'], $classResolution['status']);
}

$validated['class'] = $classResolution['class'];
```

The controller still treats request `class` as nullable/backward-compatible input and does not trust it for storage.

## Phase 6N Behavior Equivalence Confirmation

Behavior intentionally preserved:

- old clients can still send `class`
- client `class` is ignored for storage
- canonical class name wins
- legacy `students.class` fallback still works
- class id conflict returns HTTP `409` with the same message
- unresolved class returns HTTP `422` with the same message
- successful API response shape remains unchanged
- successful responses still include `period_display`
- `marked_by` still derives from authenticated API user
- terminal/inactive rejection still happens before class resolution write/create
- duplicate pre-check remains unchanged
- duplicate-key `QueryException` handling remains unchanged

## Web / Preflight / Teacher Confirmation

Unchanged in this phase:

- web attendance store behavior
- web attendance update behavior
- teacher attendance behavior
- `AttendanceBulkPreflightService`
- attendance routes
- API `update()`
- API `destroy()` guard
- API `bulkMark()` guard
- duplicate/race behavior
- period behavior
- attendance schema

## Tests Created / Updated

Created:

- `tests/Unit/Services/AttendanceClassResolverTest.php`

Coverage:

- `resolver_returns_conflict_when_student_class_ids_conflict`
- `resolver_returns_canonical_class_name_when_available`
- `resolver_falls_back_to_legacy_class_when_no_canonical_class_exists`
- `resolver_returns_unresolved_when_no_class_available`
- `resolver_source_is_canonical_or_legacy_or_conflict_or_unresolved`

The test uses isolated SQLite-memory schema only and does not use project migrations or real/local MySQL.

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/API/AttendanceController.php
Get-Content -Path app/Models/Student.php
Get-Content -Path tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php
Get-Content -Path docs/project-autopsy/PHASE_6P_ATTENDANCE_CLASS_RESOLVER_SERVICE.md
php -l app/Services/Attendance/AttendanceClassResolver.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l tests/Unit/Services/AttendanceClassResolverTest.php
php -l tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php
php artisan test --filter=AttendanceClassResolverTest --env=testing
php artisan test --filter=AttendanceApiStoreClassDerivationTest --env=testing
php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing
php artisan test --filter=AttendanceApiStoreDuplicateHandlingTest --env=testing
php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing
```

Notes:

- `Get-Content docs/project-autopsy/PHASE_6P_ATTENDANCE_CLASS_RESOLVER_SERVICE.md` initially failed because the report did not exist yet.
- The first `AttendanceClassResolverTest` run failed because the isolated `school_classes` test table omitted `deleted_at`, while `SchoolClass` uses `SoftDeletes`. The test harness was fixed by adding `deleted_at`; no application behavior changed.

## Test Result Summary

Syntax checks:

- `php -l app/Services/Attendance/AttendanceClassResolver.php`: PASS
- `php -l app/Http/Controllers/API/AttendanceController.php`: PASS
- `php -l tests/Unit/Services/AttendanceClassResolverTest.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceApiStoreClassDerivationTest.php`: PASS

Targeted tests:

- `php artisan test --filter=AttendanceClassResolverTest --env=testing`: PASS, 5 tests / 21 assertions
- `php artisan test --filter=AttendanceApiStoreClassDerivationTest --env=testing`: PASS, 10 tests / 26 assertions
- `php artisan test --filter=AttendanceApiStoreTerminalStatusGuardTest --env=testing`: PASS, 10 tests / 26 assertions
- `php artisan test --filter=AttendanceApiStoreDuplicateHandlingTest --env=testing`: PASS, 5 tests / 13 assertions
- `php artisan test --filter=AttendanceApiMarkedByGuardTest --env=testing`: PASS, 6 tests / 13 assertions

Targeted test runs emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests. No targeted test failed in the final runs.

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
- No teacher attendance behavior was changed.
- No preflight behavior was changed.
- No API `update()` behavior was changed.
- No API `destroy()` guard behavior was changed.
- No API `bulkMark()` guard behavior was changed.
- No API preflight/apply behavior was added.

## Remaining Risks

1. Web individual attendance store still trusts request/form `class`.
2. Web attendance update still accepts `class` server-side.
3. Preflight still uses legacy class mismatch warnings and does not use the resolver.
4. Teacher attendance still has a separate class/schema/status risk.
5. Existing historical attendance class drift likely remains.
6. API store duplicate pre-check remains non-atomic.
7. Nullable `period` duplicate policy remains unresolved.
8. No audit-preserving attendance correction workflow exists.

## Recommended Phase 6Q Next Step

Phase 6Q should update `AttendanceBulkPreflightService` to use `AttendanceClassResolver` in read-only mode.

Recommended Phase 6Q behavior:

- add derived class and source to normalized rows
- detect class id conflicts with resolver and mark them as errors or blocker warnings
- keep preflight read-only
- do not enable apply
- keep existing legacy mismatch warnings for compatibility, but add resolver-based canonical findings
