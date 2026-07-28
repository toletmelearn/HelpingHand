# Timetable module — Phase T4a report (the solver, pure logic)

Branch `timetable-module`, tag `timetable-T2-complete` (retroactive; see that tag's message). Continues from `timetable-T3-complete`.

Session-opening gate decision, recorded here per instruction: the T2/T3 manual real-school verification (Pushp Niketan's real structure — assembly periods, a real Saturday, a combined Sanskrit/Hindi group) is **relocated, not skipped** — it will be performed as one combined final walkthrough covering T2 through T5, before the merge to `main`. `timetable-T2-complete` was tagged code-complete on that basis; `CLAUDE.md` now records the general rule this session's own history motivated: phase tags are gates, and skipping a verification step must be a conscious, recorded decision, never silent.

This session also committed two pieces of pre-existing, uncommitted work found at session start: two T4a migrations (`prefer_morning` on `subjects`, `teacher_id`/`periods_per_week` on `combined_class_groups`) that had already been run against the dev DB in an earlier interrupted session but never committed to git (commit `3655a4d`), and `CLAUDE.md` itself (commit `e1db430`).

---

## What T4a is

Per the plan: `app/Services/Timetable/GeneratorService.php`, a backtracking constraint solver, pure logic, **zero database writes**. It reads the existing timetable data model and returns an in-memory proposal (placements + unplaced lessons with reasons) for T4b to later turn into draft `timetable_slots` rows. Nothing about live data changes this session.

## Item 1 — Model/config prep

The two T4a migrations landed in `3655a4d` added `subjects.prefer_morning` and `combined_class_groups.teacher_id`/`periods_per_week`, but neither model's `$fillable` was ever updated to match — mass-assignment of either column would have silently failed. Fixed on both models. Also added `config('timetable.generator')` (`time_budget_seconds`, default 60; `backtrack_budget_per_lesson`, default 25) per the plan's "hard time budget... config" and "depth-limited... backtrack budget" requirements.

Commit `de826e7`.

## Item 2 — GeneratorService

**Input:** an academic-year string (matching `bell_timings`/`teacher_class_subject_assignments`' free-text column, same convention as `FeasibilityService`), a `Collection` of `SchoolClass` models, and an optional `academic_session_id` (for `combined_class_groups`, which does have a real FK).

**Lesson list:** one unit per period from every `teacher_class_subject_assignment` with `periods_per_week` set, plus one unit per period from every `combined_class_groups` row with both `periods_per_week` and `teacher_id` set (T4a's own columns) whose members are all within the requested class set. `require_consecutive` assignments are pre-paired into double-period units (5 → two pairs + one single) so adjacency is guaranteed by domain generation rather than checked after the fact — see item 4.

**Domain:** teaching-type (`BellTiming::teachingType()`), active bell timings, scoped by `class_section` the same way `FeasibilityService` already does (null = global, or an exact name match — combined-group lessons only ever use the global slots, since a free-text field can't cleanly match multiple member classes at once; documented as a scope limit, not attempted).

**Hard constraints**, all enforced before a slot is ever offered as a candidate: class free, teacher free, `TeacherAvailability` blocks honoured, teacher `max_periods_per_day`/`max_periods_per_week` (per-teacher, loaded once), same-subject-per-class-per-day capped at 1 (or 2, only for `require_consecutive`, only as the guaranteed-adjacent pair), and a combined group's lesson always lands at the identical bell timing for every member class in one commit.

**Algorithm — most-constrained-first (MRV):** pending lessons are ranked by their current legal-slot count (fewest first), tie-broken by highest `periods_per_week`. Recomputing this for every pending lesson on every iteration would be O(n²); instead only lessons sharing the just-placed lesson's teacher or a class have their cached count invalidated after each commit, since nothing else could have changed.

**Backtracking, bounded:** when a lesson has zero legal slots, the solver looks at up to `backtrack_budget_per_lesson` of its domain slots and, where exactly one already-committed **single-period solo** placement is blocking it, tries relocating that placement elsewhere before giving up. **Scope limit, deliberate:** double-period and combined-group placements are never relocated once committed — only single-period solo lessons participate in backtracking. This was a conscious simplification to keep the solver's behaviour predictable and its worst case bounded; it means a double-period or combined lesson placed early could in principle block a later lesson that a full CSP backtracker might have resolved. Not observed in any of the five required scenarios, but noted here as the honest limitation.

**Soft preferences (slot-ordering only, never a rejection):** `prefer_morning` biases toward earlier `order_index` and away from the day's last period; a class's lighter days are preferred (spreads a subject out); a slot adjacent to the teacher's other placements that day is preferred (minimises gaps).

**Give-up path:** a lesson that exhausts its domain and its backtrack budget is marked UNPLACED with a sentence naming the dominant blocking constraint (teacher fully booked, class fully booked, subject's daily cap, or teacher's day/week cap) — never a raw code or stack trace. A wall-clock deadline (`time_budget_seconds`) stops the whole run early and marks everything still pending as unplaced with an explicit time-budget reason, rather than ever looping indefinitely.

Commit `93a8b86`.

## Item 3 — Tests (the plan's 5 required scenarios)

All five run against a real seeded fixture (`Model::create()` directly, matching this module's established test convention — no factories exist for these models), in `tests/Unit/Services/Timetable/GeneratorServiceTest.php`:

| # | Scenario | Result | Time |
|---|---|---|---|
| 1 | Tiny solvable school (2 classes, 3 teachers, 3 subjects × 4 periods/week, 4-day×4-period grid) | 100% placed (24/24), verified via `assertNoTeacherDoubleBooked`/`assertNoClassDoubleBooked`/`assertSubjectAppearsAtMostOncePerDay` helpers | 44.2s* |
| 2 | Infeasible: 1 teacher needs 3 periods, availability blocks 3 of the only 4 slots (1 truly free) | 1 placed, 2 correctly unplaced, each reason names the teacher and "no remaining free slots" | 0.60s |
| 3 | `require_consecutive`, periods_per_week=2, 4-period single-day grid | 1 placement with 2 adjacent `bell_timing_ids` (order_index difference of exactly 1) | 0.43s |
| 4 | Combined group, 2 member classes, periods_per_week=2 | 4 placement rows (2 periods × 2 members), both members always share the same `bell_timing_id` and `combined_class_group_id` | 0.66s |
| 5 | Realistic: 12 sections, 4 subjects, 8 teachers, 240 lesson units, 6-day×8-period (48-slot) grid | 100% placed (240/240), zero double-booking | 6.22s |

\* Scenario 1's 44.2s is one-time SQLite schema-migration cost paid by the *first* test that touches the DB in the whole PHPUnit process (confirmed: scenarios 2–5, run immediately after in the same process, complete in under a second each except the 240-lesson scenario 5). Not solver overhead.

Commit `fb133de`.

---

## Verification

**Full suite**, re-verified **by exact test name**, not just count (grepped every `FAILED` line from a complete, untruncated run and tallied by class):

- API: `SanctumTokenAbilityTest` ×6 + `ApiAccessControlAbilityTest` ×1 = 7
- Admin: 14 distinct classes summing to exactly 30
- FeeFinance: 9 distinct classes summing to exactly 34
- **71 failed, matching the documented baseline's names and counts precisely. Zero new or different failures.**

Passing count: **1238**, up from T3's 1233 — the +5 delta is exactly this session's new `GeneratorServiceTest` scenarios, none of them pre-existing tests newly broken.

Total suite duration: ~2386s (~40 min) — this is a large legacy app whose tests mostly build fixtures via direct `Model::create()` rather than factories; not specific to this session's changes.

---

## Deferred / not done this session

- **Backtracking for double-period and combined-group placements** — documented scope limit above; not attempted, not needed by any of the 5 required scenarios.
- **T4b** (job, draft/publish workflow, generation UI) — explicitly out of scope per instruction; `GeneratorService` writes nothing to the database, by design, ready for T4b to wrap.
- **T2/T3 real-school manual verification** — relocated to a single combined T2–T5 walkthrough before merge to `main`, per this session's opening gate decision.

---

## Next

Per the plan's phasing (T0 → T1a → T1b → T1c → T2a → T2b → T3 → **T4a** → T4b → T5), T4a is code-complete and tested. Next is T4b (job wrapper, draft/publish workflow, generation UI) — not started, awaiting explicit instruction, per this session's stop condition.
