# Phase A Status Check — Results
## HelpingHand ERP · branch: academic-module-rebuild · run: 2026-07-21

Produced by executing `docs/plans/phase-a-status-check.md` (Task 2, read-only). All A4 items were re-verified independently against the live database/codebase (SQL, grep, `route:list`) — not taken from git log or commit messages.

---

## 1. Status table

| Item | Status | Evidence |
|---|---|---|
| 0.1 Untracked/uncommitted files | PARTIAL | Main checkout `git status -sb`: `?? docs/plan/`, `?? storage/backups/` (a stray duplicate plan file and the gitignored backup dir — no stray code edits). |
| 0.2 DB backup exists | DONE | `storage/backups/pre-academic-rebuild-2026-07-20.sql`, 7,174,884 bytes, dated 2026-07-20 21:27. |
| 0.3 Branch pushed/up to date | PARTIAL | `git status -sb` on main checkout: `academic-module-rebuild...origin/academic-module-rebuild [ahead 1, behind 5]`. Origin is fully up to date (all session work is pushed there). The **local main checkout has never merged** — still carries orphaned local commit `26d0d4b` (content-duplicate of origin's `33183ca`) and is missing 5 commits origin has. |
| A1.1 SchoolClass relations/fillable | DONE | `app/Models/SchoolClass.php:47-59` — `section()`, `academicSession()`, `teacher()` present; `$fillable:14-24` includes `section_id`, `academic_session_id`, `teacher_id`. |
| A1.2 SchoolClassController crash fixed | DONE | `SchoolClassController.php:27` eager-loads `['section','academicSession','teacher']` — all three verified real relations. |
| A1.3 school-classes views exist | DONE | `resources/views/admin/school-classes/{index,create,edit,show}.blade.php` all present. |
| A2.1 legacy_class_map table | DONE | `SHOW TABLES` confirms existence; `SELECT COUNT(*)` = 19. |
| A2.2 Row counts | DONE (evidence) | `class_management`=20, `school_classes`=24, `classes`=**absent from DB**, `sections`=10. |
| A2.3 Merge migration exists | DONE | `database/migrations/2026_07_23_100000_merge_class_management_into_school_classes.php` — explicit id 1-19 mapping, id 20 excluded. |
| A3.1 ClassManagement live consumers | **NOT DONE** | Fresh grep: 14 live files + 1 view still use `ClassManagement`/`class_management` for real logic: `Admin/AdminAdmissionController.php`, `Admin/AdminStudentController.php`, `Admin/ClassController.php`, `Admin/ClassTeacherController.php`, `Admin/DefaulterController.php`, `Admin/ExamController.php`, `Admin/SetupWizardController.php`, `ClassManagementController.php`, `ClassTeacherAssignmentController.php`, `SectionController.php`, `Teacher/TeacherSectionController.php`, `Models/Teacher.php`, `Models/TeacherClassAssignment.php`, `Policies/ClassTeacherPolicy.php`. |
| A3.2 subjects() location | DONE | Only `SchoolClass::subjects()` exists (`SchoolClass.php:62`). |
| A3.3 broken class_managements validation | DONE | Zero live-code hits for plural `class_managements`; `TeacherClassAssignmentController.php:51-52,113-114` validates against `exists:school_classes,id` / `exists:teachers,id`. |
| A4.1 bare classes table/routes | DONE *(re-verified via SQL + route:list)* | `information_schema.tables` has no `classes` row; `route:list` has no `Admin\ClassController` or bare `classes` resource entries. |
| A4.2 attendances_temp/student_attendance | DONE *(re-verified via SQL)* | Neither table exists in `information_schema.tables`. |
| A4.3 SmartAttendanceController fake biometric | DONE *(re-verified via grep)* | Zero matches for `rand`/`biometric`/`mock`/`fake`/`simulat` in the file (case-insensitive). |
| A4.4 7 dead ClassTeacherAssignmentController routes | DONE *(re-verified via route:list + reading the controller)* | `route:list --name=class-teacher-assignments` shows exactly 7 routes, and all 7 corresponding methods (`index/create/store/show/edit/update/destroy`) genuinely exist on the controller — nothing dead remains. |
| A4.5 orphaned TeacherSubjectAssignment model | DONE *(re-verified via find)* | File does not exist. |
| A4.6 TeacherClassSubjectAssignmentController | DONE *(re-verified via find + route:list)* | File does not exist; no matching routes registered. |
| A4.7 PromotionService | DONE *(re-verified via find)* | File does not exist anywhere under `app/Services`. |
| A4.8 phantom ClassManagementPolicy | DONE *(re-verified via grep + find)* | `AuthServiceProvider.php:22` has only a removal comment; `app/Policies/ClassManagementPolicy.php` does not exist as a file. |

---

## 2. The leftover class_management row

`class_management` id 20 ("TestClass", order 0, no section/stream/description) is the one row the A2 merge migration deliberately excluded. Dependency chain investigated:

- **Zero students** reference it (`SELECT COUNT(*) FROM students WHERE class = 'TestClass'` → 0).
- **No `school_classes` counterpart** exists under any name — nothing to link it to even if migrated.
- Its **only live reference anywhere** is one row in the `class_teacher` pivot table (id=1, `class_id=20`, `teacher_id=136`).
- Teacher id 136 is itself test data: name "Test T", email `testt@example.com`, `user_id` NULL (no real login), no subject specialization, and this pivot row is its only association in the entire database (0 rows in `teacher_class_assignments`, 0 elsewhere).

**Recommendation: DELETE.** No real data to preserve — the row, its one pivot reference, and the teacher it points to form a self-contained island of test fixtures with zero production footprint.

*(Not acted on — read-only session.)*

---

## 3. Verdict

Phase A (A1–A4, 17 checkpoints) is **~94% done (16/17)** — every demolition item and the data merge check out cleanly against the live code and database, independent of commit-message claims. The single open item is **A3.1**, and it is also the **specific item that blocks Phase C2**: C2's UDISE export requires one reliable source of truth for each student's class/section, but 14 files still read and write `ClassManagement` directly instead of the canonical `SchoolClass`/`legacy_class_map` path — so a student's "real" class can differ depending on which of those 14 code paths touched their record last. C2's schema work (new columns, the `aadhar_number`→`aadhaar_number` rename, validation) is **not** blocked by this and could proceed in parallel, but the export deliverable specifically should not be built until A3.1 closes. Separately, **0.3's unmerged main checkout** doesn't block C2 technically but is a real process risk if C2 work starts there before reconciling.

**Unrelated problems noticed (untouched):** `app/Http/Controllers/Admin/ClassController.php` is orphaned dead code — its routes were cut (per the A4.4 diff, as a "duplicate registration... retired in favor of SchoolClassController"), but its internal logic still calls `ClassManagement::` directly (6 call sites) and was never repointed to `SchoolClass`. It's unreachable, not migrated — a one-line delete, but not evidence that A3 is further along than measured above.

No files were edited, no migrations run, during the audit itself.
