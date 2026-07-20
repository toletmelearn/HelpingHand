# Phase 5D - Attendance Bulk Preflight UI (Read-only)

Date: 2026-06-05

Scope: Add a read-only UI preview for bulk attendance preflight results so admins can preview risks before using existing write flows. No writes, no apply path, no schema changes.

Files inspected:
- routes/web.php
- app/Http/Controllers/AttendanceController.php
- app/Services/Attendance/AttendanceBulkPreflightService.php
- resources/views/attendance/bulk_mark.blade.php
- resources/views/attendance/preflight-result.blade.php
- resources/views/attendance/create.blade.php
- tests/Feature/Attendance/AttendancePreflightUiTest.php
- tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php
- tests/Unit/Services/AttendanceBulkPreflightServiceTest.php
- docs/project-autopsy/PHASE_5C_ATTENDANCE_PREFLIGHT_ENDPOINT.md
- docs/project-autopsy/PHASE_5B_ATTENDANCE_BULK_PREFLIGHT_SERVICE.md
- docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md

Files changed:
- routes/web.php (added POST admin/attendance/preflight-view)
- app/Http/Controllers/AttendanceController.php (added `preflightView` mapping + view rendering)
- resources/views/attendance/bulk_mark.blade.php (added Preview button)
- resources/views/attendance/preflight-result.blade.php (new read-only Blade view)
- tests/Feature/Attendance/AttendancePreflightUiTest.php (new UI feature tests)

Existing bulk attendance UI findings:
1. The bulk attendance form is `resources/views/attendance/bulk_mark.blade.php`.
2. The form currently posts to `route('attendance.store')` (POST /admin/attendance via admin prefixed resource).
3. Inputs used by the form (observed):
   - `date` (input name `date`)
   - `classes[]` (legacy class string values)
   - `period` (input name `period`)
   - `subject` (input name `subject`)
   - `default_status` (radio: present/absent/late/half_day)
   - The form **does not** send per-student `student_ids`/`statuses` by default — it expands server-side in `store()`.
4. The form posts directly to attendance store (bulk store path stays unchanged).
5. The UI uses legacy class strings (`class` column) rather than canonical `class_id`.

Route added:
- POST /admin/attendance/preflight-view → `AttendanceController@preflightView`
  - Route name: `attendance.preflight-view` (admin prefixed `admin.attendance.preflight-view`).

Controller/view behavior:
- `AttendanceController@preflightView`:
  - Authorizes with `viewAny` on `Attendance` (same as other read ops).
  - Accepts bulk form inputs: `date`, `period`, `class`, `classes[]`, `class_id`, `section_id`, `default_status`, and `attendance_rows`.
  - If `attendance_rows` present, forwards them to `AttendanceBulkPreflightService` unchanged.
  - Otherwise, expands `classes[]` or `class` into per-student `attendance_rows` by querying `students` where `class` equals legacy class string and assigning `status` from `default_status`.
  - Calls `AttendanceBulkPreflightService::preflight($payload)` and renders `resources/views/attendance/preflight-result.blade.php` with the `result` array.

Payload mapping behavior:
- The view/controller maps legacy bulk form into service payload:
  - `date` => `date`
  - `period` => `period`
  - `class` => `class` (legacy)
  - `class_id` => `class_id` if provided
  - `section_id` => `section_id` if provided
  - `attendance_rows` => an array of rows: `['student_id'=>..., 'status'=>..., 'remarks'=>...]`
- This mapping preserves service expectations and uses `Student::where('class', $class)` to expand class selections.

Read-only safety confirmation:
- The preflight view only calls the read-only `AttendanceBulkPreflightService` and renders results.
- The view/controller do NOT call `Attendance::insert()`, `AttendanceService::markAttendance`, `DB::transaction`, or any write methods.
- The `bulk_mark` view now includes a `Preview` button that posts to the preflight view; the existing `Mark Attendance` submit remains and still posts to the store route.

Confirmation that no apply/confirm/write button was added:
- `preflight-result.blade.php` intentionally shows summary, lists, and warnings, and contains only Back/Return links. No Apply/Confirm/Mark Attendance buttons were added.

Tests created/updated:
- tests/Feature/Attendance/AttendancePreflightUiTest.php (new) — 7 tests:
  1. preflight_view_route_is_registered
  2. preflight_view_renders_summary_without_writing_attendance
  3. preflight_view_displays_existing_attendance_warning
  4. preflight_view_displays_terminal_student_skip
  5. preflight_view_does_not_render_apply_or_confirm_button
  6. existing_preflight_json_endpoint_still_passes
  7. existing_attendance_store_route_is_not_changed
- Existing tests retained:
  - tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php
  - tests/Unit/Services/AttendanceBulkPreflightServiceTest.php

Commands run:
- php -l app/Http/Controllers/AttendanceController.php
- php -l routes/web.php
- php -l resources/views/attendance/preflight-result.blade.php
- php -l resources/views/attendance/bulk_mark.blade.php
- php -l tests/Feature/Attendance/AttendancePreflightUiTest.php
- php artisan route:list | Select-String "attendance/preflight"
- php artisan test --filter=AttendancePreflightUiTest --env=testing
- php artisan test --filter=AttendanceBulkPreflightEndpointTest --env=testing
- php artisan test --filter=AttendanceBulkPreflightServiceTest --env=testing

Test result summary:
- AttendancePreflightUiTest: 7 passed (15 assertions)
- AttendanceBulkPreflightEndpointTest: 6 passed (18 assertions)
- AttendanceBulkPreflightServiceTest: 12 passed (16 assertions)

Confirmation no existing write behavior was changed:
- The `store()` method in `AttendanceController` remains unchanged; bulk insert logic unchanged.
- Tests assert the store route still exists and that the preflight path is read-only.

Confirmation no full suite was run:
- Only targeted lint and tests listed above were executed. No full test suite was run.

Confirmation no migrations/schema/real MySQL/device sync were touched:
- No migration files modified, no `php artisan migrate` commands run.
- All tests used SQLite in-memory; no real MySQL data accessed.
- No biometric device sync calls added or executed.

Remaining risks:
- The preview expands classes by querying `students` table using legacy `class` string; if production data uses `class_id` inconsistently, some students may be omitted from preview.
- Authorization relies on existing `viewAny` policy; ensure admin users have role data in production.
- The Preview button uses the same form and CSRF token; double-check client-side behavior across browsers.

Recommended Phase 5E next step:
- Add an explicit per-row preflight in the per-student UI (attendance create/edit) and a protected read-only admin page to compare preflight vs. actual store output; then design a safe transactional apply flow with audit logs and user confirmation.


