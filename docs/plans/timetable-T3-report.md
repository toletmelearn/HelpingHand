# Timetable module — Phase T3 report (substitution engine)

Branch `timetable-module`, tag `timetable-T3-complete`. Continues from `timetable-T2-complete`-equivalent state (T2a + T2b, see `timetable-T2-report.md`).

Read first per the plan: `TeacherSubstitution` model + `TeacherSubstitutionController`, including the two stubbed scoring functions (`calculateSubjectMatchScore` always returned 0, `hasClassExperience` always returned false) that item 2 replaces.

---

## Item 1 — Link substitutions to the real timetable

`teacher_substitutions.bell_timing_id` (FK, required going forward) replaces the free-typed 1–10 `period_number` as the period identity. 0 rows existed in the live DB, so no data mapping was needed. `period_number`/`period_name` are kept for display; `period_name` is now auto-derived from the linked bell timing via a `saving()` hook (same pattern as `TeacherAvailability::day` and `BellTiming::period_type` from T2), so it can't drift from the real period. Create/edit forms and all display views updated.

**Bonus fix (disclosed, found while reading the controller for this item):** the `assign`/`approve`/`cancel` routes' `{substitution}` wildcard didn't match the controller's `$teacherSubstitution` parameter — implicit binding only matches by exact name or its snake_case form, so those three actions were silently operating on an empty, unsaved model instead of the real record. Same class of bug fixed in `ClassTeacherAssignmentController` during the remediation phase. Renamed the wildcard to `{teacher_substitution}`.

Commit `6147faf`. 6 tests.

## Item 2 — Real substitute scoring

New `app/Services/Timetable/SubstituteFinderService.php` implements the plan's point system:
- +40 free that period (mandatory filter — from `timetable_slots` + `TeacherAvailability`, not just a score bonus)
- +25 teaches this class (any subject) / +20 teaches this subject (any class) — combine into +45 with a merged reason when both match
- +15 fewest periods that day, scaled linearly, capped at 0 for 15+ periods
- Outright exclusion (not just a penalty) for a teacher already substituting that exact period elsewhere

Returns a ranked list with human-readable reasons per candidate: *"Free • Teaches Class 7B Maths • 2 periods today."* `TeacherSubstitutionController::suggestSubstitutes()` now delegates to it; the old `findAvailableTeachers()` and its four private stub helpers were removed (confirmed unused elsewhere first).

Commit `b65dd90`. 8 tests: absent teacher never a candidate, busy/blocked/already-substituting exclusions, cancelled substitutions elsewhere don't exclude, class+subject match outranks free-only, fewer-periods-today tiebreak, overall ranking order.

## Item 3 — "Teacher absent today" UI flow

Pick a teacher + date → their day's timetable slots listed → per slot without an existing substitution, top-5 ranked suggestions with reasons → one-click assign records the substitution directly (status `assigned`, skips the separate create-then-suggest-then-approve path the manual form uses). Rejects a duplicate assign if one's already recorded for that teacher+period+date.

Refactored `SubstituteFinderService::findCandidates()` to take raw parameters (`BellTiming`, date, `SchoolClass`, `Subject`, absent teacher id) instead of requiring a persisted `TeacherSubstitution`, so this preview flow can rank candidates for slots that don't have a record yet. `findCandidatesForSubstitution()` wraps it for the existing manual create/edit flow.

Policy: admin + a new `manage-substitutions` permission — also applied retroactively to `create`/`update`/`assignSubstitute`/`approveSubstitute`/`cancelSubstitute`, which were previously admin-only, matching the plan's stated gate for this flow.

**Bonus fix (disclosed):** the new literal-path routes (`absent-today`, `assign-from-slot`) had to be registered *before* `Route::resource('teacher-substitutions', ...)` — its `GET {teacher_substitution}` show route would otherwise have swallowed `/teacher-substitutions/absent-today` as a route parameter value. Same class of ordering bug fixed for bell-timing routes during remediation.

Commit `4bbfbca`. 7 tests.

## Item 4 — Daily arrangement sheet PDF

A period × class grid of *only* that day's substitution changes (not the whole timetable) — each cell shows the absent teacher struck through, the substitute, and the subject. Periods come from that date's day-of-week active teaching bell timings; classes are only the ones with an actual change (an unaffected class doesn't get a row). Cancelled substitutions excluded. Linked from the absent-today page.

Commit `27041a2`. 4 tests (magic bytes, friendly empty-day message, cancelled rows excluded, unauthorized 403 — same "magic bytes only" precedent as every other PDF test in this codebase, since PDF binary content isn't reliably text-searchable).

## Item 5 — HR leave integration

Investigated first, read-only: `TeacherLeave` exists with real `teacher_id`/`start_date`/`end_date`/`status` columns and an approved/pending/rejected workflow via `AdminTeacherLeaveController` — a clean hook, so integrating rather than deferring.

The absent-today page now surfaces teachers with an approved `TeacherLeave` covering the selected date as one-click shortcuts straight into the same flow. Pure read query against `teacher_leaves` — nothing is written to the HR module. Pending/rejected leave and leave on a different day are correctly excluded.

Commit `49c1964`. 3 tests.

## Item 6 — Tests + verification

Not a separate deliverable; folded into each item's own commit as built (established cadence throughout this module): 6 + 8 + 7 + 4 + 3 = **28 new T3 tests**.

**Realistic end-to-end scenario**, run against the real dev DB (rolled back after, no data changed): seeded a teacher with a real timetable slot today, a qualified free substitute (assigned to that class+subject), a busy substitute (teaching something else that period), and an approved leave record for the absent teacher.
1. Absent-today's leave shortcut correctly surfaced the absent teacher.
2. Ranked candidates: the qualified teacher scored 100 (40 free + 45 class+subject match + 15 zero-periods-today) and ranked first; the busy teacher was correctly excluded entirely, not just down-ranked.
3. One-click assign recorded the substitution correctly (`Mrs. Sharma → Mr. Verma`).
4. The arrangement sheet PDF rendered the change (`%PDF` magic bytes, real byte content).

**Full suite**, re-verified against the documented baseline **by name**, not just count: **71 failed / 1233 passed**, exactly `7 API (SanctumTokenAbilityTest ×6, ApiAccessControlAbilityTest ×1) + 34 FeeFinance + 30 Admin`, matching `docs/plans/remediation-report.md`'s ledger precisely. Zero new or different failures. Passing count is up from T2's 1205 to 1233 — the +28 delta is exactly this phase's new tests, none of them pre-existing tests newly broken.

All migrations verified up/down/up against the real dev MariaDB 10.4.32 before being committed.

---

## Deferred / not done this session

- **`teacher-substitutions/rules` and `/rules` POST routes are pre-existing broken routes** — they call controller methods `rules`/`updateRules` that don't exist (the controller only defines `substitutionRules()`). Found while reading the controller for item 1; out of scope for T3 (nothing in the plan mentions a "rules" feature), not fixed.
- **The rest of `TeacherSubstitutionController`'s legacy CRUD actions** (`index`, `create`, `store`, `edit`, `update`, `destroy`, `today`, `absenceOverview`) still have **no policy-based authorization at all**, only `auth` middleware — same pattern the verification audit flagged for other academic-domain controllers during the rebuild. Only the *new* T3 actions (`absentToday`, `assignFromSlot`, `arrangementSheetPdf`) got policy gates this session, per the plan's explicit ask for those specifically. Retrofitting the older actions would be a larger, separately-scoped hardening pass.
- **Combined-group substitution scoring**: `SubstituteFinderService`'s "already substituting elsewhere" exclusion checks raw `teacher_id` on `TimetableSlot`, which already correctly sees combined-group member rows (T2b) with no special-casing needed — not a gap, just noting it wasn't separately tested this session.

---

## Next

Per the plan's phasing (T0 → T1a → T1b → T1c → T2a → T2b → T3 substitution → T4a/b solver → T5 surfacing), T3 is code-complete and tested, tagged `timetable-T3-complete`. Next is Phase T4 (auto-generation solver, beta) — not started, awaiting explicit instruction. The plan itself calls T3 "the daily-use hook" and flags T4 as "the wow for sales demos" — a materially bigger build (backtracking constraint solver + draft/publish workflow) than any phase so far.
