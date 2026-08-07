# Timetable module — Phase T6 Item 2 report (timetable style toggle)

Branch `timetable-module`, continues from T6 Item 1 (`c3573f5`). Per the plan's session sequencing, this session did **Item 2 only** — Items 3–5 not started.

---

## What shipped

A per-generation choice between two styles:

- **`rotating`** (unchanged, still the default) — each subject's periods spread across the week, exactly as every prior phase built it.
- **`fixed_daily`** (new) — one day's pattern is solved once and repeated identically on every day the class runs (e.g. Maths is always period 3, every single day).

### Schema
`timetable_generations.style` (string, default `'rotating'`) — every pre-existing generation row keeps its actual historical behaviour with no backfill needed.

### `GeneratorService::reserveFixedDailyLessons()`
Runs before the normal MRV solve, only when `style === STYLE_FIXED_DAILY`. Deliberately reuses `isHardLegal()`/`commit()` completely unchanged — each day's placement is just an ordinary single- or double-period `'solo'` commit, called once per running day using that day's own `bell_timing_id`(s) at a chosen `order_index` (or an adjacent `order_index` pair for a daily double period). No new constraint-checking code path was needed; only a new *orchestration* loop around the existing one.

For each solo assignment not already claimed by Item 1's class-teacher rule:
1. Compute `periodsPerDay = periods_per_week / runningDays`. If this doesn't divide evenly, the assignment is reported as **not fixed-daily-compatible** and gets **zero** placement attempt — never a partial or guessed pattern.
2. `require_consecutive` assignments must work out to exactly 2 periods/day (a single daily double period); non-consecutive assignments must work out to exactly 1. Anything else is the same "not fixed-daily-compatible" report.
3. A compatible assignment searches `commonOrderIndexes` — the `order_index` values present in the class's domain on **every** running day (the only values safely repeatable across all of them) — for the lowest-order_index candidate (or adjacent pair) that's legal on every running day simultaneously. Legality is checked read-only across all days before anything commits, so a candidate that fails partway through never leaves a partial commit behind.
4. No legal candidate found → a warning (a scheduling conflict, not a math problem) via the same `warnings` array Item 1 introduced.

**The class-teacher period-1 rule (Item 1) needed zero changes.** It was already "fixed daily" in nature — identical teacher/subject in period 1 every day, unconditionally, regardless of style — so "holds in both styles" fell out for free.

### Controller / job / UI
- `TimetableController::generate()` validates `style` (`nullable|in:rotating,fixed_daily`) and stores it on the generation row.
- `GenerateTimetableJob` passes `$generation->style` through to `GeneratorService::generate()`.
- The whole-school scope-selection screen (`/admin/timetable/generate`) gained a style radio toggle with a one-line plain-language explanation of each (matching the wizard's eventual Step 3, per the plan — this is the engine-level toggle the wizard will embed later).
- The review page and the status-polling endpoint now surface the `warnings` array (previously only stored in the generation's `report` JSON, never actually shown anywhere in the UI — a gap from Item 1 that's now closed alongside Item 2).

---

## Tests

Added to `tests/Unit/Services/Timetable/GeneratorServiceTest.php`:

| Test | Proves |
|---|---|
| `test_fixed_daily_produces_identical_pattern_every_day` | Every day shows the identical (teacher, subject) at each order_index. |
| `test_rotating_style_explicitly_still_spreads_subjects_across_days` | Rotating, passed explicitly (not just relying on the default), is unaffected. |
| `test_class_teacher_period_1_rule_holds_in_fixed_daily_mode` | Item 1 + Item 2 compose correctly. |
| `test_indivisible_periods_per_week_in_fixed_daily_reports_a_warning` | The readable-warning requirement; zero placement attempt for the incompatible assignment. |
| `test_consecutive_subject_in_fixed_daily_places_a_daily_double_period` | The 2-periods/day adjacent-pair path, including that both days repeat the identical pair. |

Added to `tests/Feature/Admin/TimetableWholeSchoolGenerationTest.php`:

| Test | Proves |
|---|---|
| `test_fixed_daily_style_flows_through_to_identical_daily_draft_slots` | `style` survives the real HTTP request -> `GenerateTimetableJob` -> stored draft `TimetableSlot` rows, not just the service layer in isolation. |
| `test_review_page_shows_style_warnings` | The previously-invisible `warnings` array now actually renders on the review page. |

Full `--filter=Timetable` run: **114 tests, 114 passed**, zero regressions.

---

## Verification

Full suite, by exact test name against the documented baseline (grepped every `FAILED` line from a complete, untruncated run and tallied by class):

- API: `SanctumTokenAbilityTest` ×6 + `ApiAccessControlAbilityTest` ×1 = 7
- Admin: 14 distinct classes summing to exactly 30
- FeeFinance: 9 distinct classes summing to exactly 34
- **71 failed, matching the documented baseline's names and counts precisely. Zero new or different failures.**

Passing count: **1278**, up from 1271 (the state after T6 Item 1) -- the +7 delta is exactly this item's new tests (5 `GeneratorServiceTest` + 2 `TimetableWholeSchoolGenerationTest`), none of them pre-existing tests newly broken.

---

## Deferred / not done this session

- **Items 3–5 of the T6 plan** (per-class period counts / senior classes ending early, split-teacher/club periods, the wizard) — not started, per the plan's own session sequencing.
- **Combined-group lessons are not style-aware** — they always use the normal (rotating-style) per-period placement regardless of the chosen style. The plan's Item 2 doesn't mention combined groups; this is a deliberate, documented scope limit, not an oversight.
- **Fixed-daily adjacency is defined as `order_index` differing by exactly 1**, not the array-position adjacency `domainSlotsForLesson()` uses for rotating-mode double periods. The two coincide in every normal configuration (no gaps in a day's teaching-period `order_index` sequence); noted as a minor simplification, not expected to matter in practice.

---

## Next

Per the plan's suggested session split, Items 2 and 3 were grouped together ("Session 2: Items 2 + 3"), but this session did Item 2 only, per explicit instruction. **Item 3 (per-class period counts / senior classes ending early) is next**, not started, awaiting explicit instruction. It has its own REPORT-THEN-STOP gate in the plan (how to model a class's shorter teaching day against the existing schema) that must be resolved before any schema work, same as Item 1's gate was.
