# REMEDIATION REPORT
## HelpingHand ERP · branch `remediation` (off `main` @ `688f0a0`)
## Executed docs/plans/remediation-plan.md against docs/plans/verification-report-claude.md (commit `688f0a0`)

All 7 tasks executed, one commit per task (Task 3 split across its 5 constituent items per the plan's own "one controller per commit" instruction; Task 6 deferred by explicit decision, no commit). Every REPORT-THEN-STOP gate in the plan was honored — investigated first, reported findings, waited for direction before making any change. Full narrative with all bonus discoveries is in `docs/plans/all_about.md`; this report is the plan-mandated closing artifact: the re-verification table, the commit list, and the deferred register.

---

## Re-verification table

| Finding | Original verdict | New verdict | Evidence |
|---|---|---|---|
| **D1** — Academic Calendar deployed | PARTIALLY TRUE (code complete, migration not applied) | **VERIFIED** | `2026_07_23_100300_create_academic_events_table` ran (Task 1); table confirmed to exist; it was the only pending migration |
| **D2** — Holiday guard in both attendance controllers | VERIFIED with caveat ("non-functional at runtime") | **VERIFIED, no caveat** | Both controllers wrapped in try/catch, degrade to non-holiday on failure (Task 1); 2 new tests simulate a dropped `academic_events` table mid-request and confirm marking still succeeds |
| **C2** — StudentPromotionController authorization + idempotency | FALSE (zero authorize(), no idempotency) | **VERIFIED** | New `StudentPromotionPolicy`, `authorize()` on all 7 actions, app-level skip-if-already-promoted guard + DB unique index backstop (Task 2). Tests: unauthorized→403, double-submit promotes 0 the 2nd time, partial-batch handling, DB constraint enforcement |
| **Fresh finding #3 (CRITICAL)** — Broad authorization gaps | Multiple controllers with zero authorization | **VERIFIED for Task 3's scope**: `ClassTeacherAssignmentController`, `Admin\TeacherClassAssignmentController`, `Admin\TeacherSubjectAssignmentController`, `CertificateController` (all 13 actions, not just publish/revoke), public `attendance/*` routes | 5 commits, each with a policy/middleware + tests proving unauthorized→403 and authorized→works. **Not fully closed**: `Admin\HomeworkNoticeController`, `Admin\CertificateTemplateController`, `Admin\TeacherAttendanceController`, `Admin\SyllabusController` were named in the original finding but not in Task 3's explicit scope — still open, see deferred register |
| **B9** — bell-timing/bulk-create broken route | FALSE (still live, still broken) | **VERIFIED** | POST repointed to the real `bulkCreate()` method (Task 4); bonus fix for a route-registration-order collision also breaking `weekly`/`daily`/bare `print` GET routes; 5 new tests |
| **B10** — Admin\HomeworkController broken resource | FALSE (still exists, still fatal) | **VERIFIED** | Controller, its 2 exclusive views, and the route registration deleted (Task 4); `HomeworkNoticeController` confirmed as the complete, working replacement |
| **D6** — Attendance parent notification | FALSE (no-op stub, zero call sites) | **VERIFIED** | `sendAttendanceMarkedNotification()` re-enabled (Task 5, after flagging it was a deliberately guarded state and getting explicit direction to proceed): invoked from both web and API marking paths, genuinely queued (fixed a `ShouldQueue` declaration bug), absent-only, same-day duplicate guard. 5 new tests |
| **A2** — class_management retirement | FALSE (never retired, live references) | **STILL FALSE — deferred, not attempted** | Investigated (Task 6): 19 rows, 13 live-code files (vs. the 4 originally found), including the live admission flow (`Admin\AdminAdmissionController`) and the setup wizard. Reported to you; you chose to defer entirely. Zero changes made |
| **Admit-card/exam-seating 100% zero-student outage (CRITICAL fresh finding)** | Confirmed broken, 0/3 exams matched any student | **VERIFIED** | Repointed all 3 readers to `school_class_id`/`exam.class_id` (Task 7); backfilled the 3 existing exams; added empty-match guards killing the false-success message. Found and fixed 2 more bugs in the same code path that the class-matching bug had been masking: `AdmitCard::validateForGeneration()`'s own legacy-string check, and a SQL error from `AdmitCard::create()` writing to columns (`validation_data`/`version`/`pdf_hash`) that were never migrated. 6 new tests |

---

## Commits (chronological, branch `remediation`)

| Commit | Description |
|---|---|
| `89853c9` | Pre-flight: plan doc + gitignore backups |
| `73b2440` | Task 1: academic_events migration + defensive holiday guard |
| `c398b87` | Task 2: StudentPromotionController authorization + idempotency |
| `e2632db` | Task 3 (1/4): ClassTeacherAssignmentController — also fixed a route-model-binding bug that made update/destroy silent no-ops |
| `bb6e61b` | Task 3 (2/4): Admin\TeacherClassAssignmentController |
| `38a8245` | Task 3 (3/4): Admin\TeacherSubjectAssignmentController |
| `52fc4b6` | Task 3 (4/4): CertificateController — expanded to all 13 actions per your direction |
| `a47e6c8` | Task 3 (final item): public attendance/* routes now require auth |
| `838cd87` | Task 4: B9 + B10 broken routes, plus a bonus bell-timing route-ordering fix |
| `8e149f0` | Task 5: attendance parent-notification pipeline wired for real |
| `6b703bc` | Correction: CertificatePolicy — grant accountants create/view (regression found by Task 7's full-suite run) |
| `333aa91` | Task 7: admit cards + exam seating repointed to school_class_id, plus 2 bonus bug fixes in the same code path |

**Task 6:** no commit — deferred by explicit decision, zero changes.

---

## Test suite

**Final authoritative full run:** 71 failed / 1130 passed / 6890 assertions.

Verified the 71 failures are the **same 71** as the documented pre-remediation baseline, not a different set: 7 API + 30 Admin + 34 FeeFinance, matching the original distribution exactly, with every individual test name checked against the known list. `DefaulterWorkflowTest` — which briefly appeared as a 72nd failure after Task 3's CertificateController commit — is confirmed gone after the CertificatePolicy correction.

1130 passed vs. the original baseline's 1080 = **net +50 tests** across this session, all passing:

| Commit | Tests added | Tests removed |
|---|---|---|
| Task 1 (`AttendanceHolidayBlockTest`) | +2 | — |
| Task 2 (`StudentPromotionAuthorizationAndIdempotencyTest`) | +4 | — |
| Task 3 (1/4) (`ClassTeacherAssignmentAuthorizationTest`) | +5 | — |
| Task 3 (2/4) (`TeacherClassAssignmentAuthorizationTest`) | +5 | — |
| Task 3 (3/4) (`TeacherSubjectAssignmentAuthorizationTest`) | +5 | — |
| Task 3 (4/4) (`CertificateAuthorizationTest`) | +8 | — |
| Task 3 (final) (`AttendanceWebRoutesRequireAuthTest`) | +6 | — |
| Task 4 (`BellTimingBulkCreateRouteTest`) | +5 | — |
| Task 5 (`AttendanceMarkedNotificationTest`) | +5 | — |
| Task 5 (`AttendanceNotificationSendGuardTest`) | — | −1 (the now-re-enabled method's "stays disabled" assertion) |
| Task 7 (`ExamClassIdRepointTest`) | +6 | — |
| (Task 6: deferred) | 0 | — |
| **Total** | **+51** | **−1** |

Net **+50**, matching the observed 1080 → 1130 delta exactly.

**Zero new failures. Zero regressions in the baseline.**

---

## Deferred / out-of-scope register

1. **`class_management` retirement (Task 6)** — deferred by explicit decision. 19 rows, 13 live-code files (admissions and the setup wizard among them), a hard FK from `legacy_class_map`. Full reference list in `docs/plans/all_about.md`.
2. **4 controllers named in the original audit's authorization finding but outside Task 3's explicit scope**: `Admin\HomeworkNoticeController`, `Admin\CertificateTemplateController`, `Admin\TeacherAttendanceController`, `Admin\SyllabusController`. Still have zero-authorization write actions.
3. **The other 4 disabled `AttendanceNotificationService` methods** and the separate `TeacherAttendanceController`/`AttendanceService` teacher-attendance-writes guards — deliberately left disabled per Task 5's scoping (only `sendAttendanceMarkedNotification` was in scope).
4. **`TimetableSlotPolicy` scope gap** (MEDIUM, from the original audit) — `create`/`update` let any teacher write to any class's timetable, not just their own. Not part of this plan.
5. **3 dead `ClassManagement` imports** (`StudentPromotionController`, `Admin\ResultController`, `TeacherResultController`) — found during Task 6's investigation, trivial, untouched since Task 6 was deferred.
6. **`Admin\TeacherClassAssignmentController` race window** (pre-existing, LOW) — delete-then-create of a `class_teacher` role isn't transaction-wrapped. Noted, not fixed (out of scope for that commit).
