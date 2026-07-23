# TIMETABLE MODULE — EXECUTION PLAN FOR CLAUDE CODE
## HelpingHand ERP · Laravel + MySQL + Blade + XAMPP · DomPDF for output
## Save at: docs/plans/timetable-module-plan.md

**How to use:** One phase per fresh Claude Code session. Each phase has a paste-able prompt and a manual "Verify" checklist for you. Never start a phase until the previous one's verification passes. Commit + push after every phase. Work on branch `timetable-module`.

**Architecture decisions (already made — Claude Code must not revisit them):**
- Build ON the existing tables: `school_classes` (canonical classes), `subjects`, `teachers`, `bell_timings` (periods), `timetable_slots` (placed timetable), `teacher_class_subject_assignments` (who teaches what where), `teacher_substitutions`. Creating any parallel table for these concepts is FORBIDDEN.
- Pure Laravel/MySQL/Blade. NO Node.js, NO Redis, NO Socket.IO, NO Postgres, NO new JS frameworks. Queued jobs use the existing queue driver. PDFs use DomPDF (already in composer.json).
- Auto-generation is a PHP backtracking solver in a queued job. NO OR-Tools, no external solver services.
- Manual timetable editing + validation ships first; auto-generate comes last, as Beta.

**Golden rules for every session:** scope fence (only this phase's items; unrelated problems are listed, never fixed); tests after every numbered item; one commit per item; session ends with a report saved to docs/plans/ and pushed.

---

# PHASE T0 — PRE-FLIGHT GATE (do not skip)

Before any timetable work, confirm in the live/main working copy:
1. `C:\xampp\htdocs\HelpingHand` is on `main`, pulled, migrated (the stale-checkout problem is resolved).
2. The post-rebuild verification audit has been re-run on main and its HIGH findings (especially promotion authorization, if confirmed missing on main) are fixed or consciously deferred.
3. Fresh DB dump exists this week in storage/backups/.
4. `git checkout -b timetable-module` from main.

If any of 1–3 is false, STOP — finish that first. Timetable work on an unverified base repeats the Codex-audit confusion.

---

# PHASE T1 — HARDEN + POLISH THE MANUAL TIMETABLE (sellable on its own)

## T1a — Database-level double-booking protection

```
Read app/Models/TimetableSlot.php, its migration, Admin/TimetableController.php, and the timetable Blade views first. Then:

1. REPORT-THEN-STOP GATE: before adding constraints, run read-only duplicate checks on timetable_slots for each intended unique key: (school_class_id, section_id, day, bell_timing_id) and (teacher_id, day, bell_timing_id). Report any existing violating rows with full contents and STOP for my decision on each (which row survives). Only after my "proceed" continue.
2. Migration: add the two composite UNIQUE indexes above (teacher uniqueness must ignore rows where teacher_id is null, if nullable — use the appropriate pattern for MySQL). down() drops them.
3. Controller: wrap slot create/update in try/catch for the duplicate-key exception (QueryException 1062) and return the same friendly error format the existing clash-detection uses — the DB constraint is the safety net UNDER the existing checks, not a replacement.
4. Feature tests: creating a slot that double-books a class fails gracefully; double-booking a teacher fails gracefully; a valid slot still saves.
```

**Verify:** try to double-book via the UI — friendly error, not a 500. Commit.

## T1b — Feasibility checker (the trust-builder)

```
Build a read-only Timetable Feasibility Report. New service app/Services/Timetable/FeasibilityService.php + one admin page (route + Blade, policy-gated same as timetable viewing):

For a selected academic session, compute and display:
1. GRID CAPACITY per class-section: total weekly slots (days × periods from bell_timings) vs total periods currently placed vs (once T2 lands) periods required. For now: placed vs capacity.
2. TEACHER LOAD: per teacher — total placed periods/week, busiest day count, days with zero free periods. Flag > 36/week (make the threshold a config value) in red.
3. CONFLICT SCAN: any rows violating class/teacher uniqueness that predate the T1a constraints (should be zero now — this proves it), plus slots referencing inactive teachers/subjects/classes.
4. HUMAN-READABLE output — every flag is a sentence: "Ravi Kumar is placed for 41 periods but the week has 36 slots." "Class 8A has 12 empty periods." No raw numbers without context.
5. Unit tests for the computation logic with a seeded mini-timetable.
```

**Verify:** page loads, numbers match a hand-count for one class and one teacher. Commit.

## T1c — Printable outputs (schools judge software by its printouts)

```
Using the existing DomPDF pattern (Certificate PDF / CBSEReportCardController), add PDF exports policy-gated like timetable viewing:

1. Class timetable PDF: A4 landscape grid, days × periods, subject + teacher short-name per cell, school name header, session, class-section title.
2. Teacher timetable PDF: same grid for one teacher — class-section + subject per cell, free periods visibly blank.
3. Master timetable PDF: all classes × periods for one day per page, compact.
4. Buttons on the timetable pages for each. Filename pattern: timetable_{class|teacher}_{name}_{session}.pdf.
5. Handle the empty case (no slots) with a clear message instead of a blank PDF.
6. Feature tests: each export returns PDF content-type with %PDF magic bytes; unauthorized role gets 403.
```

**Verify:** print one of each; check a real printer/PDF reader, cells legible. Tag `timetable-T1-complete`. **This alone is demo-able to schools.**

---

# PHASE T2 — REQUIREMENTS LAYER (the question the solver will answer)

## T2a — Extend assignments + teacher availability

```
1. Migration: add to teacher_class_subject_assignments: periods_per_week (unsignedTinyInt, nullable), require_consecutive (boolean default false). Do NOT create a new assignments table.
2. Migration: new table teacher_availabilities: teacher_id FK, day (tinyint 1-7), bell_timing_id FK, is_available boolean default true, unique(teacher_id, day, bell_timing_id). Absence of a row = available (store only blocks).
3. Migration: add to teachers: max_periods_per_day (default 7), max_periods_per_week (default 36).
4. UI 1: on the existing teacher-subject-assignment screens, add the periods_per_week and require_consecutive inputs (validation: 1-12).
5. UI 2: teacher availability as a clickable day × period grid (Blade + vanilla JS, follow existing admin UI conventions): click to toggle blocked slots, save via one POST. Policy: admin edits anyone; a teacher may edit only their own (reuse the pattern from TimetableSlotPolicy).
6. FeasibilityService upgrade: now compare required periods (sum of periods_per_week per class) vs grid capacity, and per-teacher assigned-required load vs availability. New sentences: "Class 7B requires 44 periods but the week has 42." "Sunita Verma requires 30 periods but is available for only 26."
7. Tests: availability toggling, assignment validation, upgraded feasibility math.
```

## T2b — Special periods & schedule templates (the Indian-workflow moat)

```
1. Migration: add to bell_timings: period_type enum('teaching','assembly','prayer','break','zero','dispersal') default 'teaching'. Non-teaching periods are excluded from capacity math and from solver placement, but still print in the PDF grids (shaded).
2. Saturday support: confirm the timetable + bell_timings day model covers Mon-Sat with different period counts per day (schools run short Saturdays). If the current model assumes uniform days, extend bell_timings day-wise rather than inventing a new structure — report your findings and the minimal change BEFORE implementing (REPORT-THEN-STOP).
3. Combined classes: new table combined_class_groups (id, name, subject_id, session FK) + pivot to class-sections. A slot placed for a combined group writes one row per member class with the same teacher/period — the T1a unique constraints must still hold (the teacher teaches once; each class's slot is occupied). Update clash logic accordingly.
4. Update the FeasibilityService and PDFs for all of the above.
5. Tests throughout.
```

**Verify T2:** set up Pushp Niketan's real structure as test data — real periods including assembly, real Saturday, one combined Sanskrit/Hindi group. If your real school fits the model, client schools will. Tag `timetable-T2-complete`.

---

# PHASE T3 — SUBSTITUTION ENGINE (the daily-use feature; do before auto-generate)

```
Read the existing TeacherSubstitution model + controller, including the two stubbed scoring functions that return 0/false. Then:

1. Link substitutions to the real timetable: period_number int → bell_timing_id FK (migration with data mapping if any rows exist — check first, report row count).
2. Implement real scoring in app/Services/Timetable/SubstituteFinderService.php. For an absent teacher's slot, candidate teachers are scored:
   +40 free that period (from timetable_slots + availability) — MANDATORY filter, not just score
   +25 teaches this class already (any subject, from assignments)
   +20 teaches this subject (any class)
   +15 fewest total periods that day (scale inversely)
   -100 already substituting that period elsewhere (exclude)
   Return ranked list with the reasons as text per candidate ("Free • Teaches 7B Maths • 3 periods today").
3. UI: "Teacher absent today" flow — pick teacher + date → their day's slots listed → per slot, ranked substitute suggestions → one-click assign → substitution recorded. Policy-gate: admin + whoever holds a manage-substitutions permission.
4. Substitutions print on a daily "arrangement sheet" PDF (period × class grid of changes) — this is the sheet on the principal's desk at 8 AM.
5. If the teacher-leave feature exists in HR, integrate: approved leave today auto-suggests opening this flow (check what exists first — read-only — and report; integrate only if a clean hook exists, otherwise note as deferred).
6. Tests: scoring order with seeded scenarios; busy candidate excluded; assignment writes correctly.
```

**Verify:** run a realistic morning scenario end-to-end. Tag `timetable-T3-complete`.

---

# PHASE T4 — AUTO-GENERATION, BETA (the solver)

## T4a — The solver service (pure logic, no UI)

```
Build app/Services/Timetable/GeneratorService.php — a backtracking constraint solver. Specification:

INPUT: academic session + set of class-sections. Build the lesson list: for each teacher_class_subject_assignment with periods_per_week set, create that many lesson units (class, section, subject, teacher, require_consecutive).

SLOT DOMAIN: teaching-type bell_timings × active days, per class.

HARD CONSTRAINTS (must hold): class free; teacher free; teacher availability allows; teacher max_periods_per_day not exceeded; same subject max twice per class per day, and twice only when consecutive-flagged; combined-group lessons place simultaneously for all member classes; non-teaching periods never used.

ALGORITHM:
1. Order lessons most-constrained-first: fewest legal slots remaining (teacher availability × class free slots), tie-break by highest periods_per_week. Recompute order dynamically (MRV).
2. Place greedily with backtracking on dead ends. Depth-limited: on backtrack budget exhaustion for a lesson, mark it UNPLACED with the reason (which constraint had zero remaining slots) and continue — best-effort, never infinite loop.
3. Soft preferences as slot ORDERING (not constraints): prefer morning for flagged subjects, spread a subject across days, avoid last period for flagged subjects, minimize teacher gaps. Implement as a slot-scoring comparator.
4. Hard time budget: 60 seconds (config). Return best state at timeout.

OUTPUT: array of placements + array of unplaced lessons each with a human sentence ("Could not place Science for 8A: Mr. Sharma has no remaining free slots on any day — he needs 6 more but has 3."). NOTHING is written to the database by this service.

TESTS (the important part):
- Tiny solvable school (2 classes, 3 teachers, 4×4 grid) → 100% placement, all hard constraints verified by assertion helpers.
- Deliberately infeasible input (teacher requires more periods than availability) → correct unplaced list with correct reason, no hang.
- Consecutive-flag test: double periods land adjacent.
- Combined-group test: simultaneous placement.
- A realistic 12-section school fixture solves within the budget.
```

## T4b — Job, draft workflow, and UI

```
1. GenerateTimetableJob (queued): runs GeneratorService, writes results to timetable_slots with a new status column ('draft'/'published' — migration; ALL existing rows backfill as 'published'). Drafts for a session/class replace previous drafts only. Job progress + result summary stored (timetable_generations table: id, session, status, placed_count, unplaced_count, report JSON, timestamps).
2. Generation UI: "Generate (Beta)" button on the timetable page → confirm dialog stating it creates a DRAFT and touches nothing live → job dispatched → page polls a status endpoint → on completion, show the report: placement %, and the unplaced-lesson sentences prominently.
3. Draft review: the timetable grid gets a Draft/Published toggle view; draft cells visually distinct; the existing manual editor works on drafts (with all T1a protections).
4. PUBLISH action (admin-only, policy): atomically (transaction) archive current published slots for that class-section set (soft: status 'archived') and flip drafts to published. Substitutions and parent views read only published.
5. DISCARD DRAFT action.
6. The live timetable must be untouched by everything except PUBLISH — write a test that proves generation + discard leaves published slots byte-identical.
7. Feature tests: full generate→review→publish flow on the seeded school; publish is atomic; unauthorized users can't generate or publish.
```

**Verify:** generate for your real test-school data from T2's verification. Read the unplaced reasons — do they make sense in Hindi-explainable terms? Tag `timetable-T4-complete`.

---

# PHASE T5 — SURFACING (parent app + attendance)

```
1. Parent/student "today's periods" view: for the child's class-section, today's published slots (period time, subject, teacher) with substitutions applied (substitute teacher shown, marked "Arrangement"). Reuse the existing parent-portal auth pattern (same as Parent/HomeworkController's class-match security check).
2. API endpoint for the same (for the mobile/app context), token-gated like existing parent API routes.
3. Attendance linkage (read-only linkage, no attendance behavior change): on the period-wise attendance marking screen, show the timetabled subject+teacher for the period being marked, from published slots.
4. Dashboard: today's substitution count + unfilled arrangements on the admin dashboard card area (same pattern as the upcoming-events card).
5. Tests: parent sees only own child's class; substitution reflected; API auth enforced.
```

**Verify + close:** full click-through; write docs/plans/timetable-module-completion-report.md (per-phase summary, commit hashes, deferred list); tag `timetable-module-complete`; merge to main with --no-ff; STOP on any conflict; run full suite on main and compare against the known-failing ledger.

---

# DEFERRED BY DESIGN (do not build in this project)
- OR-Tools / external solver sidecar (documented upgrade path only — revisit if a client school exceeds ~40 sections)
- Room/lab management (add only when a client school actually has contended labs)
- Real-time collaborative editing, Socket.IO anything
- aSc XML import (nice-to-have; add if a prospect school already uses aSc)
- Transport-timing sync (needs the Transport module's state assessed first)

# ORDER RECAP
T0 gate → T1a constraints → T1b feasibility → T1c PDFs → T2a requirements+availability → T2b Indian workflows → T3 substitution → T4a solver → T4b draft/publish → T5 surfacing.
After T1 you have something to demo. After T3 you have the daily-use hook. T4 is the "wow" for sales demos. Realistic effort: T1-T2 ≈ 6-8 sessions, T3 ≈ 3-4, T4 ≈ 5-7, T5 ≈ 2-3.