# NEXT SESSION PROMPT — Dashboard Wiring + Phase A Status Check
## HelpingHand ERP · branch: academic-module-rebuild

Copy everything below this line into a fresh Claude Code session.

---

This session has exactly TWO tasks. Task 1 is a small code change. Task 2 is a read-only status check that produces a report. Do them in order. Do not do anything else — no refactoring, no fixing unrelated bugs, no drive-by improvements. If you notice other problems, list them at the end of your report; do not touch them.

## PRE-FLIGHT

1. Confirm current branch is `academic-module-rebuild` (`git branch --show-current`). If not, STOP and tell me.
2. Run `git status` — working tree must be clean. If there are uncommitted changes, STOP, show them to me, and wait.

---

## TASK 1 — Surface the Academic Calendar on the real admin dashboard (code change)

**Background:** `ProfessionalDashboardService::getUpcomingEvents()` already correctly merges upcoming academic-calendar events (holidays, events, PTMs — next 7 days) with upcoming exams, and it's covered by passing tests. But its only consumer is a phantom dashboard (`admin.dashboards.professional`) whose Blade view doesn't exist and which no sidebar link points to. The dashboard everyone actually uses is `admin.dashboard`, served by `AdminDashboardController::index`.

**Do exactly this:**

1. Read `app/Services/ProfessionalDashboardService.php::getUpcomingEvents()` to understand its return shape (fields per event, how exams vs calendar events are distinguished).
2. Read `AdminDashboardController::index()` and the `admin.dashboard` Blade view it renders, to find where a small "Upcoming Events" card fits among the existing dashboard cards/widgets. Follow the existing markup/card conventions of that view exactly — do not introduce new CSS frameworks or styles.
3. Inject/instantiate `ProfessionalDashboardService` in `AdminDashboardController::index()`, call `getUpcomingEvents()`, and pass the result to the view. Wrap the call in a try/catch so a failure in this service can never break the whole dashboard — on exception, log it and pass an empty collection.
4. In the Blade view, render an "Upcoming Events" card: event title, date (format: 23 Jul), and a small type badge (Holiday / Exam / Event / PTM). If the list is empty, show "No upcoming events this week." Keep it read-only — no buttons except a "View Calendar" link to `route('admin.academic-events.index')`, shown only if the current user passes the same check the sidebar uses for that link.
5. Do NOT build the phantom `admin.dashboards.professional` view, do NOT add sidebar entries, do NOT modify the service itself.
6. Tests: add a feature test asserting that an admin hitting the dashboard route gets HTTP 200 and sees a seeded upcoming event's title; and that the dashboard still returns 200 when the service throws (mock/bind a throwing instance).
7. Run the dashboard-related test suites plus your new test. All green → commit with message: `dashboard: surface upcoming academic events on admin.dashboard`. Do not push yet — push happens after Task 2.

**Acceptance criteria:** admin dashboard loads, shows the events card, degrades gracefully, tests pass, one commit.

---

## TASK 2 — Phase A status check (READ-ONLY — no file changes, no migrations, no deletions)

**Background:** The project plan has a Phase A: consolidating three class tables (`class_management`, `school_classes`, bare `classes`) into `school_classes` as the single canonical class table, then deleting dead parallel systems. Recent sessions completed items from Phases B and C. I need to know exactly how much of Phase 0 and Phase A is actually done in this working copy, verified against the code and database — not assumed from memory or commit messages alone.

**Rules for this task:** read-only. Allowed: reading files, grep, `git log`/`git status`, `php artisan route:list`, and read-only SELECT queries (COUNT, SHOW TABLES, DESCRIBE). Forbidden: any file edit, any migration command, any write query. Report findings only.

**Check each item and mark it DONE / PARTIAL / NOT DONE, with evidence (file:line, query result, or route:list output):**

### Phase 0 — Safety net
- 0.1 Any files untracked or uncommitted right now? (`git status`)
- 0.2 Does a database dump exist in `storage/backups/` (or anywhere findable)? Name, date, size.
- 0.3 Is the branch pushed and up to date with origin? (`git status -sb`)

### Phase A1 — SchoolClass canonical-ready
- A1.1 Does `app/Models/SchoolClass.php` have working `section()`, `academicSession()`, `teacher()` relationships, and are `section_id`, `academic_session_id`, `teacher_id` in `$fillable`?
- A1.2 Does `SchoolClassController::index()` eager-load only relationships that exist (i.e., is the known crash fixed)?
- A1.3 Does `resources/views/admin/school-classes/` exist with index/create/edit views?

### Phase A2 — Data merge
- A2.1 Does a `legacy_class_map` table exist? Row count?
- A2.2 Current row counts: `class_management`, `school_classes`, `classes`, `sections`.
- A2.3 Does any migration file exist whose name/content indicates the class_management → school_classes merge?

### Phase A3 — Consumers repointed
- A3.1 Does `ClassManagement` still have live consumers? Grep for `ClassManagement::`, `class_management` (outside migrations), and report every file still referencing it, marked live-code vs migration vs comment.
- A3.2 Where does the subjects pivot relationship live now — `ClassManagement::subjects()`, `SchoolClass::subjects()`, or both?
- A3.3 Is `TeacherClassAssignment`'s broken validation (`exists:class_managements,id` — nonexistent table) still present?

### Phase A4 — Demolition
- A4.1 Does the bare `classes` table still exist in the DB? Do its duplicate/dead routes still appear in `route:list`?
- A4.2 Do `attendances_temp` and `student_attendance` tables still exist?
- A4.3 Does `Admin/SmartAttendanceController.php` (fake rand() biometric) still exist?
- A4.4 Do the 7 dead routes on the non-Admin `ClassTeacherAssignmentController` still exist?
- A4.5 Does the orphaned `TeacherSubjectAssignment` model still exist with zero references?
- A4.6 Does `Admin/TeacherClassSubjectAssignmentController` (the raw DB::table duplicate) still exist with live routes?
- A4.7 Does `app/Services/YearClosing/PromotionService.php` still exist?
- A4.8 Is the phantom `ClassManagementPolicy` still registered in `AuthServiceProvider`?

### Output format
1. A status table: item | DONE/PARTIAL/NOT DONE | evidence.
2. A one-paragraph verdict: "Phase A is X% done; the next required step is ___."
3. The list of any unrelated problems you noticed during both tasks (report only — untouched).

After the report: push the Task 1 commit to origin. Then stop.
