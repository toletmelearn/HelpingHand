# Timetable module — Phase T6 Item 1 report (class-teacher period-1 rule)

Branch `timetable-module`, continues from `timetable-T5-complete` plus the post-completion navigation-gap fix and whole-school Generate feature. Per `docs/plans/timetable-T6-plan.md`'s explicit session sequencing ("Do NOT attempt all five in one session"), **only Item 1 was done this session.** Items 2–5 are not started.

---

## REPORT-THEN-STOP gate (approved before any schema/code work)

Investigated where "class teacher" should live before touching anything, per the plan's explicit instruction. Finding: **three parallel, non-synchronized "class teacher" concepts already existed:**

| Source | What it is | Business rules | Who reads it |
|---|---|---|---|
| `school_classes.teacher_id` | Plain FK, set via the Class edit form's "Class Teacher" dropdown | None | Only the class list display |
| `teacher_class_subject_assignments.is_class_teacher` | Boolean flag on a subject-assignment row, set via a checkbox on the Teacher-Subject Assignment form | One class-teacher per (class+section+academic_year); a teacher may be class-teacher of at most 2 classes | `TeacherAcademicService`, `Teacher::assignedClasses()`, the teacher-portal dashboard, `Teacher/TeacherClassController`, `API/TeacherController` -- the field a logged-in teacher's own portal already treats as ground truth |
| `teacher_class_assignments.role='class_teacher'` (`Admin\TeacherClassAssignmentController`, the "survivor" system from the earlier navigation-gap analysis) | A separate table, one row per teacher/class/role | One `class_teacher` per class | Nothing else -- isolated from subject/periods data entirely |

None of the three sync with each other. **Approved recommendation, built on this session:** `teacher_class_subject_assignments.is_class_teacher`, because it's the only one already co-located with `subject_id`/`periods_per_week` (what the period-1 rule needs directly, no joins), and it's the one the teacher-facing portal already trusts -- building the solver rule on the same field guarantees "my dashboard says I'm class teacher of 9B" and "the generated timetable puts me in period 1 of 9B" read from the identical row.

`school_classes.teacher_id` and the `teacher_class_assignments` survivor table are **not touched, not synced, not deprecated** this session -- that's a separate, larger data-integrity cleanup, flagged but out of scope for "teach the solver the period-1 rule."

---

## What shipped

`GeneratorService::reserveClassTeacherPeriod1()` -- runs **before** the normal MRV solve:

1. For every requested class with an `is_class_teacher = true` assignment that also has `periods_per_week` set, build a single-period "lesson" for that teacher/subject.
2. For every day the class has at least one teaching period, find "period 1" (the lowest-`order_index` teaching bell timing in that day's own class-filtered domain -- reuses the exact same `class_section` matching convention every other lesson type already uses) and commit the class-teacher there directly, bypassing MRV entirely (this is a mandatory-first placement, not a solver choice).
3. That assignment is **excluded** from the normal `periods_per_week`-driven lesson loop in `buildLessons()` -- period 1 daily *is* this assignment's placement, not an addition stacked on top of it. (Its stored `periods_per_week` number is not separately re-validated against "one per teaching day" -- in practice a school sets it to match the number of teaching days, but the rule doesn't require that equality; it always reserves one per day.)
4. A class with no `is_class_teacher` row (or one with no `periods_per_week` set) is simply skipped -- not an error, generates normally.
5. **Impossible case handled explicitly, never silently:** since a teacher may be class-teacher of up to 2 classes, and period 1 of a given day is typically the identical physical `bell_timing_id` across every class (when `class_section` is unscoped), a teacher who's class-teacher of two *requested* classes can collide on some day's period 1. This produces a human-readable entry in a new top-level `warnings` array in `GeneratorService::generate()`'s return value -- never a crash, a silent drop, or a double-booking. `TimetableSlot`-level no-double-booking is unaffected: `assertNoTeacherDoubleBooked` holds in the clash test.

### Return-shape changes
- New `warnings` key (array of strings) alongside `placements`/`unplaced`/`stats`. Already flows through unchanged: `GenerateTimetableJob` stores the whole `$result` blob in `TimetableGeneration.report`, so warnings are persisted automatically with zero job changes needed.
- `stats.total_lessons` now includes the forced period-1 attempts (placed or warned) alongside the normal per-period lesson count, so the placement-percent math stays meaningful.
- New `stats.warnings_count`.

### Refactor needed to support this
`loadTeacherLimits(Collection $lessons)` (derived teacher IDs from the already-built lesson list) became `loadTeacherLimitsForClasses(Collection $classIds, ?int $academicSessionId)` (derived from the requested classes directly), since teacher day/week limits must be loaded *before* the class-teacher reservations commit, which is *before* the normal lesson list is even built. `buildLessons()` gained an `$excludedAssignmentIds` parameter.

---

## Tests

Added to `tests/Unit/Services/Timetable/GeneratorServiceTest.php`:

| Test | Proves |
|---|---|
| `test_class_teacher_holds_period_1_every_day` | Across a 4-day grid, the class-teacher's teacher/subject occupies exactly the period-1 slot on all 4 days; zero warnings. |
| `test_class_with_no_class_teacher_still_generates` | No `is_class_teacher` row -> normal 100%-placed generation, no warnings, unaffected by the new code path. |
| `test_same_teacher_class_teacher_of_two_classes_sharing_period_1_reports_a_warning` | The structurally-impossible clash produces a non-empty, readable `warnings` array (containing "class teacher") and **zero** teacher double-bookings in the actual placements. |

All 5 pre-existing `GeneratorServiceTest` scenarios re-verified green (no regression from the `loadTeacherLimits` refactor). Full `--filter=Timetable` run: **107 tests, 107 passed**, zero regressions across every timetable-related test (T1 through T5, the navigation-gap fix, and whole-school Generate).

---

## Verification

Full suite, by exact test name against the documented baseline (grepped every `FAILED` line from a complete, untruncated run and tallied by class):

- API: `SanctumTokenAbilityTest` ×6 + `ApiAccessControlAbilityTest` ×1 = 7
- Admin: 14 distinct classes summing to exactly 30
- FeeFinance: 9 distinct classes summing to exactly 34
- **71 failed, matching the documented baseline's names and counts precisely. Zero new or different failures.**

Passing count: **1271**, up from 1268 (the state after the navigation-gap fix + whole-school Generate) -- the +3 delta is exactly this item's new `GeneratorServiceTest` scenarios, none of them pre-existing tests newly broken.

---

## Deferred / not done this session

- **Items 2–5 of the T6 plan** (style toggle, per-class period counts, split-teacher/club periods, the wizard) -- explicitly out of scope per the plan's own session-sequencing instruction. Not started.
- **Syncing/deprecating `school_classes.teacher_id` and the `teacher_class_assignments` survivor table** -- the REPORT-THEN-STOP finding flagged this as a real, separate data-integrity gap (three unsynced sources of the same fact), but fixing it is a larger cleanup than this item's scope.
- **`periods_per_week` mismatch on a class-teacher's own assignment** -- if a school sets it to something other than the class's actual teaching-day count, the rule still reserves one period 1 per day regardless (no validation/warning for the mismatch itself). Not flagged as a problem by the plan; noted here for completeness.

---

## Next

Per the plan's own sequencing, Item 1 (the flagship rule) is done. **The owner's real-data check is the next step**: assign real class teachers, generate, and confirm each class teacher lands in period 1 against the actual Pushp Niketan structure -- this is the check the plan calls out explicitly as the one that matters. Items 2 (style toggle) and 3 (per-class period counts / senior classes ending early) are next in the plan's suggested split, not started, awaiting explicit instruction.
