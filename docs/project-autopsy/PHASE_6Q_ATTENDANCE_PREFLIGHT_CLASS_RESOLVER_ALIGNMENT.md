# Phase 6Q - Attendance Preflight Class Resolver Alignment

Date: 2026-06-06

Scope: Use `AttendanceClassResolver` inside the read-only bulk preflight service and expose resolver-derived class information without enabling any write/apply flow.

## Files Inspected

- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Attendance.php`
- `app/Http/Controllers/AttendanceController.php`
- `resources/views/attendance/preflight-result.blade.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `tests/Unit/Services/AttendanceClassResolverTest.php`
- `tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `docs/project-autopsy/PHASE_6P_ATTENDANCE_CLASS_RESOLVER_SERVICE.md`
- `docs/project-autopsy/PHASE_6O_WEB_ATTENDANCE_CLASS_PREFLIGHT_AUDIT.md`
- `docs/project-autopsy/PHASE_5B_ATTENDANCE_BULK_PREFLIGHT_SERVICE.md`

## Files Changed

- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `docs/project-autopsy/PHASE_6Q_ATTENDANCE_PREFLIGHT_CLASS_RESOLVER_ALIGNMENT.md`

## Resolver Integration Summary

`AttendanceBulkPreflightService` now uses `AttendanceClassResolver` in read-only mode.

Implementation:

- Added optional constructor dependency:

```php
public function __construct(?AttendanceClassResolver $classResolver = null)
```

- Falls back to `app(AttendanceClassResolver::class)` so existing direct `new AttendanceBulkPreflightService()` calls continue to work.
- For each valid loaded student row, calls:

```php
$classResolution = $this->classResolver->resolveForStudent($student);
```

No write behavior was added.

## New Derived Class Fields

Each normalized row now includes these read-only fields:

```php
'derived_class' => ...
'derived_class_source' => ...
'class_resolution_ok' => ...
'class_resolution_status' => ...
'class_resolution_message' => ...
```

Existing compatibility fields remain:

- `class_id`
- `school_class_id`
- `legacy_class`
- `section_id`
- `legacy_section`
- existing action/error/warning fields

## Conflict / Unresolved Class Behavior

Resolver conflict or unresolved status now becomes a preflight blocker.

Conflict:

- Adds row error: `student_class_conflict`
- Sets row action to `error`
- Makes `is_valid` false
- Preserves resolver metadata:
  - `derived_class_source = conflict`
  - `class_resolution_ok = false`
  - `class_resolution_status = 409`
  - resolver message

Unresolved:

- Adds row error: `student_class_unresolved`
- Sets row action to `error`
- Makes `is_valid` false
- Preserves resolver metadata:
  - `derived_class_source = unresolved`
  - `class_resolution_ok = false`
  - `class_resolution_status = 422`
  - resolver message

No attendance rows are written because preflight remains read-only.

## Payload Mismatch Warning Behavior

Existing payload-vs-legacy class warning remains:

- `payload_legacy_class_mismatch`

New resolver-based warning:

- `payload_derived_class_mismatch`

This warning is emitted when a payload `class` differs from the resolver-derived class. It is warning-only because future safe apply should write the derived class rather than trusting payload class.

## Existing Legacy Warning Compatibility

The following existing warnings remain:

- `payload_class_id_mismatch`
- `payload_legacy_class_mismatch`
- `payload_section_id_mismatch`
- `existing_attendance_legacy_class_mismatch`
- terminal status warnings
- soft-deleted student warning

Existing output keys were not removed.

## School Class ID Output

Fixed normalized `school_class_id` output.

Previous behavior:

```php
'school_class_id' => $student->class_id ?? null,
```

New behavior:

```php
'school_class_id' => $student->school_class_id ?? null,
```

This is read-only output only and does not change persistence.

## Preflight Read-Only Confirmation

Preflight remains read-only:

- no `Attendance::insert()`
- no `Attendance::create()`
- no `Attendance::update()`
- no `Attendance::updateOrCreate()`
- no transaction/write apply
- no database mutations
- no view Apply/Confirm/Mark button added

`resources/views/attendance/preflight-result.blade.php` was not changed in this phase.

## No Apply / Write Behavior Added

No apply, confirm, save, mark attendance, token, approval, or write path was added.

Unchanged:

- web `AttendanceController@store()`
- API `AttendanceController@store()`
- API `update()`
- API `destroy()` guard
- API `bulkMark()` guard
- teacher attendance behavior
- routes
- migrations/schema

## Tests Created / Updated

Updated:

- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `tests/Feature/Attendance/AttendancePreflightUiTest.php`

Added/covered in service tests:

- `preflight_includes_derived_class_from_canonical_class`
- `preflight_includes_derived_class_source`
- `preflight_falls_back_to_legacy_class_when_no_canonical_class`
- `preflight_marks_class_id_conflict_as_error_or_blocker`
- `preflight_marks_unresolved_class_as_error_or_blocker`
- `preflight_warns_when_payload_class_differs_from_derived_class`
- `preflight_keeps_existing_legacy_mismatch_warnings`
- `preflight_reports_actual_school_class_id`
- existing read-only and legacy warning tests still pass

Isolated test harness updates:

- added `students.school_class_id` where needed
- added `school_classes.deleted_at` where needed because `SchoolClass` uses `SoftDeletes`

These changes are limited to SQLite-memory test schemas.

## Commands Run

```powershell
Get-Content -Path app/Services/Attendance/AttendanceBulkPreflightService.php
Get-Content -Path app/Services/Attendance/AttendanceClassResolver.php
Get-Content -Path tests/Unit/Services/AttendanceBulkPreflightServiceTest.php
Get-Content -Path tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php
Get-Content -Path tests/Feature/Attendance/AttendancePreflightUiTest.php
rg -n "Schema::create\('school_classes'|Schema::create\('students'|class_id|school_class_id|deleted_at" tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php tests/Feature/Attendance/AttendancePreflightUiTest.php
php -l app/Services/Attendance/AttendanceBulkPreflightService.php
php -l app/Services/Attendance/AttendanceClassResolver.php
php -l tests/Unit/Services/AttendanceBulkPreflightServiceTest.php
php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing
php artisan test --filter=AttendanceClassResolverTest --env=testing
php artisan test --filter=AttendanceBulkPreflightEndpointTest --env=testing
php artisan test --filter=AttendancePreflightUiTest --env=testing
```

## Test Result Summary

Syntax checks:

- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php`: PASS
- `php -l app/Services/Attendance/AttendanceClassResolver.php`: PASS
- `php -l tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`: PASS

Targeted tests:

- `php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing`: PASS, 20 tests / 34 assertions
- `php artisan test --filter=AttendanceClassResolverTest --env=testing`: PASS, 5 tests / 21 assertions
- `php artisan test --filter=AttendanceBulkPreflightEndpointTest --env=testing`: PASS, 6 tests / 18 assertions
- `php artisan test --filter=AttendancePreflightUiTest --env=testing`: PASS, 7 tests / 15 assertions

Targeted test runs emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests. No targeted test failed.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No database schema was changed.
- No real/local MySQL data was touched.
- No attendance writes were performed against real/local MySQL.
- No attendance deletes were performed against real/local MySQL.
- No attendance write route was executed.
- No API store/update/destroy/bulkMark route was executed.
- No biometric sync was changed or triggered.
- No biometric device command was run.
- No web store behavior was changed.
- No API store/update behavior was changed.
- No teacher attendance behavior was changed.
- No apply/write approval flow was added.

## Remaining Risks

1. Web individual attendance store still trusts request/form `class`.
2. Web attendance update still accepts `class` server-side.
3. Preflight result UI does not yet display derived class/source fields.
4. Future safe apply flow still does not exist.
5. Teacher attendance still has separate class/schema/status risks.
6. Existing historical attendance class drift likely remains.
7. API/web duplicate and nullable-period risks remain outside this phase.

## Recommended Phase 6R Next Step

Phase 6R should apply `AttendanceClassResolver` to web individual attendance store with a narrow, tested behavior change.

Recommended focus:

- load each submitted student before building insert rows
- derive `attendances.class` from the resolver per student
- reject class conflict/unresolved rows before insert
- keep terminal/inactive and duplicate policy unchanged unless explicitly scoped
- preserve web bulk direct-write guard and preflight read-only behavior

An alternative smaller display phase would be to show derived class/source in `preflight-result.blade.php`, but the higher safety value is moving web individual store away from request-class trust.
