# Phase 6T - Web Attendance Update Identity Guard

Date: 2026-06-06

Scope: Guard ordinary web attendance update so it cannot mutate attendance `class`, `date`, or `period`.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Models/Attendance.php`
- `app/Services/Attendance/AttendanceClassResolver.php`
- `resources/views/attendance/edit.blade.php`
- `resources/views/attendance/show.blade.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceWebStoreClassDerivationTest.php`
- `tests/Feature/Attendance/AttendanceCreateReadOnlyTest.php`
- `docs/project-autopsy/PHASE_6S_WEB_ATTENDANCE_UPDATE_MUTATION_AUDIT.md`
- `docs/project-autopsy/PHASE_6R_WEB_ATTENDANCE_STORE_CLASS_DERIVATION.md`
- `docs/project-autopsy/PHASE_5N_API_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`
- `docs/project-autopsy/PHASE_5P_API_ATTENDANCE_UPDATE_DATE_PERIOD_GUARD.md`

## Files Changed

- `app/Http/Controllers/AttendanceController.php`
- `resources/views/attendance/edit.blade.php`
- `tests/Feature/Attendance/AttendanceWebUpdateIdentityGuardTest.php`
- `docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`

## Previous Update Mutation Risk

Phase 6S found that web `AttendanceController@update()` accepted and wrote:

- `date`
- `class`
- `status`
- `subject`
- `period`
- `remarks`

That meant crafted web requests could mutate attendance identity/correction fields (`class`, `date`, and `period`) even when the edit form made `class` readonly.

There was no duplicate conflict check, transaction, duplicate-key handling, or audit-preserving correction workflow around those identity/date/period changes.

## New Web Update Allowed Fields

The ordinary web update path now validates and writes only:

- `status`
- `subject`
- `remarks`

The authorization call remains unchanged:

```php
$this->authorize('update', $attendance);
```

A short controller comment was added:

```php
// Phase 6T: ordinary update cannot mutate attendance class/date/period.
```

## Fields Now Blocked From Mutation

The ordinary web update path no longer validates or writes:

- `class`
- `date`
- `period`
- `student_id`
- `marked_by`

`student_id` and `marked_by` were already not written by the current web update method; tests now explicitly cover crafted payloads for both.

## Edit Form Changes

`resources/views/attendance/edit.blade.php` now renders `date`, `class`, and `period` as display-only readonly controls without submitted `name` attributes.

Removed submitted field names:

- `name="date"`
- `name="class"`
- `name="period"`

Still editable and submitted:

- `name="status"`
- `name="subject"`
- `name="remarks"`

No correction workflow UI was added.
No hidden identity fields were added.

## Class / Date / Period Mutation Confirmation

Focused tests prove crafted request payloads cannot change:

- `class`
- `date`
- `period`

The stored values remain unchanged after ordinary update requests containing spoofed values.

## Editable Field Confirmation

Focused tests prove ordinary update still changes:

- `status`
- `subject`
- `remarks`

## Unchanged Behavior Confirmation

This phase did not change:

- web `store()`
- web bulk direct-write guard
- preflight
- API controller behavior
- teacher attendance behavior
- destroy/delete behavior
- routes
- migrations/schema
- duplicate/race policy
- period/null policy

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceWebUpdateIdentityGuardTest.php`

Coverage:

- `web_update_does_not_mutate_class_from_crafted_request`
- `web_update_does_not_mutate_date_from_crafted_request`
- `web_update_does_not_mutate_period_from_crafted_request`
- `web_update_still_updates_status_subject_and_remarks`
- `web_update_does_not_mutate_marked_by`
- `web_update_does_not_mutate_student_id_even_if_payload_contains_it`
- `edit_form_does_not_submit_class_date_period_as_editable_fields`

The test uses isolated SQLite-memory tables only and does not use project migrations or real/local MySQL.

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/AttendanceController.php
Get-Content -Path resources/views/attendance/edit.blade.php
Get-Content -Path app/Models/Attendance.php
Get-Content -Path docs/project-autopsy/PHASE_6S_WEB_ATTENDANCE_UPDATE_MUTATION_AUDIT.md
rg -n "function hasRole|roles\(|hasPermission" app/Models/User.php
Get-Content -Path app/Models/User.php
php -l app/Http/Controllers/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceWebUpdateIdentityGuardTest.php
php -l app/Models/Attendance.php
php artisan test --filter=AttendanceWebUpdateIdentityGuardTest --env=testing
php artisan test --filter=AttendanceWebStoreClassDerivationTest --env=testing
php artisan test --filter=AttendanceCreateReadOnlyTest --env=testing
php artisan test --filter=AttendanceClassResolverTest --env=testing
```

Notes:

- One attempted `Get-Content ... | Select-Object -Index 430..470` route snippet command had a PowerShell range syntax error and was not needed.
- The first `AttendanceWebUpdateIdentityGuardTest` run exposed test harness issues:
  - response redirect assertions were too specific for the current route/test setup
  - the isolated `students` table needed `deleted_at` because `Student` uses `SoftDeletes`
- The harness was adjusted to assert persisted database behavior directly and match the model schema.

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceWebUpdateIdentityGuardTest.php`: PASS
- `php -l app/Models/Attendance.php`: PASS
- `php artisan test --filter=AttendanceWebUpdateIdentityGuardTest --env=testing`: PASS, 7 tests / 14 assertions
- `php artisan test --filter=AttendanceWebStoreClassDerivationTest --env=testing`: PASS, 7 tests / 24 assertions
- `php artisan test --filter=AttendanceCreateReadOnlyTest --env=testing`: PASS, 5 tests / 7 assertions
- `php artisan test --filter=AttendanceClassResolverTest --env=testing`: PASS, 5 tests / 21 assertions

Targeted PHPUnit runs emitted existing doc-comment metadata deprecation warnings from unrelated tests. No final targeted test failed.

## Full Suite / Data Safety Confirmation

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No real/local MySQL data was touched.
- No attendance writes were performed against real/local MySQL.
- No attendance deletes were performed against real/local MySQL.
- No export route was executed.
- No biometric sync or device command was run.

All update/write assertions used isolated SQLite in-memory test schemas only.

## Remaining Risks

- Web destroy/delete remains unchanged and still needs separate audit/guard consideration.
- Web update is no longer a date/period correction workflow; no replacement correction workflow exists yet.
- Web individual store duplicate pre-check still uses request `class`.
- Web individual store still uses raw multi-row `Attendance::insert()` without a transaction.
- Web individual store terminal/inactive policy remains unchanged.
- Teacher attendance class/status/schema risks remain separate.
- Existing historical attendance class/date/period drift still needs read-only reconciliation.

## Recommended Phase 6U Next Step

Phase 6U should perform a read-only audit of web attendance destroy/delete behavior and decide whether ordinary web delete should be guarded like API destroy until an audit-preserving correction/void workflow exists.
