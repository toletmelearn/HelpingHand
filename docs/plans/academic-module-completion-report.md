# Academic Module Rebuild — Completion Report
## HelpingHand ERP · branch: academic-module-rebuild → main

Covers Phases 0, A, B, and C (C1–C3) of the Academic module rebuild, from the pre-rebuild checkpoint (`4bfd3d3`) through Phase C3's close.

---

## Phase A — Class system consolidation

**A1–A4 (initial demolition pass):**
- `c33998d` — A1: SchoolClass repaired and canonical-ready
- `13c98ec` — A2: class data merged, mapping table created
- `a1a1200` — A3: all consumers on school_classes
- `bac1d6f` — A4.1: drop bare unused classes table
- `2c2a4c1` — A4.2: drop dead attendances_temp and student_attendance tables
- `98a36d9` — A4.3: remove rand()-based fake biometric auto-mark from SmartAttendanceController
- `5752067` — A4.4: remove 7 dead routes pointing at nonexistent ClassTeacherAssignmentController methods
- `37a85b5` — A4.5: delete orphaned TeacherSubjectAssignment model
- `66765bf` — A4.6: delete TeacherClassSubjectAssignmentController duplicate
- `ba92060` — A4.8: remove unused PromotionService, delete the unused service
- `8e7827a` — A4.9: remove dead ClassManagementPolicy registration

**Status-check audit** (`ed269b2`, `1962bf6`) found Phase A ~94% done, with one open item: A3.1 (14 live files still consuming `ClassManagement` directly instead of `SchoolClass`).

**Phase A closure** (student class-column canonicalization + final cleanup):
- `afd54d6` — make `school_class_id` the master class column (flipped the backwards `class_id`-first priority in 4 `Student` methods + the saving() hook)
- `e79e45e` — simplify promotion write to `school_class_id` only
- `fb7e4d6` — repoint 13 reader files to `school_class_id`
- `bce224d` — make `school_class_id` mass-assignable (found it was missing from `$fillable` entirely) + feature test
- `e8a8117` — delete the leftover `class_management` id 20 ("TestClass") row, per confirmed DELETE recommendation
- `cd68d08` — delete confirmed-dead `Admin\ClassController`
- `772a890` — fix 2 tests that had locked in the old backwards priority
- `881a8b3` — closure session report

**Verdict recorded then:** Phase A is CLOSED, with A3.1 (14 live `ClassManagement` consumers) tracked as a known, out-of-scope exception — never claimed as part of this closure.

---

## Phase B — Repair survivors

- `33183ca` — add `TimetableSlotPolicy` and gate `TimetableController` actions (the newest of three parallel timetable implementations had no access control at all)

---

## Phase C1 — Academic Calendar / Events / Holidays

- `ca1fda9` — build the Academic Calendar feature: `academic_events` table/model (`holidays()`, `forSession()`, `between()` scopes, `isHoliday()` static helper), full admin CRUD, sidebar nav entry
  - Integration 1: attendance marking (web + API) now blocks on holidays
  - Integration 2: `ProfessionalDashboardService::getUpcomingEvents()` merges calendar events with exams (also fixed a pre-existing bug where it queried nonexistent `exams.date`/`exams.class` columns)
- `e6e4cf8` — session notes doc
- `08afa66` — surface upcoming academic events on the actual `admin.dashboard` page (the `getUpcomingEvents()` integration had no reachable consumer until this)

---

## Phase C2 — Compliance Fields (UDISE / APAAR / Aadhaar)

- `49d7e0a` — add compliance fields (`udise_pen`, `apaar_id`, `name_as_per_aadhaar`, `apaar_consent_given/date/by`) + rename `students.aadhar_number` → `aadhaar_number`
- `e40d4f6` — mismatch detector (`hasAadhaarNameMismatch()`, case/whitespace-insensitive), filterable admin list, `UdiseStudentsExport`, and the dedicated `recordApaarConsent()` action (consent fields deliberately excluded from `$fillable` — DPDP requirement)
- `7c33fe7` — rename `aadhar_number` → `aadhaar_number` across remaining application code (18 files, verified individually against `Teacher`/`Guardian`'s separate, unrenamed columns)
- `ebd6a20` — same rename across 75 test files and 2 seeders

---

## Phase C3 — Certificate PDF Export

- `45aaa1b` — `Admin\CertificateController::downloadPdf()`, reusing the exact DomPDF pattern already present (but unrouted) in `CBSEReportCardController` — no new PDF package. Gated to `generated`/`published`/`locked` certificates only. New `certificate-pdf.blade.php` derived from the existing preview template, with a prominent Serial No / Issue Date line for TC certificates — verified with `pdftotext` against an actual generated PDF (not just the HTML) that this renders correctly.

---

## Known-failing-test ledger (as of this report)

**71 pre-existing failures, unrelated to any Academic module work, confirmed via repeated `git diff`/stash A/B checks throughout every phase above:**

| Suite | Failed | Notes |
|---|---|---|
| `tests/Feature/API` | 7 | `SanctumTokenAbilityTest` (×6), `ApiAccessControlAbilityTest` (×1) — "Account inactive" 403s during login; files untouched by any commit in this rebuild |
| `tests/Feature/FeeFinance` | 34 | Fee-register/counter-collection 403 permission issues and similar; count and pattern confirmed identical before/after every phase via stash A/B |
| `tests/Feature/Admin` | 30 | Fee-register/counter-collection 403s, architecture/module-completeness audit checks (obsolete view dirs, CSV template header drift, route-name/blade-variable integrity), all in files never touched by this rebuild |

All other suites (`Attendance`, `Students`, `Unit`, plus the new suites added by C1/C2/C3) pass cleanly.

---

## Deferred / out of scope

- **Fee module's `class_name` → FK migration** — separate project, was to start after Phase A settled (see A3 step 5 report)
- **Exam module** — next module after Academic; `CBSEReportCardController` stays untouched beyond reusing its PDF pattern
- **BellSchedule deprecation removal** — one of three parallel timetable implementations (`BellTiming`, `BellSchedule`, `TimetableSlot`); which to retire is an Exam-module-adjacent decision
- **Legacy `class`/`class_id` column drop on `students`** — kept one release as read-only-in-practice legacy per Phase A closure's own plan; drop after a stable release
- **`zz_retired_class_management` rename/final drop** — blocked on A3.1 (14 live consumers) being repointed first; not attempted this rebuild
- **`PromotionRule`/`AcademicRanker` eligibility engine decision** — whether it feeds report cards or gets deleted, to be decided during the Exam module
- **`CertificateController` has no role/policy-based authorization anywhere** (including the pre-existing `preview()` action) — `downloadPdf()` was built to match `preview()` exactly ("no weaker"), so it inherits this gap rather than introducing a new one. Flagged here per the scope fence, not fixed.
