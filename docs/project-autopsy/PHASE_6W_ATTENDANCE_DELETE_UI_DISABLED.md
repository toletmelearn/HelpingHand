# Phase 6W - Attendance Delete UI Disabled

Date: 2026-06-06

Scope: Remove active ordinary web attendance delete controls from index/show views after the Phase 6V server-side destroy guard.

## Files Inspected

- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`
- `resources/views/attendance/edit.blade.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Policies/AttendancePolicy.php`
- `routes/web.php`
- `tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php`
- `docs/project-autopsy/PHASE_6V_WEB_ATTENDANCE_DESTROY_GUARD.md`
- `docs/project-autopsy/PHASE_6U_WEB_ATTENDANCE_DESTROY_DELETE_AUDIT.md`

## Files Changed

- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`
- `tests/Feature/Attendance/AttendanceDeleteUiDisabledTest.php`
- `docs/project-autopsy/PHASE_6W_ATTENDANCE_DELETE_UI_DISABLED.md`

## Previous Delete UI Risk

Phase 6V guarded web `AttendanceController@destroy()` server-side so attendance rows are no longer deleted through ordinary web routes.

Remaining UI risk before this phase:

- `attendance.index` still rendered an active delete form per row.
- `attendance.show` still rendered an active delete form in the action panel.
- The forms used `POST` plus method spoofing to `DELETE`.
- The buttons could mislead users because deletion is now disabled server-side.
- The delete controls were not visibly policy-gated with `@can`.

## Index View Change

`resources/views/attendance/index.blade.php` no longer renders an active delete form in each row's action group.

Removed active behavior:

- delete `<form>`
- `method="POST"`
- `@method('DELETE')`
- active delete submit button
- delete confirmation prompt

Replacement:

```blade
<button type="button"
        class="btn btn-outline-secondary disabled"
        disabled
        title="Deletion is disabled until an audit-preserving correction workflow is enabled.">
    <i class="bi bi-trash"></i>
    <span class="visually-hidden">Delete disabled</span>
</button>
```

View and edit actions remain unchanged.

## Show View Change

`resources/views/attendance/show.blade.php` no longer renders an active delete form in the actions card.

Removed active behavior:

- delete `<form>`
- `method="POST"`
- `@method('DELETE')`
- active delete submit button
- delete confirmation prompt

Replacement:

```blade
<button type="button" class="btn btn-outline-secondary w-100" disabled>
    <i class="bi bi-trash"></i> Delete disabled
</button>
<div class="small text-muted">
    Deletion is disabled until an audit-preserving correction workflow is enabled.
</div>
```

Back, edit, and student report navigation remain unchanged.

## Edit View Confirmation

`resources/views/attendance/edit.blade.php` was inspected and not changed.

The edit view remains free of attendance delete UI.

## Active DELETE Form Confirmation

Focused view tests confirm:

- index view does not render active attendance delete method spoofing
- index view does not render the old delete confirmation prompt
- show view does not render active attendance delete method spoofing
- show view does not render the old delete confirmation prompt
- edit view does not render a delete form
- index/show render disabled deletion messaging or disabled controls

## Server-Side Destroy Guard Confirmation

`app/Http/Controllers/AttendanceController.php` was not changed in this phase.

The Phase 6V server-side guard remains the source of truth:

- authorization still runs
- `$attendance->delete()` remains unreachable
- route remains registered
- warning response remains in place

Targeted `AttendanceWebDestroyGuardTest` passed after the UI changes.

## API Destroy Guard Confirmation

API destroy was not changed in this phase.

The Phase 5M API destroy guard remains unchanged:

- returns HTTP `423`
- does not delete attendance
- keeps the audit-preserving correction workflow message

## Tests Created / Updated

Created:

- `tests/Feature/Attendance/AttendanceDeleteUiDisabledTest.php`

Coverage:

- `index_view_does_not_render_active_delete_form`
- `index_view_shows_delete_disabled_message_or_disabled_control`
- `show_view_does_not_render_active_delete_form`
- `show_view_shows_delete_disabled_message_or_disabled_control`
- `edit_view_does_not_render_delete_form`

The tests render views directly with minimal in-memory model objects. They do not execute the delete route and do not touch real/local MySQL.

## Commands Run

```powershell
Get-Content -Path resources/views/attendance/index.blade.php
Get-Content -Path resources/views/attendance/show.blade.php
Get-Content -Path resources/views/attendance/edit.blade.php
Get-Content -Path tests/Feature/Attendance/AttendanceWebDestroyGuardTest.php
Get-Content -Path docs/project-autopsy/PHASE_6V_WEB_ATTENDANCE_DESTROY_GUARD.md
php -l app/Http/Controllers/AttendanceController.php
php -l tests/Feature/Attendance/AttendanceDeleteUiDisabledTest.php
php artisan test --filter=AttendanceDeleteUiDisabledTest --env=testing
php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing
php artisan test --filter=AttendanceWebUpdateIdentityGuardTest --env=testing
```

## Test Result Summary

- `php -l app/Http/Controllers/AttendanceController.php`: PASS
- `php -l tests/Feature/Attendance/AttendanceDeleteUiDisabledTest.php`: PASS
- `php artisan test --filter=AttendanceDeleteUiDisabledTest --env=testing`: PASS, 5 tests / 15 assertions
- `php artisan test --filter=AttendanceWebDestroyGuardTest --env=testing`: PASS, 4 tests / 8 assertions
- `php artisan test --filter=AttendanceWebUpdateIdentityGuardTest --env=testing`: PASS, 7 tests / 14 assertions

Targeted PHPUnit runs emitted existing doc-comment metadata deprecation warnings from unrelated tests. No targeted test failed.

## Full Suite / Data Safety Confirmation

- No full test suite was run.
- No migrations were run.
- No schema changes were made.
- No SoftDeletes were added.
- No correction/void workflow was implemented.
- No real/local MySQL data was touched.
- No attendance deletes were performed against real/local MySQL.
- No attendance writes were performed against real/local MySQL.
- No export route was executed.
- No biometric sync or device command was run.

## Remaining Risks

- The server-side destroy route remains registered by design, guarded from Phase 6V.
- There is still no audit-preserving correction/void workflow.
- `Attendance` still does not use `SoftDeletes`.
- Route-name ambiguity remains between API and web attendance resources using generic names like `attendance.destroy`.
- Historical attendance rows may still require reconciliation/audit reports.
- Teacher attendance behavior remains a separate risk area and was not changed.

## Recommended Phase 6X Next Step

Phase 6X should perform a read-only audit of teacher attendance write/update/delete behavior, because teacher attendance remains intentionally untouched by the recent API/web attendance hardening sequence and may have separate class, status, update, and deletion assumptions.
