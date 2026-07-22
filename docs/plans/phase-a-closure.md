# PHASE A CLOSURE — Student Class-Column Canonicalization + Final Cleanup
## Save at: docs/plans/phase-a-closure.md · Branch: academic-module-rebuild

This session closes Phase A. Three tasks, strictly in order. Tasks 1 and 2 each contain a REPORT-THEN-STOP gate — you present findings and WAIT for my explicit "proceed" before changing anything. Task 3 is unconditional cleanup.

Scope fence: do NOT touch the Fee module, the Exam remnants, or anything in Phase C. If you find other problems, list them at the end; do not fix them.

Pre-flight: confirm branch `academic-module-rebuild`, clean `git status`, and confirm a database dump from this month exists in storage/backups/ (if not, STOP and tell me — a fresh dump must be taken first).

---

## TASK 1 — Make school_class_id the single authoritative class source per student

**Background:** Students carry three class columns: `class` (string), `class_id` (legacy → class_management), and `school_class_id` (→ school_classes, the canonical table). An Eloquent saving() hook syncs them on model saves, but raw queries bypass it, and different readers trust different columns. This task makes `school_class_id` the single source of truth, with the other two demoted to derived/legacy status.

### Step 1A — Evidence report (READ-ONLY, then STOP)

1. **Data consistency census** across all students (read-only SQL):
   - Count of students where school_class_id IS NULL
   - Count where class_id IS NULL but school_class_id IS NOT NULL (and vice versa)
   - Count where the three columns DISAGREE: school_class_id's class name ≠ class string, or class_id's mapped class (via legacy_class_map) ≠ school_class_id
   - List (id, name, class, class_id, school_class_id) for every disagreeing or school_class_id-NULL student — full list if ≤ 50, sample + count otherwise
2. **Reader census** (grep): every live code location that READS student class identity, classified by which column it trusts: `->class` string / `class_id` / `school_class_id` / the saving-hook-synced trio. Include Blade views and API resources.
3. **Writer census**: every live code location that WRITES any of the three columns outside the saving() hook (raw DB::table, updates, imports).
4. Present the report and STOP. I will give per-student decisions for disagreements (I know the school's real classes) and then say "proceed".

### Step 1B — Execution (only after my "proceed")

1. **Backfill migration**: set school_class_id for every student where it's NULL, derived from class_id via legacy_class_map, else by name+section match on the class string. Any student that cannot be resolved automatically gets listed for me — never guessed.
2. **Repoint readers**: every reader from the census now reads through school_class_id (relationship: `$student->schoolClass`). The `class` string column becomes display-only fallback. Do this file-by-file, running the test suite after each file. One commit per logical group.
3. **Guard writers**: any raw writer found in the census gets repointed to write school_class_id (and only it). The saving() hook is updated to derive `class` string and `class_id` FROM school_class_id (one-way sync, school_class_id is master) instead of multi-directional syncing.
4. **Do NOT drop the class or class_id columns** — they stay one release as read-only legacy. Add a code comment on both in the model marking them deprecated.
5. Feature test: creating/promoting a student sets all three columns consistently; a student's class page renders from schoolClass relationship.

---

## TASK 2 — Resolve the leftover class_management row, then retire the table

### Step 2A — Confirm recommendation (READ-ONLY, then STOP)
Re-verify the leftover row's status in the CURRENT working copy (post-Task-1): full row contents, student references (including via the newly backfilled school_class_id), school_classes name+section counterpart, legacy_class_map entry, and any remaining live writers to class_management. State exactly one recommendation — MIGRATE / DELETE / BLOCKED — with evidence, and STOP for my decision.

### Step 2B — Execute my decision
- If MIGRATE: one idempotent migration inserting it into school_classes + legacy_class_map entry.
- If DELETE: delete the row via migration with the evidence recorded in the migration's comment.
- Then, in both cases: verify zero live code references to ClassManagement remain (the Task 3 deletion will have removed ClassController's); if truly zero, rename the table to `zz_retired_class_management` via migration (rename, not drop — final drop happens after one stable release). If live references remain, list them and stop — no rename.

---

## TASK 3 — Delete confirmed-dead code (no gate needed)

1. Delete `app/Http/Controllers/Admin/ClassController.php` — confirmed unrouted dead code. First re-verify with route:list + grep that nothing references the class; then delete.
2. Grep for any Blade views, form requests, or tests that referenced ONLY this controller; delete those too if orphaned by its removal.
3. Run the full test suite.

---

## SESSION END

1. Full test suite green (pre-existing known failures excepted — list them, confirm unchanged in count).
2. Write a session report to docs/plans/phase-a-closure-report.md: what was done per task, the disagreement-resolution table from 1A, the leftover-row decision and evidence, files deleted, and a final statement: "Phase A is CLOSED / NOT CLOSED because ___".
3. Commit the report, push everything, tag: `git tag academic-phase-A-complete && git push --tags`.
4. Stop.
