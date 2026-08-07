# Timetable module — Phase T4b report (job, draft/publish workflow, UI)

Branch `timetable-module`, continues from `timetable-T2-complete`/T3/T4a (T4a not yet tagged; per this module's phase-gate rule in `CLAUDE.md`, T4b proceeds on explicit instruction with T4a's own gate — full-suite regression at zero-new-failures — already satisfied).

---

## Item 1 — Schema

- `timetable_slots.status` (`draft`/`published`/`archived`), default `'published'` — every pre-existing row backfills that way with no separate data migration, since they were the live timetable before this column existed.
- `timetable_slots.timetable_generation_id` (nullable FK) — ties a draft row back to the job run that produced it.
- New `timetable_generations` table: `academic_year` (free-text, matching the module's existing convention), `academic_session_id` (real FK, added in a small follow-up migration — `GeneratorService` needs it to scope `combined_class_groups`, which the free-text year can't), `school_class_ids` (JSON), `status`, `placed_count`, `unplaced_count`, `report` (JSON — the full `GeneratorService` output), `error`, `requested_by`, `started_at`/`completed_at`.
- **The T1a/T2b unique indexes had to be rebuilt.** They were built before `status` existed, so a draft row proposing a different arrangement for a class+period that already had a *published* slot would have collided with it as a false double-booking. Fix: two new STORED generated columns (`class_bell_active_key`, `teacher_active_key`) that are `NULL` for `status='archived'` rows — the same NULL-distinctness trick `section_id_norm` (T1a) and `teacher_bell_solo_key` (T2b) already relied on — so archived rows (which legitimately repeat the same class/period/teacher across many past publish events) never collide with anything, while `status` joins the composite key so a `draft` and a `published` row at the identical slot no longer collide with **each other**, but two `published` (or two `draft`) rows still do, exactly as before T4b.

All 4 migrations verified up/down/up against the real dev MariaDB. Commit `d83469f` (schema) + the academic_session_id follow-up folded into commit `01dbe8f`.

## Item 2 — GenerateTimetableJob

Runs `GeneratorService`, writes **only** `'draft'` rows — the live timetable is untouched by anything except PUBLISH. `TimetableGeneration` is created by the controller *before* dispatch (same pattern as `StageYearClosingJob`/`FinancialYearClosing`), so the UI has an id to poll immediately regardless of queue latency. Before writing new drafts, it deletes only the previous `'draft'` rows for the same `academic_year`+class scope ("drafts for a session/class replace previous drafts only" — never touches published/archived). A double-period placement's 2 `bell_timing_ids` become 2 `TimetableSlot` rows, same as any other period. Failures are caught and recorded on the generation row (`status='failed'`, `error` message) rather than left silently stuck at `'running'`.

Commit `01dbe8f`.

## Item 3 — Generate (Beta) UI

`TimetableController::generate()` (admin-only via `TimetableSlotPolicy::generate()`) creates the `TimetableGeneration` row and dispatches the job; `generationStatus()` is the polling endpoint, returning the unplaced-lesson sentences only once the run has actually completed (keeps the payload small while queued/running). The grid page's confirm dialog states plainly, in the plan's own required wording: *"Generate (Beta) will create a DRAFT timetable proposal for this class. It does NOT change the live, published timetable... Continue?"* On completion, the polling JS shows placement % and every unplaced sentence, then routes to the draft review.

**Scope decision:** Generate targets one class at a time (the class currently selected on the grid page), not a whole-school batch. `GeneratorService` and the job both already support an arbitrary class collection — extending the UI to multi-class/whole-school generation is a straightforward follow-up, not a redesign, deferred to keep this phase's surface area matched to what the plan actually asked for.

## Item 4 — Draft review

`index()` takes `?status=draft` and shows the active (not yet published/discarded) draft generation for the selected class instead of the live timetable — found via the most recent `TimetableSlot::draft()` row's `timetable_generation_id` for that class. Draft cells get visibly distinct styling (dashed amber border, amber slot card, a "DRAFT" badge) so nobody mistakes a draft for the live grid at a glance. `store()`/`storeCombined()` and `checkSlotConflicts()` are now status-aware via a hidden form field carrying the page's current toggle state through — **the existing manual editor works on drafts with all T1a protections intact**: a draft row is free to differ from what's live, but the same DB-level uniqueness (now status-scoped) and app-level conflict checks still apply *within* whichever status is being edited.

## Item 5 — PUBLISH / DISCARD

`publishGeneration()` (admin-only via `TimetableSlotPolicy::publish()`) does, in one `DB::transaction`:
1. Archive (`status='archived'`) every currently-`published` slot for the generation's class-section set.
2. Flip this generation's own `draft` rows to `published`.
3. Mark the generation `status='published'`.

**Scope decision:** archiving is whole-class, not a partial patch — matches `GeneratorService` building its lesson list from *all* of a class's `teacher_class_subject_assignments`, i.e. a full regeneration replaces the whole previously-published set for that class, not just the periods the draft happened to cover.

`discardGeneration()` (same policy) deletes only this generation's own draft rows and marks it `discarded` — published/archived rows are never touched, which is exactly what the mandatory byte-identical test proves.

## Item 6 — Tests

`tests/Feature/Admin/TimetableGenerationWorkflowTest.php`, 5 tests (all against a real seeded class — `QUEUE_CONNECTION=sync` in `phpunit.xml`, so the job runs synchronously inside the `generate()` POST, no queue faking needed):

| Test | Proves |
|---|---|
| `test_generate_then_discard_leaves_published_slots_byte_identical` | **Mandatory.** Full attribute snapshot of every published slot, taken before generate+discard and compared after — identical. |
| `test_full_generate_review_publish_flow` | End-to-end: generate → poll status → review page shows "Draft review" + unplaced sentences → publish → old published slot archived, draft rows now published, generation marked `published`. |
| `test_publish_is_atomic_a_failed_flip_leaves_nothing_changed` | Forces a real unique-constraint collision (an unrelated, out-of-scope class already holds a published slot for the same teacher+bell-timing the draft would flip into) — asserts the draft rows are still `draft`, nothing new got archived or published, and the generation never flipped to `published`. Whole transaction rolled back, not a partial write. |
| `test_non_admin_cannot_generate` | 403, zero `TimetableGeneration` rows created. |
| `test_non_admin_cannot_publish_or_discard` | 403 on both actions; generation status unchanged. |

Commit `44ab279`.

## Item 7 — Reader audit

Grepped every `TimetableSlot::`/`TimetableSlot ` usage across `app/` (5 files touch the model at all: `CombinedClassGroup`, `TeacherSubstitutionController`, `SubstituteFinderService`, `TimetableController`, `FeasibilityService` — confirmed no other controller, service, or Blade view reads timetable data). Status per reader:

| Reader | Status |
|---|---|
| `FeasibilityService::build()` main slots query | **Fixed** — `->published()` added |
| `FeasibilityService::conflictScan()`'s 3 queries (class dupes, teacher dupes, inactive-reference scan) | **Fixed** — all 3 now `->published()` |
| `SubstituteFinderService::findCandidates()` busy-teacher scan | **Fixed** — `->published()` added (a teacher only committed in a draft proposal isn't really unavailable) |
| `SubstituteFinderService::findCandidates()` periods-today scan | **Fixed** — `->published()` added |
| `TeacherSubstitutionController::absentToday()` timetable query | **Fixed** — `->published()` added |
| `TimetableController::classPdf()`/`teacherPdf()`/`masterPdf()` | **Fixed** — all 3 `->published()` |
| `TimetableController::index()` (the grid itself) | **By design, not a gap** — this IS draft review; shows `published()` or `draft()` depending on the toggle, never unscoped |
| `TimetableController::store()`/`storeCombined()`/`checkSlotConflicts()` | **By design, not a gap** — status-aware (item 4), writes/checks whichever status the page's toggle says |
| `CombinedClassGroup::slots()` relation | **Not read anywhere** — defined but unused outside the model itself; nothing to fix |
| Parent/student views, attendance linkage | **N/A** — T5 not built yet, nothing exists there to audit |

Commit `9161a53`.

---

## Verification

**Full suite**, re-verified **by exact test name**, not just count (grepped every `FAILED` line from a complete, untruncated run and tallied by class):

- API: `SanctumTokenAbilityTest` ×6 + `ApiAccessControlAbilityTest` ×1 = 7
- Admin: 14 distinct classes summing to exactly 30
- FeeFinance: 9 distinct classes summing to exactly 34
- **71 failed, matching the documented baseline's names and counts precisely. Zero new or different failures.**

Passing count: **1243**, up from T4a's 1238 — the +5 delta is exactly this session's new `TimetableGenerationWorkflowTest`, none of them pre-existing tests newly broken.

All migrations verified up/down/up against the real dev MariaDB before being committed.

---

## Deferred / not done this session

- **Whole-school / multi-class Generate** — scoped to one class per generation this session (see item 3's scope decision); the service and job already support a bigger class collection, so this is additive, not a redesign.
- **Editing/rescheduling an already-placed draft's combined-group slots** — inherits T2b's existing limitation (only fresh placement is implemented there); not touched this session.
- **T5 surfacing** (parent/student views, attendance linkage, dashboard cards) — not started, explicitly out of scope per instruction.

---

## Next

Per the plan's phasing (T0 → T1a → T1b → T1c → T2a → T2b → T3 → T4a → **T4b** → T5), T4b is code-complete and tested. Per the plan's own "Verify" step for T4, this should be exercised against real test-school data before tagging `timetable-T4-complete` — read the unplaced reasons and confirm they make sense in Hindi-explainable terms, same as the relocated T2/T3 walkthrough. Next is Phase T5 (surfacing) — not started, awaiting explicit instruction, per this session's stop condition.
