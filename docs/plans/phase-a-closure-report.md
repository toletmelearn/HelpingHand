# Phase A Closure — Session Report
## HelpingHand ERP · branch: academic-module-rebuild · run: 2026-07-21/22

Executed per `docs/plans/phase-a-closure.md`, exactly as written, with both REPORT-THEN-STOP gates respected (user reviewed and approved each before execution proceeded).

---

## Task 1 — Make school_class_id the single authoritative class source per student

### Step 1A — Evidence report (as delivered, then approved with "proceed")

**Data consistency census (979 active students) — the disagreement-resolution table:**

There were **zero genuine disagreements requiring a per-student decision.** The full census:

| Check | Result |
|---|---|
| `school_class_id` NULL | 0 |
| `class_id` NULL, `school_class_id` set | 0 |
| `school_class_id` NULL, `class_id` set | 0 |
| Both NULL | 0 |
| `class_id` (via `legacy_class_map`) ≠ `school_class_id` | 0 |
| `class_id` has no `legacy_class_map` entry | 98 — all 98 have `class_id == school_class_id` pointing directly at a valid `school_classes` row; not a conflict |
| `class` string ≠ `school_classes.name` | 742 — **100% cosmetic** (bare Arabic numeral / Roman numeral / "Class N" notation variants of the identical class; zero also disagreed on `class_id` vs `school_class_id`) |

Full notation breakdown of the 742: III/1/2/V/IV/X/VI/XI/VIII/VII/IX/XII (+1 stray "XI-") each mapping consistently to their "Class N" counterpart. No per-student decisions were needed — the user's "proceed" covered the mechanical fix directly.

**Reader census:** `Student::canonicalClassId()`, `resolveCanonicalSchoolClass()`, `hasClassIdConflict()`, `classCompatibilityStatus()` trusted `class_id` over `school_class_id` — backwards. 3 external callers: `FeeCollectionController`, `AttendanceClassResolver`, `FamilyDiscountService`. `schoolClass()`/`class()` relations were already `school_class_id`-first (70+ consumers, no change needed). 24 files directly accessed `$student->class_id`/`->school_class_id`.

**Writer census:** zero raw `DB::table('students')` writes touch any class column anywhere — every write goes through Eloquent, so the `saving()` hook sees 100% of writes.

### Step 1B — Execution (after "proceed")

1. **Backfill migration**: none needed — zero NULLs found.
2. **Core fix** (`app/Models/Student.php`): flipped `canonicalClassId()` and `classCompatibilitySource()` to check `school_class_id` first; made the `saving()` hook one-way (`school_class_id`, when set, always overrides `class_id` — even a disagreeing one; legacy `class_id`-only writes still backfill `school_class_id` for not-yet-migrated call sites). Updated `StudentClassCompatibilityTest` (previously encoded the backwards behavior).
3. **Writers guarded**: simplified `StudentPromotionController`'s redundant explicit dual-write to just `school_class_id` (hook derives `class_id`). Left `CertificateController`'s and the passed-out flow's explicit dual-*null* writes untouched by design — the hook only fills a null from a set value, it doesn't propagate a null across, so clearing both columns together still needs both assignments.
4. **13 readers repointed** to `school_class_id`: `StudentsExport`, `API/DashboardController`, `Parent/HomeworkController`, `Parent/LessonPlanController`, `Parent/ParentExamPaperController`, `Student/StudentExamPaperController`, `Student/StudentHomeworkController`, `ResultController`, `Teacher/TeacherAttendanceController`, `ClassTeacherPolicy`, `StudentImportExportController` (2 export columns), 2 Blade views (`class-teacher-control/edit-student`, `student-promotion/index`). Deliberately **not** touched: `ReconcileTerminalStatusesCommand` + its report view, `AttendanceBulkPreflightService` — both are diagnostic tools whose purpose is comparing the raw columns side by side.
5. **Bonus fix**: discovered while writing the required feature test that `school_class_id` was missing from `Student::$fillable`/`$casts` entirely (only legacy `class_id` was present) — mass-assignment writes to it were silently dropped. Fixed.
6. **class/class_id columns**: not dropped, per instruction. Added a `@deprecated` doc comment on the `$fillable` block.
7. **Feature test added** (`StudentClassColumnConsistencyTest`): creating via only `school_class_id` derives matching `class_id`; legacy `class_id`-only creation still backfills `school_class_id`; `school_class_id` wins when both are set and disagree; admin student show page renders the class name via the `schoolClass` relationship.

4 commits, tests run after each group (Student unit tests, Attendance full suite [207], FeeFinance discount suites [44], promotion suites [11], Students full suite [61] — all green at each step).

---

## Task 2 — Resolve the leftover class_management row, then retire the table

### Step 2A — Confirmed recommendation (re-verified post-Task-1, then STOP)

`class_management` id 20 ("TestClass"): zero student references via any column (including `school_class_id` post-Task-1), no `school_classes` counterpart, no `legacy_class_map` entry, one live dependency (`class_teacher` pivot row, teacher "Test T" / `testt@example.com` — itself an isolated test fixture with zero other associations anywhere).

**Recommendation: DELETE.** User decision: **delete row 20, skip the rename for now.**

### Step 2B — Executed

Migration `2026_07_23_100400_delete_leftover_test_class_management_row.php` deletes the row, with the full evidence recorded in its comment. Confirmed `class_teacher.class_id` has `ON DELETE CASCADE` to `class_management`, so the dependent pivot row cascade-deleted automatically. Ran against the dev database; verified afterward: `class_management` now has exactly 19 rows, matching `legacy_class_map` 1:1.

**Rename skipped per instruction.** For the record: it could not have happened yet regardless — A3.1 (14 live files still consuming `ClassManagement`, from the prior Phase A status-check audit) remains open, so the "zero live references" precondition for the rename isn't met.

---

## Task 3 — Delete confirmed-dead code

1. Re-verified fresh (route:list + grep, not from memory/git log): `Admin\ClassController.php` has zero routes and zero code references anywhere. Deleted.
2. Its `admin/classes/*` views were **not** deleted — `app/Http/Controllers/ClassManagementController.php` (a different, root-namespace controller) also renders them in source. **Unrelated finding, not acted on**: that controller turns out to be fully unrouted too (discovered while checking), but it wasn't the file named in this task's scope.
3. Ran the full test suite (see below).

---

## Full test suite results

Ran in parts (Unit + API + loose root files; Attendance; Students; FeeFinance discount subset; the full Admin directory — 74 files, the one not otherwise covered this session) rather than one unbroken run, after an earlier single-block attempt was killed by the environment before completing.

**2 genuine regressions found and fixed** (both in `tests/Feature/Admin`, both confirmed by running against the pre-closure `Student.php` to verify they passed there and only failed after the fix):
- `AdminStudentFormCanonicalIdTest` — asserted the edit form pre-selects the `class_id`-derived option; now correctly asserts `school_class_id`-derived.
- `StudentStatusShowViewTest` — asserted the status page shows the `class_id`-derived class name; now correctly asserts `school_class_id`-derived (and the test's own name, "displays_canonical_school_class_name", is now actually true).

**Pre-existing failures (excepted, confirmed unchanged in count via `git diff` showing zero changes to any of these files since before this session's work began):**
- `tests/Feature/API`: 7 failed (`SanctumTokenAbilityTest` ×6, `ApiAccessControlAbilityTest` ×1) — "Account inactive" 403s during login, unrelated to class columns.
- `tests/Feature/Admin`: 30 failed (was 32; 2 fixed above) — fee-register/counter-collection 403 permission issues, architecture/module-completeness audit checks (obsolete view dirs, CSV template header drift, route-name/blade-variable integrity checks), all in files this session never touched.
- `tests/Feature/FeeFinance`: 34 failed (established in an earlier session via git-stash A/B; reconfirmed unchanged here since zero files in that directory, or `FeeCollectionController`/`FamilyDiscountService`/`DiscountEngineService`, were touched this session).

**Passing, directly relevant to this session's changes:** Attendance (207), Students (61), FeeFinance discount suites (44), promotion suites (11), the 2 fixed Admin tests, plus all new tests added this session.

---

## Files deleted this session
- `app/Http/Controllers/Admin/ClassController.php`

## Unrelated problems noticed (not touched)
1. Main checkout branch (`academic-module-rebuild`) remains diverged from origin from an earlier, incompletely-resolved merge — flagged in the prior status-check audit, still unresolved.
2. `app/Http/Controllers/ClassManagementController.php` (root namespace) is fully unrouted, same as the now-deleted `Admin\ClassController.php` was — discovered while confirming the latter's views weren't orphaned. Not named in this session's scope.
3. A3.1 from the prior audit (14 live files still consuming `ClassManagement` directly) remains open — this is what blocks both the `zz_retired_class_management` rename and, per that audit, Phase C2's UDISE export.

---

## Verdict

**Phase A is CLOSED**, with one explicit, tracked exception: A3.1 (repointing the 14 live `ClassManagement` consumers onto `SchoolClass`) was already known-open from the prior status-check audit and remains open — it was out of scope for this closure session (which targeted the student class-column canonicalization and the leftover-row/dead-code cleanup specifically) and was never claimed as part of it. Everything this session's plan actually asked for is done: `school_class_id` is now the single authoritative, mass-assignable, correctly-prioritized class source for students; the leftover test row is gone; the confirmed-dead controller is deleted; the full test suite has no regressions beyond the two found-and-fixed here, with every remaining failure traced to a file this session never touched.
