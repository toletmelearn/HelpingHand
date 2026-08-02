# Timetable module — Phase T6 Item 5 report (the guided setup wizard)

Branch `timetable-module`, continues from T6 Item 4 (`954ef4d`). This is the last item in the T6 plan (`docs/plans/timetable-T6-plan.md`) -- the wizard was explicitly the final, biggest-payoff piece: *"Everything below builds the pieces that make those 5 steps work."*

---

## The teacher-load gap (requested to be described before building)

**Where it showed:** `FeasibilityService::teacherLoad()` -- the exact report Step 4 of the wizard reuses.

**What was counted wrong:** the method only ever counted a slot where the teacher was the *primary* `teacher_id`. A team-taught slot (T6 item 4) also carries `co_teacher_id`, but `teacherLoad()` never checked that column -- so a co-teacher's own placed periods, busiest day, days-fully-booked, and required-vs-available comparison all understated their true weekly load. The `required_periods` side had the identical gap: `TeacherClassSubjectAssignment` rows were only summed against the primary `teacher_id`.

**Does it stem from Item 4:** yes, directly. Before Item 4, `teacher_id` was the only teacher column on a slot, so the original query was complete; Item 4 added a second teacher column without updating this consumer.

**Resolution:** fixed as part of building Step 4, not deferred -- the gap lives exactly where the wizard's readiness check reads from. `teacherLoad()` now filters slots by `teacher_id === $teacher->id || co_teacher_id === $teacher->id`, and required-periods sums both `teacher_id` and `co_teacher_id` assignment rows. Covered by a new test (`test_teacher_load_counts_co_teaching_periods_toward_the_co_teachers_own_load`).

---

## A schema question, resolved without a migration (documented instead of a full REPORT-THEN-STOP round-trip, since no schema changed)

Step 1 picks a class's teacher *before* Step 2 knows their subject. Step 2 needs to read that pick back to pre-fill/pre-flag the right grid row. Neither of the two REAL class-teacher signals `GeneratorService`/`FeasibilityService` read (`teacher_class_subject_assignments.is_class_teacher`, which requires a subject to already exist; the legacy `class_teacher_assignments` table, which is keyed by `users.id` not `teachers.id` -- a confirmed bug worked around when reading it, not something new code should build on) can hold "who's picked, subject unknown yet."

Used `school_classes.teacher_id` instead -- an existing, correctly Teacher-FK'd column that was sitting completely unused elsewhere in the codebase (only ever bound to a form field, never read by any service). It's purely the wizard's own working memory of Step 1's pick, read only by Step 2. Neither `GeneratorService` nor `FeasibilityService`'s readiness check were taught to read it -- no existing read semantics changed. Step 2's save is what creates the REAL `is_class_teacher`-flagged row those services already read.

---

## What shipped

Per T6's own plan text, one guided flow:

- **Step 1 -- Class Teachers**: every active class, one teacher dropdown each, one Save. Writes `school_classes.teacher_id` (see above).
- **Step 2 -- Subject Assignments**: one class at a time ("Class N of M", Previous/Next), a subject/teacher/periods/require-consecutive/is-class-teacher grid. If Step 1 picked a teacher for this class and no `is_class_teacher` row exists yet, the top row is pre-filled with that teacher and the class-teacher checkbox pre-checked -- the admin only has to pick the subject. Saves via the same `updateOrCreate` shape `TeacherSubjectAssignmentController::store()` already uses (same unique key: teacher+class+section+subject+year), enforcing the same one-class-teacher-row-per-class swap. "Copy from another class" / "Copy last year" were skipped, per the plan's own explicit permission ("if cheap; otherwise skip").
- **Step 3 -- Style**: the `rotating`/`fixed_daily` toggle in plain language ("Different Each Day" / "Same Every Day"), with an explicit note that the class-teacher's subject holds period 1 regardless of which is chosen.
- **Step 4 -- Readiness**: calls `FeasibilityService::build()` directly and shows classes without a class-teacher, over-required classes, overloaded teachers, and conflicts in plain sentences -- never blocking; a "Continue to Create Timetable" button always proceeds.
- **Step 5 -- Generate & Publish**: the *existing* `/admin/timetable/generate` page, embedded as the final step via a link carrying `?style=&select_all=1`. That page gained a small additive query-param prefill (pre-select the style radio, auto-check "select all") -- its own already-tested JS/polling/publish flow is completely untouched.

New sidebar entry **"Set Up Timetable"**, admin-only (`TimetableSlotPolicy::generate()`, the same gate the rest of the Generate flow already uses), listed first -- the front door the plan asked for, with every individual page still reachable below it.

No schema changes; no migrations.

---

## Tests

Added `tests/Feature/Admin/TimetableWizardTest.php`:

| Test | Proves |
|---|---|
| `test_non_admin_cannot_access_the_wizard` | Admin-only gate. |
| `test_step1_shows_all_active_classes_with_a_teacher_dropdown` | Step 1 renders. |
| `test_step3_shows_style_options_with_the_period_1_note` | Step 3 renders, includes the period-1 note. |
| `test_step1_saves_class_teachers_onto_school_classes` | Step 1 writes `school_classes.teacher_id` correctly. |
| `test_step2_prefills_the_class_teachers_row_from_step_1` | Step 2 reads Step 1's pick back correctly. |
| `test_step2_store_writes_the_same_is_class_teacher_row_the_standalone_form_would` | Step 2's write is byte-for-byte what the standalone assignment form would create (teacher, subject, `is_class_teacher=true`, periods, academic year). |
| `test_step2_store_redirects_to_step3_after_the_last_class` | Per-class paging terminates correctly. |
| `test_step4_shows_the_same_readiness_notes_feasibility_service_produces` | Step 4 is a faithful `FeasibilityService` render, not a parallel computation. |
| `test_step4_link_to_generate_carries_the_chosen_style` | Step 3's choice survives into Step 5's link. |
| `test_full_wizard_run_on_seeded_school_ends_in_a_published_timetable` | **The plan's own required test** -- Steps 1-4 through the wizard's own routes, Step 5 through the real, already-tested `timetable.generate`/`timetable.generation.publish` endpoints, ending with both classes' class-teachers correctly holding period 1 on all 4 running days in the LIVE published timetable. |

Added to `tests/Unit/Services/Timetable/FeasibilityServiceTest.php`: `test_teacher_load_counts_co_teaching_periods_toward_the_co_teachers_own_load` (the teacher-load fix above).

Full `--filter=Timetable` run: **136 tests, 136 passed** (up from 125), zero regressions.

---

## Verification

Full suite, by exact test name against the documented baseline (grepped every `FAILED` line from a complete, untruncated run and tallied by class):

- API: `SanctumTokenAbilityTest` ×6 + `ApiAccessControlAbilityTest` ×1 = 7
- Admin: 14 distinct classes summing to exactly 30
- FeeFinance: 9 distinct classes summing to exactly 34
- **71 failed, matching the documented baseline's names and counts precisely. Zero new or different failures.**

Passing count: **1300**, up from 1289 (the state after T6 Item 4) -- the +11 delta is exactly this session's new tests (10 `TimetableWizardTest` + 1 `FeasibilityServiceTest`), none of them pre-existing tests newly broken.

---

## Deferred / not done this session

- **Manual browser click-through was not performed** -- this environment doesn't have an interactive browser session available. In its place: every GET step asserts `assertOk()` (fails on any server error/rendering exception) and the full end-to-end test drives every step's real HTTP path (including the two POST-heavy steps) through to a published, verified-correct timetable. This is Feature-test coverage of the actual request/response cycle, not just unit-level logic, but it is not the same as a human confirming the pages *look* right -- flagging explicitly rather than claiming a browser check that didn't happen.
- **"Copy from another class" / "Copy last year"** (Step 2) -- skipped per the plan's own explicit permission.
- **The manual single-slot editor's team-teaching support and club/elective blocks** (T6 item 4's deferred register) -- still not built, unchanged from the Item 4 report.

---

## Next

T6's plan is now fully built (Items 1-5). Per CLAUDE.md's phase-gate rule, this branch has NOT been merged to main -- the plan's own text reserves that for "the final combined walkthrough," which hasn't happened. Awaiting instruction on next steps: a combined walkthrough/real-data check (the plan's "THE TEST THAT MATTERS" section calls for generating with real class-teacher + subject data and comparing against the actual CLASS_WISE_TIME_TABLE.pdf), or proceeding straight to merge review.
