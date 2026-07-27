# REMEDIATION PLAN — Fix Verified Audit Findings on main
## Save at: docs/plans/remediation-plan.md · HelpingHand ERP
## Source of truth: docs/plans/verification-report-claude.md (commit 688f0a0)

**Purpose:** Fix every verified finding from the post-rebuild audit so the Timetable module (docs/plans/timetable-module-plan.md) can start on honest ground. Work on branch `remediation` off main. One task per commit. The audit report is the acceptance test: after all tasks, each fixed claim must flip to VERIFIED when re-checked.

**Rules:** Scope is EXACTLY the tasks below. Anything else found gets listed at the end, not fixed. Tests after every task. If any task's reality differs from what's described here (code moved, already fixed, different line numbers), STOP on that task, report the difference, and wait for my instruction — do not improvise. After each task, re-run the specific audit check it corresponds to and state the new verdict.

**Pre-flight:** branch off current main (`git checkout main && git pull && git checkout -b remediation`), clean status, confirm HEAD includes 688f0a0. Confirm a DB dump from this week exists; if not, take one first (mysqldump to storage/backups/, never commit it — verify storage/backups/ is in .gitignore, add it if missing).

---

## TASK 1 — Apply the missing academic_events migration + make the holiday guard defensive

1. `php artisan migrate:status` — list pending migrations. Expected: the academic_events migration (and possibly others) pending on this database.
2. Run `php artisan migrate` (this task is the explicit exception to no-migrations rules — it is the fix). Report exactly which migrations ran.
3. Verify: `SHOW TABLES` includes academic_events; mark attendance for a normal date via a feature test — no exception.
4. Defensive wrap: in BOTH attendance controllers (web + API), wrap the AcademicEvent::isHoliday() call in try/catch — on any exception, log a warning and treat as non-holiday (attendance must never break because the calendar is unavailable). Follow the same degrade-gracefully pattern the dashboard events card uses.
5. Tests: holiday date blocked; normal date works; isHoliday throwing (bind a mock) does not break marking.

## TASK 2 — Student promotion: authorization + idempotency (the falsely-claimed Phase B fix, done for real)

1. Create app/Policies/StudentPromotionPolicy.php: viewAny/create allowed for admin (and a `manage-promotions` permission if the permission system supports named permissions — check how other policies do it and match the pattern). Register it.
2. Add $this->authorize() calls to EVERY action in StudentPromotionController.
3. Idempotency guard in store(): inside the existing transaction, before executing, check StudentPromotionLog for the same student promoted in the same academic session to the same target class; skip those students with a per-student "already promoted" notice in the result summary (do not fail the whole batch).
4. DB backstop: unique index on student_promotion_logs (student_id, academic_session_id, to_school_class_id) — check for existing violating rows first (read-only), report if any and STOP for my decision.
5. Tests: unauthorized role gets 403; double-submit of the same batch promotes zero students the second time and says so; partial batch (some already promoted) handles correctly.

## TASK 3 — Authorization sweep: close the zero-auth write controllers

For each controller the audit listed with zero authorization on write actions — ClassTeacherAssignmentController, Admin\TeacherClassAssignmentController, Admin\TeacherSubjectAssignmentController, certificate publish/revoke actions in CertificateController, and the unauthenticated public attendance routes — do the following, ONE CONTROLLER PER COMMIT:

1. First re-verify the gap exists (grep for authorize/hasRole/middleware on that controller and its routes). If already protected, mark done and move on.
2. Protect writes with the existing pattern: policy where a model fits (follow TimetableSlotPolicy as the template), or permission middleware where the codebase uses that convention for similar features. Admin always allowed; choose the narrowest sensible role set and STATE your choice in the commit message so I can review.
3. For the public/unauthenticated attendance routes: determine what they're for (grep usage). If they serve a legitimate device/API purpose, they get token auth like the existing parent API routes; if nothing uses them, delete the routes. REPORT-THEN-STOP before deleting anything here.
4. Test per controller: unauthorized write attempt → 403; authorized → works.

## TASK 4 — The two broken routes (B9, B10)

1. bell-timing/bulk-create POST → nonexistent processBulkCreate: repoint the route to the real bulkCreate method if their signatures align; otherwise delete the route. Verify with route:list + a smoke test.
2. Admin\HomeworkController: it implements only index/show but is routed as a full resource. Restrict the route to `->only(['index','show'])` OR delete route+controller entirely if HomeworkNoticeController covers everything (grep the views for links to either before choosing; report your choice).

## TASK 5 — Attendance parent notification: wire the no-op for real

1. Trace why the audit called the pipeline "a disabled no-op" — find the call path from attendance marking to sendAttendanceMarkedNotification and identify exactly where it dead-ends (never called, feature-flagged off, or queue never processed).
2. Fix minimally: invoke it on marking, queued, absent-students-only, once per student per day (guard against re-marks).
3. If the dead-end is that no queue worker runs in production (likely on XAMPP), implement it to degrade safely: queue if the queue is live, and log a clear warning otherwise — and tell me in the report what the school needs to run (e.g. `php artisan queue:work` as a service) for notifications to actually send.
4. Tests: absent student → notification queued; present student → none; re-mark same day → no duplicate.

## TASK 6 — class_management retirement (finish what was claimed)

1. Read-only first: current row count of class_management; grep for ALL live-code references (exclude migrations/comments/tests — list each hit classified). The legacy_class_map FK dependency on class_management (noted by the earlier Codex audit) must be examined: report how it's constrained.
2. REPORT-THEN-STOP: show me the reference list and your plan.
3. After my approval: repoint/remove the remaining live references; drop or re-key the legacy_class_map FK so it no longer depends on class_management; rename the table to zz_retired_class_management via migration (rename, not drop). Verify app boots and the full test suite baseline is unchanged.

## TASK 7 — Admit cards + exam seating: repoint to school_class_id (fixes the 100% zero-student outage)

1. Migration/none needed for schema — exams.class_id already exists, dormant. Add it to ExamController's create/update: replace the free-text class_name input with a school_classes dropdown (keep class_name populated automatically from the chosen class's name for backward display compatibility). Validate class_id as required|exists:school_classes,id.
2. Backfill migration for the 3 existing exams: map their class_name ("Class 10", "Class 9") to school_classes ids by name; any unmappable exam gets listed and left NULL with a report — never guessed.
3. Repoint the three readers: AdmitCardController::store() (~:58), ExamArrangementController::seatingIndex() (~:76) and generateSeating() (~:99) to Student::where('school_class_id', $exam->class_id). Re-verify line numbers first.
4. Empty-match guards: store() and seatingIndex() get the same explicit "No students found" error that generateSeating() already has. Kill the "Successfully generated 0 admit cards" lie: zero generated → error message, not success.
5. Tests: exam with class_id → correct students matched (seed both legacy-string and canonical students to prove the string is irrelevant now); zero-student class → visible error on all three paths; exam create requires a valid class pick.

## SESSION END (after all 7 tasks)

1. Full test suite: baseline must be ≤ 71 known failures (fewer is fine if any known-failing test now passes — report which), ZERO new failures.
2. Re-run the audit checks corresponding to every task (C2, D2, D6, B9, B10, A2-retirement, plus the admit-card finding) and produce a short "remediation verification" table: finding → new status → evidence.
3. Write docs/plans/remediation-report.md with the table, all commits, and the deferred list. Commit, push branch, then merge to main with --no-ff (STOP on any conflict), push main, tag `remediation-complete`.
4. Run the suite once on main; confirm baseline; report final HEAD.