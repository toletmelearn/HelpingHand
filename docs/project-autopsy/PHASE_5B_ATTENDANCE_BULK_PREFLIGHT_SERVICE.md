# Phase 5B - Attendance Bulk Preflight Service

Date: 2026-06-05

Scope: Implement a read-only preflight/dry-run validator and normalizer for bulk attendance submissions. This phase is strictly read-only: no schema changes, no controller wiring, no data mutations.

Files inspected:
- app/Http/Controllers/AttendanceController.php
- app/Http/Controllers/API/AttendanceController.php
- app/Http/Controllers/Teacher/TeacherAttendanceController.php
- app/Services/AttendanceService.php
- app/Models/Attendance.php
- app/Models/Student.php
- app/Models/SchoolClass.php
- app/Models/Section.php
- app/Models/StudentStatus.php
- database/migrations/2026_01_21_083000_create_attendances_table.php
- database/migrations/2026_02_18_120158_create_student_attendance_system.php
- docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md
- docs/project-autopsy/PHASE_3C_STUDENT_CLASS_COMPATIBILITY_LAYER.md
- docs/project-autopsy/PHASE_3M_PASSED_OUT_STATUS_FIX.md
- docs/project-autopsy/PHASE_3S_STUDENT_STATUS_CRUD_RESTRICTION.md

Files changed:
- app/Services/Attendance/AttendanceBulkPreflightService.php (new)
- tests/Unit/Services/AttendanceBulkPreflightServiceTest.php (new)

Service class summary:
- `App\Services\Attendance\AttendanceBulkPreflightService` exposes `preflight(array $payload): array`.
- It is strictly read-only: it queries `students`, `student_statuses`, and `attendances`, and returns structured diagnostics without writing.

Preflight input contract:
- `date` (string YYYY-MM-DD)
- `period` (string|null)
- `class_id`, `section_id`, `class` (optional payload-level hints)
- `attendance_rows`: array of rows with `student_id`, `status`, `remarks` (optional)

Preflight output contract:
- `summary`: counts for total, valid, errors, warnings, would_create/would_update/would_skip
- `normalized`: per-row normalized data (student info, legacy/canonical class, existing attendance id, action create/update/skip/error)
- `errors`: per-row errors
- `warnings`: per-row warnings
- `is_valid`: boolean

Validation/detection behavior:
- Missing/invalid `date` detected.
- Missing/invalid `student_id` detected.
- Invalid status (allowed: present, absent, late, half_day) detected.
- Duplicate rows inside payload detected by `student_id|date|period` key.
- Existing attendance detected by querying `attendances` for `student_id`,`date`,`period`.
- Legacy class mismatch: compares `attendances.class` vs current `student.class` and warns.
- Payload vs student `class_id`/legacy `class`/`section_id` mismatches produce warnings.
- Terminal/inactive student statuses (`passed_out`,`left_school`,`tc_issued`,`inactive`) are detected via latest `student_statuses` row per student and will set action `skip`.
- Soft-deleted students (non-null `deleted_at`) generate a warning.

Latest student status behavior:
- Latest status is determined by the highest `student_statuses.id` per student (query orders by `id` desc and takes first). No rows → defaults to `active`.

Existing attendance duplicate behavior:
- If an existing attendance row is found for the same `student_id`,`date`,`period`, the preflight marks the normalized row `existing_attendance_id` and recommends `update` (unless terminal skip applies). No writes performed.

Terminal/inactive handling:
- Terminal statuses cause the row's `action` to be `skip` and a `terminal_status_*` warning is emitted.

Tests created:
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php` — uses in-memory SQLite and creates minimal schema (students, student_statuses, attendances, school_classes, sections) in test `setUp`.
- Tests added (file includes):
  1. preflight_reports_missing_date
  2. preflight_reports_invalid_student_id
  3. preflight_accepts_valid_present_absent_late_half_day_statuses
  4. preflight_detects_duplicate_rows_in_payload
  5. preflight_detects_existing_attendance_for_student_date_period
  6. preflight_excludes_passed_out_student
  7. preflight_excludes_inactive_student
  8. preflight_detects_student_class_id_conflict
  9. preflight_detects_class_mismatch_from_payload
 10. preflight_detects_section_mismatch_from_payload
 11. preflight_returns_create_action_for_clean_new_row
 12. preflight_does_not_modify_database

Commands run:
- PHP lint target files (performed next in verification).
- PHPUnit filter for the new test (performed next).

Test result summary:
- (To be filled after running the targeted test command below.)

Confirmations:
- No controllers or write paths were modified.
- No migrations or schema changes were made.
- No real MySQL or production data were read or modified; tests use SQLite in-memory only.
- Full test suite was not run; only the targeted test filter should be executed.

Remaining risks:
- This preflight service depends on current model columns names (`class`, `class_id`, `section_id`, `deleted_at`) — if models diverge, adapt.
- It reports warnings for legacy `class` drift but cannot reconcile or migrate data.

Recommended Phase 5C next step:
- Implement a read-only preflight endpoint to surface these results in the UI and require explicit approval before bulk writes; then implement a safe, transactional apply path that honours `skip` and `update` policies.
