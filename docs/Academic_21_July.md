# Academic Module — Session Notes (21 July 2026)

Branch: `academic-module-rebuild`
Commits: `33183ca` (Timetable policy), `ca1fda9` (C1 — Academic Calendar)
Status: committed and pushed to `academic-module-rebuild` on origin. Not yet merged to `main` (merge happens after Phase C item 3, per the project plan).

---

## 1. New feature: Academic Calendar / Events / Holidays

**Where to find it in the app:** Sidebar → **"4. Academic Management"** section (mortarboard icon) → new link **"Academic Calendar"**, listed right below "Academic Sessions".

Proof — `resources/views/layouts/sidebar.blade.php:478-486`:
```blade
@if(Route::has('admin.academic-events.index') && (Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-academic-events')))
<li class="nav-item">
    <a class="nav-link ..." href="{{ route('admin.academic-events.index') }}">
        <i class="bi bi-calendar-week me-2"></i>
        <span>Academic Calendar</span>
    </a>
</li>
@endif
```

**Who sees the link:**
- The whole "Academic Management" sidebar section only renders for `admin`, `staff`, or holders of the `view-syllabi` / `view-daily-teaching-work` permissions (`sidebar.blade.php:435`).
- Within that section, the "Academic Calendar" link specifically needs `admin`, `staff`, or the `view-academic-events` permission.

**Who can do what on the page itself** — `app/Policies/AcademicEventPolicy.php`:

| Action | Allowed roles |
|---|---|
| View list / view one entry | `admin`, or anyone with role `clerk`, `accountant`, `staff`, `teacher` |
| Create | `admin` only |
| Update | `admin` only |
| Delete | `admin` only |

Note: the policy is looser than the sidebar link (it lets `teacher`/`clerk`/`accountant` view the calendar even though the sidebar hides the link from them unless they hold `view-academic-events`). This mirrors the existing pattern used by the Sections/Subjects links — not a bug introduced here.

**Routes created** (`routes/web.php:824-828`, all under the `admin.` name prefix):
```
GET    admin/academic-events                admin.academic-events.index
POST   admin/academic-events                admin.academic-events.store
GET    admin/academic-events/create         admin.academic-events.create
GET    admin/academic-events/{id}           admin.academic-events.show
PUT    admin/academic-events/{id}           admin.academic-events.update
DELETE admin/academic-events/{id}           admin.academic-events.destroy
GET    admin/academic-events/{id}/edit      admin.academic-events.edit
```
Verified live with `php artisan route:list --name=academic-events`.

**Data stored** — migration `database/migrations/2026_07_23_100300_create_academic_events_table.php`:
- `academic_session_id` (nullable FK to `academic_sessions`, `set null` on delete)
- `title`
- `type` — enum: `holiday`, `exam`, `event`, `ptm`, `other`
- `start_date`, `end_date`
- `description` (nullable)
- `is_active` (boolean, default true)
- Index on `(academic_session_id, start_date)`

**Model** — `app/Models/AcademicEvent.php`:
- Scopes: `holidays()`, `forSession($id)`, `between($start, $end)`
- Static helper: `AcademicEvent::isHoliday($date)` — returns the matching active holiday `AcademicEvent` (or `null`) for a given date

**Other files:**
- Controller: `app/Http/Controllers/Admin/AcademicEventController.php`
- Validation: `app/Http/Requests/AcademicEventRequest.php`
- Views: `resources/views/admin/academic-events/{index,create,edit,show}.blade.php`

---

## 2. Integration: holidays block attendance marking

**Where you'd feel this:** Sidebar → **"5. Attendance System"** → **"Student Attendance"** (`sidebar.blade.php:519-533`, route `admin.attendance.index`). Marking attendance for a date inside an active `holiday`-type calendar entry is now refused.

Proof — `app/Http/Controllers/AttendanceController.php` (web store method), added right after the existing "already marked" check:
```php
if ($holiday = AcademicEvent::isHoliday($request->date)) {
    return back()->with('error', "Attendance cannot be marked on a holiday: {$holiday->title}.");
}
```
The same guard was added to the API path in `app/Http/Controllers/API/AttendanceController.php`.

**Who this affects:** anyone who can mark attendance at all — per `app/Policies/AttendancePolicy.php:54`, that's `admin` or anyone holding the `create-attendance` permission (typically teachers). The block is a date rule, not a permission rule, so it applies regardless of role.

---

## 3. Integration: admin dashboard "upcoming events" — logic done, page not wired

`ProfessionalDashboardService::getUpcomingEvents()` (`app/Services/ProfessionalDashboardService.php`) now merges calendar events (holidays/events/PTMs due in the next 7 days) alongside exams, sorted by date — replacing the old version that only returned exams with a `// TODO: holidays, meetings, etc.` comment.

**Reachability caveat (pre-existing, not introduced this session):** the only consumer of this service is `ProfessionalDashboardController::adminDashboard()`, routed as `admin.dashboards.professional`, rendering view `admin.dashboards.professional`. That view file **does not exist** in the codebase, and **no sidebar link points to that route** — confirmed by direct search, not assumption. The dashboard tab you actually land on (`admin.dashboard`, backed by `AdminDashboardController::index`) is a completely separate controller that never calls this service.

**Net effect:** the logic is correct and covered by tests at the service level, but there is currently no page in the sidebar where you'd see it rendered. This gap existed before this session. To surface it, either:
- add a Blade view for `admin.dashboards.professional` and link it from the sidebar, or
- fold `getUpcomingEvents()` output into the main `AdminDashboardController` / `admin.dashboard` view you actually use today.

---

## 4. Unrelated leftover finished this session: Timetable permissions

Found finished-but-uncommitted code sitting in the working copy from an earlier session (a new `TimetableSlotPolicy`), verified it was complete and correct, and committed it separately (commit `33183ca`) before starting the calendar work.

**Where:** wherever the Timetable admin page is linked (`Admin\TimetableController`).
**Access rules** — `app/Policies/TimetableSlotPolicy.php`:
- View / create — `admin` or `teacher`
- Delete — `admin` only

Wired into `app/Providers/AuthServiceProvider.php` and enforced via `$this->authorize(...)` calls added in `app/Http/Controllers/Admin/TimetableController.php`.

---

## 5. Tests written (all passing)

- `tests/Feature/Admin/AcademicEventCrudTest.php` — create / update / delete / permission-denial / listing (5 tests)
- `tests/Feature/Attendance/AttendanceHolidayBlockTest.php` — web + API holiday blocking, confirms non-holiday dates still work (3 tests)
- `tests/Feature/Admin/DashboardUpcomingEventsTest.php` — calendar events merge with exams; inactive events excluded (2 tests)

Also fixed 6 pre-existing attendance test files that hand-build a minimal in-memory DB schema (rather than running real migrations) — they were missing the new `academic_events` table, which the holiday check now depends on. Added that table to each fixture's manual schema so they keep passing.

**Regression check:** full `tests/Feature/Attendance` suite (207 tests) and the admin dashboard/analytics suites pass. A full-suite run showed pre-existing failures in the Fee Finance module and one Accountant Dashboard test; these were confirmed — via a `git stash` A/B comparison — to fail identically with and without this session's changes, i.e. unrelated to this work.

---

## Branch status

Everything above is committed and pushed to `academic-module-rebuild` on GitHub. Not yet merged to `main` — per the project plan, that happens after Phase C item 3 (Certificate PDF Export), preceded by item 2 (Compliance Fields).
