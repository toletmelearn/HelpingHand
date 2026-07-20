# Phase 2P - Broken Public API Route Decision Audit

## 1. Files Inspected

- `routes/api.php`
- `app/Http/Controllers/API/ExamPaperController.php`
- `app/Http/Controllers/API/BellTimingController.php`
- `app/Models/ExamPaper.php`
- `app/Models/BellTiming.php`
- `app/Models/SchoolClass.php`
- `app/Models/ClassManagement.php`
- `app/Models/Section.php`
- `app/Models/Subject.php`
- `app/Http/Controllers/ExamPaperController.php`
- `app/Http/Controllers/BellTimingController.php`
- `database/migrations/*exam_papers*`
- `database/migrations/*bell_timings*`
- `database/migrations/*school_classes*`
- `database/migrations/*class_management*`
- `database/migrations/*sections*`
- `database/migrations/*subjects*`
- `database/migrations/*class_sections*`
- `database/migrations/*class_subject*`
- `tests/Feature/API`
- `docs/project-autopsy/PHASE_2N_API_CONTROLLER_STABILITY_AUDIT.md`
- `docs/project-autopsy/PHASE_2O_API_CONTROLLER_STABILITY_FIXES.md`

## 2. Commands Run

```powershell
php -l routes/api.php
php -l app/Http/Controllers/API/ExamPaperController.php
php -l app/Http/Controllers/API/BellTimingController.php
php artisan route --path=api/v1 | Select-String "exam-papers"
php artisan route --path=api/v1 | Select-String "bell-timing"
Get-Content routes/api.php
Get-Content app/Http/Controllers/API/ExamPaperController.php
Get-Content app/Http/Controllers/API/BellTimingController.php
Get-Content app/Models/ExamPaper.php
Get-Content app/Models/BellTiming.php
Get-Content app/Models/SchoolClass.php
Get-Content app/Models/ClassManagement.php
Get-Content app/Models/Section.php
Get-Content app/Models/Subject.php
rg -n "exam-papers/available|exam-papers/search|bell-timing/today|api.exam-papers.available-for-class|api.exam-papers.search|api.bell-timing.today|availableForClass|todaysSchedule" routes app resources tests docs -g "*.php" -g "*.blade.php" -g "*.js" -g "*.md"
Select-String -Path app/Http/Controllers/ExamPaperController.php -Pattern "function availableForClass|function search|function available" -Context 0,80
Select-String -Path app/Http/Controllers/BellTimingController.php -Pattern "function todaysSchedule|function weekly|function current" -Context 0,80
Get-ChildItem database/migrations -Filter '*exam_papers*' | Select-Object -ExpandProperty FullName
Get-ChildItem database/migrations -Filter '*bell_timings*' | Select-Object -ExpandProperty FullName
Get-ChildItem database/migrations | Where-Object { $_.Name -match 'school_classes|class_management|sections|subjects|class_sections|class_subject' } | Select-Object -ExpandProperty FullName
Get-Content database/migrations/2026_02_19_151317_fix_exam_papers_final_erp_structure.php
Get-Content database/migrations/2026_01_21_090000_create_bell_timings_table.php
Get-ChildItem tests/Feature/API -File | Select-Object -ExpandProperty Name
Get-Content docs/project-autopsy/PHASE_2O_API_CONTROLLER_STABILITY_FIXES.md
```

Notes:

- `php -l` passed for `routes/api.php`, `ExamPaperController.php`, and `BellTimingController.php`.
- The requested `php artisan route --path=api/v1` form is not a valid Artisan command in this app. Laravel reported the available `route:*` commands and no code/database state was changed. Static `routes/api.php` inspection was used instead.
- No tests were run.

## 3. Broken Public Route Map

| Route name | Method / URI | Current target | Current state | Middleware |
| --- | --- | --- | --- | --- |
| `api.exam-papers.available-for-class` | `GET /api/v1/exam-papers/available/{classSection}` | `ExamPaperController@availableForClass` | Method missing | `throttle:10,1`, `ApiAccessControl` |
| `api.exam-papers.search` | `POST /api/v1/exam-papers/search` | `ExamPaperController@search` | Method missing | `throttle:10,1`, `ApiAccessControl` |
| `api.bell-timing.today` | `GET /api/v1/bell-timing/today/{classSection}` | `BellTimingController@todaysSchedule` | Method missing | `throttle:10,1`, `ApiAccessControl` |

All three are still blocked by `ApiAccessControl` via the public temporary blocklist.

## 4. Route Purpose Audit

| Route | Intended purpose | Expected input | Expected output | Should it be public? |
| --- | --- | --- | --- | --- |
| `api.exam-papers.available-for-class` | Return available exam papers for a class/section. | `classSection` path parameter; optional `academic_year` query param. | Published/approved papers for the class. | Only if restricted to public, approved, published, non-expired papers. |
| `api.exam-papers.search` | Search exam papers by keyword and optional class. | `query`, optional `class_section`, optional `academic_year`. | Matching safe exam-paper metadata/list. | Prefer authenticated-only; if public, return only public approved metadata. |
| `api.bell-timing.today` | Return today's active bell schedule for a class/section. | `classSection` path parameter. | Active periods for current day, ordered by `order_index`. | Reasonable public candidate after implementation/tests. |

## 5. ExamPaper Route Feasibility Findings

### Existing Useful Fields

`ExamPaper` has model fields and scopes suitable for safe read endpoints:

- Class targeting:
  - `class_section`
  - `class_id`
  - `allowed_classes`
- Publication/security fields:
  - `is_published`
  - `is_approved`
  - `access_level`
  - `valid_from`
  - `valid_until`
  - `password_protected`
- Searchable display fields:
  - `title`
  - `subject`
  - `class_section`
  - `exam_type`
  - `paper_type`
  - `academic_year`

Existing helper methods/scopes:

- `published()`
- `approved()`
- `accessible()`
- `getAvailableForClass($classSection, $academicYear = null)`
- `getBySubjectAndClass($subject, $classSection, $academicYear = null)`

### Existing Web Reference

The web `ExamPaperController@availableForClass` and `search` methods are view-oriented and policy-protected:

- They call `$this->authorize('viewAny', ExamPaper::class)`.
- They return Blade views, not JSON.
- They query `published()` and `approved()`.
- Search uses regular `LIKE` matching instead of fulltext.

These methods are useful implementation references, but should not be copied directly into the API controller without API-specific response/security rules.

### Fulltext / SQLite Risk

The initial `exam_papers` migration defines a fulltext index over `title`, `subject`, and `class_section`, but previous API test phases already found SQLite migration incompatibility around fulltext indexes. A future API search implementation should not depend on fulltext for isolated tests.

Recommended safe search implementation:

- Use validated `LIKE` queries.
- Require a minimum query length, such as 2 or 3 characters.
- Limit/paginate results.
- Return metadata only, not unrestricted file URLs.

### Security Decision

Exam papers are more sensitive than timetable data. A public route can leak assessment material unless every query is constrained.

Safe public rule if implemented:

- `is_published = true`
- `is_approved = true`
- not expired:
  - `valid_until IS NULL OR valid_until > now()`
- `access_level = public`
- `password_protected = false`, unless response excludes download access and only exposes metadata
- class match uses `class_section` exactly

Safer default:

- Keep both exam-paper public routes blocked until implementation and isolated tests exist.
- Consider moving them to authenticated mobile users later if the school intends exam papers to be student/teacher-only.

## 6. BellTiming Route Feasibility Findings

### Existing Useful Fields

`BellTiming` has a clean schema/model contract for this route:

- `day_of_week`
- `period_name`
- `start_time`
- `end_time`
- `class_section`
- `is_active`
- `is_break`
- `order_index`
- `academic_year`
- `semester`
- `custom_label`
- `color_code`

Existing helper:

- `BellTiming::getTodaysSchedule($day = null, $classSection = null)`

Existing API methods:

- `weeklyTimetable($classSection)`
- `currentPeriod()`

Existing web reference:

- Web `BellTimingController@todaysSchedule(Request $request)` uses `BellTiming::getTodaysSchedule($day, $classSection)` and returns a Blade view.

### Security Decision

Bell timings are low-sensitivity operational data. Making today's schedule public is acceptable for many school contexts, as long as the route:

- returns only active schedule rows;
- accepts a class-section string, not arbitrary SQL;
- uses the model helper or equivalent Eloquent query;
- does not expose creator/admin details unless needed.

Recommended response shape:

```json
{
  "success": true,
  "message": "Today's bell schedule retrieved successfully",
  "data": {
    "class_section": "...",
    "day": "Wednesday",
    "schedule": []
  }
}
```

## 7. Public / Security Decision By Route

| Route | Recommendation | Decision |
| --- | --- | --- |
| `api.exam-papers.available-for-class` | Implement later, but keep blocked until tests pass. If opened publicly, return only `access_level = public`, published, approved, non-expired metadata. | D now, A later with strict public filter |
| `api.exam-papers.search` | Keep blocked for now. Implement after availability route, using non-fulltext search and strict filters. Prefer authenticated-only unless public search has a real product need. | D now, C or A later depending product decision |
| `api.bell-timing.today` | Best first implementation candidate. Implement safely, keep blocked until isolated test passes, then consider public allow. | D now, A first in Phase 2Q |

Do not convert any of these to admin-only. Admin-only would make the currently public route group misleading and duplicate existing protected admin capabilities. The real choice is safe public read versus authenticated mobile read.

## 8. Recommended Implementation / Quarantine Choice

No route should be removed immediately because route helpers or external clients may already know these URIs, and `ApiAccessControl` currently blocks them safely.

Recommended choice:

1. Keep all three blocked now.
2. Implement `BellTimingController@todaysSchedule` first in Phase 2Q.
3. Add isolated tests while it remains blocked.
4. Only after tests pass, remove `api.bell-timing.today` from the public temporary blocklist in a separate tiny step.
5. Implement exam-paper routes later with strict exposure rules.
6. If exam-paper public access is not a confirmed product requirement, convert exam-paper availability/search to authenticated-only instead of public.

## 9. Safe Isolated Test Plan

Use the Phase 2K/2M isolated schema pattern. Do not use full migrations.

### BellTiming Tests

Test file suggestion:

- `tests/Feature/API/PublicRouteStabilityTest.php`

Minimal tables:

- `users`
- `roles`
- `role_user`
- `bell_timings`
- `personal_access_tokens`

Tests:

- blocked route still returns 403 before allowlist change;
- after later allowlist change, today's schedule returns only current `day_of_week`;
- filters by `class_section`;
- excludes inactive rows;
- orders by `order_index`.

### ExamPaper Tests

Minimal tables:

- `users`
- `roles`
- `role_user`
- `exam_papers`
- `personal_access_tokens`

Availability tests:

- returns only matching `class_section`;
- excludes unpublished papers;
- excludes unapproved papers;
- excludes expired papers;
- excludes non-public access levels if public route remains public;
- does not expose private file paths unless intended.

Search tests:

- validates required/minimum query;
- uses non-fulltext search;
- filters by safe fields;
- respects publication/approval/access filters;
- paginates or limits results.

## 10. Safe Phase 2Q Implementation Plan

Recommended first task:

1. Implement `API\BellTimingController@todaysSchedule(string $classSection)`.
2. Use `BellTiming::getTodaysSchedule(now()->format('l'), $classSection)`.
3. Return the existing `BaseApiController::success()` response structure.
4. Keep `api.bell-timing.today` in `ApiAccessControl` temporary blocklist during initial implementation.
5. Add an isolated test proving the method exists and the route is still blocked.
6. Add an isolated direct-controller or temporary middleware-bypass test for response shape if feasible without changing middleware.
7. In a later tiny phase, remove only `api.bell-timing.today` from the temporary blocklist after tests pass.

Exam-paper Phase 2Q+ plan:

1. Implement `availableForClass` after bell timing.
2. Add strict public filters:
   - published
   - approved
   - non-expired
   - `access_level = public`
3. Return metadata only.
4. Add isolated tests.
5. Decide whether to keep it public or move it into authenticated routes.
6. Implement `search` last, using non-fulltext search and the same filters.

## 11. No Code / Database Modification Confirmation

- No application code was modified.
- No routes were changed.
- `ApiAccessControl` was not changed.
- Missing methods were not implemented.
- No migrations were touched.
- No tests were run.
- No database-changing commands were run.
- Real/local MySQL data was not touched.
- Only this report file was created.
