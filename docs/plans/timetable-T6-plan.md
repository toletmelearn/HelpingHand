# PHASE T6 — SCHOOL-SPECIFIC GENERATION + SIMPLE WIZARD
## HelpingHand ERP · Timetable module · branch: timetable-module
## Save at: docs/plans/timetable-T6-plan.md

**Why this phase exists:** The real Pushp Niketan aSc timetable (CLASS_WISE_TIME_TABLE) revealed rules the generic solver doesn't know, and the current setup process (many separate forms) is too tedious for a real admin. T6 does two things at once: (1) teach the solver the school's actual rules, and (2) collapse the whole setup into ONE simple guided wizard.

**Acceptance target (state this to Claude Code):** given only each class's class-teacher and the teacher-subject-periods assignments, the generator must produce a timetable the school would actually accept — i.e. recognizably like the real aSc PDF: class teacher takes period 1 in their own class, no clashes, senior classes ending early respected.

**Rules:** one item per commit, tests after each, REPORT-THEN-STOP where marked, full-suite baseline check (71 known failures by name) before the session report. Do NOT merge to main — the final combined walkthrough gates the merge.

---

## THE SIMPLE FLOW WE ARE BUILDING (build the engine first, then the wizard on top)

The admin experience, start to finish, should be exactly this:

1. **Set class teachers** — one screen: every class in a list, pick a class-teacher (a teacher) for each. Done once a year.
2. **Assign subjects** — one grid screen per class (or one big grid): rows = subjects, choose the teacher + periods-per-week. The class-teacher's own subject is auto-flagged for period 1.
3. **Choose timetable style** — one toggle: "Same timetable every day" OR "Different each day (rotate)".
4. **Press Generate** — pick classes (or all), one click.
5. **Review & Publish** — see placement %, unplaced reasons, publish.

Everything below builds the pieces that make those 5 steps work.

---

## ITEM 1 — Class-teacher assignment (data + the period-1 rule)

```
Goal: every class has ONE class-teacher, and that class-teacher's subject is placed in PERIOD 1 of their own class, matching how the real school runs (see the aSc PDF: III-A period 1 = PRASHANT PATRA/Maths every day; III-B period 1 = LALITA DEVI/Hindi every day).

Read first: SchoolClass model, teacher_class_subject_assignments, the existing ClassTeacherAssignment situation (there are legacy tables — check remediation history; do NOT create a new parallel class-teacher table if school_classes already has a teacher_id/class-teacher concept). REPORT-THEN-STOP with your finding on where class-teacher should live before writing schema.

After approval:
1. Ensure a clean single source for "this class's class-teacher" (reuse school_classes.teacher_id if present, else the surviving assignment table). 
2. Ensure the class-teacher also has a normal teacher_class_subject_assignment for the subject they teach to that class (the wizard will enforce this; here just support it).
3. GeneratorService: add a HARD rule — for each class that has a class-teacher WITH a subject assignment in that class, period 1 (the first teaching bell timing of each day) is reserved for the class-teacher's subject/teacher. This applies every day the class runs. If the class-teacher has no subject in their own class, skip the rule for that class and note it in the unplaced/warnings report.
4. This must not break the no-double-booking guarantee: a teacher who is class-teacher of 9B can't simultaneously be forced into period 1 elsewhere — if a conflict is structurally impossible (same teacher is class-teacher of two classes), report it as a clear warning rather than failing silently.
5. Tests: class-teacher's subject lands in period 1 all days; a class with no class-teacher still generates; the impossible-double-class-teacher case reports a readable warning.
```

## ITEM 2 — Timetable style toggle (same-every-day vs rotate)

```
Goal: the admin chooses, per generation, whether the week is the SAME every day or DIFFERENT each day — both patterns exist in the real PDF (class-teacher period 1 is fixed daily; other subjects rotate).

1. Add a 'style' option to the generation request: 'fixed_daily' or 'rotating' (default rotating, matching aSc-style output).
2. GeneratorService respects it:
   - rotating: current behaviour — spread each subject's periods across different days/slots.
   - fixed_daily: generate ONE day's pattern, then repeat it for every running day (so Monday = Tuesday = ... for each class). Period counts must still be honoured (a subject with 6/week in a 6-day week = 1/day; a subject whose weekly count doesn't divide evenly into the running days is reported as not-fixed-daily-compatible, with a clear message, rather than guessed).
   - The class-teacher period-1 rule (Item 1) holds in BOTH styles.
3. Tests: fixed_daily produces identical days per class; rotating spreads; period-1 rule holds in both; an indivisible count in fixed_daily reports the readable warning.
```

## ITEM 3 — Per-class period counts (senior classes end early)

```
Goal: the real PDF shows XII SCIENCE ending at period 6 (7-8 blank), while lower classes use all 8. The grid is NOT uniform across classes.

1. Confirm how bell_timings models per-day periods (from T2b, days can already differ). Extend so a CLASS can have a shorter teaching day — the cleanest approach given existing schema: a per-class 'last_teaching_period' or a class↔bell-timing applicability, whichever fits what already exists. REPORT-THEN-STOP with the minimal-change proposal before building.
2. GeneratorService: never place a lesson in a period beyond that class's last teaching period. FeasibilityService and the PDFs already shade non-teaching cells — extend that to these class-specific trailing empties.
3. Tests: a class capped at period 6 never gets a period-7/8 placement; a full-8 class is unaffected.
```

## ITEM 4 — Split-teacher / elective / club periods (the slash cells)

```
Goal: the PDF has cells like "CS — GARISHT SINGH / Rajesh" (one class, two teachers same slot) and club/house periods (VI-A CLUB F1/F2, HA-1/HA-2). The existing combined-class-groups feature handles multiple-classes-one-teacher; this is different.

Scope this CAREFULLY and REPORT-THEN-STOP with a design before building — this is the most complex item and may be split or partially deferred:
1. Two-teacher-one-slot ("team teaching"): a single assignment that carries a second teacher; the slot occupies BOTH teachers (both must be free), displays both. 
2. Elective/club blocks: an optional named block placed at a fixed slot for a class, not tied to the normal subject-periods math.
3. Whatever isn't cleanly doable this pass is explicitly deferred with a written reason — do NOT half-build it. A correct partial (just team-teaching, say) beats a shaky full attempt.
4. Tests for whatever ships; PDFs render the multi-teacher cell like the real one.
```

## ITEM 5 — THE SIMPLE WIZARD (the whole point — build after 1–4 work)

```
Goal: collapse setup into ONE guided flow so a non-technical admin can go from nothing to a published timetable without hunting through separate screens. A single "Create Timetable" wizard, step by step, each step saving as it goes:

STEP 1 — Class Teachers: a table of all classes, a teacher dropdown per row, one Save. (Writes Item 1's class-teacher data.)
STEP 2 — Subject Assignments: for each class (paged, one class at a time with a progress bar "Class 3 of 24"), a simple grid — subject | teacher | periods/week — with the class-teacher's subject pre-filled at the top and auto-marked for Period 1. "Copy from another class" and "Copy last year" buttons if those are cheap; otherwise skip. (Writes teacher_class_subject_assignments.)
STEP 3 — Style: the fixed_daily vs rotating toggle (Item 2), with one plain-language sentence explaining each.
STEP 4 — Review readiness: run FeasibilityService and show the plain-English report BEFORE generating ("Class 7B needs 44 periods but the week has 42") so the admin fixes inputs first.
STEP 5 — Generate & Publish: the existing whole-school Generate (Beta) + review + publish, embedded as the final step.

Constraints:
- Reuse existing controllers/services under the hood — the wizard is a UI orchestration layer, NOT a rewrite. Each step calls the pages/logic that already exist.
- Every step is resumable (save-as-you-go); closing the browser mid-wizard loses nothing.
- Admin-only, one sidebar entry: "Create Timetable" — make THIS the obvious front door, with the individual pages still reachable for edits.
- Tests: the wizard writes the same data the individual pages do; a full wizard run on a seeded school ends in a published timetable.
```

---

## SESSION SEQUENCING

Do NOT attempt all five in one session. Suggested split:
- Session 1: Item 1 (class-teacher period-1 rule) — the flagship, most of the value.
- Session 2: Items 2 + 3 (style toggle, per-class periods).
- Session 3: Item 4 (split/club — or its scoped subset).
- Session 4: Item 5 (the wizard).

After each session: report to docs/plans/timetable-T6-item{N}-report.md, baseline check, push. After Item 1 especially, I (the owner) will do a quick real-data check: assign class teachers, generate, confirm each class teacher lands in period 1 — because that's the rule that makes the output recognizably ours.

## OUT OF SCOPE / DEFERRED
- Anything not needed to reproduce the real PDF's structure.
- aSc XML import (separate future feature).
- Rooms/labs (still not needed for this school).

## THE TEST THAT MATTERS
When Item 1 is done, generate with real class-teacher + subject data and compare against CLASS_WISE_TIME_TABLE.pdf: does each class teacher hold period 1 in their class? Is it clash-free? If yes, the solver now knows this school. Everything after is polish and ease-of-use.