# Timetable module — completion report (T1–T5)

Branch `timetable-module`, off `main` @ `remediation-complete`. This closes out the module per `docs/plans/timetable-module-plan.md`. **Not merged to `main`** — merge is blocked pending the owner's final manual walkthrough (gate decision recorded in the T2/T4a session history and in `CLAUDE.md`'s phase-gate rule); see the walkthrough checklist delivered alongside this report.

---

## Per-phase summary

### T0 — Pre-flight gate
Confirmed `main` pulled/migrated, post-rebuild verification audit's HIGH findings addressed, fresh DB backup existed, branched `timetable-module` from `main`. Commit `5c082d1`.

### T1 — Manual timetable, hardened (sellable on its own)
Tag **`timetable-T1-complete`**.

| Sub-phase | What shipped | Commit |
|---|---|---|
| T1a | DB-level double-booking protection: composite unique indexes on `timetable_slots` (class+section+period via `section_id_norm`, teacher+period), app-level `checkSlotConflicts()` as primary guard with the DB constraint as the safety net under it | `f9507a6`, policy narrowing `9922c2d` |
| T1b | Read-only Timetable Feasibility Report (`FeasibilityService`) — grid capacity, teacher load, conflict scan, all in hand-countable human sentences | `fa3d822`, sidebar link `c78ff32` |
| T1c | Printable PDFs: class / teacher / master timetable, A4 landscape, DomPDF | `51ecdd8` |

**Known, documented gap from T1a** (never closed): a class-wide slot (`section_id_norm=0`) and a specific-section slot of the *same* class at the *same* period have different `section_id_norm` values, so the DB constraint does not catch that cross-case — flagged for T1b's conflict scan at the time, but `FeasibilityService::conflictScan()` was never extended to check it either. Still open; see deferred register.

### T2 — Requirements layer
Tag **`timetable-T2-complete`** (message: *"T2 code-complete; manual verification relocated to final pre-merge walkthrough per owner decision"*).

| Sub-phase | What shipped | Commit |
|---|---|---|
| T2a | `periods_per_week`/`require_consecutive` on assignments; `teacher_availabilities` (click-to-toggle grid); `max_periods_per_day`/`max_periods_per_week` on teachers; `FeasibilityService` upgraded with required-vs-capacity/availability math | `1f2d789`, `af933e1`, `cc2c5d4`, `d2921b3` |
| T2b | `bell_timings.period_type` (teaching/assembly/prayer/break/zero/dispersal); Saturday/day-wise periods confirmed already supported (REPORT-THEN-STOP, no code needed); `combined_class_groups` + member pivot, one `TimetableSlot` row per member class sharing teacher/period; `FeasibilityService`/PDFs updated for both | `dc5bc06`, `d9f87b4`, `d788a8d` |

Full report: `docs/plans/timetable-T2-report.md`.

### T3 — Substitution engine (the daily-use hook)
Tag **`timetable-T3-complete`**.

`teacher_substitutions.bell_timing_id` replaces free-typed `period_number`; real scoring in `SubstituteFinderService` (free/class-match/subject-match/load, mandatory exclusions); "Teacher absent today" UI with one-click assign; daily arrangement-sheet PDF; read-only HR leave integration (approved leave surfaces as a shortcut into the flow). Commits `6147faf`, `b65dd90`, `4bbfbca`, `27041a2`, `49c1964`. Full report: `docs/plans/timetable-T3-report.md`.

### T4a — The solver (pure logic)
Not separately tagged (T4 tags at the phase boundary after T4b, per the plan). `GeneratorService`: MRV backtracking solver, hard constraints (class/teacher free, availability, day/week caps, subject-per-day cap, combined-group simultaneity), soft preferences as slot-ordering only, bounded local backtrack, 60s time budget, zero DB writes. All 5 plan-required test scenarios pass. Commits `de826e7`, `93a8b86`, `fb133de`. Full report: `docs/plans/timetable-T4a-report.md`.

### T4b — Job, draft/publish workflow, UI
`timetable_slots.status` (draft/published/archived) + `timetable_generations`; `GenerateTimetableJob` writes only drafts; "Generate (Beta)" UI with the required plain-language confirm dialog + status polling; Draft/Published toggle with visually distinct draft cells; PUBLISH (atomic, admin-only) archives-and-flips in one transaction; DISCARD deletes only its own drafts. Every reader outside draft review audited to `published()`-only. Commits `d83469f`, `01dbe8f`, `fe972c1`, `9161a53`, `44ab279`. Full report: `docs/plans/timetable-T4b-report.md`.

### T5 — Surfacing
This session.

1. **Parent portal** — `Parent\TimetableController::today()`: today's published periods for the parent's own child, substitutions applied ("Arrangement" badge). Security: `HomeworkController`'s exact class-match pattern.
2. **Mobile/app API** — `GET /api/v1/students/{studentId}/timetable-today`, token-gated in the same protected group as every other v1 route. Real ownership check via the `parents` table (see deferred register for why it can't use the Guardian system).
3. **Attendance linkage** — read-only reference panel on the period-wise attendance screen, today's published timetable for the class, substitutions applied. Zero change to attendance-marking behavior.
4. **Admin dashboard card** — today's substitution count + unfilled arrangements, same try/catch degrade pattern as the existing upcoming-events card, via a new mockable `SubstitutionDashboardService`.
5. Tests for all four: parent/cross-family isolation, substitution reflected in both view and API, API auth enforced (401 unauthenticated, 403 wrong role, 403 wrong family), dashboard degrades gracefully.

Commits `15da229`, `26fd09b`, `8b5d6f8`, `2e2c69c`.

---

## Verification

**Full suite**, re-verified **by exact test name**, not just count (grepped every `FAILED` line from a complete, untruncated run and tallied by class):

- API: `SanctumTokenAbilityTest` ×6 + `ApiAccessControlAbilityTest` ×1 = 7
- Admin: 14 distinct classes summing to exactly 30
- FeeFinance: 9 distinct classes summing to exactly 34
- **71 failed, matching the documented baseline's names and counts precisely across every phase of this module, including this final check. Zero new or different failures introduced by T1 through T5.**

Passing count: **1257**, up from T4b's 1243 — the +14 delta is exactly this session's new T5 tests (5 parent-portal + 5 API + 2 attendance-linkage + 2 dashboard-card), none of them pre-existing tests newly broken.

All migrations across all phases verified up/down/up against the real dev MariaDB before being committed.

---

## Deferred register (everything deferred, across all phases, gathered here)

### By design (from the plan itself, `DEFERRED BY DESIGN` section — never attempted)
- **OR-Tools / external solver sidecar** — documented upgrade path only, revisit if a client school exceeds ~40 sections.
- **Room/lab management** — add only when a client school actually has contended labs.
- **Real-time collaborative editing, Socket.IO anything.**
- **aSc XML import** — nice-to-have, add if a prospect school already uses aSc.
- **Transport-timing sync** — needs the Transport module's state assessed first.

### Found during the build, deliberately scoped out (each phase's own report has the full reasoning)
- **T1a's class-wide-vs-specific-section collision gap** (above) — the DB constraint and `FeasibilityService.conflictScan()` both miss it. Not a regression, never closed.
- **T2b Saturday cosmetic follow-up** — explicitly shading a short Saturday's trailing empty cells instead of a bare "N/A" in the grid. Noted, never requested.
- **Combined-group editing/rescheduling** — only *fresh placement* of a combined group's slot is implemented (T2b); moving or clearing an already-placed combined group's slots needs its own conflict-check design (self-exclusion by `combined_class_group_id`, not just row `id`). Still true through T4b — the draft/publish workflow doesn't touch this either.
- **`teacher-substitutions/rules` and `/rules` POST routes** — pre-existing, call controller methods (`rules`/`updateRules`) that don't exist; the controller only defines `substitutionRules()`. Found reading the substitution controller for T3, still unfixed — out of scope every time it's been seen since.
- **`TeacherSubstitutionController`'s legacy CRUD actions** (`index`, `create`, `store`, `edit`, `update`, `destroy`, `today`, `absenceOverview`) — no policy-based authorization, only `auth` middleware. Only the T3-added actions (`absentToday`, `assignFromSlot`, `arrangementSheetPdf`) got real policy gates. A larger, separately-scoped hardening pass.
- **T4a's backtracking scope limit** — the bounded local backtrack only relocates already-committed *single-period solo* placements; double-period and combined-group placements are treated as fixed once placed. Not observed to matter in any of the 5 required scenarios or the T4b workflow tests, but an honest limitation of the current solver.
- **T4b whole-school/multi-class Generate** — "Generate (Beta)" is scoped to one class per run. `GeneratorService`/`GenerateTimetableJob` already accept an arbitrary class collection; the UI just doesn't expose more than one class yet. Additive, not a redesign.
- **BellSchedule deprecation** — `app/Models/BellSchedule.php` + `BellScheduleController` + `bell-schedules.*` routes are a separate, still-registered legacy system that predates `BellTiming`/the timetable module entirely. The whole module was built on `BellTiming` per the plan's explicit "build ON the existing tables" rule; `BellSchedule` was never touched, never migrated, never removed. It still has live routes today. Removing it is a standalone cleanup task, not part of this module's scope, but it's dead weight worth scheduling.

### Found during T5, newly discovered this session
- **`Student::whereHas('guardians', ...)` in `API\DashboardController::parentDashboard()` is broken code** — `Student` only defines the singular `guardian()` relation (`belongsToMany(Guardian::class, 'student_guardian', ...)`); `guardians()` doesn't exist, so this call throws. Also true of `API\GuardianController::children()`'s eager-load `'students.guardians'`. Never surfaces in practice because `ApiAccessControl::parentBlockedRoutes()` already deliberately blocks `dashboard.parent` and every `guardians.*` route before the controller ever runs — this looks like a parked, half-built feature, not a live regression. Not fixed (a real fix is a parent-identity redesign, well beyond this module).
- **Even if the relation existed, it wouldn't work**: `guardians.id` (the `Guardian` model's own PK) is not `users.id` — there is no `user_id` column on the `guardians` table at all. There is no working bridge from a Sanctum-authenticated `User` to a `Guardian`/`Student` anywhere in this codebase today. T5's new API endpoint deliberately does **not** depend on this — it bridges through the `parents` table (`ParentModel`) by email instead, a self-contained, real ownership check that doesn't require fixing the broader system.
- **`API\StudentController::attendance()`/`results()`/`fees()` have zero ownership check** — any authenticated Sanctum token (any role) can read any student's attendance/results/fees by guessing an id; no `isStudentSelf()`-style gate is applied to these three specifically (unlike `dashboard.student`, which is gated). A real IDOR gap, pre-existing, not introduced or fixed this session — flagged here because T5's own new endpoint deliberately does the opposite (real, per-request ownership verification) and the contrast is worth a maintainer's attention.

---

## Next

Per the plan's own close-out step: full click-through (delivered separately as the walkthrough checklist), the owner's manual walkthrough of T1 through T5 against Pushp Niketan's real structure, then tag `timetable-T4-complete` and `timetable-T5-complete`, merge to `main` with `--no-ff` only after that walkthrough passes. **Do not merge before then** — this is a recorded gate, not a suggestion.
