# Academic Management — Category B Fixture Cleanup Candidates

**Phase 1 output (read-only inventory, no writes performed).** Live query against the real
`helpinghand` mariadb database, 2026-09-04. Every dependency count below is a fresh query, not
carried over from the audit doc.

Legend: **DELETE** = safe to hard-delete (dependency check confirms zero real dependents).
**DEACTIVATE** = has a real dependent; use `is_active`/soft-delete, never hard-delete.
**SKIP** = already soft-deleted or otherwise leave untouched. **NEEDS DECISION** = ambiguous,
STOP-AND-ASK.

---

## school_classes (21 name matches out of 45 total rows)

| id | name | active | already deleted | real dependents | recommendation |
|---|---|---|---|---|---|
| 20 | ZZZTestClass | 1 | **yes** | 0 | SKIP (already gone) |
| 24 | ZTestClass6 | 1 | **yes** | 0 | SKIP (already gone) |
| 25 | Test Class XYZ | 1 | **yes** | 0 | SKIP (already gone) |
| 42 | AutoFix New | 1 | no | 0 | DELETE |
| 43 | AutoFix A | 1 | no | 0 | DELETE |
| 44 | AutoFix B | 1 | no | 0 | DELETE |
| 55 | DbgClass | 1 | no | 1 timetable_slot | NEEDS DECISION — see note 1 |
| 57 | UAT Class Alpha | 1 | no | 2 students, 2 tcsa, 1 combined-group member | DELETE as part of fixture cluster — see note 2 |
| 58 | UAT Class Beta | 1 | no | 2 students, 2 tcsa | DELETE as part of fixture cluster |
| 59 | UAT Class Gamma | 1 | no | 1 tcsa, 1 combined-group member | DELETE as part of fixture cluster |
| 101 | UATSource3 | 1 | **yes** | 0 | SKIP |
| 102–108 | UATTemplate4–10 (7 rows) | 1 | **yes** | 0 | SKIP |
| 109 | UATSixPeriod | 1 | **yes** | 0 | SKIP |
| 110 | BulkEdit UAT Dep | 1 | **yes** | 0 | SKIP |
| 112 | Dbg Class2 | 1 | no | 1 tcsa | NEEDS DECISION — see note 1 |

**Note 1** — `DbgClass`/`Dbg Class2` each have exactly one real-looking dependent row (a
timetable slot / a teacher-subject assignment). Before recommending DELETE or DEACTIVATE I'd want
to look at that one dependent row directly to confirm it's itself fixture data (created in the
same window as the class) rather than something a real user later attached to a conveniently-named
class. Flagging as NEEDS DECISION rather than guessing.

**Note 2** — `UAT Class Alpha`/`Beta`/`Gamma` are the anchor of a self-contained fixture cluster:
4 fake student rows, a fake combined-class group, and fake sections all reference each other (see
below). Deleting the classes alone would orphan those rows — this must be executed as one
cluster, in FK-safe order (members → combined group → students → sections → classes), not four
separate unrelated deletes.

---

## teachers (14 name matches)

| id | name | real dependents | recommendation |
|---|---|---|---|
| 136 | Test T | 0 | DELETE |
| 157 | AutoFix Shared Teacher | 0 | DELETE |
| 178 | DbgAbsent | 1 timetable slot, 1 substitution | DEACTIVATE |
| 179 | DbgSub | 1 substitution | DEACTIVATE |
| 180 | UAT Teacher Alpha | 2 tcsa | DEACTIVATE or delete with fixture cluster |
| 181 | UAT Teacher Beta | 2 tcsa | DEACTIVATE or delete with fixture cluster |
| 182 | UAT Teacher Gamma | 1 tcsa | DEACTIVATE or delete with fixture cluster |
| 183 | UAT Teacher Delta | 0 | DELETE |
| 184 | UAT Teacher Epsilon | 0 | DELETE |
| 315 | BulkEdit UAT Teacher | already deleted, 0 | SKIP |
| 316 | Dbg Teacher2 | 1 tcsa | DEACTIVATE |
| 317 | Browser Verify TEMP | already deleted, 0 | SKIP |
| 318 | CT Phase7 Verify Teacher | 2 tcsa | DEACTIVATE |
| 319 | DS UAT Verify Teacher | 1 tcsa | DEACTIVATE |

**Cross-check against the audit's "possible real nicknames" list** — `Prashant Patra`, `Rajandar`,
`Mabel Mam`, `Shubh Agarwal`, `Diksha Mam`, `Harmit`, `Shaurabh Kumar`: **confirmed real, active
teachers** (ids 149–155), each with 3–12 of their own legitimate `teacher_class_subject_assignments`
rows outside the WALKTHROUGH set. These are informally-named real staff, not test fixtures —
**do not touch them.** (A separately-real `MABLE WALTER`, id 101, exists alongside `Mabel Mam`,
id 151 — two different real people or one person recorded twice under different names is a
question only school staff can answer; not something to guess or auto-merge.)

---

## subjects (6 matches)

| id | name | real dependents | recommendation |
|---|---|---|---|
| 48 | DbgSubject | 1 timetable slot | DEACTIVATE |
| 49 | UAT Mathematics | 3 tcsa | DEACTIVATE or delete with fixture cluster |
| 50 | UAT Science | 1 tcsa, 1 combined group | DEACTIVATE or delete with fixture cluster |
| 51 | UAT English | 1 tcsa | DEACTIVATE or delete with fixture cluster |
| 72 | BulkEdit UAT Subject | already deleted, 0 | SKIP |
| 73 | Dbg Subject2 | 1 tcsa | DEACTIVATE |

---

## sections (3 matches)

| id | name | real dependents | recommendation |
|---|---|---|---|
| 5 | ZZZTestSection | already deleted, 0 | SKIP |
| 24 | UAT Section A | 3 students, 4 tcsa, 2 combined-group members | DELETE with fixture cluster |
| 25 | UAT Section B | 1 student, 1 tcsa | DELETE with fixture cluster |

---

## academic_sessions (1 match)

| id | name | real dependents | recommendation |
|---|---|---|---|
| 5 | TESTDEBUG | already deleted, is_current=0 | SKIP |

---

## combined_class_groups (1 match)

| id | name | real dependents | recommendation |
|---|---|---|---|
| 3 | UAT Combined Science (Alpha-A + Gamma-A) | 2 members, 0 timetable slots | DELETE with fixture cluster |

---

## NEW finding not in the original audit list: fake student records

The fixture classes/sections above are populated by **4 real rows in the `students` table**,
clearly fake by name and admission number, but real rows nonetheless — deleting a student record
is a different risk tier than deleting a class, so calling this out on its own:

| id | name | admission_no | school_class_id | section_id |
|---|---|---|---|---|
| 8415 | UAT Student One | UAT-ADM-0001 | 57 (UAT Class Alpha) | 24 (UAT Section A) |
| 8416 | UAT Student Two-A | UAT-ADM-0002 | 57 (UAT Class Alpha) | 25 (UAT Section B) |
| 8417 | UAT Student Two-B | UAT-ADM-0003 | 58 (UAT Class Beta) | 24 (UAT Section A) |
| 8418 | UAT Student Three | UAT-ADM-0004 | 58 (UAT Class Beta) | 24 (UAT Section A) |

**Checked dependents — all zero**: `fee_collections`, `student_fee_assignments`, `results`,
`attendances`, `admit_cards`, `exam_seating_arrangements` all return 0 for these 4 ids. No
financial or academic history would be lost. **Recommendation: DELETE**, as part of the same
fixture-cluster cleanup as their classes/sections above.

---

## fee_structures (2 matches, separate module)

| id | class_name | real dependents | recommendation |
|---|---|---|---|
| 7 | Class 3 Debug | 0 (`student_fee_assignments`) | DELETE |
| 10 | ZTestClass6 | 0 (`student_fee_assignments`) | DELETE |

---

## `teacher_class_subject_assignments` with `academic_year = "2026-2027-WALKTHROUGH-ARCHIVED"` — NEEDS DECISION, more complex than the original audit assumed

**205 rows** (out of 287 total TCSA rows — i.e. 71% of all class-teacher-subject assignment data
in the system carries this label), spanning real classes 3 through 12 (incl. Science streams),
every section, with real, properly-cased teacher names throughout (a handful of the names look
like nicknames but are confirmed real, active teachers — see the teachers section above). **This
is not fixture data** — it is what looks like a complete rehearsal/dry-run of the school's real
staff-assignment roster, created in a single 1-second window (`2026-08-02 16:08:05` to `:06`,
unmistakably one bulk/automated operation), that was correctly recognized as not-yet-live and
suffixed `-ARCHIVED`, but never cleaned up.

**The audit's assumption ("fix is probably: correct the value to 2026-2027") does not hold
uniformly.** I checked what would happen if every WALKTHROUGH row were simply renamed to
`2026-2027`:

- **49 of the 205 rows would violate the table's own unique constraint**
  (`teacher_id`+`class_id`+`section_id`+`subject_id`+`academic_year`) — an identical,
  properly-dated `2026-2027` row for the exact same teacher/class/section/subject **already
  exists**. Those 49 clean counterparts were created `2026-08-08` onward (six days after the
  WALKTHROUGH batch) and are still being actively edited as recently as today. This strongly
  indicates the 49 WALKTHROUGH rows are confirmed-stale duplicates, safely superseded by real,
  later data entry — **recommend DELETE**, not rename.

- **156 of the 205 rows have no matching clean counterpart at all** for that exact
  teacher+class+section+subject combination — e.g. WALKTHROUGH row id 3, "Prashant Patra teaches
  Mathematics to Class 3 Section 1," has no corresponding clean `2026-2027` row, even though
  Class 3 Section 1 otherwise has 9 real, properly-dated assignments for its other subjects. This
  is genuinely ambiguous and **I am not going to guess**: it could mean (a) this specific
  assignment is still accurate today and was simply never re-entered after the walkthrough batch
  (in which case correcting its year to `2026-2027` restores a real, currently-missing
  assignment), or (b) the subject/teacher pairing shown here has since changed and the walkthrough
  row is accurately superseded (in which case it should stay archived, or be deleted, not
  resurrected under the current year).

**STOP AND ASK, per the loop's own rules**: I need your direction on the 156 ambiguous rows
specifically. Options, not mutually exclusive:
1. Leave all 156 exactly as they are (still labeled WALKTHROUGH-ARCHIVED, effectively inert —
   nothing in the app treats that string as a current academic year) — safest, fully reversible,
   defers the decision.
2. Have someone at the school (whoever owns the real timetable/staffing data) review the 156
   pairings against reality before any are corrected or deleted — I can produce the full list
   (teacher, class, section, subject) as a spreadsheet-friendly export for that review.
3. Delete all 156 outright on the theory that the clean `2026-2027` set is the authoritative,
   intentionally-curated one and anything missing from it was deliberately left out — riskier,
   since I can't independently verify that assumption.

The 49 confirmed-duplicate rows are far more clear-cut (recommend DELETE), but I'd still like your
explicit go-ahead before Phase 7 touches 49 rows of real assignment history, per rule 4.

---

## Summary counts

| Category | Count | Recommended action |
|---|---|---|
| Already soft-deleted, zero dependents | 15 school_classes rows, 2 teacher rows, 1 subject row, 1 section row, 1 academic_session row | SKIP — no action needed |
| Zero-dependent, unambiguously fake | 3 school_classes, 4 teachers, 2 fee_structures | DELETE |
| Fixture cluster (classes+teachers+subjects+sections+students+combined group, all cross-referencing each other) | 3 classes, 3 teachers, 3 subjects, 2 sections, 4 students, 1 combined group | DELETE as one FK-ordered cluster operation |
| Real dependent(s) attached, name suggests fixture | 2 school_classes, 5 teachers, 3 subjects | DEACTIVATE, or investigate the one dependent row first |
| TCSA WALKTHROUGH — confirmed duplicate | 49 rows | DELETE |
| TCSA WALKTHROUGH — ambiguous, no clean counterpart | 156 rows | **NEEDS YOUR DECISION** (see options above) |

**Nothing has been deleted, deactivated, or modified. This is the complete Phase 1 report for
your review before Phase 7 executes anything.**
