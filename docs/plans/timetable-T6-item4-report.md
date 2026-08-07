# Timetable module — Phase T6 Item 4 report (team teaching)

Branch `timetable-module`, continues from the T6 Item 1 revision (`73bd25c`). Per the plan's session sequencing and the approved scope, this session did **team teaching only** — club/elective blocks and the manual single-slot editor are explicitly deferred, and Item 5 (the wizard) was not started.

---

## REPORT-THEN-STOP resolution

Item 4's plan text scopes two sub-features ("team teaching" and "elective/club blocks") and explicitly permits building only part of it: *"Whatever isn't cleanly doable this pass is explicitly deferred with a written reason -- do NOT half-build it. A correct partial (just team-teaching, say) beats a shaky full attempt."*

I investigated the schema and reported:

- **Team teaching**: fits cleanly as a nullable `co_teacher_id` on `teacher_class_subject_assignments` and `timetable_slots` -- one row, two teacher columns, preserving the existing class-uniqueness constraint (writing two rows, one per teacher, the way combined-class-groups writes one row per member *class*, would collide with the class+period unique index since both rows would share the identical class+period).
- **Club/elective blocks**: no existing "named activity block decoupled from periods-per-week math" concept in the schema; the closest fit (ad-hoc `Section` rows, since `sections` is already an unscoped free-text label pool) is a genuinely separate feature with open UI/rendering questions.

Approved: team teaching only, generator + PDF path; manual editor and clubs both deferred to the register, with two additional constraints to honour:

1. A team-taught slot must not read as a teacher clash between its own two teachers, at either the DB or app layer.
2. Each team-teacher is still fully "busy" that period for their own other classes -- the solver must still block them from being double-booked elsewhere.

---

## What shipped

### Schema
- `teacher_class_subject_assignments.co_teacher_id` -- nullable FK -> `teachers`, `nullOnDelete` (asymmetric vs. the primary `teacher_id`'s cascade -- losing the co-teacher's record shouldn't destroy the whole assignment).
- `timetable_slots.co_teacher_id` -- same, nullable FK, `nullOnDelete`.
- New unique index `timetable_slots_co_teacher_bell_status_unique` on `(co_teacher_id, teacher_active_key, status)`, reusing the generated column T4b already built (NULL when archived, so history never collides). Verified via `--pretend` / migrate / rollback / re-migrate against the real dev MariaDB -- the rollback needed a fix mid-verification: MariaDB refuses to drop a unique index before the foreign key that depends on it for its "FK column must be indexed" requirement (error 1553), so `down()` drops the FK first, then the index, then the column.
- **Known, documented gap**: this index catches co-teacher-vs-co-teacher double-booking, but not the cross case (person X is primary teacher in one row, co-teacher in another, same period). `GeneratorService::isHardLegal()` is the actual enforcer for that case -- the same "solver is primary guard, DB constraint is partial backstop" tradeoff already accepted for combined-groups' `teacher_active_key` carve-out, not a new class of risk.

### `GeneratorService`
A team-taught lesson carries `co_teacher_id` alongside `teacher_id` from `buildLessons()` (rotating) and `reserveFixedDailyLessons()` (fixed_daily) -- both pull from the same assignment rows, so a co-teacher behaves consistently under either style. `isHardLegal()`, `commit()`, and `uncommitPlacement()` all treat the co-teacher exactly like the primary teacher: busy-state check, `TeacherAvailability` block check, day/week limit check, and reservation, all mirrored. `attemptBacktrack()`'s blocker-relocation path preserves a relocated blocker's own `co_teacher_id` (dropping it there would silently "free" the co-teacher's busy state at the new slot, letting a different lesson double-book them). The MRV dirty-invalidation after each commit now checks both teacher columns on both sides.

Class-teacher period-1 reservations and combined-class-group lessons deliberately do NOT carry `co_teacher_id` -- out of scope per the approved proposal.

### Job / PDFs
- `GenerateTimetableJob` writes `co_teacher_id` through to each draft `TimetableSlot` row.
- `classPdf`: cell shows `Primary / Co-teacher` (matches the real PDF's own format).
- `teacherPdf`: query now matches `teacher_id` OR `co_teacher_id`, so a co-teacher's own PDF shows their team-taught periods; each cell shows `with {the other teacher}`.
- `masterPdf`: cell shows `Subject/Primary+Co-teacher` (`+`, not `/`, to avoid ambiguity with the cell's existing subject/teacher separator).

### Admin UI
- Teacher-subject assignment create/edit forms gained a "Co-Teacher" dropdown; `TeacherSubjectAssignmentController` validates `co_teacher_id` as `nullable|exists:teachers,id|different:teacher_id` and passes it through `store()`/`update()`.
- The assignment listing shows the co-teacher's name alongside the primary teacher's.

---

## Tests

Added to `tests/Unit/Services/Timetable/GeneratorServiceTest.php`:

| Test | Proves |
|---|---|
| `test_team_teaching_places_both_teachers_at_the_same_slot_without_a_false_clash` | A team-taught assignment places cleanly (0 unplaced), every placement carries both `teacher_id` and `co_teacher_id` correctly -- no false self-clash between a lesson's own two teachers. |
| `test_team_teacher_cannot_be_double_booked_elsewhere_in_the_same_period` | A single-slot grid where a team-teacher's co-teaching commitment competes with their OWN solo commitment in a different class: exactly one of the two lessons places, the other goes unplaced, and the shared teacher never appears twice (as primary or co-teacher) at the same period. |

Added to `tests/Feature/Admin/TimetableSlotUniqueConstraintsTest.php`:

| Test | Proves |
|---|---|
| `test_db_constraint_allows_a_team_taught_slot_without_a_false_clash` | A single `TimetableSlot` row with both `teacher_id` and `co_teacher_id` set saves without tripping either unique index. |
| `test_db_constraint_blocks_a_direct_duplicate_co_teacher_slot` | Two different rows (different class/primary-teacher/subject) sharing the same `co_teacher_id` at the same period collide via the new unique index. |

Full `--filter=Timetable` run: **125 tests, 125 passed** (up from 121), zero regressions.

---

## Verification

Full suite, by exact test name against the documented baseline (grepped every `FAILED` line from a complete, untruncated run and tallied by class):

- API: `SanctumTokenAbilityTest` ×6 + `ApiAccessControlAbilityTest` ×1 = 7
- Admin: 14 distinct classes summing to exactly 30
- FeeFinance: 9 distinct classes summing to exactly 34
- **71 failed, matching the documented baseline's names and counts precisely. Zero new or different failures.**

Passing count: **1289**, up from 1281 (the state after T6 Item 3) -- the +8 delta is exactly this session's new tests (4 from the T6 Item 1 revision + 4 from this item), none of them pre-existing tests newly broken.

---

## Deferred / not done this session

- **Club/elective blocks (Item 4, part 2)** -- deferred whole, with the written reason above, per the plan's own explicit permission.
- **Manual single-slot editor** (`TimetableController::store()`/`checkSlotConflicts()`) -- does not support `co_teacher_id`; team-teaching slots are created via Generate only this pass.
- **`FeasibilityService::teacherLoad()` does not count a co-teacher's team-taught periods toward their own load** -- an omission I noticed but wasn't in the approved scope (generator + PDF path only). Flagging as a candidate follow-up: right now a co-teacher's true weekly load is understated on the feasibility/readiness report.
- **Items 5** (the wizard) -- not started, per explicit instruction to stop after Item 4.

---

## Next

Awaiting explicit instruction. Candidates: Item 5 (the wizard), or closing the `FeasibilityService::teacherLoad()` gap noted above, or the deferred club/elective blocks / manual-editor team-teaching support.
