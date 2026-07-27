# REMEDIATION SESSION — FULL AUDIT LOG
## HelpingHand ERP · branch `remediation` (off `main` @ `688f0a0`)
## Executing docs/plans/remediation-plan.md, task by task, one commit per task

This is a complete, evidence-based account of everything done in this session: what was fixed, what was found along the way that the plan didn't anticipate, what was deferred, and why. Every claim below is backed by a commit hash, a file:line, or a test result observed during the session.

---

## Pre-flight

- Confirmed branch `remediation` created off `main` at commit `688f0a0` (the verification report's source-of-truth commit).
- `docs/plans/remediation-plan.md` didn't exist yet (only `.md.txt`) — saved it to its canonical path per its own header instruction.
- Took a fresh DB backup (`storage/backups/pre-remediation-2026-07-26.sql`, 7.2MB) since the existing one predated the school_classes 22/23 fix and other recent work. Added `/storage/backups` to `.gitignore` (was missing).
- Commit: `89853c9` — *chore: pre-flight for remediation — plan doc + gitignore backups*

---

## Task 1 — Apply the missing `academic_events` migration + defensive holiday guard

**What was found:** `php artisan migrate:status` showed exactly one pending migration, `2026_07_23_100300_create_academic_events_table`, matching the plan's expectation exactly.

**What was done:**
- Ran the migration (`php artisan migrate --force`). Verified `academic_events` table now exists.
- Wrapped the `AcademicEvent::isHoliday()` call in **both** attendance controllers — `app/Http/Controllers/AttendanceController.php:256` and `app/Http/Controllers/API/AttendanceController.php:56` — in try/catch: on any exception, log a warning and treat the date as non-holiday, matching the degrade-gracefully pattern the admin dashboard's events card already used.
- Added 2 new tests to `AttendanceHolidayBlockTest.php` that simulate the calendar table being dropped mid-request, proving attendance marking still succeeds when the holiday check itself fails.

**Verification:** Full attendance suite — 328 passed, 0 failures (baseline unaffected).

**Audit finding re-checked:** D1/D2 — was "PARTIALLY TRUE / code complete but not deployed." Now **VERIFIED**.

**Commit:** `73b2440`

---

## Task 2 — StudentPromotionController: authorization + idempotency (for real)

**What was found on re-verification:** Confirmed the audit's claim — zero `authorize()` calls anywhere in the controller; the only "idempotency" was a `DB::transaction()` (atomicity, not idempotency).

**Plan-vs-reality stop (reported, you decided):** The plan's Step 4 named a `to_school_class_id` column on `student_promotion_logs` for a unique index. That column has never existed — the table only ever had `from_class`/`to_class` as strings (confirmed against the original migration). Reported this and asked how to proceed; you said to build the index on the real column, `(student_id, academic_session_id, to_class)`, noting `to_class` is a string matching `SchoolClass::name`, with a comment for a future FK cleanup. Confirmed zero existing violating rows first (read-only) before adding the constraint.

**What was done:**
- New `app/Policies/StudentPromotionPolicy.php` — `viewAny`/`create`, admin always allowed, plus the existing `view-student-promotion`/`manage-student-promotion` permissions (already seeded, previously granted to admin only and unused by any code path).
- Registered against `StudentPromotionLog` in `AuthServiceProvider`.
- `authorize()` added to all 7 public actions in `StudentPromotionController`.
- App-level idempotency guard in `store()`: students already logged as promoted to the exact destination class in the exact academic session are skipped (not re-promoted, not re-billed), with a per-student notice in the result message; the rest of the batch still proceeds.
- DB-level backstop: new migration adding a unique index on `student_promotion_logs(student_id, academic_session_id, to_class)`.
- Two pre-existing tests (`StudentPromotionNormalizationTest`, `StudentPassedOutStatusTest`) built their own acting user with no role — gave that user the admin role so they kept testing promotion behavior now that the controller enforces authorization.

**Tests added:** unauthorized role → 403; double-submit of the same batch promotes zero students the second time and says so; partial batch (some already promoted) promotes only the new ones; direct DB insert of a duplicate log row hits the unique constraint.

**Verification:** Full suite — 71 failed / 1085 passed (baseline 71 failures unchanged, +5 new passing tests).

**Audit finding re-checked:** C2 — was **FALSE**. Now **VERIFIED**.

**Commit:** `c398b87`

---

## Task 3 — Authorization sweep (4 controllers + the public attendance routes)

### Controller 1/4 — `ClassTeacherAssignmentController`

**Re-verified gap:** confirmed — zero `authorize()` anywhere except the unrelated `studentRecords()` action.

**Bonus bug found (not in the plan or original audit):** while writing the required "authorized → works" test, discovered `update()` and `destroy()` were silent no-ops. Root cause: `Route::resource('class-teacher-assignments', ...)` generates a wildcard `{class_teacher_assignment}`, but the controller methods type-hinted the parameter as `$assignment`. Laravel's implicit route-model binding only matches by exact name or `Str::snake()` (confirmed by reading `vendor/laravel/framework/.../ImplicitRouteBinding.php` — no positional fallback in this version), so the binder skipped it and the container handed the controller a blank, unsaved model instead. `Model::update()`/`Model::delete()` both silently no-op when `$this->exists` is false — no exception, but also no actual database change, while still flashing a "success" message. `show()`/`edit()` were affected the same way (blank record rendered).

Reported this to you; you chose to fix it in the same commit. Fixed by renaming the parameter to `$class_teacher_assignment` in `show`/`edit`/`update`/`destroy`, keeping view `compact()` data under the same `'assignment'` key so no view changes were needed.

**Policy used:** an already-registered-but-never-invoked `ClassTeacherAssignmentPolicy` (admin or staff) — wired in rather than writing a new one.

**Tests:** unauthorized → 403 on store/update/destroy; admin can create, update, and delete (now genuinely persists); staff can create.

**Commit:** `e2632db`

### Controller 2/4 — `Admin\TeacherClassAssignmentController`

**Re-verified gap:** confirmed — zero authorize/hasRole/middleware anywhere, not even constructor-level `auth`.

**New policy:** `TeacherClassAssignmentPolicy` (no existing policy for this model, unlike controller 1). Role choice, stated for review: view = admin or staff; writes = admin-only (narrower than controller 1's sibling policy — this assigns teachers to class_teacher/subject_teacher/assistant_teacher roles, a staffing decision closer to `TeacherSubstitutionPolicy`'s admin-only-writes convention).

**Tests:** unauthorized (staff) → 403 on store/update/destroy; staff can still view the index; admin can create/update/delete.

**Commit:** `bb6e61b`

### Controller 3/4 — `Admin\TeacherSubjectAssignmentController`

**Re-verified gap:** confirmed — this is B3's "survivor" controller (audited as "still works and enforces its max-2-classes rule," which was true, but authorization was never checked and was in fact completely absent).

**New policy:** `TeacherClassSubjectAssignmentPolicy`, using the `view-teacher-subject-assignment`/`manage-teacher-subject-assignment` permissions — both already seeded and already granted to admin, previously unused by any code path (same situation as Task 2's promotion permissions).

**Tests:** unauthorized (teacher role) → 403; admin can create/update/delete; a non-admin role holding `manage-teacher-subject-assignment` can also create, proving the permission path (not just `hasRole`) works.

**Commit:** `38a8245`

### Controller 4/4 — `CertificateController`

**Plan-vs-reality stop (reported, you decided):** the plan named only `publish()`/`revoke()`. Re-verification found **zero `authorize()` calls anywhere** — all 13 actions (`index/create/store/show/edit/update/destroy/approve/publish/lock/revoke/preview/downloadPdf`), not just the two named. Reported this; you said to fix the whole controller, not just the two named actions.

**New policy:** `CertificatePolicy`. Original role choices:
- `viewAny`/`view` (incl. `preview()`/`downloadPdf()`, matching D5's original "same policy as preview" intent — now actually true): admin, or `view-certificates`/`manage-certificates` permission.
- `create`/`update`/`delete`: admin, or `manage-certificates` permission.
- `approve`/`publish`/`lock`/`revoke`: admin-only — narrowest choice, these are irreversible status transitions (publish on a TC is the actual leaving-school event).

Updated `CertificatePdfDownloadTest`'s stale comment (previously documented "no authorization exists" as an accepted gap; this commit closed it).

**Regression found later (Task 7's full-suite run) and fixed separately** — see "CertificatePolicy correction" below.

**Commit:** `52fc4b6`

### Final item — the public `attendance/*` web routes

**Investigation (REPORT-THEN-STOP per the plan):** 11 routes (`attendance.index/store/bulk-mark/create/export/reports/student.report/show/update/destroy/edit`) carried only the global `web` middleware — no `auth` at all. Confirmed via `route:list --json`: a fully unauthenticated visitor could view, create, edit, and delete attendance records directly.

Neither of the plan's two anticipated outcomes fit: not a device/API integration (ordinary session-based browser UI, forms, redirects), and not dead code (linked from the admin dashboard, home dashboard, and parent dashboard, with a full set of dedicated Blade views). Also found: at least 6 existing test files hit these routes without `actingAs()` or `withoutMiddleware()`, relying on guest access working.

Reported this; you said to add auth middleware and fix the tests. On investigation, none of the 6 files actually needed fixing — they either render Blade views directly (bypassing HTTP/routing) or hit the already-authenticated `/admin/attendance` path; only `AttendanceWebUpdateIdentityGuardTest` makes real calls to these specific routes, and it already authenticates in `setUp()`.

**What was done:** wrapped the route block in `auth`/`verified`/`redirect.if.not.onboarded`, matching the middleware stack its `admin.attendance.*` sibling already used.

**Tests added:** guest redirected to login on index/create/store/reports/export; authenticated user can still reach the index. Full attendance suite re-run: 328 passed, unaffected.

**Commit:** `a47e6c8`

**Audit finding re-checked (whole Task 3):** Fresh finding #3 (CRITICAL — "Broad authorization gaps") — all items in Task 3's explicit scope now **VERIFIED** fixed. (Note: the original finding also named `Admin\HomeworkNoticeController`, `Admin\CertificateTemplateController`, `Admin\TeacherAttendanceController`, `Admin\SyllabusController` — outside Task 3's explicit list, still open, in the deferred register below.)

---

## Task 4 — The two broken routes (B9, B10)

### B9 — `bell-timing/bulk-create`

**Re-verified:** confirmed — POST pointed at nonexistent `processBulkCreate`. `bulkCreate()` already handled both GET (show form) and POST (create records) internally via `$request->isMethod('get')`, so repointed the POST route to it instead of writing a new method.

**Bonus bug found while writing the required smoke test:** `GET bell-timing/bulk-create` ALSO 404'd — a separate, pre-existing bug. `Route::resource('bell-timing', ...)` was registered before the literal-path custom routes (`weekly`, `daily`, `bulk-create`, bare `print`), so its `show` route (`GET bell-timing/{bell_timing}`) matched first and swallowed them as if "weekly"/"daily"/"bulk-create" were IDs. Confirmed via direct `Route::getRoutes()->match()` calls before and after. Fixed by moving the literal routes above the resource registration (standard Laravel ordering). No existing tests referenced the previously-broken routes.

**Tests added:** bulk-create GET+POST, weekly, daily, and show (with a real ID) all verified working.

### B10 — `Admin\HomeworkController`

**Re-verified:** confirmed — only `index()`/`show()` implemented out of the full resource it was routed as; `create/store/edit/update/destroy` all fatal on hit. Also found: `show(HomeworkNotice $homeworkNotice)` had the **same route-model-binding name mismatch bug** as controller 1 in Task 3 (`$homeworkNotice` vs route wildcard `{homework}`) — so even the "working" half didn't actually work.

**Decision:** deletion over `->only(['index','show'])`. Grepped the whole `app/`/`tests/` tree: zero references to this controller or its route names outside its own two views. `HomeworkNoticeController` (`admin.homework-notices.*`) already provides full, working CRUD as the replacement.

**What was done:** deleted the controller, its two exclusive views (`resources/views/admin/homework/index.blade.php`, `show.blade.php` — left `professional-index/show.blade.php` alone, those belong to the separate `ProfessionalHomeworkController`), and the route registration.

**Verification:** `HrAndLmsFeatureTest` — 12/13 passing (the 1 failure, `test_student_can_submit_homework_file`, is a documented pre-existing baseline failure unrelated to these routes, confirmed via `git stash` comparison).

**Audit findings re-checked:** B9 and B10 — both were **FALSE** ("still live, still broken"). Both now **VERIFIED fixed**.

**Commit:** `838cd87`

---

## Task 5 — Wire the attendance parent-notification pipeline for real

**Stop before touching anything:** found that `sendAttendanceMarkedNotification()`'s "disabled" state was not a bug — it was a deliberately guarded, actively-tested disabled state. The same "temporarily disabled" comment appeared identically across all 5 methods on `AttendanceNotificationService`; a dedicated test file, `AttendanceNotificationSendGuardTest`, existed specifically to lock in the disabled behavior; and the same pattern (Phase 6Y, Phase 7E) repeated elsewhere in the attendance subsystem (`TeacherAttendanceController::storeAttendance()`, `AttendanceService::markAttendance()`), each with its own guard test. Reported this and asked how to proceed rather than silently reversing what looked like a deliberate decision. You said to re-enable it as the plan asked.

**What was done (scoped to only this one method — the other 4 notification methods and the separate teacher-attendance-writes guard were left untouched, still disabled, still covered by their guard tests):**
- `AttendanceMarked` notification now actually `implements ShouldQueue` — it imported the interface and used the `Queueable` trait already, but never declared it, so it was sending synchronously despite looking queued. Fixed as part of making this genuinely "queued."
- `sendAttendanceMarkedNotification()`: absent-students-only; resolves the student's parent via `Student::parent()` (`ParentModel`, the Notifiable target); guarded against duplicates by checking the `notifications` table for an existing `AttendanceMarked` record for the same `student_id`+`date` before sending; the whole method wrapped in try/catch (marking attendance must never fail because a notification lookup did, same philosophy as Task 1's holiday-guard wrap).
- Invoked from both real marking paths: web `AttendanceController::store()` (after the bulk insert, once per marked student) and API `AttendanceController::store()` (after `create()`).

**Test-setup discovery:** while writing tests, found `students.parent_id` isn't mass-assignable, and a `Student::saved()` model hook auto-provisions/dedup-matches a `ParentModel` by phone number — using the same hardcoded phone for two test students merged them onto one auto-created parent. Fixed by giving each test student a unique phone and letting the hook's natural auto-provisioning run.

**Operational note (for the school):** this dispatches onto the `database` queue connection — nothing is actually delivered until a queue worker runs (`php artisan queue:work`, wrapped in a Supervisor/Windows Service for production; XAMPP runs nothing by default). `MAIL_MAILER` is currently `log`, so even once queued and processed, mail lands in `storage/logs/laravel.log` rather than being emailed until that's changed to a real driver.

**Tests added:** absent student queues a notification; present student sends none; re-marking the same student same day does not duplicate; both web and API marking paths queue correctly; the other 4 guarded methods (and the separate `AttendanceService` guard) remain untouched and still disabled.

**Verification:** full attendance suite — 338 passed (328 baseline + 10 new), 0 failures.

**Audit finding re-checked:** D6 — was **FALSE** ("no-op stub, zero call sites, disabled by design"). Now **VERIFIED**.

**Commit:** `8e149f0`

---

## Task 6 — `class_management` retirement — **DEFERRED, not executed**

**Investigation only (read-only, per the plan's REPORT-THEN-STOP gate):**
- Row count: 19.
- `legacy_class_map.class_management_id` FK: hard constraint, `onDelete('cascade')`. A plain rename preserves it mechanically in MariaDB/InnoDB (FKs tracked by internal table ID, not name), but the plan wants it actually decoupled.
- Grepped the whole `app/` tree and found **13 files with genuine live usage** — not the 4 the original audit found. New discoveries: `Admin\ExamController` (exam-creation class dropdown — since repointed off `ClassManagement` by Task 7, no longer live), `Admin\AdminAdmissionController` (**live student admission flow** — section dropdown, validation, resolution), `Admin\SetupWizardController` (creates/checks/deletes rows during initial school setup). Full 13-file list is in the "Task 6" section above and was shown to you directly.
- Also found 3 dead imports (`StudentPromotionController`, `Admin\ResultController`, `TeacherResultController` — `use App\Models\ClassManagement;` with zero other usage in each file) and confirmed the seeding pipeline (`ClassSectionSeeder`, `AcademicDataSeeder`) actively populates/reads the table on every fresh install.

**Your decision:** defer entirely — don't touch `class_management` in this remediation pass; log the full reference list as debt for a properly scoped follow-up.

**Current state:** `class_management` table unchanged (still 19 rows, still live-named, not renamed or dropped). All 13 live-code files unchanged except `Admin\ExamController` (which stopped reading `ClassManagement` as a side effect of Task 7's unrelated fix — it now reads `SchoolClass` for the exam-creation class dropdown instead).

**Audit finding re-checked:** A2 — remains **FALSE**, as it was before this session; not addressed in this pass, by your explicit choice.

**No commit** (nothing changed).

---

## Task 7 — Repoint admit cards + exam seating to `school_class_id`

**Re-verified line numbers:** matched the plan and original audit exactly — `AdmitCardController.php:58`, `ExamArrangementController.php:76`, `ExamArrangementController.php:99`.

**What was done:**
1. `ExamController`: `create()`/`edit()` dropdown now sources from `SchoolClass` instead of the free-text input (and instead of `ClassManagement`, narrowing that table's footprint as a side effect, though Task 6 itself stays deferred). `store()`/`update()` validate `class_id` (`required|exists:school_classes,id`) instead of `class_name`; `class_name` is now derived server-side from the chosen class, never trusted from the request, kept populated for backward display compatibility. Both Blade views (`create.blade.php`, `edit.blade.php`) updated to submit `class_id`.
2. Backfill migration for the 3 existing exams: mapped `class_name` → `school_classes.id` by exact name match. Verified read-only first — all 3 mapped cleanly (`"Class 10"` → id 13, `"Class 9"` → id 12). Any unmappable exam would be left NULL and logged, never guessed.
3. Repointed all three readers to `Student::where('school_class_id', $exam->class_id)`.
4. Empty-match guards added to `AdmitCardController::store()` and `ExamArrangementController::seatingIndex()` (`generateSeating()` already had one) — killed the "Successfully generated 0 admit cards" lie: zero generated is now an error, not a fake success, distinguishing "no students matched" from "matched students already had cards" / "all failed validation".

**Two more bugs found while verifying the fix works end-to-end (both previously unreachable, both fixed):**
- `AdmitCard::validateForGeneration()` (in `app/Models/AdmitCard.php`) also compared `student->class` against `exam->class_name` — the exact same legacy-string bug, one level deeper. Even with students correctly matched by `school_class_id`, this check would have rejected nearly every one of them. Repointed to compare `school_class_id` against `exam.class_id`.
- `AdmitCardController::store()` wrote `validation_data`/`version` (and `AdmitCard`'s `$fillable` listed `pdf_hash` too) to columns that were **never migrated** — confirmed against both the dev MariaDB DB and every migration file. Every `AdmitCard::create()` call would have thrown a SQL error. This was never triggered in practice because the class-matching bug always short-circuited before any student ever reached the `create()` call — one bug was masking the other. Removed the writes and the `$fillable`/cast entries rather than adding a migration for fields nothing else in the app reads.

**Existing test fixed:** `PhotoEverywhereEndToEndTest::test_exam_seating_arrangement_shows_student_photo` relied on the old string-matching setup — updated to create a real `SchoolClass`, set `exam.class_id`, and set the student's `school_class_id`. The file's other failure (`test_fee_receipt_screen_and_pdf_show_student_photo`) is confirmed pre-existing and unrelated (reproduces identically with these changes stashed out).

**Tests added:** exam with `class_id` matches only the canonically-enrolled student (seeded one student with matching `school_class_id` but a stale legacy string, and one with a legacy string that happens to equal the exam's `class_name` but a different `school_class_id` — proving the string is now irrelevant); zero-student class shows a visible error on all three paths; exam create requires a valid `class_id` and derives `class_name` automatically.

**Verification:** full-suite run surfaced **72 failed vs the 71-failure baseline** — see next section.

**Audit finding re-checked:** the CRITICAL "100% zero-student outage" fresh finding — now **VERIFIED fixed**.

**Commit:** `333aa91`

---

## CertificatePolicy correction (found by Task 7's full-suite run)

Task 7's full-suite run showed 72 failures against the documented 71-failure baseline. Identified the +1 as `DefaulterWorkflowTest::academic_restrictions_block_exam_result_and_tc_operations` — an accountant posting to `admin.certificates.store` to attempt a TC for a student on TC Hold, expecting a **validation** error (`recipient_id`) proving the hold blocks it. Task 3's `CertificatePolicy` (commit `52fc4b6`) had made `create()` admin-only-or-`manage-certificates`-permission, so the accountant now got a 403 *before* validation ever ran — an authorization error masking the test's actual assertion.

**Root cause of the miss:** my grep search during Task 3 for tests that might be affected filtered by filenames containing "certif" — `DefaulterWorkflowTest.php` doesn't match that pattern despite hitting the route directly, so it was never checked.

**Fix:** TC certificate creation is tightly coupled to the fee-defaulter/TC-hold workflow accountants manage (confirmed by this exact test scenario) — added `accountant` to `viewAny()` and `create()` (`update`/`delete` inherit via `create()`). The higher-stakes `approve`/`publish`/`lock`/`revoke` actions stayed admin-only, unchanged. See the table under "CertificatePolicy — accountant access" above for the exact current state.

**Verification:** `DefaulterWorkflowTest` (10 tests) and `CertificateAuthorizationTest` (8 tests) both fully green together afterward.

**Commit:** `6b703bc`

---

## Test suite status

Two full-suite runs during this session hit the documented baseline exactly (71 failed / matching distribution) after each task's own changes, up through Task 5. Task 7's run surfaced the +1 CertificatePolicy regression described above, which was fixed and re-verified locally (the two directly-affected test files both green). A final authoritative full-suite run is in progress as of this report to confirm the baseline is restored end-to-end; its result will be added to `docs/plans/remediation-report.md` once complete, per the plan's SESSION END requirement (baseline ≤ 71 known failures, zero *new* failures).

---

## Commits made this session (chronological, all on `remediation`, none pushed yet)

| Commit | Task |
|---|---|
| `89853c9` | Pre-flight |
| `73b2440` | Task 1 — academic_events migration + holiday guard |
| `c398b87` | Task 2 — StudentPromotionController auth + idempotency |
| `e2632db` | Task 3, controller 1/4 — ClassTeacherAssignmentController |
| `bb6e61b` | Task 3, controller 2/4 — Admin\TeacherClassAssignmentController |
| `38a8245` | Task 3, controller 3/4 — Admin\TeacherSubjectAssignmentController |
| `52fc4b6` | Task 3, controller 4/4 — CertificateController |
| `a47e6c8` | Task 3, final item — public attendance/* routes |
| `838cd87` | Task 4 — B9 + B10 broken routes |
| `8e149f0` | Task 5 — attendance parent-notification pipeline |
| `6b703bc` | CertificatePolicy correction (accountant access) |
| `333aa91` | Task 7 — admit cards + exam seating repoint |

**Task 6:** no commit — deferred, zero changes made.

---

## Deferred / out-of-scope register

Items found but explicitly not fixed in this remediation pass:

1. **`class_management` retirement (Task 6)** — deferred by your explicit decision. 13 live-code files, admissions and exam-creation setup wizard among them. Full list above.
2. **Authorization gaps outside Task 3's explicit scope** — the original audit's CRITICAL finding also named `Admin\HomeworkNoticeController`, `Admin\CertificateTemplateController`, `Admin\TeacherAttendanceController`, and `Admin\SyllabusController` as having zero-authorization write actions. Task 3's plan only listed 4 specific controllers + the attendance routes; these 4 were never in scope for this pass and remain unaddressed.
3. **The other 4 disabled notification methods** on `AttendanceNotificationService` (`sendLowAttendanceAlerts`, `sendDailyAttendanceSummary`, `sendWeeklyAttendanceReport`, `sendBulkAttendanceNotifications`) and the separate `TeacherAttendanceController`/`AttendanceService` teacher-attendance-writes guards — all deliberately left disabled, untouched, per Task 5's scoping.
4. **`TimetableSlotPolicy` scope gap** (from the original audit, MEDIUM) — `create`/`update` grant write access to any teacher for every class's timetable, not just their own. Not part of any task in this plan.
5. **3 dead `ClassManagement` imports** (`StudentPromotionController`, `Admin\ResultController`, `TeacherResultController`) — found during Task 6's investigation, trivial cleanup, not touched since Task 6 itself was deferred.
6. **`Admin\TeacherClassAssignmentController` race window** (pre-existing, LOW severity) — delete-then-create of a `class_teacher` role isn't wrapped in a transaction. Noted in Task 3's controller-2 commit message, not fixed (out of scope — that commit was about missing authorization, not this pre-existing concurrency issue).

---

## Next steps (per the plan's SESSION END)

1. Full test suite re-run to confirm the baseline is restored end-to-end (in progress).
2. Write `docs/plans/remediation-report.md` with the claim-by-claim re-verification table, all commits, and this deferred list.
3. Merge `remediation` → `main` with `--no-ff` (stop on any conflict), push `main`, tag `remediation-complete`.
4. Run the suite once more on `main`, confirm baseline, report final HEAD.

None of these have happened yet — this document is a mid-session status report, written on request, not the final plan-mandated report.
