# PHASE 4E - Safe Student Import Apply Flow Audit

## Files inspected

- `app/Http/Controllers/StudentController.php`
- `app/Services/Students/StudentImportNormalizer.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `resources/views/students/import-preview.blade.php`
- `resources/views/students/index.blade.php`
- `routes/web.php`
- `tests/Feature/Students/StudentImportPreviewTest.php`
- `tests/Feature/Students/StudentImportDirectRouteGuardTest.php`
- `tests/Unit/Services/StudentImportNormalizerTest.php`
- `docs/project-autopsy/PHASE_4B_STUDENT_IMPORT_NORMALIZER_DRY_RUN.md`
- `docs/project-autopsy/PHASE_4C_STUDENT_IMPORT_PREVIEW.md`
- `docs/project-autopsy/PHASE_4D_STUDENT_IMPORT_DIRECT_WRITE_GUARD.md`

## Commands run

- `php -l app/Http/Controllers/StudentController.php`
- `php -l app/Services/Students/StudentImportNormalizer.php`
- `php -l app/Models/Student.php`
- `php artisan route | Select-String "students/import"`
- `php artisan route | Select-String "students.export"`
- `php artisan route | Select-String "students.import"`
- `php artisan route:list | Select-String "students/import"`
- `php artisan route:list | Select-String "students.export"`
- `php artisan route:list | Select-String "students.import"`
- `php artisan tinker --execute="dump(Schema::getColumnListing('students'));"`
- Read-only `php artisan tinker --execute=...` checks for missing `admission_no`, `mobile`, and `phone`
- Read-only `php artisan tinker --execute=...` checks for duplicate candidate values in `aadhar_number`, `roll_number`, `phone`, and `mobile`
- `rg` searches for student import, preview, direct import, and apply-related references

Notes:

- The requested `php artisan route ...` command is not available in this Laravel version. It returned Artisan namespace help only.
- Equivalent read-only route inspection was performed with `php artisan route:list`.
- Two initial read-only tinker count attempts failed due to command quoting/parse errors. They did not write data. The checks were rerun successfully.

## Current import state map

| Area | Current state | Risk |
| --- | --- | --- |
| Preview route | `POST students/import/csv/preview`, route name `students.import.csv.preview`, handled by `StudentController@previewImportCsv` | Safe preview-only path exists |
| Direct import route | `POST students/import/csv`, route name `students.import.csv`, handled by `StudentController@importCsv` | Route remains registered but Phase 4D guard returns before legacy writes |
| Export route | `GET students/export/csv`, route name `students.export.csv` | Still exports legacy-style CSV data |
| Visible import form | `resources/views/students/index.blade.php` posts to `students.import.csv.preview` | Safe: visible UI no longer posts directly to write route |
| Preview view | `resources/views/students/import-preview.blade.php` displays dry-run results | Safe: no apply/import-now controls |
| Normalizer | `StudentImportNormalizer::normalizeRow()` resolves class/section into canonical and compatibility fields | Safe: read-only |
| Old legacy import code | Still present below the early guard in `StudentController@importCsv()` | Unreachable now, but risky if guard is removed incorrectly later |

## Apply flow option comparison

| Option | Summary | Security | Stale data risk | Duplicate risk | UX | Complexity | Testability |
| --- | --- | --- | --- | --- | --- | --- | --- |
| A. Session-backed apply | Preview stores normalized clean rows in session, then apply imports from session | Good with auth, CSRF, nonce/hash, and expiry | Medium; mitigate with short TTL and revalidation | Medium; mitigate by rechecking duplicates at apply | Simple and familiar | Low | High |
| B. Temporary file/token-backed apply | Preview stores file or normalized payload with signed token | Good if storage and token handling are careful | Medium; needs cleanup and expiry | Medium; still needs revalidation | Better for larger uploads | Medium/high | Medium |
| C. No apply flow yet | Keep preview-only and require manual entry | Safest for data | None | None | Poor for bulk import | Low | High |

Recommended option for Phase 4F: **Option A, session-backed apply**.

This is the safest next implementation because the current preview is already web/session oriented, the flow can be kept POST-only and CSRF-protected, and it avoids persistent uploaded-file storage. It should still revalidate duplicates and normalized rows immediately before writing.

## Recommended apply design

Add a future apply route only after preview:

- `POST students/import/csv/apply`
- Suggested route name: `students.import.csv.apply`
- Keep `students.import.csv` guarded.
- Do not reuse the old legacy import write loop.

Preview should:

- Parse the uploaded CSV/Excel file.
- Normalize every row with `StudentImportNormalizer`.
- Build row-numbered errors and warnings.
- Store a preview payload in session with:
  - a nonce or preview ID
  - timestamp/expiry
  - row count
  - normalized rows
  - summary counts
  - a hash of the normalized payload
- Show an Apply button only when there are zero errors and zero warnings.

Apply should:

- Be POST-only.
- Require the preview nonce/hash from the session.
- Reject missing, expired, or mismatched preview payloads.
- Re-run duplicate checks immediately before writing.
- Block apply if errors or duplicate warnings are present.
- Import all rows inside a single `DB::transaction()`.
- Use normalized payload fields:
  - `class_id`
  - `school_class_id`
  - `class`
  - `section_id`
  - `section`
- Explicitly assign fields not covered by mass assignment.
- Clear the session preview after successful apply.
- Prevent repeated apply of the same preview.

## Apply blocking rules

Recommended initial blocking policy:

- Rows with errors must block apply.
- Duplicate warnings should block apply initially.
- Duplicate checks should include at least:
  - `aadhar_number`
  - `roll_number`
  - `phone`
  - `mobile`
- Missing required class or section resolution should block apply.
- Missing required core student data should block apply where the current import/model contract requires it.
- Expired, missing, or mismatched preview sessions should block apply.
- Repeated apply should be blocked by clearing or invalidating the preview session after use.

Duplicate warnings should block apply because Phase 4F should prefer correctness over convenience. A later phase can add explicit admin override behavior if the school wants controlled duplicate handling.

## Student field contract

Observed `students` columns:

- `id`
- `user_id`
- `guardian_id`
- `name`
- `photo`
- `father_name`
- `mother_name`
- `guardian_name`
- `date_of_birth`
- `aadhar_number`
- `admission_no`
- `address`
- `phone`
- `mobile`
- `gender`
- `category`
- `class`
- `class_id`
- `school_class_id`
- `section_id`
- `section`
- `roll_number`
- `religion`
- `caste`
- `blood_group`
- `nationality`
- `medical_history`
- `previous_school`
- `created_at`
- `updated_at`
- `deleted_at`
- `is_verified`

Future apply payload should support:

- `name`
- `father_name`
- `mother_name`
- `date_of_birth`
- `aadhar_number`
- `phone`
- `mobile`
- `gender`
- `category`
- `class_id`
- `school_class_id`
- `class`
- `section_id`
- `section`
- `roll_number`
- `religion`
- `caste`
- `blood_group`
- `address`
- `admission_no` if added to the import template

Fields requiring care:

- `school_class_id` is not currently in `Student::$fillable`; apply should explicitly assign it or update fillable in a focused tested phase.
- `section_id` is not currently in `Student::$fillable`; apply should explicitly assign it or update fillable in a focused tested phase.
- `class_id` is fillable, but apply should still keep it aligned with `school_class_id`.
- `section` should remain the section ID string temporarily for compatibility.
- `admission_no` exists but current data has many missing values; import template behavior should be decided before making it required.

Fields to ignore in the first safe apply flow:

- `id`
- `user_id`
- `guardian_id`
- `photo`
- `nationality`
- `medical_history`
- `previous_school`
- `is_verified`
- password/user creation fields

The apply flow must not create default users or introduce a default password such as `123456`. User account creation should remain a separate designed workflow.

## Read-only data observations

- Total students checked: `760`
- Students missing `admission_no`: `760`
- Students missing `mobile`: `0`
- Students missing `phone`: `0`
- Duplicate `aadhar_number` values: `0`
- Duplicate `roll_number` values: `10`
- Duplicate `phone` values: `0`
- Duplicate `mobile` values: `0`

These counts were gathered with read-only SELECT/count checks only.

## Test plan for Phase 4F

Suggested isolated tests:

- Preview with row errors cannot apply.
- Preview with duplicate warnings cannot apply.
- Clean preview imports normalized `class_id`, `school_class_id`, `class`, `section_id`, and `section`.
- Apply writes all rows inside a transaction.
- Apply rolls back all rows if one row fails.
- Direct `students.import.csv` remains guarded.
- Old legacy import path remains unreachable.
- Apply does not create users or default passwords.
- Repeated apply of the same preview is prevented.
- Missing session preview is rejected.
- Expired or mismatched preview hash is rejected.

## Safe Phase 4F implementation plan

1. Extract reusable parsing helpers from `StudentController` only if needed, preserving Phase 4C preview behavior.
2. Update preview to store a normalized preview payload in session with a nonce, hash, timestamp, and summary.
3. Show an Apply button only when the preview has zero errors and zero warnings.
4. Add a new POST-only apply route.
5. Implement apply using session payload validation, duplicate recheck, and `DB::transaction()`.
6. Create students using normalized class/section values.
7. Explicitly assign `school_class_id` and `section_id` if `Student::$fillable` remains unchanged.
8. Clear the preview session after successful apply.
9. Keep the direct import guard from Phase 4D unchanged.
10. Add isolated tests for apply success, blocking, rollback, and no default password/user creation.

Do not change export in Phase 4F. Export/template alignment should be a later phase after the apply contract is proven.

## Top apply-flow risks

1. Stale preview data could be applied after class, section, or duplicate state changes.
2. Duplicate values could appear between preview and apply unless rechecked inside apply.
3. Partial imports could occur without a transaction.
4. `school_class_id` and `section_id` could be silently omitted because they are not currently fillable.
5. The old legacy import write loop could be accidentally re-enabled and bypass normalization.

## Confirmation

- No application code was modified in this phase.
- No routes were modified in this phase.
- No views, controllers, services, models, or migrations were modified in this phase.
- No students were imported, created, updated, deleted, seeded, promoted, or passed out.
- No migrations, schema changes, composer setup, database write commands, or full test suite were run.
- Real/local MySQL data was inspected only through read-only schema/count queries.

## Remaining risks

- A safe apply route does not exist yet.
- The direct import route is guarded but still registered.
- The old legacy write loop remains in the controller below the guard.
- Export/sample CSV still encourages legacy Class/Section-only data.
- Duplicate `roll_number` values already exist and should be handled carefully before apply is enabled.

## Recommended next step

Phase 4F should implement the session-backed apply flow with duplicate warnings as blockers, transaction-protected writes, normalized class/section fields, and the direct import guard preserved.
