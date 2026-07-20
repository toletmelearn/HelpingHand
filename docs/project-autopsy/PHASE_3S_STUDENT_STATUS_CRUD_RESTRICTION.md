# Phase 3S - Student Status CRUD Restriction

## Files Inspected

- `app/Http/Controllers/Admin/StudentStatusController.php`
- `app/Models/StudentStatus.php`
- `resources/views/admin/student-statuses/create.blade.php`
- `resources/views/admin/student-statuses/edit.blade.php`
- `resources/views/admin/student-statuses/index.blade.php`
- `resources/views/admin/student-statuses/show.blade.php`
- `tests/Feature/Admin/StudentPassedOutStatusTest.php`
- `tests/Feature/Admin/StudentStatusShowViewTest.php`
- `docs/project-autopsy/PHASE_3R_MANUAL_STUDENT_STATUS_WORKFLOW_AUDIT.md`
- `docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md`
- `docs/project-autopsy/PHASE_3Q_STUDENT_STATUS_SHOW_VIEW_FIX.md`

## Files Changed

- `app/Http/Controllers/Admin/StudentStatusController.php`
- `resources/views/admin/student-statuses/create.blade.php`
- `resources/views/admin/student-statuses/edit.blade.php`
- `tests/Feature/Admin/StudentStatusCrudRestrictionTest.php`
- `docs/project-autopsy/PHASE_3S_STUDENT_STATUS_CRUD_RESTRICTION.md`

## Controller Validation Restriction Summary

Generic student status CRUD now allows only:

- `active`
- `inactive`

`StudentStatusController@store()` and `StudentStatusController@update()` now reject:

- `passed_out`
- `left_school`
- `tc_issued`

A validation message was added for rejected terminal statuses:

> Terminal statuses such as Passed Out, Left School, and TC Issued must be handled through their dedicated workflows.

No changes were made to `index()`, `show()`, or `destroy()`.

## Create/Edit Dropdown Changes

The create and edit status dropdowns now expose only:

- Active
- Inactive

Removed from generic create/edit forms:

- Passed Out
- TC Issued
- Left School

Both forms now include helper text:

> Passed Out, TC Issued, and Left School require dedicated workflows and are not available from this generic status form.

## Terminal Status Read / Display Preservation

Existing terminal status records remain listable and showable.

The show view was not restricted and still displays existing terminal statuses such as `passed_out`. Phase 3Q's safe class/section display remains intact.

No terminal status rows were deleted, changed, or repaired.

## Tests Created / Updated

Created:

- `tests/Feature/Admin/StudentStatusCrudRestrictionTest.php`

Tests added:

- `generic_status_store_accepts_active`
- `generic_status_store_accepts_inactive`
- `generic_status_store_rejects_passed_out`
- `generic_status_store_rejects_left_school`
- `generic_status_store_rejects_tc_issued`
- `generic_status_update_rejects_terminal_status`
- `create_form_does_not_show_terminal_status_options`
- `edit_form_does_not_show_terminal_status_options`
- `show_view_can_still_display_existing_terminal_status`

The test file uses an isolated SQLite-memory schema and does not use full project migrations.

## Commands Run

- `php -l app/Http/Controllers/Admin/StudentStatusController.php`
- `php -l app/Models/StudentStatus.php`
- `php -l tests/Feature/Admin/StudentStatusCrudRestrictionTest.php`
- `php artisan test --filter=StudentStatusCrudRestrictionTest --env=testing`
- `php artisan test --filter=StudentStatusShowViewTest --env=testing`
- `php artisan test --filter=StudentPassedOutStatusTest --env=testing`
- `php artisan test --filter=AdvancedReportStudentStatusAnalyticsTest --env=testing`

## Test Result Summary

- `StudentStatusCrudRestrictionTest`: 9 passed, 32 assertions
- `StudentStatusShowViewTest`: 4 passed, 7 assertions
- `StudentPassedOutStatusTest`: 5 passed, 19 assertions
- `AdvancedReportStudentStatusAnalyticsTest`: 5 passed, 10 assertions

PHPUnit emitted existing doc-comment metadata deprecation warnings from unrelated tests during discovery. No targeted test failed.

## Failures And Fixes

No failures occurred in the targeted test runs.

## Safety Confirmations

- No full test suite was run.
- No migrations were run.
- No schema files were changed.
- No real/local MySQL data was touched.
- Existing students and status rows were not bulk-updated.
- Phase 3M passed-out workflow was not changed.
- `StudentPromotionController` was not changed.
- Promotion routes were not changed.
- `AdvancedReportController` was not changed.
- CSV/import/API student writes were not changed.

## Remaining Risks

- Historical terminal status rows may still have class/section drift from earlier manual CRUD usage.
- Generic `destroy()` can still delete existing terminal status records; this phase intentionally did not change delete behavior.
- `left_school` and `tc_issued` still need dedicated workflows before they should be enabled as terminal transitions.
- Existing terminal records remain editable, but cannot be saved with terminal status through generic update. A later phase may need a terminal-status metadata-only edit path.
- No bulk reconciliation was performed for students whose latest terminal status conflicts with class/section fields.

## Recommended Next Step

Phase 3T should be a read-only reconciliation audit for existing terminal status drift:

1. Identify latest terminal statuses with populated `class_id`, `school_class_id`, `section_id`, or `section`.
2. Compare `student_statuses`, student class compatibility fields, and `student_promotion_logs`.
3. Decide whether a safe manual repair report or dedicated reconciliation command is needed.
4. Do not bulk repair data until the mapping is reviewed.
