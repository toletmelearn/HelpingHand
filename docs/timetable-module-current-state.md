# Timetable Module — Current State (as of main @ f523944)

This document is a read-only inventory + source snapshot of the Timetable module as it exists on `main` right now. It is being built incrementally (Part 3 is large and is appended section by section). Nothing in the application was modified to produce this document.

## Part 1 — Git Checkpoint

- **Branch:** `main`
- **HEAD:** `f523944eba44066d6d9d75838273ac401d68f562`
- **origin/main:** `f523944eba44066d6d9d75838273ac401d68f562` (local HEAD == origin/main, confirmed via `git ls-remote`)
- **Working tree:** clean except two known untracked docs (`docs/plans/verification-audit.md`, `docs/timetable-system-documentation.md`) — not modified by this investigation.

**Timetable hardening sequence, verified directly from git (not assumed):**

| Commit | Subject | Reachable from HEAD |
|---|---|---|
| `7669e9549687f736479d45d4e85322ce59c5ce5d` | fix(timetable): prevent subset-generation from double-booking teachers outside the requested class set | yes |
| `6f7a4a065ce4a381845c3d048905cb4d7751f32b` | fix(timetable): resolve Auto-Fix multi-blocker conflicts | yes |
| `3c24b82555bf2e744d617529ff572a7c3282271e` | feat(timetable): add teacher weekly timetable view | yes |
| `f523944eba44066d6d9d75838273ac401d68f562` | feat(timetable): add parent weekly timetable | yes |

Recent full commit history (`git log --oneline`, most recent first) shows the complete lineage back through the original T1–T6 build-out, the Locked Lessons/Rebalancing/Auto-Fix-chain phases, PR #12 (`timetable-pilot-hardening`) and PR #13 (`timetable-workspace-authz`), and `550b325` (class-teacher navigation clarification) as the pre-hardening-sequence baseline.

## Part 2 — Complete Module Inventory

Discovered by tracing actual dependencies (models used, routes registered, views rendered, tests exercising each path), not just filename grep.

### Services — `app/Services/Timetable/`

| File | Lines | Purpose |
|---|---|---|
| `GeneratorService.php` | 1528 | T4a bulk auto-generation: backtracking constraint solver. Pure logic, writes nothing to the DB. |
| `TimetableAutoFixService.php` | 482 | Phase 4: single-blocker and N-hop chain relocation to resolve a conflict for one new placement. |
| `TimetableConflictResolver.php` | 493 | Authoritative single-placement conflict check for every interactive write path (grid edit, Auto-Fix, Rebalance, Swap, API). |
| `TimetableSwapService.php` | 222 | Swaps two existing slots' periods atomically. |
| `TimetableRebalanceService.php` | 756 | Bounded greedy hill-climbing search to improve one class-section's own slot arrangement. |
| `FeasibilityService.php` | 416 | T1b/T2b feasibility & readiness report (capacity math, class-teacher readiness, etc.) — read-only analysis, not modified by this investigation. |
| `SubstituteFinderService.php` | 148 | Finds candidate substitute teachers for an absence. |
| `SubstitutionDashboardService.php` | 26 | Small stats aggregator for the substitution admin dashboard card. |
| `TimetableSuggestionService.php` | 236 | Candidate-period suggestion engine used by Auto-Fix's chain search and Rebalance. |

### Controllers

| File | Lines | Purpose |
|---|---|---|
| `app/Http/Controllers/Admin/TimetableController.php` | 1600 | Main admin hub: CRUD, workspace grid, generate/publish/discard, Auto-Fix/Rebalance/Swap endpoints, feasibility, PDF/Excel exports, teacher/room views. |
| `app/Http/Controllers/Admin/TimetableWizardController.php` | 291 | Guided "Set Up Timetable" wizard (subjects step, style step, readiness step). |
| `app/Http/Controllers/Admin/TeacherSubstitutionController.php` | 478 | Substitution CRUD, assign/approve/cancel, rules, arrangement sheet PDF, absence overview. |
| `app/Http/Controllers/Admin/CombinedClassGroupController.php` | 93 | CRUD for combined class groups (T2b). |
| `app/Http/Controllers/Teacher/TeacherTimetableController.php` | 135 | Teacher weekly timetable view (Commit `3c24b82`). |
| `app/Http/Controllers/Teacher/TeacherDashboardController.php` | — (shared file) | `todaysPeriodsForTeacher()` — the "Today" card on the teacher dashboard; documented here for its Timetable-relevant method only. |
| `app/Http/Controllers/Parent/TimetableController.php` | 185 | Parent today + weekly timetable views (T5 + Commit `f523944`). |
| `app/Http/Controllers/API/ParentTimetableController.php` | 101 | Mobile API: today's periods for a parent's linked student. |
| `app/Http/Controllers/API/BellTimingController.php` | 332 | Mobile API: bell timing today/weekly/current-period/bulk-create. |
| `app/Http/Controllers/BellTimingController.php` | 377 | Web admin: bell timing CRUD, weekly/daily views, bulk-create, print. |
| `app/Http/Controllers/AttendanceController.php` | — (shared file) | `todaysTimetableForClass()` — read-only published-timetable reference panel on the attendance-marking screen; documented here for its Timetable-relevant method only. **(Reconciliation addition.)** |
| `app/Http/Controllers/Admin/AISmartFeaturesController.php` | — (shared file) | `autoTimetable()`/`generateBasicTimetable()` — a separate, unrelated, unrouted random-assignment stub with no live route and no matching view (confirmed dead code, not connected to `GeneratorService`); documented here for completeness only. **(Reconciliation addition.)** |

### Middleware

| File | Lines | Purpose |
|---|---|---|
| `app/Http/Middleware/TeacherAuth.php` | 39 | Enforces authenticated-teacher identity on every `/teacher/*` route (including Teacher Weekly Timetable). **(Reconciliation addition.)** |
| `app/Http/Middleware/ParentAuth.php` | 37 | Enforces authenticated-parent identity on every `/parent/*` route (including Parent Weekly Timetable). **(Reconciliation addition.)** |

### Models

| File | Lines | Purpose |
|---|---|---|
| `app/Models/TimetableSlot.php` | 110 | Core slot row (draft/published/archived). |
| `app/Models/BellTiming.php` | 284 | Period/bell timing definition. |
| `app/Models/TeacherSubstitution.php` | 166 | Substitution/arrangement record. |
| `app/Models/TimetableGeneration.php` | 75 | One row per `GenerateTimetableJob` run (lifecycle status, report). |
| `app/Models/CombinedClassGroup.php` | 56 | T2b combined-class teaching group. |
| `app/Models/CombinedClassGroupMember.php` | 32 | Member class-section of a combined group. |

### Exports (Excel) — `app/Exports/`

| File | Purpose |
|---|---|
| `TeacherTimetableExport.php` | Excel export of one teacher's timetable. |
| `RoomTimetableExport.php` | Excel export of one room's timetable. |
| `MasterTimetableExport.php` | Full-school master Excel export. |
| `MasterTimetableDaySheetExport.php` | Master export, one sheet per day. |
| `ClassTimetableExport.php` | Excel export of one class's timetable. |

### Jobs

| File | Purpose |
|---|---|
| `app/Jobs/GenerateTimetableJob.php` | Queued wrapper around `GeneratorService::generate()` — clears stale drafts, inserts placements, updates the owning `TimetableGeneration` row. |

### Policies

| File | Purpose |
|---|---|
| `app/Policies/TimetableSlotPolicy.php` | Authorization gate for slot read/write actions. |
| `app/Policies/TeacherAvailabilityPolicy.php` | Authorization for teacher-availability blocks. |
| `app/Policies/CombinedClassGroupPolicy.php` | Authorization for combined-group CRUD. |
| `app/Policies/BellTimingPolicy.php` | Authorization for bell-timing CRUD (role-only, no per-teacher ownership scoping). **(Reconciliation addition.)** |
| `app/Policies/TeacherSubstitutionPolicy.php` | Authorization for substitution CRUD/assign/approve/cancel/rules (permission-based: `view-teachers`/`manage-substitutions`, plus admin-only `delete`/`manageRules`). **(Follow-up addition.)** |

### Configuration

| File | Purpose |
|---|---|
| `config/timetable.php` | Generator time/backtrack budgets, Auto-Fix chain depth/search budget, Rebalance candidate/movement/iteration/time limits — all env-overridable. Full contents reproduced in Part 3. |

### Routes

- `routes/web.php` — Admin block (`/admin/timetable/...`, ~40 routes: CRUD, workspace, generate/publish/discard, auto-fix, rebalance, swap, feasibility, PDF/Excel exports, teacher/room views, wizard, combined groups), Teacher block (`/teacher/timetable`), Parent block (`/parent/timetable/today`, `/parent/timetable/weekly`), plus standalone `bell-timing.*` and `teacher-substitutions.*` resource/route groups.
- `routes/api.php` — `bell-timing.*` apiResource + today/weekly/current-period/bulk-create, `students.{id}.timetable-today` (Parent mobile API).

### Views — `resources/views/`

Admin: `admin/timetable/{generate,generation-review,grid,room-view,teacher-view,feasibility,workspace}.blade.php`, `admin/timetable/wizard/{_progress,empty,step1,step2,step3}.blade.php`, `admin/timetable/pdf/{class,master,teacher}.blade.php`, `admin/timetable/partials/_review-edit.blade.php`, `admin/timetable/combined-groups/{index,create}.blade.php`.
Teacher: `teacher/timetable/index.blade.php`.
Parent: `parent/timetable/{today,weekly}.blade.php`.
Navigation: `layouts/sidebar.blade.php` (admin), `layouts/teacher.blade.php`, `layouts/parent.blade.php` — each carries the relevant "Timetable"/"My Timetable" nav entry.

### Migrations (schema history, not modified)

`create_timetable_slots_table`, `add_unique_constraints_to_timetable_slots`, `add_combined_class_group_id_to_timetable_slots_table`, `create_timetable_generations_table`, `add_status_and_generation_id_to_timetable_slots_table`, `update_timetable_slots_unique_constraints_for_status`, `add_academic_session_id_to_timetable_generations_table`, `add_style_to_timetable_generations_table`, `add_co_teacher_id_to_timetable_slots_table`, `add_is_locked_to_timetable_slots_table`, `create_bell_timings_table`, `add_period_type_to_bell_timings_table`, `add_bell_timing_id_to_teacher_substitutions_table`, `create_teacher_substitutions_table`.

No dedicated seeders or factories exist for these models — tests build fixtures directly via `::create()`.

### Tests (36 files — listed here; not reproduced verbatim, per the production-code-only scope of Part 3)

**Unit** (`tests/Unit/Services/Timetable/`): `GeneratorServiceTest`, `TimetableAutoFixServiceTest`, `TimetableConflictResolverTest`, `TimetableSwapServiceTest`, `TimetableRebalanceServiceTest`, `TimetableSuggestionServiceTest`, `FeasibilityServiceTest`. Plus `tests/Unit/Http/Controllers/Admin/TimetableControllerPeriodMetaTest.php`, `tests/Unit/Models/TimetableT2aSchemaTest.php`, `tests/Unit/Models/BellTimingPeriodTypeTest.php`, `tests/Unit/Models/TeacherSubstitutionPeriodLinkTest.php`.

**Feature — Admin**: `TeacherSubjectAssignmentTimetableFieldsTest`, `TimetableActivityLogTest`, `TimetableFeasibilityPageTest`, `TimetableGenerationWorkflowTest`, `TimetableSlotPolicyScopeTest`, `TimetableSlotUniqueConstraintsTest`, `TimetableWholeSchoolGenerationTest`, `TimetableCombinedSlotTest`, `TimetableSuggestionQueryCountTest`, `TimetableWizardTest`, `TimetableAutoFixChainEndpointTest`, `TimetableAutoFixEndpointTest`, `TimetableLockTest`, `TimetableSlotUpdateTest`, `TimetableSwapTest`, `TimetableRebalanceQueryCountTest`, `TimetablePdfExportTest`, `TimetableRebalanceEndpointTest`, `TimetableViewsAndExportsTest`, `TimetableSchedulerTest`, `TimetableReadAuthorizationTest`, `TimetableWorkspaceTest`, `TimetableWorkspaceAuthorizationTest`, `TimetableAutoFixQueryCountTest`, `CombinedClassGroupTest`, `AdminDashboardSubstitutionCardTest`, `TeacherSubstitutionAssignRouteTest`, `TeacherSubstitutionAuthorizationTest`.

**Feature — Teacher**: `TeacherDashboardTimetableTest`, `TeacherWeeklyTimetableTest`.
**Feature — Parent**: `ParentTimetableTodayTest`, `ParentWeeklyTimetableTest`.
**Feature — API**: `ParentTimetableApiTest`, `BellTimingTodayRouteTest`.
**Feature — Attendance**: `AttendanceTimetableReferenceTest`.
**Feature — misc**: `BellTimingBulkCreateRouteTest`.

---

## Part 3 — Complete Function Source

Coverage: complete verbatim source for every production PHP file in the Timetable module — 9 services, 12 controllers/controller-methods (10 primary + 2 shared-file methods), 6 models, 5 Excel exports, 1 job, 4 policies, 2 gating middleware, and `config/timetable.php`. Part 4 (below Part 3) contains a later reconciliation pass that added `BellTimingPolicy`, `TeacherAuth`, `ParentAuth`, `AISmartFeaturesController`'s two methods, and `AttendanceController::todaysTimetableForClass()`, and corrected two earlier omissions in `TimetableConflictResolver` and `TimetableSuggestionService` (see Part 4 for details).

### `config/timetable.php`

Full file (env-overridable budgets for the generator, Auto-Fix chain search, and Rebalance engine):

```php
<?php

return [
    /*
    |--------------------------------------------------------------------
    | Max periods per week (feasibility threshold)
    |--------------------------------------------------------------------
    |
    | Used by the Timetable Feasibility Report (T1b) to flag teachers
    | placed for more periods than a normal working week should hold.
    |
    */
    'max_periods_per_week' => env('TIMETABLE_MAX_PERIODS_PER_WEEK', 36),

    /*
    |--------------------------------------------------------------------
    | Auto-generation solver (T4a)
    |--------------------------------------------------------------------
    |
    | time_budget_seconds: wall-clock cap on GeneratorService::generate();
    | it returns the best state found so far once exceeded, per the plan's
    | "hard time budget" requirement.
    |
    | backtrack_budget_per_lesson: how many local-relocation attempts the
    | solver makes for a single lesson with zero legal slots before giving
    | up on it and marking it UNPLACED -- bounds the "depth-limited...
    | backtracking on dead ends" requirement so a hard-to-place lesson
    | can't stall the whole run.
    |
    */
    'generator' => [
        'time_budget_seconds' => env('TIMETABLE_GENERATOR_TIME_BUDGET', 60),
        'backtrack_budget_per_lesson' => env('TIMETABLE_GENERATOR_BACKTRACK_BUDGET', 25),

        // Whole-school Generate: GenerateTimetableJob's own timeout, separate
        // from time_budget_seconds above -- this is headroom for the DB work
        // around the solve (delete old drafts, insert every placement row),
        // not the solver's own search budget.
        'job_timeout_seconds' => env('TIMETABLE_GENERATOR_JOB_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------
    | Auto-Fix chain repair (Phase 4)
    |--------------------------------------------------------------------
    |
    | max_chain_depth: how many lessons deep a repair chain is allowed to
    | go (1 = exactly the pre-existing single-blocker-relocation case; 2+
    | means the blocker's own blocker also has to move, and so on).
    |
    | search_budget: total TimetableConflictResolver::check() calls the
    | chain search may spend across the whole attempt before giving up and
    | reporting "no fix found" -- each call costs roughly 14-19 queries
    | (see TimetableConflictResolver), so this bounds worst-case query
    | volume for one interactive Auto-Fix preview the same way
    | backtrack_budget_per_lesson bounds the generator's search.
    |
    */
    'autofix' => [
        'max_chain_depth' => env('TIMETABLE_AUTOFIX_MAX_CHAIN_DEPTH', 3),
        'search_budget' => env('TIMETABLE_AUTOFIX_SEARCH_BUDGET', 150),
    ],

    /*
    |--------------------------------------------------------------------
    | Rebalancing Engine
    |--------------------------------------------------------------------
    |
    | A bounded, greedy hill-climbing search over ONE class-section's own
    | slots: each accepted movement is the single best-scoring legal swap
    | or relocation found this iteration, repeated until no movement
    | improves the score, or one of the limits below is hit -- never an
    | unbounded search.
    |
    | max_candidate_evaluations: total TimetableConflictResolver::check()/
    | TimetableSuggestionService::checkSwap() CALLS the whole analyze()
    | pass may spend before giving up on finding further improvements --
    | NOT total queries: each checkSwap() is itself ~2 check() calls
    | (~14-19 queries apiece), so 100 evaluations already costs on the
    | order of 1,000-2,000 real queries (measured:
    | TimetableRebalanceQueryCountTest). This default is deliberately
    | lower than autofix.search_budget for exactly that reason -- a
    | rebalance evaluates candidate SWAPS (2 checks each), autofix's chain
    | search mostly evaluates single relocations (1 check each), so the
    | same nominal budget costs roughly twice as much here.
    |
    | max_movements: the largest number of movements a single preview may
    | propose -- keeps "prefer the smallest number of changes" enforced by
    | more than just the greedy stopping condition.
    |
    | max_iterations: safety cap on the outer greedy loop itself (one
    | iteration = one accepted movement, so in practice this rarely binds
    | before max_movements does -- kept as an independent, explicit limit
    | rather than relying on max_movements alone). Lowered alongside
    | max_candidate_evaluations for the same query-cost reason: each
    | iteration re-scans the (shrinking) remaining candidate pool from
    | scratch, so iteration count is a direct multiplier on total cost.
    |
    | time_budget_seconds: wall-clock cap on one analyze() call, same
    | reasoning as generator.time_budget_seconds.
    |
    */
    'rebalance' => [
        'max_candidate_evaluations' => env('TIMETABLE_REBALANCE_MAX_CANDIDATES', 100),
        'max_movements' => env('TIMETABLE_REBALANCE_MAX_MOVEMENTS', 6),
        'max_iterations' => env('TIMETABLE_REBALANCE_MAX_ITERATIONS', 6),
        'time_budget_seconds' => env('TIMETABLE_REBALANCE_TIME_BUDGET', 10),
    ],
];
```

### `app/Services/Timetable/TimetableConflictResolver.php` (complete — 493 lines)

Class purpose: the single authoritative place that answers "can this lesson go here?" for every interactive, single-placement caller against real, persisted `TimetableSlot` data (manual grid editor, Auto-Fix, Rebalance, API). Deliberately NOT used by `GeneratorService`'s hot loop, which uses its own in-memory hash-map checks for performance.

**Class constant** (Reconciliation pass — previously omitted from this document; reproduced exactly as it exists in current source, first line of the class body):

```php
class TimetableConflictResolver
{
    private const DEFAULT_MAX_PER_DAY = 7;
```

This constant is the fallback used by `teacherLoadConflicts()` below whenever a teacher record has no explicit `max_periods_per_day` set (`$teacher->max_periods_per_day ?? self::DEFAULT_MAX_PER_DAY`).

#### `check(array $placement): array` — public
The main entry point. Resolves the row a placement's own natural key currently occupies (so an in-place resubmission never conflicts with itself), resolves the applicable academic year, then runs all 7 conflict checks and merges their results.

```php
    public function check(array $placement): array
    {
        $teacherId = $placement['teacher_id'] ?? null;
        $bellTimingId = $placement['bell_timing_id'] ?? null;

        if (!$teacherId || !$bellTimingId) {
            return $this->result([]);
        }

        $bellTiming = BellTiming::find($bellTimingId);
        if (!$bellTiming) {
            return $this->result([]);
        }

        // The manual editor upserts by natural key (class, section,
        // bell_timing, status), not by row id -- resubmitting the exact
        // same cell (e.g. re-saving with a new room number) never sends an
        // id at all. Auto-resolving the row that occupies this placement's
        // own natural key -- if any -- and treating it as implicitly
        // ignored means every check below is naturally self-consistent:
        // "does this violate a constraint against some OTHER row" rather
        // than "does it violate a constraint against the row it's about to
        // replace." An explicitly passed ignore_slot_id always wins.
        $placement['ignore_slot_id'] = $placement['ignore_slot_id'] ?? $this->resolveSelfId($placement);

        // Which academic year "other periods that day" / "other periods
        // that overlap" should be drawn from -- defaults to whatever
        // session is current, matching the wizard's own
        // currentAcademicYear() pattern. Applied tolerantly (only when
        // known) to BellTiming/assignment queries below AND, as of the
        // Hardening pass, to the TimetableSlot queries in baseQuery(),
        // teacherLoadConflicts(), and subjectPerDayConflicts() too: every
        // current write path (store(), update(), storeCombined(),
        // GenerateTimetableJob) now stamps academic_year on every row it
        // creates, so a stale published slot left over from a prior year
        // (e.g. a class that wasn't regenerated when the school rolled into
        // a new session) no longer counts as "busy" against a same-period,
        // same-teacher placement in the CURRENT year -- it was only ever a
        // false-positive risk (blocking a legitimate edit), never a
        // false-negative one, since the filter only narrows an already
        // status-scoped match, but it's worth closing regardless. The
        // filter is "same year OR untagged" rather than a strict equals:
        // an untagged legacy row (academic_year null -- e.g. a slot
        // created directly, outside the app's own write paths, before
        // this stamping was consistent) still counts as a real occupant,
        // exactly as it always has; only a row explicitly tagged with a
        // DIFFERENT year is excluded.
        $academicYear = $placement['academic_year'] ?? AcademicSession::current()->first()?->code;

        $overlappingIds = $this->overlappingBellTimingIds($bellTiming, $academicYear);
        $sameDayIds = $this->sameDayBellTimingIds($bellTiming, $academicYear);

        $conflicts = [];
        $conflicts = array_merge($conflicts, $this->periodTypeConflicts($bellTiming));
        $conflicts = array_merge($conflicts, $this->teacherOverlapConflicts($placement, $overlappingIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->classSectionOverlapConflicts($placement, $overlappingIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->roomOverlapConflicts($placement, $overlappingIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->teacherAvailabilityConflicts($placement, $bellTiming));
        $conflicts = array_merge($conflicts, $this->teacherLoadConflicts($placement, $sameDayIds, $academicYear));
        $conflicts = array_merge($conflicts, $this->subjectPerDayConflicts($placement, $bellTiming, $sameDayIds, $academicYear));

        return $this->result($conflicts);
    }
```

#### `resolveSelfId(array $placement): ?int` — private
The id of the existing row, if any, that occupies this exact (class, section, bell_timing, status) natural key.

```php
    private function resolveSelfId(array $placement): ?int
    {
        if (empty($placement['school_class_id']) || empty($placement['bell_timing_id'])) {
            return null;
        }

        return TimetableSlot::where('school_class_id', $placement['school_class_id'])
            ->where('section_id', $placement['section_id'] ?? null)
            ->where('bell_timing_id', $placement['bell_timing_id'])
            ->where('status', $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED)
            ->value('id');
    }
```

#### `result(array $conflicts): array` — private
Normalizes the conflicts list into the return shape `check()` promises: first-violation summary plus the full list.

```php
    private function result(array $conflicts): array
    {
        $first = $conflicts[0] ?? null;

        return [
            'conflict' => !empty($conflicts),
            'type' => $first['type'] ?? null,
            'message' => $first['message'] ?? null,
            'conflicts' => $conflicts,
        ];
    }
```

#### `overlappingBellTimingIds(BellTiming $bellTiming, ?string $academicYear): Collection` — private
Every active, current-year bell_timing_id on the same day whose time range overlaps the target's.

```php
    private function overlappingBellTimingIds(BellTiming $bellTiming, ?string $academicYear): Collection
    {
        $startTime = $bellTiming->start_time->format('H:i:s');
        $endTime = $bellTiming->end_time->format('H:i:s');

        return BellTiming::where('is_active', true)
            ->where('day_of_week', $bellTiming->day_of_week)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->pluck('id');
    }
```

#### `sameDayBellTimingIds(BellTiming $bellTiming, ?string $academicYear): Collection` — private
Every active, current-year bell_timing_id on the same day (no time-overlap filter — used for daily-load and subject-per-day checks).

```php
    private function sameDayBellTimingIds(BellTiming $bellTiming, ?string $academicYear): Collection
    {
        return BellTiming::where('is_active', true)
            ->where('day_of_week', $bellTiming->day_of_week)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->pluck('id');
    }
```

#### `baseQuery(array $placement, Collection $bellTimingIds, ?string $academicYear = null)` — private
Shared base query for the overlap-style checks: status-scoped, year-tolerant, self-ignoring.

```php
    private function baseQuery(array $placement, Collection $bellTimingIds, ?string $academicYear = null): \Illuminate\Database\Eloquent\Builder
    {
        $status = $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        return $this->applyIgnore(
            TimetableSlot::whereIn('bell_timing_id', $bellTimingIds)
                ->where('status', $status)
                ->when($academicYear, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('academic_year')->orWhere('academic_year', $academicYear))),
            $placement['ignore_slot_id'] ?? null
        );
    }
```

#### `applyIgnore(\Illuminate\Database\Eloquent\Builder $query, $ignore)` — private
Excludes the row(s) a placement itself would replace — accepts a single id or an array (used by swap/chain checks that must ignore multiple rows at once).

```php
    private function applyIgnore(\Illuminate\Database\Eloquent\Builder $query, $ignore): \Illuminate\Database\Eloquent\Builder
    {
        if (empty($ignore)) {
            return $query;
        }

        return is_array($ignore) ? $query->whereNotIn('id', $ignore) : $query->where('id', '!=', $ignore);
    }
```

#### `periodTypeConflicts(BellTiming $bellTiming): array` — private
A teaching lesson cannot be placed into a non-teaching period.

```php
    private function periodTypeConflicts(BellTiming $bellTiming): array
    {
        if ($bellTiming->period_type === BellTiming::PERIOD_TYPE_TEACHING) {
            return [];
        }

        return [[
            'type' => 'period_type',
            'message' => "This is a {$bellTiming->period_type} period, not a teaching period -- a lesson can't be scheduled here.",
        ]];
    }
```

#### `teacherOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array` — private
Both people on a lesson (teacher + optional co-teacher) must be free, checked against both columns on every existing overlapping row.

```php
    private function teacherOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array
    {
        $conflicts = [];
        $people = array_filter([$placement['teacher_id'] ?? null, $placement['co_teacher_id'] ?? null]);

        foreach ($people as $personId) {
            $busy = $this->baseQuery($placement, $overlappingIds, $academicYear)
                ->where(fn ($q) => $q->where('teacher_id', $personId)->orWhere('co_teacher_id', $personId))
                ->with('schoolClass')
                ->first();

            if ($busy) {
                $personName = Teacher::find($personId)->name ?? 'This teacher';
                $conflicts[] = [
                    'type' => 'teacher',
                    'message' => "{$personName} is already scheduled to teach " . ($busy->schoolClass->name ?? 'another class') . ' during this period.',
                    'blocking_slot_id' => $busy->id,
                ];
            }
        }

        return $conflicts;
    }
```

#### `classSectionOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array` — private
Catches the cross-case a plain unique-index can't: a class-wide slot vs. a specific-section slot of the same class.

```php
    private function classSectionOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array
    {
        $schoolClassId = $placement['school_class_id'] ?? null;
        if (!$schoolClassId) {
            return [];
        }

        $sectionId = $placement['section_id'] ?? null;

        $existing = $this->baseQuery($placement, $overlappingIds, $academicYear)
            ->where('school_class_id', $schoolClassId)
            ->when($sectionId, fn ($q) => $q->whereNull('section_id'), fn ($q) => $q->whereNotNull('section_id'))
            ->with('section')
            ->first();

        if (!$existing) {
            return [];
        }

        $message = $existing->section_id === null
            ? 'This class already has a whole-class lesson scheduled during this period -- it applies to every section.'
            : 'Section ' . ($existing->section->name ?? '') . ' of this class already has a lesson scheduled during this period.';

        return [[
            'type' => 'class',
            'message' => $message,
            'blocking_slot_id' => $existing->id,
        ]];
    }
```

#### `roomOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array` — private
Room double-booking check (only runs when `room_number` is set).

```php
    private function roomOverlapConflicts(array $placement, Collection $overlappingIds, ?string $academicYear = null): array
    {
        $roomNumber = $placement['room_number'] ?? null;
        if (!$roomNumber) {
            return [];
        }

        $existing = $this->baseQuery($placement, $overlappingIds, $academicYear)
            ->where('room_number', $roomNumber)
            ->with('schoolClass')
            ->first();

        if (!$existing) {
            return [];
        }

        return [[
            'type' => 'room',
            'message' => "Room {$roomNumber} is already occupied by Class " . ($existing->schoolClass->name ?? 'another class') . ' during this period.',
            'blocking_slot_id' => $existing->id,
        ]];
    }
```

#### `teacherAvailabilityConflicts(array $placement, BellTiming $bellTiming): array` — private
A teacher (or co-teacher) with an explicit `TeacherAvailability(is_available=false)` row for this exact bell timing is a hard block.

```php
    private function teacherAvailabilityConflicts(array $placement, BellTiming $bellTiming): array
    {
        $conflicts = [];
        $people = array_filter([$placement['teacher_id'] ?? null, $placement['co_teacher_id'] ?? null]);

        if (empty($people)) {
            return [];
        }

        $blocked = TeacherAvailability::whereIn('teacher_id', $people)
            ->where('bell_timing_id', $bellTiming->id)
            ->where('is_available', false)
            ->pluck('teacher_id');

        foreach ($blocked as $personId) {
            $personName = Teacher::find($personId)->name ?? 'This teacher';
            $conflicts[] = [
                'type' => 'availability',
                'message' => "{$personName} has been marked unavailable for this period.",
            ];
        }

        return $conflicts;
    }
```

#### `teacherLoadConflicts(array $placement, Collection $sameDayIds, ?string $academicYear = null): array` — private
Daily/weekly period-count cap enforcement per teacher/co-teacher.

```php
    private function teacherLoadConflicts(array $placement, Collection $sameDayIds, ?string $academicYear = null): array
    {
        $conflicts = [];
        $status = $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED;
        $people = array_filter([$placement['teacher_id'] ?? null, $placement['co_teacher_id'] ?? null]);

        foreach ($people as $personId) {
            $teacher = Teacher::find($personId);
            if (!$teacher) {
                continue;
            }

            $maxPerDay = $teacher->max_periods_per_day ?? self::DEFAULT_MAX_PER_DAY;
            $maxPerWeek = $teacher->max_periods_per_week ?? config('timetable.max_periods_per_week', 36);

            // ignore_slot_id (auto-resolved in check() to the row this
            // placement's own natural key already occupies, if any) keeps
            // a same-cell resubmission from counting its own current
            // occupant as an ADDITIONAL period on top of the one being placed.
            // Tolerant academic_year scoping (only applied when known) --
            // a prior-year published slot must not count toward this
            // year's daily/weekly load caps. A row with no academic_year
            // at all (untagged legacy data) still counts -- only a row
            // explicitly tagged with a DIFFERENT year is excluded.
            $personQuery = fn () => $this->applyIgnore(
                TimetableSlot::where('status', $status)
                    ->when($academicYear, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                    ->where(fn ($q) => $q->where('teacher_id', $personId)->orWhere('co_teacher_id', $personId)),
                $placement['ignore_slot_id'] ?? null
            );

            $dayCount = (clone $personQuery())->whereIn('bell_timing_id', $sameDayIds)->count();
            if ($dayCount + 1 > $maxPerDay) {
                $conflicts[] = [
                    'type' => 'teacher_day_limit',
                    'message' => "{$teacher->name} is already scheduled for {$dayCount} period(s) that day -- their daily limit is {$maxPerDay}.",
                ];
                continue;
            }

            $weekCount = (clone $personQuery())->count();
            if ($weekCount + 1 > $maxPerWeek) {
                $conflicts[] = [
                    'type' => 'teacher_week_limit',
                    'message' => "{$teacher->name} is already scheduled for {$weekCount} period(s) this week -- their weekly limit is {$maxPerWeek}.",
                ];
            }
        }

        return $conflicts;
    }
```

#### `subjectPerDayConflicts(array $placement, BellTiming $bellTiming, Collection $sameDayIds, ?string $academicYear): array` — private
Same-subject-per-day cap (1, or 2 for an assignment marked `require_consecutive`).

```php
    private function subjectPerDayConflicts(array $placement, BellTiming $bellTiming, Collection $sameDayIds, ?string $academicYear): array
    {
        $schoolClassId = $placement['school_class_id'] ?? null;
        $subjectId = $placement['subject_id'] ?? null;
        if (!$schoolClassId || !$subjectId) {
            return [];
        }

        $sectionId = $placement['section_id'] ?? null;
        $status = $placement['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        // Tolerant academic_year scoping (only applied when known) --
        // otherwise a retired/other-year assignment row (e.g. leftover
        // test-data) could wrongly mark this subject as a double period.
        $requiresConsecutive = TeacherClassSubjectAssignment::where('class_id', $schoolClassId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $placement['teacher_id'] ?? null)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->value('require_consecutive');

        $cap = $requiresConsecutive ? 2 : 1;

        // ignore_slot_id excludes a same-cell resubmission's own current
        // occupant from counting as an extra period (see check()). Tolerant
        // academic_year scoping (only applied when known) -- a prior-year
        // published slot must not count toward this year's subject-per-day
        // cap either; an untagged legacy row still counts.
        $existingCount = $this->applyIgnore(
            TimetableSlot::where('status', $status)
                ->where('school_class_id', $schoolClassId)
                ->where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->when($academicYear, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('academic_year')->orWhere('academic_year', $academicYear)))
                ->whereIn('bell_timing_id', $sameDayIds),
            $placement['ignore_slot_id'] ?? null
        )->count();

        if ($existingCount + 1 > $cap) {
            $subjectName = \App\Models\Subject::find($subjectId)->name ?? 'This subject';
            $dayName = $bellTiming->day_of_week;

            return [[
                'type' => 'subject_per_day',
                'message' => "{$subjectName} already has {$existingCount} period(s) on {$dayName} for this class" . ($cap > 1 ? " (double-period limit is {$cap})" : ' (once a day unless marked as a double period)') . '.',
            ]];
        }

        return [];
    }
```

### `app/Services/Timetable/TimetableAutoFixService.php` (complete — 482 lines)

Class purpose: applies Auto-Fix relocations. `applyBlockerRelocation()` is the original single-hop case; `previewChainFix()`/`applyChainFix()` generalize to N-hop chains (Commit `6f7a4a0`'s multi-blocker fix lives in `discoverChain()`).

#### `__construct(?TimetableConflictResolver $resolver = null, ?TimetableSuggestionService $suggestions = null)` — public

```php
    public function __construct(?TimetableConflictResolver $resolver = null, ?TimetableSuggestionService $suggestions = null)
    {
        $this->resolver = $resolver ?? new TimetableConflictResolver();
        $this->suggestions = $suggestions ?? new TimetableSuggestionService($this->resolver);
    }
```

#### `applyBlockerRelocation(array $newPlacement, int $blockingSlotId, int $blockerNewBellTimingId): array` — public
Single-hop fix: moves one named blocker to a named new period, then places the new lesson into the freed period. Locks the blocker row, re-validates both halves against live data inside the transaction, logs via `activity()`.

```php
    public function applyBlockerRelocation(array $newPlacement, int $blockingSlotId, int $blockerNewBellTimingId): array
    {
        try {
            return DB::transaction(function () use ($newPlacement, $blockingSlotId, $blockerNewBellTimingId) {
                $blocker = TimetableSlot::lockForUpdate()->find($blockingSlotId);
                if (!$blocker) {
                    return ['applied' => false, 'message' => 'The lesson this fix was supposed to move no longer exists -- someone may have already changed it.'];
                }

                if ($blocker->is_locked) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- the lesson it planned to move has since been locked.'];
                }

                $blockerAtNewPeriod = [
                    'school_class_id' => $blocker->school_class_id,
                    'section_id' => $blocker->section_id,
                    'teacher_id' => $blocker->teacher_id,
                    'co_teacher_id' => $blocker->co_teacher_id,
                    'subject_id' => $blocker->subject_id,
                    'room_number' => $blocker->room_number,
                    'status' => $blocker->status,
                    'academic_year' => $blocker->academic_year,
                    'bell_timing_id' => $blockerNewBellTimingId,
                    'ignore_slot_id' => $blocker->id,
                ];

                $blockerCheck = $this->resolver->check($blockerAtNewPeriod);
                if ($blockerCheck['conflict']) {
                    return ['applied' => false, 'message' => "This fix is no longer valid -- moving the other lesson there would now conflict: {$blockerCheck['message']}"];
                }

                // Simulates "as if the blocker had already moved": the new
                // lesson's own check must ignore the blocker's CURRENT row,
                // since that's precisely the occupant this fix is about to
                // relocate.
                $newPlacementCheck = $this->resolver->check(array_merge($newPlacement, ['ignore_slot_id' => $blocker->id]));
                if ($newPlacementCheck['conflict']) {
                    return ['applied' => false, 'message' => "This fix is no longer valid -- your lesson would still conflict: {$newPlacementCheck['message']}"];
                }

                $originalBellTimingId = $blocker->bell_timing_id;

                $blocker->update(['bell_timing_id' => $blockerNewBellTimingId]);

                TimetableSlot::updateOrCreate(
                    [
                        'school_class_id' => $newPlacement['school_class_id'],
                        'section_id' => $newPlacement['section_id'] ?? null,
                        'bell_timing_id' => $newPlacement['bell_timing_id'],
                        'status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED,
                    ],
                    array_merge($newPlacement, ['status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED])
                );

                activity()->causedBy(Auth::user())->performedOn($blocker)
                    ->withProperties([
                        'moved_from_bell_timing_id' => $originalBellTimingId,
                        'moved_to_bell_timing_id' => $blockerNewBellTimingId,
                        'freed_for' => $newPlacement,
                    ])
                    ->log('timetable_autofix_applied');

                return ['applied' => true, 'message' => 'Fix applied -- the other lesson was moved and your lesson is now scheduled.'];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Driver-agnostic (MySQL/SQLite/Postgres all throw this same
            // subclass in modern Laravel), unlike store()'s older
            // errorInfo[1]===1062 check which is MySQL-specific.
            return ['applied' => false, 'message' => 'This fix is no longer valid -- something else was scheduled in the moment this was being applied.'];
        }
    }
```

#### `previewChainFix(array $newPlacement, ?int $maxDepth = null): array` — public
Read-only. Delegates the search to `discoverChain()` and formats the result for the UI.

```php
    public function previewChainFix(array $newPlacement, ?int $maxDepth = null): array
    {
        $maxDepth = $maxDepth ?? (int) config('timetable.autofix.max_chain_depth', 3);
        $budget = (int) config('timetable.autofix.search_budget', 150);

        $rootBellTimingId = (int) ($newPlacement['bell_timing_id'] ?? 0);
        if (!$rootBellTimingId) {
            return ['ok' => false, 'message' => 'No target period given.', 'steps' => [], 'final_placement' => null];
        }

        $movedSlotIds = [];
        $claimedBellTimingIds = [$rootBellTimingId];

        $steps = $this->discoverChain($newPlacement, $maxDepth, $movedSlotIds, $claimedBellTimingIds, $budget);

        if ($steps === null) {
            return [
                'ok' => false,
                'message' => $budget <= 0
                    ? 'Could not find a fix within the search budget -- the timetable may be too tightly packed, or this needs a manual move.'
                    : "Could not find a chain of moves that resolves this conflict within {$maxDepth} step(s).",
                'steps' => [],
                'final_placement' => null,
            ];
        }

        return [
            'ok' => true,
            'message' => empty($steps)
                ? 'No conflict -- this can be placed directly.'
                : 'Found a fix: ' . count($steps) . ' lesson(s) need to move first.',
            'steps' => array_map(fn ($step) => $this->describeStep($step), $steps),
            'final_placement' => $this->describePlacement($newPlacement),
        ];
    }
```

#### `applyChainFix(array $newPlacement, array $steps): array` — public
Re-validates and applies a chain previously returned by `previewChainFix()` — never trusts the preview. Locks every touched row (ascending id order, deadlock-free), re-checks every step against live data ignoring every other in-chain row, then writes all moves plus the new placement in one transaction.

```php
    public function applyChainFix(array $newPlacement, array $steps): array
    {
        $rootBellTimingId = (int) ($newPlacement['bell_timing_id'] ?? 0);
        if (!$rootBellTimingId) {
            return ['applied' => false, 'message' => 'No target period given.'];
        }

        $slotIds = array_map(fn ($step) => (int) $step['slot_id'], $steps);
        if (count($slotIds) !== count(array_unique($slotIds))) {
            return ['applied' => false, 'message' => 'This fix is no longer valid -- it referenced the same lesson twice.'];
        }

        $allTargets = array_merge(array_map(fn ($step) => (int) $step['to_bell_timing_id'], $steps), [$rootBellTimingId]);
        if (count($allTargets) !== count(array_unique($allTargets))) {
            return ['applied' => false, 'message' => 'This fix is no longer valid -- two lessons in the chain would land on the same period.'];
        }

        return DB::transaction(function () use ($newPlacement, $steps, $slotIds, $rootBellTimingId) {
            // Lock every row this chain touches, in a fixed ascending-id
            // order, so two concurrent Auto-Fix runs sharing a row can
            // never deadlock against each other -- same reasoning as
            // TimetableSwapService::apply().
            sort($slotIds);
            $slots = TimetableSlot::whereIn('id', $slotIds)->lockForUpdate()->get()->keyBy('id');

            if ($slots->count() !== count($steps)) {
                return ['applied' => false, 'message' => 'This fix is no longer valid -- one or more of the lessons it planned to move no longer exist.'];
            }

            foreach ($slots as $slot) {
                if ($slot->combined_class_group_id) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- a combined-group lesson is now in the way and can\'t be auto-moved.'];
                }
                if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- one of the lessons it planned to move is now archived history.'];
                }
                if ($slot->is_locked) {
                    return ['applied' => false, 'message' => 'This fix is no longer valid -- one of the lessons it planned to move has since been locked.'];
                }
            }

            // Re-validate every step against LIVE data, each ignoring
            // every OTHER slot in the chain (they're all moving in this
            // same transaction, so none of their current positions should
            // count as a real collision against each other).
            foreach ($steps as $step) {
                $slot = $slots->get((int) $step['slot_id']);

                $trial = [
                    'school_class_id' => $slot->school_class_id,
                    'section_id' => $slot->section_id,
                    'teacher_id' => $slot->teacher_id,
                    'co_teacher_id' => $slot->co_teacher_id,
                    'subject_id' => $slot->subject_id,
                    'room_number' => $slot->room_number,
                    'status' => $slot->status,
                    'academic_year' => $slot->academic_year,
                    'bell_timing_id' => (int) $step['to_bell_timing_id'],
                    'ignore_slot_id' => $slotIds,
                ];

                $check = $this->resolver->check($trial);
                if ($check['conflict']) {
                    return ['applied' => false, 'message' => "This fix is no longer valid -- moving one of the lessons would now conflict: {$check['message']}"];
                }
            }

            $newPlacementCheck = $this->resolver->check(array_merge($newPlacement, ['ignore_slot_id' => $slotIds]));
            if ($newPlacementCheck['conflict']) {
                return ['applied' => false, 'message' => "This fix is no longer valid -- your lesson would still conflict: {$newPlacementCheck['message']}"];
            }

            $before = [];
            foreach ($steps as $step) {
                $slot = $slots->get((int) $step['slot_id']);
                $before[$slot->id] = $slot->bell_timing_id;
                $slot->update(['bell_timing_id' => (int) $step['to_bell_timing_id']]);
            }

            $newSlot = TimetableSlot::updateOrCreate(
                [
                    'school_class_id' => $newPlacement['school_class_id'],
                    'section_id' => $newPlacement['section_id'] ?? null,
                    'bell_timing_id' => $rootBellTimingId,
                    'status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED,
                ],
                array_merge($newPlacement, ['status' => $newPlacement['status'] ?? TimetableSlot::STATUS_PUBLISHED])
            );

            foreach ($steps as $step) {
                $slot = $slots->get((int) $step['slot_id']);
                activity()->causedBy(Auth::user())->performedOn($slot)
                    ->withProperties([
                        'moved_from_bell_timing_id' => $before[$slot->id],
                        'moved_to_bell_timing_id' => $step['to_bell_timing_id'],
                        'chain_length' => count($steps),
                        'freed_for' => $newPlacement,
                    ])
                    ->log('timetable_autofix_chain_applied');
            }

            activity()->causedBy(Auth::user())->performedOn($newSlot)
                ->withProperties(['chain_length' => count($steps), 'moved_slot_ids' => $slotIds])
                ->log('timetable_autofix_chain_placed');

            return [
                'applied' => true,
                'message' => empty($steps)
                    ? 'Lesson scheduled -- no other lessons needed to move.'
                    : 'Fix applied -- ' . count($steps) . ' lesson(s) moved and your lesson is now scheduled.',
            ];
        });
    }
```

#### `discoverChain(array $placement, int $depth, array &$movedSlotIds, array &$claimedBellTimingIds, int &$budget): ?array` — private
Recursive, budgeted, greedy search. This is where Commit `6f7a4a0` fixed the multi-blocker bug: the outer `while(true)` loop re-checks `$placement` after each resolved blocker instead of returning after the first.

```php
    private function discoverChain(array $placement, int $depth, array &$movedSlotIds, array &$claimedBellTimingIds, int &$budget): ?array
    {
        // Room safety pilot-completion pass: $placement can be blocked by
        // more than one UNRELATED occupant at once (e.g. one lesson blocks
        // it on teacher, a completely different lesson blocks it on room) --
        // resolving the first blocker found is not enough on its own, so
        // this loops, re-checking $placement after each successful
        // relocation, until it's genuinely clean or nothing more can be
        // moved. Previously this returned as soon as ONE blocker was
        // resolved, regardless of whether $placement was actually legal
        // yet -- previewChainFix() could report "found a fix" while a
        // second, untouched blocker (of a different conflict type) still
        // occupied the exact target period. applyChainFix() itself already
        // independently re-validates the complete placement before writing
        // anything (line ~274), so this never actually corrupted data --
        // only the preview was misleadingly optimistic.
        $allSteps = [];

        while (true) {
            if ($budget <= 0) {
                return null;
            }
            $budget--;

            $check = $this->resolver->check(array_merge($placement, ['ignore_slot_id' => $movedSlotIds]));
            if (!$check['conflict']) {
                return $allSteps;
            }

            if ($depth <= 0) {
                return null;
            }

            $blockerId = null;
            foreach ($check['conflicts'] as $conflict) {
                if (!empty($conflict['blocking_slot_id']) && !in_array((int) $conflict['blocking_slot_id'], $movedSlotIds, true)) {
                    $blockerId = (int) $conflict['blocking_slot_id'];
                    break;
                }
            }

            if (!$blockerId) {
                return null; // constraint-only violation (load limit, subject-per-day, period type) -- no row to move.
            }

            $blocker = TimetableSlot::find($blockerId);
            // Phase 5 (Locked Lessons): a locked slot is never a candidate to
            // relocate -- the search simply treats it as an immovable wall and
            // reports "no fix found" if nothing else can be moved instead,
            // exactly like a combined-group or archived row already does.
            if (!$blocker || $blocker->combined_class_group_id || $blocker->status === TimetableSlot::STATUS_ARCHIVED || $blocker->is_locked) {
                return null;
            }

            $movedSlotIds[] = $blockerId;

            $blockerPlacement = [
                'school_class_id' => $blocker->school_class_id,
                'section_id' => $blocker->section_id,
                'teacher_id' => $blocker->teacher_id,
                'co_teacher_id' => $blocker->co_teacher_id,
                'subject_id' => $blocker->subject_id,
                'room_number' => $blocker->room_number,
                'status' => $blocker->status,
                'academic_year' => $blocker->academic_year,
            ];

            $resolvedThisBlocker = false;

            foreach ($this->suggestions->candidatePeriodsFor($blockerPlacement) as $candidate) {
                if ($budget <= 0) {
                    break;
                }
                $candidateId = (int) $candidate->id;
                if ($candidateId === (int) $blocker->bell_timing_id || in_array($candidateId, $claimedBellTimingIds, true)) {
                    continue;
                }

                $claimedBellTimingIds[] = $candidateId;

                $trial = array_merge($blockerPlacement, ['bell_timing_id' => $candidateId]);
                $subMoves = $this->discoverChain($trial, $depth - 1, $movedSlotIds, $claimedBellTimingIds, $budget);

                if ($subMoves !== null) {
                    $subMoves[] = ['slot_id' => $blockerId, 'to_bell_timing_id' => $candidateId, 'from_bell_timing_id' => (int) $blocker->bell_timing_id];
                    $allSteps = array_merge($allSteps, $subMoves);
                    $resolvedThisBlocker = true;
                    break;
                }

                array_pop($claimedBellTimingIds); // backtrack: this candidate didn't pan out.
            }

            if (!$resolvedThisBlocker) {
                // No candidate worked for this blocker within budget/depth -- back out of committing to move it.
                array_splice($movedSlotIds, array_search($blockerId, $movedSlotIds, true), 1);

                return null;
            }

            // This blocker is resolved -- loop back and re-check $placement:
            // it may now be clean, or another, still-unrelated blocker may
            // remain (e.g. the room conflict in the scenario above).
        }
    }
```

#### `describeStep(array $step): array` — private
Formats one chain step for the UI (loads names via the relevant relations).

```php
    private function describeStep(array $step): array
    {
        $slot = TimetableSlot::with(['subject', 'teacher', 'schoolClass', 'section', 'bellTiming'])->find($step['slot_id']);
        $toTiming = BellTiming::find($step['to_bell_timing_id']);

        return [
            'slot_id' => $step['slot_id'],
            'to_bell_timing_id' => $step['to_bell_timing_id'],
            'class' => $slot?->schoolClass?->name,
            'section' => $slot?->section?->name,
            'subject' => $slot?->subject?->name,
            'teacher' => $slot?->teacher?->name,
            'description' => sprintf(
                'Move %s (%s) from %s %s to %s %s',
                $slot?->subject?->name ?? 'this lesson',
                $slot?->teacher?->name ?? '',
                $slot?->bellTiming?->day_of_week ?? '?',
                $slot?->bellTiming?->period_name ?? '?',
                $toTiming?->day_of_week ?? '?',
                $toTiming?->period_name ?? '?'
            ),
        ];
    }
```

#### `describePlacement(array $placement): array` — private
Formats the final new-lesson placement for the UI.

```php
    private function describePlacement(array $placement): array
    {
        $timing = BellTiming::find($placement['bell_timing_id'] ?? null);
        $subject = Subject::find($placement['subject_id'] ?? null);
        $teacher = Teacher::find($placement['teacher_id'] ?? null);

        return [
            'bell_timing_id' => $placement['bell_timing_id'] ?? null,
            'day' => $timing?->day_of_week,
            'period' => $timing?->period_name,
            'subject' => $subject?->name,
            'teacher' => $teacher?->name,
        ];
    }
```

### `app/Services/Timetable/GeneratorService.php` (complete — 1528 lines, part 1 of 4)

Class purpose: T4a backtracking constraint solver. Pure logic — writes nothing to the database. Most-constrained-first (MRV) placement with bounded local backtracking, a wall-clock time budget, hard constraints (teacher/class freedom, availability, load caps, subject-per-day, class-teacher period-1 reservation, team teaching, combined groups) and soft scoring (morning preference, day-load spreading, gap minimization).

Class constants and properties:

```php
class GeneratorService
{
    /** T6 item 2: each subject's periods spread across different days (the original, still the default). */
    public const STYLE_ROTATING = 'rotating';

    /** T6 item 2: one day's pattern is solved once and repeated identically on every running day. */
    public const STYLE_FIXED_DAILY = 'fixed_daily';

    private int $backtrackBudgetPerLesson;

    private int $timeBudgetSeconds;

    private float $deadline;

    /** @var array<int,array{day_order:int,order_index:int,class_section:?string}> bell_timing_id => meta */
    private array $timingMeta = [];

    /** @var array<int,array<int,int>> day_order => sorted [bell_timing_id, ...] (teaching, active only) */
    private array $timingsByDay = [];

    /** @var array<string,bool> "teacherId|bellTimingId" */
    private array $teacherBusy = [];

    /** @var array<string,bool> "teacherId|bellTimingId" blocked by TeacherAvailability */
    private array $teacherBlocked = [];

    /** @var array<string,bool> "classId|sectionId|bellTimingId" */
    private array $classBusy = [];

    /** @var array<string,int> "teacherId|dayOrder" => periods placed that day */
    private array $teacherDayCount = [];

    /** @var array<int,int> teacherId => periods placed this week */
    private array $teacherWeekCount = [];

    /** @var array<string,int> "classId|sectionId|subjectId|dayOrder" => periods placed that day */
    private array $classSubjectDay = [];

    /** @var array<string,int> "classId|sectionId|dayOrder" => periods placed that day (soft: spread) */
    private array $classDayLoad = [];

    /** @var array<int,array{max_per_day:int,max_per_week:int}> teacherId => limits */
    private array $teacherLimits = [];

    /** @var array<int,array> placementId => committed lesson placement */
    private array $committed = [];

    /** @var array<string,int> "teacherId|bellTimingId" => placementId, single-period solo placements only */
    private array $placementByTeacherSlot = [];

    /** @var array<string,int> "classId|sectionId|bellTimingId" => placementId, single-period solo placements only */
    private array $placementByClassSlot = [];

    /** @var array<string,?int> className => last_teaching_period (order_index ceiling), null = uncapped. T6 item 3. */
    private array $classLastPeriod = [];

    private array $unplaced = [];

    private int $nextLessonId = 1;

    private int $nextPlacementId = 1;
```

#### `generate(?string $academicYear, Collection $schoolClasses, ?int $academicSessionId = null, ?string $style = null): array` — public
Main entry point: resets solver state, loads timing/availability/teacher-limit data, guards against stale draft rows outside a subset-generation run (Commit `7669e95`'s fix lives here), reserves locked slots and class-teacher period-1 slots, builds the lesson list, then runs the MRV placement loop until every lesson is placed, unplaced, or the time budget expires.

```php
    public function generate(?string $academicYear, Collection $schoolClasses, ?int $academicSessionId = null, ?string $style = null): array
    {
        $style = $style ?? self::STYLE_ROTATING;
        $this->resetState();

        $this->timeBudgetSeconds = (int) config('timetable.generator.time_budget_seconds', 60);
        $this->backtrackBudgetPerLesson = (int) config('timetable.generator.backtrack_budget_per_lesson', 25);
        $startedAt = microtime(true);
        $this->deadline = $startedAt + $this->timeBudgetSeconds;

        $classIds = $schoolClasses->pluck('id');
        $classesById = $schoolClasses->keyBy('id');
        $this->classLastPeriod = $schoolClasses->pluck('last_teaching_period', 'name')->all();

        $timings = BellTiming::query()
            ->where('is_active', true)
            ->teachingType()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $this->loadTimingMeta($timings);

        $this->teacherBlocked = TeacherAvailability::where('is_available', false)
            ->whereIn('bell_timing_id', $timings->pluck('id'))
            ->get()
            ->mapWithKeys(fn ($a) => ["{$a->teacher_id}|{$a->bell_timing_id}" => true])
            ->all();

        // Subset-generation safety: GenerateTimetableJob clears existing
        // DRAFT rows for exactly these classes and this academic year right
        // before inserting the new placements -- but it can never know
        // that here, before a single lesson has been placed. Any draft row
        // this run's own solve doesn't already know about (a teacher's
        // commitment in a class outside this subset, or a stale row the
        // upcoming cleanup's exact-match academic_year filter will miss)
        // still occupies its bell timing as far as the unique DB
        // constraints (teacher_bell_status, class_section_bell_status) are
        // concerned. Without this, the solver can happily plan a placement
        // that looks perfectly legal in memory and then fails at INSERT
        // time with a raw duplicate-key exception -- exactly the staging
        // failure this was found from (teacher 76, bell_timing 2: an
        // orphaned draft row for a class outside the requested subset,
        // never cleared, silently invisible to every check the solver had
        // until now).
        $staleDraftQuery = TimetableSlot::query()
            ->where('status', TimetableSlot::STATUS_DRAFT)
            ->whereIn('bell_timing_id', $timings->pluck('id'));
        if ($academicYear !== null) {
            // Mirror the exact complement of the cleanup delete() below:
            // a row survives it (and so must still be treated as busy)
            // when it belongs to a DIFFERENT class than this run's own,
            // OR its academic_year doesn't exactly match this run's --
            // including NULL, which a plain `<>` comparison would never
            // catch under SQL's null-handling.
            $staleDraftQuery->where(function ($q) use ($classIds, $academicYear) {
                $q->whereNotIn('school_class_id', $classIds->all())
                    ->orWhereNull('academic_year')
                    ->orWhere('academic_year', '!=', $academicYear);
            });
        }
        // When $academicYear itself is null, the cleanup's own
        // `where('academic_year', $academicYear)` matches no row at all
        // (SQL NULL semantics) -- nothing is ever cleared, so every draft
        // row at these bell timings survives and must be considered here,
        // with no extra filter needed.
        foreach ($staleDraftQuery->get(['school_class_id', 'section_id', 'teacher_id', 'co_teacher_id', 'bell_timing_id', 'combined_class_group_id']) as $stale) {
            if ($stale->combined_class_group_id !== null) {
                continue; // structurally exempt from teacher_active_key uniqueness (see the migration); not a real solo-teacher conflict.
            }

            $this->teacherBlocked["{$stale->teacher_id}|{$stale->bell_timing_id}"] = true;
            if ($stale->co_teacher_id !== null) {
                $this->teacherBlocked["{$stale->co_teacher_id}|{$stale->bell_timing_id}"] = true;
            }

            // Class-side collisions can only ever matter for a class this
            // run is actually placing lessons into -- a stale row on an
            // unrelated class can never class-collide, since nothing here
            // will ever target that class/section.
            if (in_array($stale->school_class_id, $classIds->all(), true)) {
                $this->classBusy["{$stale->school_class_id}|{$stale->section_id}|{$stale->bell_timing_id}"] = true;
            }
        }

        // Teacher limits must be loaded before ANY commits, including the
        // class-teacher reservations below -- scoped by class, not by the
        // (not yet built) lesson list, since reservation happens first.
        $this->loadTeacherLimitsForClasses($classIds, $academicSessionId);

        $excludedAssignmentIds = [];
        $warnings = [];

        // Phase 5 (Locked Lessons): locked PUBLISHED slots are existing,
        // committed reality -- reserved first, before anything else claims
        // a period, exactly like the class-teacher period-1 reservation
        // below claims its own periods first. If a lock happens to collide
        // with what would otherwise be a period-1 reservation or a
        // fixed-daily slot, isHardLegal() catches it the same way it
        // catches every other reservation conflict, producing a warning
        // instead of a double-booking.
        $lockedCountsByAssignmentId = [];
        $forcedLessonAttempts = $this->reserveLockedSlots($classIds, $classesById, $academicYear, $lockedCountsByAssignmentId, $warnings);

        $forcedLessonAttempts += $this->reserveClassTeacherPeriod1($classIds, $classesById, $academicYear, $excludedAssignmentIds, $warnings);

        if ($style === self::STYLE_FIXED_DAILY) {
            $forcedLessonAttempts += $this->reserveFixedDailyLessons($classIds, $classesById, $academicYear, $excludedAssignmentIds, $warnings);
        }

        $lessons = $this->buildLessons($academicYear, $academicSessionId, $classIds, $classesById, $excludedAssignmentIds, $lockedCountsByAssignmentId);

        $pending = collect($lessons)->keyBy('lesson_id');
        $dirty = $pending->keys()->all();
        $domainCountCache = [];

        while ($pending->isNotEmpty()) {
            if (microtime(true) > $this->deadline) {
                foreach ($pending as $lesson) {
                    $this->unplaced[] = $this->unplacedResult(
                        $lesson,
                        "Could not place {$lesson['subject_name']} for {$lesson['label']}: the {$this->timeBudgetSeconds}-second generation time budget ran out before this lesson could be scheduled."
                    );
                }
                break;
            }

            foreach ($dirty as $id) {
                if ($pending->has($id)) {
                    $domainCountCache[$id] = count($this->legalSlots($pending[$id]));
                }
            }
            $dirty = [];

            $lessonId = $this->pickMostConstrained($pending, $domainCountCache);
            $lesson = $pending->pull($lessonId);
            unset($domainCountCache[$lessonId]);

            $legal = $this->legalSlots($lesson);
            if (empty($legal)) {
                $legal = $this->attemptBacktrack($lesson);
            }

            if (empty($legal)) {
                $this->unplaced[] = $this->unplacedResult($lesson);
                continue;
            }

            $scored = array_map(fn ($slot) => ['slot' => $slot, 'score' => $this->softScore($lesson, $slot)], $legal);
            usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

            $this->commit($lesson, $scored[0]['slot']);

            // T6 item 4: a co-teacher (either side) makes a pending lesson's
            // cached domain count stale exactly like the primary teacher
            // does -- checked in both directions so a lesson pending on the
            // co-teacher side is invalidated too.
            $affectedTeacherIds = array_filter([$lesson['teacher_id'], $lesson['co_teacher_id'] ?? null], fn ($id) => $id !== null);
            foreach ($pending as $id => $other) {
                $otherTeacherIds = array_filter([$other['teacher_id'], $other['co_teacher_id'] ?? null], fn ($id) => $id !== null);
                if (array_intersect($otherTeacherIds, $affectedTeacherIds) || array_intersect($other['class_ids'], $lesson['class_ids'])) {
                    $dirty[] = $id;
                }
            }
        }

        $placements = [];
        foreach ($this->committed as $p) {
            foreach ($p['class_ids'] as $idx => $classId) {
                $placements[] = [
                    'school_class_id' => $classId,
                    'section_id' => $p['section_ids'][$idx] ?? null,
                    'subject_id' => $p['subject_id'],
                    'teacher_id' => $p['teacher_id'],
                    'co_teacher_id' => $p['co_teacher_id'] ?? null,
                    'bell_timing_ids' => $p['bell_timing_ids'],
                    'combined_class_group_id' => $p['combined_class_group_id'],
                    'is_locked' => $p['is_locked'],
                    'room_number' => $p['room_number'],
                ];
            }
        }

        return [
            'placements' => $placements,
            'unplaced' => $this->unplaced,
            'warnings' => $warnings,
            'stats' => [
                'style' => $style,
                'total_lessons' => count($lessons) + $forcedLessonAttempts,
                'placed_lessons' => count($this->committed),
                'placed_rows' => count($placements),
                'unplaced_lessons' => count($this->unplaced),
                'warnings_count' => count($warnings),
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
            ],
        ];
    }
```

### `app/Services/Timetable/GeneratorService.php` (part 2 of 4)

#### `resetState(): void` — private
Clears every solver-state property between `generate()` calls.

```php
    private function resetState(): void
    {
        $this->timingMeta = [];
        $this->timingsByDay = [];
        $this->teacherBusy = [];
        $this->teacherBlocked = [];
        $this->classBusy = [];
        $this->teacherDayCount = [];
        $this->teacherWeekCount = [];
        $this->classSubjectDay = [];
        $this->classDayLoad = [];
        $this->teacherLimits = [];
        $this->committed = [];
        $this->placementByTeacherSlot = [];
        $this->placementByClassSlot = [];
        $this->classLastPeriod = [];
        $this->unplaced = [];
        $this->nextLessonId = 1;
        $this->nextPlacementId = 1;
    }
```

#### `loadTimingMeta(Collection $timings): void` — private
Builds `timingMeta` and the day-sorted `timingsByDay` index from the active teaching bell timings for this run.

```php
    private function loadTimingMeta(Collection $timings): void
    {
        $byDay = [];
        foreach ($timings as $t) {
            $dayOrder = $t->day_order;
            $this->timingMeta[$t->id] = [
                'day_order' => $dayOrder,
                'order_index' => (int) $t->order_index,
                'class_section' => $t->class_section,
            ];
            $byDay[$dayOrder][] = $t->id;
        }
        foreach ($byDay as $day => $ids) {
            usort($ids, fn ($a, $b) => $this->timingMeta[$a]['order_index'] <=> $this->timingMeta[$b]['order_index']);
            $byDay[$day] = $ids;
        }
        $this->timingsByDay = $byDay;
    }
```

#### `loadTeacherLimitsForClasses(Collection $classIds, ?int $academicSessionId): void` — private
Scoped by class rather than derived from the lesson list, since the class-teacher reservations (which need limits loaded first) commit before the lesson list is even built. Includes co-teachers (T6 item 4) and combined-group teachers.

```php
    private function loadTeacherLimitsForClasses(Collection $classIds, ?int $academicSessionId): void
    {
        $teacherIds = TeacherClassSubjectAssignment::whereIn('class_id', $classIds)
            ->whereNotNull('periods_per_week')
            ->pluck('teacher_id')
            ->merge(
                // T6 item 4: a co-teacher's own limits must be loaded too --
                // isHardLegal() checks their day/week caps exactly like the
                // primary teacher's.
                TeacherClassSubjectAssignment::whereIn('class_id', $classIds)
                    ->whereNotNull('periods_per_week')
                    ->whereNotNull('co_teacher_id')
                    ->pluck('co_teacher_id')
            )
            ->merge(
                CombinedClassGroup::whereNotNull('periods_per_week')
                    ->whereNotNull('teacher_id')
                    ->when($academicSessionId, fn ($q) => $q->where('academic_session_id', $academicSessionId))
                    ->pluck('teacher_id')
            )
            ->unique();

        $this->teacherLimits = Teacher::whereIn('id', $teacherIds)->get()
            ->mapWithKeys(fn ($t) => [$t->id => [
                'max_per_day' => (int) $t->max_periods_per_day,
                'max_per_week' => (int) $t->max_periods_per_week,
            ]])
            ->all();
    }
```

#### `reserveLockedSlots(Collection $classIds, Collection $classesById, ?string $academicYear, array &$lockedCountsByAssignmentId, array &$warnings): int` — private
Phase 5 (Locked Lessons): carries every locked, currently-PUBLISHED slot into the fresh draft unchanged, committed directly (bypassing MRV) with `protected` treatment so nothing later can bump it.

```php
    private function reserveLockedSlots(Collection $classIds, Collection $classesById, ?string $academicYear, array &$lockedCountsByAssignmentId, array &$warnings): int
    {
        $lockedSlots = TimetableSlot::with(['subject', 'teacher'])
            ->published()
            ->locked()
            ->whereIn('school_class_id', $classIds)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $attempts = 0;

        foreach ($lockedSlots as $slot) {
            $class = $classesById->get($slot->school_class_id);
            if (!$class) {
                continue;
            }

            if (!isset($this->timingMeta[$slot->bell_timing_id])) {
                $warnings[] = "Locked lesson for {$class->name} (slot #{$slot->id}) could not be carried into this draft -- its period is no longer active.";
                continue;
            }

            $attempts++;

            $lesson = [
                'lesson_id' => $this->nextLessonId++,
                'type' => 'solo',
                'teacher_id' => $slot->teacher_id,
                'co_teacher_id' => $slot->co_teacher_id,
                'subject_id' => $slot->subject_id,
                'class_ids' => [$slot->school_class_id],
                'section_ids' => [$slot->section_id],
                'class_name' => $class->name,
                'room_number' => $slot->room_number,
                'require_consecutive' => false,
                'source' => ['locked' => true],
            ];
            $slotChoice = ['bell_timing_ids' => [$slot->bell_timing_id]];

            if ($this->isHardLegal($lesson, $slotChoice)) {
                $this->commit($lesson, $slotChoice);

                $assignmentId = TeacherClassSubjectAssignment::where('teacher_id', $slot->teacher_id)
                    ->where('class_id', $slot->school_class_id)
                    ->where('section_id', $slot->section_id)
                    ->where('subject_id', $slot->subject_id)
                    ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
                    ->value('id');

                if ($assignmentId) {
                    $lockedCountsByAssignmentId[$assignmentId] = ($lockedCountsByAssignmentId[$assignmentId] ?? 0) + 1;
                }
            } else {
                $teacherName = optional($slot->teacher)->name ?? 'This teacher';
                $warnings[] = "Locked lesson for {$class->name} (slot #{$slot->id}, {$teacherName}) could not be carried into this draft -- it now conflicts with an already-reserved period. Unlock it or resolve the conflict manually.";
            }
        }

        return $attempts;
    }
```

#### `reserveClassTeacherPeriod1(Collection $classIds, Collection $classesById, ?string $academicYear, array &$excludedAssignmentIds, array &$warnings): int` — private
T6 item 1: reserves period 1 of every teaching day for each class's designated class-teacher, committed before the normal solve as a permanent fact. Also reports the legacy-table gap case (a class-teacher with no subject assignment).

```php
    private function reserveClassTeacherPeriod1(Collection $classIds, Collection $classesById, ?string $academicYear, array &$excludedAssignmentIds, array &$warnings): int
    {
        $classTeacherAssignments = TeacherClassSubjectAssignment::with('subject')
            ->whereIn('class_id', $classIds)
            ->where('is_class_teacher', true)
            ->whereNotNull('periods_per_week')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $attempts = 0;

        foreach ($classTeacherAssignments as $assignment) {
            $class = $classesById->get($assignment->class_id);
            $subject = $assignment->subject;
            if (! $class || ! $subject) {
                continue;
            }

            $excludedAssignmentIds[] = $assignment->id;

            $section = $assignment->section_id ? Section::find($assignment->section_id) : null;
            $label = $section ? "{$class->name}{$section->name}" : $class->name;

            $lesson = [
                'lesson_id' => $this->nextLessonId++,
                'type' => 'solo',
                'teacher_id' => $assignment->teacher_id,
                'subject_id' => $assignment->subject_id,
                'subject_name' => $subject->name,
                'prefer_morning' => (bool) $subject->prefer_morning,
                'require_consecutive' => false,
                'periods_needed' => 1,
                'periods_per_week' => 1,
                'class_ids' => [$assignment->class_id],
                'section_ids' => [$assignment->section_id],
                'class_name' => $class->name,
                'label' => $label,
                'source' => ['assignment_id' => $assignment->id, 'class_teacher_period1' => true],
            ];

            $daysPlaced = 0;
            foreach ($this->timingsByDay as $dayIds) {
                $periodOneSlot = $this->firstPeriodSlotForLesson($lesson, $dayIds);
                if ($periodOneSlot === null) {
                    continue; // this class has no teaching period at all on this day
                }

                $attempts++;

                if ($this->isHardLegal($lesson, $periodOneSlot)) {
                    $this->commit($lesson, $periodOneSlot);
                    $daysPlaced++;
                } else {
                    $teacherName = optional(Teacher::find($assignment->teacher_id))->name ?? "Teacher #{$assignment->teacher_id}";
                    $warnings[] = "Could not reserve period 1 for {$label}: {$teacherName} (class teacher) already has a commitment at that exact period -- most likely they're class-teacher of another class with the same period 1 that day.";
                }
            }

            if ($daysPlaced === 0 && $attempts === 0) {
                $warnings[] = "Could not reserve period 1 for {$label}: {$subject->name} has no teaching day to reserve on.";
            }
        }

        // T6 item 1 (revised): a class whose designated class-teacher (the
        // legacy class_teacher_assignments table -- see ClassTeacherAssignment
        // model) has no is_class_teacher-flagged subject in this class never
        // gets period 1 forced (there's no subject to anchor it to); this is
        // reported here as a warning naming the gap, not silently skipped --
        // distinct from a class with NO designated class-teacher at all
        // (App\Services\Timetable\FeasibilityService::classTeacherReadiness()
        // handles that case, as a readiness note rather than a per-generation
        // warning).
        $classIdsWithoutSubjectAssignment = $classIds->diff($classTeacherAssignments->pluck('class_id')->unique());

        if ($classIdsWithoutSubjectAssignment->isNotEmpty()) {
            $classNames = $classIdsWithoutSubjectAssignment
                ->map(fn ($id) => optional($classesById->get($id))->name)
                ->filter();

            $legacyAssignmentsByClassName = ClassTeacherAssignment::whereIn('assigned_class', $classNames)
                ->active()
                ->get()
                ->filter(fn (ClassTeacherAssignment $a) => $a->isCurrentlyAssigned())
                ->keyBy('assigned_class');

            foreach ($classIdsWithoutSubjectAssignment as $classId) {
                $class = $classesById->get($classId);
                if (! $class) {
                    continue;
                }

                $legacyAssignment = $legacyAssignmentsByClassName->get($class->name);
                if (! $legacyAssignment) {
                    continue; // no class-teacher designated at all -- FeasibilityService's readiness note, not a generation warning.
                }

                // class_teacher_assignments.teacher_id is FK'd to users.id
                // (see the migration + ClassTeacherAssignmentAuthorizationTest),
                // NOT teachers.id -- the model's own teacher() relationship/
                // getClassTeacherName() incorrectly queries Teacher by that
                // id and would silently resolve to "Unknown Teacher" on real
                // data, so resolve via Teacher.user_id here instead.
                $teacherName = Teacher::where('user_id', $legacyAssignment->teacher_id)->value('name') ?? 'Unknown Teacher';
                $warnings[] = "Class {$class->name}: class teacher {$teacherName} has no subject assigned for this class -- period 1 was not reserved. Assign them a subject for {$class->name} if you want them to take first period.";
            }
        }

        return $attempts;
    }
```

#### `firstPeriodSlotForLesson(array $lesson, array $dayIds): ?array` — private
The lowest-order_index domain slot for a lesson within one day's own sorted, filtered id list, or null if none apply.

```php
    private function firstPeriodSlotForLesson(array $lesson, array $dayIds): ?array
    {
        $cap = $lesson['type'] === 'solo' ? ($this->classLastPeriod[$lesson['class_name']] ?? null) : null;

        $filtered = array_values(array_filter($dayIds, function ($btId) use ($lesson, $cap) {
            if ($cap !== null && $this->timingMeta[$btId]['order_index'] > $cap) {
                return false;
            }

            $cs = $this->timingMeta[$btId]['class_section'];
            if ($cs === null || $cs === '') {
                return true;
            }

            return $lesson['type'] === 'solo' && $cs === $lesson['class_name'];
        }));

        return empty($filtered) ? null : ['bell_timing_ids' => [$filtered[0]]];
    }
```

### `app/Services/Timetable/GeneratorService.php` (part 3 of 4)

#### `reserveFixedDailyLessons(Collection $classIds, Collection $classesById, ?string $academicYear, array &$excludedAssignmentIds, array &$warnings): int` — private
T6 item 2, `STYLE_FIXED_DAILY`: solves each solo assignment once per class and repeats it identically on every running day, instead of spreading across the week.

```php
    private function reserveFixedDailyLessons(Collection $classIds, Collection $classesById, ?string $academicYear, array &$excludedAssignmentIds, array &$warnings): int
    {
        $assignments = TeacherClassSubjectAssignment::with('subject')
            ->whereIn('class_id', $classIds)
            ->whereNotNull('periods_per_week')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get()
            ->reject(fn ($a) => in_array($a->id, $excludedAssignmentIds, true));

        $sectionsById = Section::whereIn('id', $assignments->pluck('section_id')->filter()->unique())->get()->keyBy('id');

        $attempts = 0;

        foreach ($assignments->groupBy('class_id') as $classId => $classAssignments) {
            $class = $classesById->get($classId);
            if (! $class) {
                continue;
            }

            $runningDays = $this->runningDaysForClass($class->name);
            if (empty($runningDays)) {
                continue;
            }

            $commonOrderIndexes = $this->commonOrderIndexesForClass($class->name, $runningDays);
            $usedOrderIndexes = [];

            foreach ($classAssignments as $assignment) {
                $subject = $assignment->subject;
                if (! $subject) {
                    continue;
                }

                $excludedAssignmentIds[] = $assignment->id;
                $attempts++;

                $periodsPerWeek = (int) $assignment->periods_per_week;
                $requireConsecutive = (bool) $assignment->require_consecutive;
                $section = $assignment->section_id ? $sectionsById->get($assignment->section_id) : null;
                $label = $section ? "{$class->name}{$section->name}" : $class->name;
                $runningDayCount = count($runningDays);

                if ($periodsPerWeek % $runningDayCount !== 0) {
                    $warnings[] = "Could not use fixed-daily style for {$subject->name} in {$label}: {$periodsPerWeek} periods/week does not divide evenly across {$runningDayCount} running days -- not fixed-daily-compatible.";

                    continue;
                }

                $periodsPerDay = intdiv($periodsPerWeek, $runningDayCount);

                if ($requireConsecutive && $periodsPerDay !== 2) {
                    $warnings[] = "Could not use fixed-daily style for {$subject->name} in {$label}: a consecutive-flagged subject needs exactly 2 periods/day in fixed-daily mode, this works out to {$periodsPerDay} -- not fixed-daily-compatible.";

                    continue;
                }
                if (! $requireConsecutive && $periodsPerDay !== 1) {
                    $warnings[] = "Could not use fixed-daily style for {$subject->name} in {$label}: a non-consecutive subject needs exactly 1 period/day in fixed-daily mode, this works out to {$periodsPerDay} -- not fixed-daily-compatible.";

                    continue;
                }

                $lessonTemplate = [
                    'type' => 'solo',
                    'teacher_id' => $assignment->teacher_id,
                    'co_teacher_id' => $assignment->co_teacher_id,
                    'subject_id' => $assignment->subject_id,
                    'subject_name' => $subject->name,
                    'prefer_morning' => (bool) $subject->prefer_morning,
                    'require_consecutive' => $requireConsecutive,
                    'periods_needed' => $periodsPerDay,
                    'periods_per_week' => $periodsPerWeek,
                    'class_ids' => [$assignment->class_id],
                    'section_ids' => [$assignment->section_id],
                    'class_name' => $class->name,
                    'label' => $label,
                ];

                $candidate = $this->findFixedDailyCandidate($lessonTemplate, $runningDays, $commonOrderIndexes, $usedOrderIndexes);

                if ($candidate === null) {
                    $warnings[] = "Could not reserve a fixed-daily slot for {$subject->name} in {$label}: no order-index pattern is free on every running day.";

                    continue;
                }

                foreach ($candidate['orderIndexes'] as $oi) {
                    $usedOrderIndexes[] = $oi;
                }

                foreach ($runningDays as $dayOrder) {
                    $lesson = $lessonTemplate;
                    $lesson['lesson_id'] = $this->nextLessonId++;
                    $this->commit($lesson, ['bell_timing_ids' => $candidate['idsByDay'][$dayOrder]]);
                }
            }
        }

        return $attempts;
    }
```

#### `runningDaysForClass(?string $className): array` — private
Day-order values where this class has at least one domain slot.

```php
    private function runningDaysForClass(?string $className): array
    {
        $days = [];
        foreach ($this->timingsByDay as $dayOrder => $ids) {
            $hasAny = array_filter($ids, fn ($btId) => $this->slotAppliesToClass($btId, $className));
            if (! empty($hasAny)) {
                $days[] = $dayOrder;
            }
        }
        sort($days);

        return $days;
    }
```

#### `commonOrderIndexesForClass(?string $className, array $runningDays): array` — private
`order_index` values present in this class's domain on every one of `$runningDays`.

```php
    private function commonOrderIndexesForClass(?string $className, array $runningDays): array
    {
        $perDaySets = [];
        foreach ($runningDays as $dayOrder) {
            $ids = array_filter($this->timingsByDay[$dayOrder] ?? [], fn ($btId) => $this->slotAppliesToClass($btId, $className));
            $perDaySets[] = array_map(fn ($btId) => $this->timingMeta[$btId]['order_index'], $ids);
        }

        if (empty($perDaySets)) {
            return [];
        }

        $common = array_shift($perDaySets);
        foreach ($perDaySets as $set) {
            $common = array_intersect($common, $set);
        }

        $common = array_values(array_unique($common));
        sort($common);

        return $common;
    }
```

#### `slotAppliesToClass(int $btId, ?string $className): bool` — private

```php
    private function slotAppliesToClass(int $btId, ?string $className): bool
    {
        $cap = $this->classLastPeriod[$className] ?? null;
        if ($cap !== null && $this->timingMeta[$btId]['order_index'] > $cap) {
            return false;
        }

        $cs = $this->timingMeta[$btId]['class_section'];

        return $cs === null || $cs === '' || $cs === $className;
    }
```

#### `findFixedDailyCandidate(array $lessonTemplate, array $runningDays, array $commonOrderIndexes, array $usedOrderIndexes): ?array` — private
Tries each not-yet-used order_index (or adjacent pair for a double period), lowest first, accepting only a candidate legal on every running day.

```php
    private function findFixedDailyCandidate(array $lessonTemplate, array $runningDays, array $commonOrderIndexes, array $usedOrderIndexes): ?array
    {
        $available = array_values(array_diff($commonOrderIndexes, $usedOrderIndexes));
        sort($available);

        $candidateGroups = [];
        if ($lessonTemplate['periods_needed'] === 1) {
            foreach ($available as $oi) {
                $candidateGroups[] = [$oi];
            }
        } else {
            for ($i = 0; $i < count($available) - 1; $i++) {
                if ($available[$i + 1] - $available[$i] === 1) {
                    $candidateGroups[] = [$available[$i], $available[$i + 1]];
                }
            }
        }

        foreach ($candidateGroups as $orderIndexes) {
            $idsByDay = [];
            $allLegal = true;

            foreach ($runningDays as $dayOrder) {
                $dayIds = [];
                foreach ($orderIndexes as $oi) {
                    $btId = $this->bellTimingIdForDayAndOrderIndex($dayOrder, $oi);
                    if ($btId === null) {
                        $allLegal = false;

                        break 2;
                    }
                    $dayIds[] = $btId;
                }

                if (! $this->isHardLegal($lessonTemplate, ['bell_timing_ids' => $dayIds])) {
                    $allLegal = false;

                    break;
                }

                $idsByDay[$dayOrder] = $dayIds;
            }

            if ($allLegal) {
                return ['orderIndexes' => $orderIndexes, 'idsByDay' => $idsByDay];
            }
        }

        return null;
    }
```

#### `bellTimingIdForDayAndOrderIndex(int $dayOrder, int $orderIndex): ?int` — private

```php
    private function bellTimingIdForDayAndOrderIndex(int $dayOrder, int $orderIndex): ?int
    {
        foreach ($this->timingsByDay[$dayOrder] ?? [] as $btId) {
            if ($this->timingMeta[$btId]['order_index'] === $orderIndex) {
                return $btId;
            }
        }

        return null;
    }
```

#### `buildLessons(?string $academicYear, ?int $academicSessionId, Collection $classIds, Collection $classesById, array $excludedAssignmentIds = [], array $lockedCountsByAssignmentId = []): array` — private
Builds the lesson list: one unit per period (require_consecutive assignments pre-paired into double-period units via `splitIntoPairs()`), plus one lesson per remaining period-per-week for every eligible combined class group.

```php
    private function buildLessons(?string $academicYear, ?int $academicSessionId, Collection $classIds, Collection $classesById, array $excludedAssignmentIds = [], array $lockedCountsByAssignmentId = []): array
    {
        $lessons = [];

        $assignments = TeacherClassSubjectAssignment::with(['subject'])
            ->whereIn('class_id', $classIds)
            ->whereNotNull('periods_per_week')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->orderBy('id')
            ->get()
            // T6 item 1: a class-teacher's own subject was already reserved
            // for period 1 every day by reserveClassTeacherPeriod1() -- that
            // IS this assignment's placement, not an addition on top of it.
            ->reject(fn ($a) => in_array($a->id, $excludedAssignmentIds, true));

        $sectionsById = Section::whereIn('id', $assignments->pluck('section_id')->filter()->unique())->get()->keyBy('id');

        foreach ($assignments as $assignment) {
            $class = $classesById->get($assignment->class_id);
            $subject = $assignment->subject;
            if (! $class || ! $subject) {
                continue;
            }

            $preferMorning = (bool) $subject->prefer_morning;
            $requireConsecutive = (bool) $assignment->require_consecutive;
            $periodsPerWeek = max(0, (int) $assignment->periods_per_week - ($lockedCountsByAssignmentId[$assignment->id] ?? 0));
            $section = $assignment->section_id ? $sectionsById->get($assignment->section_id) : null;
            $label = $section ? "{$class->name}{$section->name}" : $class->name;

            $units = $requireConsecutive ? $this->splitIntoPairs($periodsPerWeek) : array_fill(0, $periodsPerWeek, 1);

            foreach ($units as $periodsNeeded) {
                $lessons[] = [
                    'lesson_id' => $this->nextLessonId++,
                    'type' => 'solo',
                    'teacher_id' => $assignment->teacher_id,
                    'co_teacher_id' => $assignment->co_teacher_id,
                    'subject_id' => $assignment->subject_id,
                    'subject_name' => $subject->name,
                    'prefer_morning' => $preferMorning,
                    'require_consecutive' => $requireConsecutive,
                    'periods_needed' => $periodsNeeded,
                    'periods_per_week' => $periodsPerWeek,
                    'class_ids' => [$assignment->class_id],
                    'section_ids' => [$assignment->section_id],
                    'class_name' => $class->name,
                    'label' => $label,
                    'source' => ['assignment_id' => $assignment->id],
                ];
            }
        }

        $groups = CombinedClassGroup::with(['subject', 'members'])
            ->whereNotNull('periods_per_week')
            ->whereNotNull('teacher_id')
            ->when($academicSessionId, fn ($q) => $q->where('academic_session_id', $academicSessionId))
            ->get()
            ->filter(fn ($g) => $g->members->isNotEmpty() && $g->members->pluck('school_class_id')->diff($classIds)->isEmpty());

        foreach ($groups as $group) {
            $subject = $group->subject;
            if (! $subject) {
                continue;
            }

            $memberClassIds = $group->members->pluck('school_class_id')->all();
            $memberSectionIds = $group->members->pluck('section_id')->all();
            $periodsPerWeek = (int) $group->periods_per_week;

            for ($i = 0; $i < $periodsPerWeek; $i++) {
                $lessons[] = [
                    'lesson_id' => $this->nextLessonId++,
                    'type' => 'combined',
                    'teacher_id' => $group->teacher_id,
                    'subject_id' => $group->subject_id,
                    'subject_name' => $subject->name,
                    'prefer_morning' => (bool) $subject->prefer_morning,
                    'require_consecutive' => false,
                    'periods_needed' => 1,
                    'periods_per_week' => $periodsPerWeek,
                    'class_ids' => $memberClassIds,
                    'section_ids' => $memberSectionIds,
                    'class_name' => null,
                    'label' => $group->name,
                    'source' => ['group_id' => $group->id],
                ];
            }
        }

        return $lessons;
    }
```

#### `splitIntoPairs(int $periodsPerWeek): array` — private
5 → [2, 2, 1]; 6 → [2, 2, 2]; 1 → [1].

```php
    private function splitIntoPairs(int $periodsPerWeek): array
    {
        $units = array_fill(0, intdiv($periodsPerWeek, 2), 2);
        if ($periodsPerWeek % 2 === 1) {
            $units[] = 1;
        }

        return $units;
    }
```

### `app/Services/Timetable/GeneratorService.php` (part 4 of 4)

#### `domainSlotsForLesson(array $lesson): array` — private
Candidate slots ignoring current occupancy — just the domain (class_section-matched teaching bell_timings, capped by `last_teaching_period` for solo lessons, single or adjacent-pair for double periods).

```php
    private function domainSlotsForLesson(array $lesson): array
    {
        $slots = [];
        $cap = $lesson['type'] === 'solo' ? ($this->classLastPeriod[$lesson['class_name']] ?? null) : null;

        foreach ($this->timingsByDay as $ids) {
            $filtered = array_values(array_filter($ids, function ($btId) use ($lesson, $cap) {
                if ($cap !== null && $this->timingMeta[$btId]['order_index'] > $cap) {
                    return false;
                }

                $cs = $this->timingMeta[$btId]['class_section'];
                if ($cs === null || $cs === '') {
                    return true;
                }

                return $lesson['type'] === 'solo' && $cs === $lesson['class_name'];
            }));

            if ($lesson['periods_needed'] === 1) {
                foreach ($filtered as $btId) {
                    $slots[] = ['bell_timing_ids' => [$btId]];
                }

                continue;
            }

            for ($i = 0; $i < count($filtered) - 1; $i++) {
                $slots[] = ['bell_timing_ids' => [$filtered[$i], $filtered[$i + 1]]];
            }
        }

        return $slots;
    }
```

#### `legalSlots(array $lesson): array` — private
Filters `domainSlotsForLesson()` down to slots that also pass `isHardLegal()`.

```php
    private function legalSlots(array $lesson): array
    {
        return array_values(array_filter(
            $this->domainSlotsForLesson($lesson),
            fn ($slot) => $this->isHardLegal($lesson, $slot)
        ));
    }
```

#### `isHardLegal(array $lesson, array $slot): bool` — private
The in-memory hard-constraint check: teacher/co-teacher free and unblocked, class free, daily/weekly load caps (both teacher and co-teacher), subject-per-day cap.

```php
    private function isHardLegal(array $lesson, array $slot): bool
    {
        $ids = $slot['bell_timing_ids'];
        $limits = $this->teacherLimits[$lesson['teacher_id']] ?? ['max_per_day' => 7, 'max_per_week' => 36];
        // T6 item 4: a team-taught lesson's co-teacher must be exactly as
        // free as the primary teacher -- both must be free, both get
        // reserved, both are bound by their own day/week limits. A plain
        // (non-team-taught) lesson simply has co_teacher_id === null and
        // every check below is skipped, unchanged from before this item.
        $coTeacherId = $lesson['co_teacher_id'] ?? null;
        $coLimits = $coTeacherId !== null ? ($this->teacherLimits[$coTeacherId] ?? ['max_per_day' => 7, 'max_per_week' => 36]) : null;

        foreach ($ids as $btId) {
            $teacherKey = "{$lesson['teacher_id']}|{$btId}";
            if (isset($this->teacherBusy[$teacherKey]) || isset($this->teacherBlocked[$teacherKey])) {
                return false;
            }

            if ($coTeacherId !== null) {
                $coTeacherKey = "{$coTeacherId}|{$btId}";
                if (isset($this->teacherBusy[$coTeacherKey]) || isset($this->teacherBlocked[$coTeacherKey])) {
                    return false;
                }
            }

            foreach ($lesson['class_ids'] as $idx => $classId) {
                $sectionId = $lesson['section_ids'][$idx] ?? null;
                if (isset($this->classBusy["{$classId}|{$sectionId}|{$btId}"])) {
                    return false;
                }
            }
        }

        $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
        $dayKey = "{$lesson['teacher_id']}|{$dayOrder}";
        if (($this->teacherDayCount[$dayKey] ?? 0) + count($ids) > $limits['max_per_day']) {
            return false;
        }
        if (($this->teacherWeekCount[$lesson['teacher_id']] ?? 0) + count($ids) > $limits['max_per_week']) {
            return false;
        }

        if ($coTeacherId !== null) {
            $coDayKey = "{$coTeacherId}|{$dayOrder}";
            if (($this->teacherDayCount[$coDayKey] ?? 0) + count($ids) > $coLimits['max_per_day']) {
                return false;
            }
            if (($this->teacherWeekCount[$coTeacherId] ?? 0) + count($ids) > $coLimits['max_per_week']) {
                return false;
            }
        }

        $cap = $lesson['require_consecutive'] ? 2 : 1;
        foreach ($lesson['class_ids'] as $idx => $classId) {
            $sectionId = $lesson['section_ids'][$idx] ?? null;
            $key = "{$classId}|{$sectionId}|{$lesson['subject_id']}|{$dayOrder}";
            if (($this->classSubjectDay[$key] ?? 0) + count($ids) > $cap) {
                return false;
            }
        }

        return true;
    }
```

#### `commit(array $lesson, array $slot): int` — private
Marks every busy-state map, increments day/week counters, records the single-period solo indexes used by `attemptBacktrack()`, and stores the committed placement (including the `protected` flag from Commit history's T6 real-data-walkthrough bugfix).

```php
    private function commit(array $lesson, array $slot): int
    {
        $ids = $slot['bell_timing_ids'];
        $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
        $coTeacherId = $lesson['co_teacher_id'] ?? null;

        foreach ($ids as $btId) {
            $this->teacherBusy["{$lesson['teacher_id']}|{$btId}"] = true;
            if ($coTeacherId !== null) {
                $this->teacherBusy["{$coTeacherId}|{$btId}"] = true;
            }
            foreach ($lesson['class_ids'] as $idx => $classId) {
                $sectionId = $lesson['section_ids'][$idx] ?? null;
                $this->classBusy["{$classId}|{$sectionId}|{$btId}"] = true;
            }
        }

        $dayKey = "{$lesson['teacher_id']}|{$dayOrder}";
        $this->teacherDayCount[$dayKey] = ($this->teacherDayCount[$dayKey] ?? 0) + count($ids);
        $this->teacherWeekCount[$lesson['teacher_id']] = ($this->teacherWeekCount[$lesson['teacher_id']] ?? 0) + count($ids);

        if ($coTeacherId !== null) {
            $coDayKey = "{$coTeacherId}|{$dayOrder}";
            $this->teacherDayCount[$coDayKey] = ($this->teacherDayCount[$coDayKey] ?? 0) + count($ids);
            $this->teacherWeekCount[$coTeacherId] = ($this->teacherWeekCount[$coTeacherId] ?? 0) + count($ids);
        }

        foreach ($lesson['class_ids'] as $idx => $classId) {
            $sectionId = $lesson['section_ids'][$idx] ?? null;
            $key = "{$classId}|{$sectionId}|{$lesson['subject_id']}|{$dayOrder}";
            $this->classSubjectDay[$key] = ($this->classSubjectDay[$key] ?? 0) + count($ids);
            $loadKey = "{$classId}|{$sectionId}|{$dayOrder}";
            $this->classDayLoad[$loadKey] = ($this->classDayLoad[$loadKey] ?? 0) + count($ids);
        }

        $placementId = $this->nextPlacementId++;

        if ($lesson['type'] === 'solo' && count($ids) === 1) {
            $btId = $ids[0];
            $classId = $lesson['class_ids'][0];
            $sectionId = $lesson['section_ids'][0] ?? null;
            $this->placementByTeacherSlot["{$lesson['teacher_id']}|{$btId}"] = $placementId;
            $this->placementByClassSlot["{$classId}|{$sectionId}|{$btId}"] = $placementId;
        }

        $this->committed[$placementId] = [
            'placement_id' => $placementId,
            'lesson_id' => $lesson['lesson_id'],
            'type' => $lesson['type'],
            'teacher_id' => $lesson['teacher_id'],
            'co_teacher_id' => $coTeacherId,
            'subject_id' => $lesson['subject_id'],
            'class_ids' => $lesson['class_ids'],
            'section_ids' => $lesson['section_ids'],
            'class_name' => $lesson['class_name'],
            'require_consecutive' => $lesson['require_consecutive'],
            'bell_timing_ids' => $ids,
            'combined_class_group_id' => $lesson['type'] === 'combined' ? $lesson['source']['group_id'] : null,
            // Phase 5 (Locked Lessons): carried through to the placements
            // list and on into the newly-written draft row -- a lock
            // survives regeneration, it isn't a one-time pin.
            'is_locked' => !empty($lesson['source']['locked'] ?? false),
            'room_number' => $lesson['room_number'] ?? null,
            // Bugfix (found by the T6 real-data walkthrough): a class-
            // teacher's period-1 reservation is committed BEFORE the normal
            // solve as a permanent fact, but until this flag existed
            // attemptBacktrack() couldn't tell it apart from an ordinary
            // relocatable lesson once it was sitting in $this->committed --
            // an unrelated overloaded lesson elsewhere in the same class
            // could silently bump it to make room for itself. See
            // attemptBacktrack()'s protected-blocker check. A locked slot
            // gets the exact same protection, for the exact same reason.
            'protected' => !empty($lesson['source']['class_teacher_period1'] ?? false) || !empty($lesson['source']['locked'] ?? false),
        ];

        return $placementId;
    }
```

#### `uncommitPlacement(int $placementId): array` — private
Reverses every effect of `commit()` for one placement; returns the removed committed record so the caller can rebuild a lesson-like array.

```php
    private function uncommitPlacement(int $placementId): array
    {
        $p = $this->committed[$placementId];
        $ids = $p['bell_timing_ids'];
        $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
        $coTeacherId = $p['co_teacher_id'] ?? null;

        foreach ($ids as $btId) {
            unset($this->teacherBusy["{$p['teacher_id']}|{$btId}"]);
            if ($coTeacherId !== null) {
                unset($this->teacherBusy["{$coTeacherId}|{$btId}"]);
            }
            foreach ($p['class_ids'] as $idx => $classId) {
                $sectionId = $p['section_ids'][$idx] ?? null;
                unset($this->classBusy["{$classId}|{$sectionId}|{$btId}"]);
            }
        }

        $dayKey = "{$p['teacher_id']}|{$dayOrder}";
        $this->teacherDayCount[$dayKey] -= count($ids);
        $this->teacherWeekCount[$p['teacher_id']] -= count($ids);

        if ($coTeacherId !== null) {
            $coDayKey = "{$coTeacherId}|{$dayOrder}";
            $this->teacherDayCount[$coDayKey] -= count($ids);
            $this->teacherWeekCount[$coTeacherId] -= count($ids);
        }

        foreach ($p['class_ids'] as $idx => $classId) {
            $sectionId = $p['section_ids'][$idx] ?? null;
            $key = "{$classId}|{$sectionId}|{$p['subject_id']}|{$dayOrder}";
            $this->classSubjectDay[$key] -= count($ids);
            $loadKey = "{$classId}|{$sectionId}|{$dayOrder}";
            $this->classDayLoad[$loadKey] -= count($ids);
        }

        if ($p['type'] === 'solo' && count($ids) === 1) {
            $btId = $ids[0];
            $classId = $p['class_ids'][0];
            $sectionId = $p['section_ids'][0] ?? null;
            unset($this->placementByTeacherSlot["{$p['teacher_id']}|{$btId}"]);
            unset($this->placementByClassSlot["{$classId}|{$sectionId}|{$btId}"]);
        }

        unset($this->committed[$placementId]);

        return $p;
    }
```

#### `attemptBacktrack(array $lesson): array` — private
Bounded local backtrack, single-period solo lessons only: for each domain slot (up to `backtrackBudgetPerLesson`), if blocked by exactly one relocatable placement, try moving that placement elsewhere; if that frees the slot, use it — otherwise the blocker is restored before trying the next candidate.

```php
    private function attemptBacktrack(array $lesson): array
    {
        if ($lesson['periods_needed'] !== 1 || $lesson['type'] !== 'solo') {
            return [];
        }

        $classId = $lesson['class_ids'][0];
        $sectionId = $lesson['section_ids'][0] ?? null;
        $attempts = 0;

        foreach ($this->domainSlotsForLesson($lesson) as $slot) {
            if ($attempts >= $this->backtrackBudgetPerLesson) {
                break;
            }

            $btId = $slot['bell_timing_ids'][0];
            $teacherBlockerId = $this->placementByTeacherSlot["{$lesson['teacher_id']}|{$btId}"] ?? null;
            $classBlockerId = $this->placementByClassSlot["{$classId}|{$sectionId}|{$btId}"] ?? null;

            if ($teacherBlockerId === null && $classBlockerId === null) {
                continue; // illegal here for a non-relocatable reason (availability, day/week cap, subject cap)
            }
            if ($teacherBlockerId !== null && $classBlockerId !== null && $teacherBlockerId !== $classBlockerId) {
                continue; // two different placements block this slot; relocating one alone can't free it
            }

            $blockerId = $teacherBlockerId ?? $classBlockerId;
            if (!empty($this->committed[$blockerId]['protected'] ?? false)) {
                continue; // a class-teacher's period-1 reservation is never relocatable -- see commit()
            }

            $attempts++;
            $removed = $this->uncommitPlacement($blockerId);
            $blockerLesson = [
                'lesson_id' => $removed['lesson_id'],
                'type' => 'solo',
                'teacher_id' => $removed['teacher_id'],
                // T6 item 4: preserve the blocker's own co-teacher (if any)
                // through relocation -- dropping it here would silently
                // "free" the co-teacher's busy state at the NEW slot,
                // letting a different lesson double-book them there.
                'co_teacher_id' => $removed['co_teacher_id'] ?? null,
                'subject_id' => $removed['subject_id'],
                'class_ids' => $removed['class_ids'],
                'section_ids' => $removed['section_ids'],
                'class_name' => $removed['class_name'],
                'require_consecutive' => false,
                'periods_needed' => 1,
            ];

            $alternate = null;
            foreach ($this->domainSlotsForLesson($blockerLesson) as $altSlot) {
                if ($altSlot['bell_timing_ids'][0] === $btId) {
                    continue;
                }
                if ($this->isHardLegal($blockerLesson, $altSlot)) {
                    $alternate = $altSlot;
                    break;
                }
            }

            if ($alternate === null) {
                $this->commit($blockerLesson, $slot);

                continue;
            }

            $newBlockerPlacementId = $this->commit($blockerLesson, $alternate);

            if ($this->isHardLegal($lesson, $slot)) {
                return [$slot];
            }

            // The relocation didn't end up freeing $slot for $lesson after
            // all (some other hard constraint on $lesson itself still
            // blocks it) -- put the blocker back exactly where it was
            // before trying the next candidate slot, per this method's
            // documented contract. Without this, the blocker stays
            // permanently (and pointlessly) relocated even though this
            // attempt never placed $lesson.
            $this->uncommitPlacement($newBlockerPlacementId);
            $this->commit($blockerLesson, $slot);
        }

        return [];
    }
```

#### `pickMostConstrained(Collection $pending, array $domainCountCache): int` — private
MRV selection: fewest legal slots remaining, tie-break highest periods_per_week.

```php
    private function pickMostConstrained(Collection $pending, array $domainCountCache): int
    {
        $bestId = null;
        $bestCount = null;
        $bestPeriodsPerWeek = -1;

        foreach ($pending as $id => $lesson) {
            $count = $domainCountCache[$id] ?? 0;
            $periodsPerWeek = $lesson['periods_per_week'];

            if ($bestId === null || $count < $bestCount || ($count === $bestCount && $periodsPerWeek > $bestPeriodsPerWeek)) {
                $bestId = $id;
                $bestCount = $count;
                $bestPeriodsPerWeek = $periodsPerWeek;
            }
        }

        return $bestId;
    }
```

#### `softScore(array $lesson, array $slot): float` — private
Slot ordering only (every candidate already passed `isHardLegal()`): morning preference, day-load spreading, teacher-gap minimization.

```php
    private function softScore(array $lesson, array $slot): float
    {
        $firstId = $slot['bell_timing_ids'][0];
        $dayOrder = $this->timingMeta[$firstId]['day_order'];
        $dayIds = $this->timingsByDay[$dayOrder] ?? [];

        $maxOrderIndex = 0;
        foreach ($dayIds as $id) {
            $maxOrderIndex = max($maxOrderIndex, $this->timingMeta[$id]['order_index']);
        }
        $orderIndex = $this->timingMeta[$firstId]['order_index'];

        $score = 0.0;

        if ($lesson['prefer_morning']) {
            $score += ($maxOrderIndex - $orderIndex) * 10;
            if ($orderIndex === $maxOrderIndex) {
                $score -= 50;
            }
        }

        $classId = $lesson['class_ids'][0];
        $sectionId = $lesson['section_ids'][0] ?? null;
        $loadKey = "{$classId}|{$sectionId}|{$dayOrder}";
        $score -= ($this->classDayLoad[$loadKey] ?? 0) * 3;

        foreach ($slot['bell_timing_ids'] as $btId) {
            $oi = $this->timingMeta[$btId]['order_index'];
            foreach ($dayIds as $otherId) {
                if ($otherId === $btId) {
                    continue;
                }
                $otherOi = $this->timingMeta[$otherId]['order_index'];
                if (abs($otherOi - $oi) === 1 && isset($this->teacherBusy["{$lesson['teacher_id']}|{$otherId}"])) {
                    $score += 20;
                }
            }
        }

        return $score;
    }
```

#### `unplacedResult(array $lesson, ?string $reason = null): array` — private

```php
    private function unplacedResult(array $lesson, ?string $reason = null): array
    {
        return [
            'lesson_id' => $lesson['lesson_id'],
            'type' => $lesson['type'],
            'teacher_id' => $lesson['teacher_id'],
            'subject_id' => $lesson['subject_id'],
            'class_ids' => $lesson['class_ids'],
            'label' => $lesson['label'],
            'reason' => $reason ?? $this->buildReason($lesson),
        ];
    }
```

#### `buildReason(array $lesson): string` — private
Tallies which hard constraint has zero remaining slots across the lesson's whole domain and phrases the dominant one as a human-readable sentence.

```php
    private function buildReason(array $lesson): string
    {
        $teacherBlockCount = 0;
        $classBlockCount = 0;
        $dayCapCount = 0;
        $otherCount = 0;

        foreach ($this->domainSlotsForLesson($lesson) as $slot) {
            $ids = $slot['bell_timing_ids'];
            $teacherBad = false;
            $classBad = false;

            foreach ($ids as $btId) {
                $teacherKey = "{$lesson['teacher_id']}|{$btId}";
                if (isset($this->teacherBusy[$teacherKey]) || isset($this->teacherBlocked[$teacherKey])) {
                    $teacherBad = true;
                }
                foreach ($lesson['class_ids'] as $idx => $classId) {
                    $sectionId = $lesson['section_ids'][$idx] ?? null;
                    if (isset($this->classBusy["{$classId}|{$sectionId}|{$btId}"])) {
                        $classBad = true;
                    }
                }
            }

            if ($teacherBad) {
                $teacherBlockCount++;

                continue;
            }
            if ($classBad) {
                $classBlockCount++;

                continue;
            }

            $dayOrder = $this->timingMeta[$ids[0]]['day_order'];
            $cap = $lesson['require_consecutive'] ? 2 : 1;
            $dayBad = false;
            foreach ($lesson['class_ids'] as $idx => $classId) {
                $sectionId = $lesson['section_ids'][$idx] ?? null;
                $key = "{$classId}|{$sectionId}|{$lesson['subject_id']}|{$dayOrder}";
                if (($this->classSubjectDay[$key] ?? 0) + count($ids) > $cap) {
                    $dayBad = true;
                }
            }

            if ($dayBad) {
                $dayCapCount++;
            } else {
                $otherCount++; // teacher day/week max on every remaining slot
            }
        }

        $teacherName = optional(Teacher::find($lesson['teacher_id']))->name ?? "Teacher #{$lesson['teacher_id']}";
        $subjectName = $lesson['subject_name'];
        $label = $lesson['label'];
        $prefix = "Could not place {$subjectName} for {$label}:";

        $max = max($teacherBlockCount, $classBlockCount, $dayCapCount, $otherCount);

        if ($max === 0) {
            return "{$prefix} no legal slot remained after exhausting available scheduling attempts.";
        }
        if ($teacherBlockCount === $max) {
            return "{$prefix} {$teacherName} has no remaining free slots on any day.";
        }
        if ($classBlockCount === $max) {
            return "{$prefix} every remaining period in {$label}'s week is already occupied.";
        }
        if ($dayCapCount === $max) {
            return "{$prefix} {$subjectName} already appears the maximum allowed times per day on every remaining day.";
        }

        return "{$prefix} {$teacherName} has reached their maximum periods for the day or week on every remaining slot.";
    }
}
```

*(end of GeneratorService.php — full 1528-line file now completely reproduced across parts 1–4 above)*

### `app/Http/Controllers/Teacher/TeacherTimetableController.php` (complete — 135 lines)

Class purpose: teacher weekly timetable view (Commit `3c24b82`). Published-only, scoped to the authenticated teacher via `Auth::guard('teacher')`, no route parameter.

#### `index()` — public
The only method: resolves the authenticated teacher, loads their published slots (as primary or co-teacher), groups by real active day, overlays this-week substitutions/arrangements, and lists out-of-schedule covering assignments separately.

```php
class TeacherTimetableController extends Controller
{
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? Teacher::find($teacherLogin->teacher_id) : null;

        if (!$teacher) {
            return view('teacher.timetable.index', [
                'teacher' => null,
                'days' => [],
                'periodsByDay' => collect(),
            ]);
        }

        $slots = TimetableSlot::published()
            ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhere('co_teacher_id', $teacher->id))
            ->with(['subject', 'schoolClass', 'section', 'teacher', 'coTeacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null);

        // Active teaching days, in real calendar order -- derived from the
        // actual bell timings this teacher's own slots reference, not a
        // hard-coded Monday-Saturday assumption (a school that only
        // teaches Monday-Friday must never show a blank, misleading
        // Saturday column).
        $days = $slots->pluck('bellTiming.day_of_week')->unique()
            ->sortBy(fn ($day) => $slots->firstWhere('bellTiming.day_of_week', $day)?->bellTiming?->day_order)
            ->values();

        $periodsByDay = $slots
            ->groupBy('bellTiming.day_of_week')
            ->map(fn ($daySlots) => $daySlots->sortBy('bellTiming.order_index')->values());

        // This week's actual calendar dates for each active day, so any
        // substitution recorded for a specific date this week can be
        // overlaid -- mirrors Parent\TimetableController::today()'s own
        // "Arrangement" treatment, extended across the whole week instead
        // of just today.
        $weekStart = now()->startOfWeek();
        $dateByDayOfWeek = collect($days)->mapWithKeys(function ($day) use ($weekStart) {
            $date = $weekStart->copy();
            while ($date->format('l') !== $day) {
                $date->addDay();
            }

            return [$day => $date];
        });

        // A plain date range, not an exact-string whereIn -- substitution_date
        // is stored as a full datetime column ("2026-08-03 00:00:00"), so a
        // strict string match against pure "Y-m-d" values is DB-driver
        // dependent (works on MySQL's loose datetime coercion, silently
        // matches nothing on SQLite's literal string comparison). A
        // whereBetween range is portable across both, and the exact date is
        // still filtered precisely in PHP below via ->toDateString().
        $weekRangeStart = $weekStart->copy()->startOfDay();
        $weekRangeEnd = $weekStart->copy()->addDays(6)->endOfDay();

        // Substitutions touching this teacher's OWN regular slots this
        // week -- either direction (they were absent and covered, or they
        // are the one covering) -- overlaid onto the matching grid cell
        // below.
        $substitutions = TeacherSubstitution::whereIn('bell_timing_id', $slots->pluck('bell_timing_id')->unique()->values())
            ->where(function ($q) use ($teacher) {
                $q->where('absent_teacher_id', $teacher->id)->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->where('status', '!=', 'cancelled')
            ->whereBetween('substitution_date', [$weekRangeStart, $weekRangeEnd])
            ->with('substituteTeacher', 'absentTeacher')
            ->get()
            ->groupBy(fn ($s) => $s->substitution_date->toDateString() . '|' . $s->bell_timing_id);

        $periodsByDay = $periodsByDay->map(function ($daySlots, $day) use ($dateByDayOfWeek, $substitutions, $teacher) {
            $date = $dateByDayOfWeek->get($day);

            return $daySlots->map(function (TimetableSlot $slot) use ($date, $substitutions, $teacher) {
                $key = $date ? $date->toDateString() . '|' . $slot->bell_timing_id : null;
                $arrangement = $key ? $substitutions->get($key, collect())->first() : null;

                // "Team teaching with" from the VIEWER's own perspective --
                // if they're the co-teacher on this lesson, show the
                // primary teacher's name (not their own); if they're the
                // primary, show the co-teacher's name as before.
                $withTeacher = $slot->teacher_id === $teacher->id ? $slot->coTeacher : $slot->teacher;

                return (object) [
                    'slot' => $slot,
                    'arrangement' => $arrangement,
                    'with_teacher_name' => $slot->co_teacher_id ? ($withTeacher->name ?? null) : null,
                ];
            });
        });

        // Extra assignments this week where the teacher is covering a
        // DIFFERENT class than any of their own regular slots -- these
        // have no cell of their own in the grid above, so listed
        // separately rather than silently dropped.
        $ownBellTimingIds = $slots->pluck('bell_timing_id')->unique();
        $coveringAssignments = TeacherSubstitution::where('substitute_teacher_id', $teacher->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('substitution_date', [$weekRangeStart, $weekRangeEnd])
            ->whereNotIn('bell_timing_id', $ownBellTimingIds)
            ->with(['absentTeacher', 'bellTiming', 'class', 'section', 'subject'])
            ->get()
            ->sortBy(fn ($s) => [$s->substitution_date->toDateString(), $s->bellTiming?->order_index]);

        return view('teacher.timetable.index', [
            'teacher' => $teacher,
            'days' => $days,
            'periodsByDay' => $periodsByDay,
            'coveringAssignments' => $coveringAssignments,
        ]);
    }
}
```

### `app/Http/Controllers/Parent/TimetableController.php` (complete — 185 lines)

Class purpose: parent "today" (T5) and "weekly" (Commit `f523944`) timetable views. Both scoped exclusively to `Auth::guard('parent')->user()->student` (`ParentModel::getStudentAttribute()`'s session-aware accessor, pre-validated by `switchStudent()`), never a route parameter.

#### `today()` — public
Resolves the authenticated parent's active student, then delegates the actual query to the private `todaysPeriods()` helper.

```php
class TimetableController extends Controller
{
    public function today()
    {
        $parent = Auth::guard('parent')->user();

        if (!$parent) {
            abort(403, 'Parent not logged in');
        }

        $student = $parent->student;

        if (!$student) {
            return view('parent.timetable.today', [
                'student' => null,
                'periods' => collect(),
                'date' => now(),
            ]);
        }

        $classId = $student->canonicalClassId();
        $sectionId = $student->section_id;
        $date = now();

        if (!$classId) {
            return view('parent.timetable.today', [
                'student' => $student,
                'periods' => collect(),
                'date' => $date,
            ]);
        }

        $periods = $this->todaysPeriods($classId, $sectionId, $date);

        return view('parent.timetable.today', compact('student', 'periods', 'date'));
    }
```

#### `weekly()` — public
Same security pattern as `today()`. Loads the student's published class-section slots, groups by real active day, overlays this-week substitutions as "Arrangement".

```php
    public function weekly()
    {
        $parent = Auth::guard('parent')->user();

        if (!$parent) {
            abort(403, 'Parent not logged in');
        }

        $student = $parent->student;

        if (!$student) {
            return view('parent.timetable.weekly', ['student' => null, 'days' => [], 'periodsByDay' => collect()]);
        }

        $classId = $student->canonicalClassId();
        $sectionId = $student->section_id;

        if (!$classId) {
            return view('parent.timetable.weekly', ['student' => $student, 'days' => [], 'periodsByDay' => collect()]);
        }

        $slots = TimetableSlot::published()
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
            }))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null);

        // Active teaching days, in real calendar order -- derived from this
        // class's own actual bell timings, not a hard-coded Mon-Sat.
        $days = $slots->pluck('bellTiming.day_of_week')->unique()
            ->sortBy(fn ($day) => $slots->firstWhere('bellTiming.day_of_week', $day)?->bellTiming?->day_order)
            ->values();

        $weekStart = now()->startOfWeek();
        $dateByDayOfWeek = collect($days)->mapWithKeys(function ($day) use ($weekStart) {
            $date = $weekStart->copy();
            while ($date->format('l') !== $day) {
                $date->addDay();
            }

            return [$day => $date];
        });

        $weekRangeStart = $weekStart->copy()->startOfDay();
        $weekRangeEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $substitutions = TeacherSubstitution::where('class_id', $classId)
            ->whereBetween('substitution_date', [$weekRangeStart, $weekRangeEnd])
            ->where('status', '!=', 'cancelled')
            ->with('substituteTeacher')
            ->get()
            ->groupBy(fn ($s) => $s->substitution_date->toDateString() . '|' . $s->bell_timing_id);

        $periodsByDay = $slots
            ->groupBy('bellTiming.day_of_week')
            ->map(function ($daySlots, $day) use ($dateByDayOfWeek, $substitutions) {
                $date = $dateByDayOfWeek->get($day);

                return $daySlots->sortBy('bellTiming.order_index')->values()->map(function (TimetableSlot $slot) use ($date, $substitutions) {
                    $key = $date ? $date->toDateString() . '|' . $slot->bell_timing_id : null;
                    $sub = $key ? $substitutions->get($key, collect())->first() : null;
                    $isArrangement = (bool) ($sub && $sub->substitute_teacher_id);

                    return (object) [
                        'bell_timing_id' => $slot->bell_timing_id,
                        'period_name' => $slot->bellTiming->period_name,
                        'start_time' => $slot->bellTiming->start_time,
                        'end_time' => $slot->bellTiming->end_time,
                        'subject_name' => $slot->subject->name ?? 'N/A',
                        'teacher_name' => $isArrangement ? ($sub->substituteTeacher->name ?? 'N/A') : ($slot->teacher->name ?? 'N/A'),
                        'room_number' => $slot->room_number,
                        'is_arrangement' => $isArrangement,
                    ];
                });
            });

        return view('parent.timetable.weekly', compact('student', 'days', 'periodsByDay'));
    }
```

#### `todaysPeriods(int $classId, ?int $sectionId, \Carbon\Carbon $date)` — private
Shared query helper behind `today()`: today's published slots for the class-section, substitutions keyed by bell_timing_id for that exact date.

```php
    private function todaysPeriods(int $classId, ?int $sectionId, \Carbon\Carbon $date)
    {
        $dayOfWeek = $date->format('l');

        $slots = TimetableSlot::published()
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
            }))
            ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
            ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
            ->values();

        $substitutions = TeacherSubstitution::where('class_id', $classId)
            ->whereDate('substitution_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('bell_timing_id');

        return $slots->map(function (TimetableSlot $slot) use ($substitutions) {
            $sub = $substitutions->get($slot->bell_timing_id);
            $isArrangement = (bool) ($sub && $sub->substitute_teacher_id);

            return (object) [
                'bell_timing_id' => $slot->bell_timing_id,
                'period_name' => $slot->bellTiming->period_name,
                'start_time' => $slot->bellTiming->start_time,
                'end_time' => $slot->bellTiming->end_time,
                'subject_name' => $slot->subject->name ?? 'N/A',
                'teacher_name' => $isArrangement ? ($sub->substituteTeacher->name ?? 'N/A') : ($slot->teacher->name ?? 'N/A'),
                'is_arrangement' => $isArrangement,
            ];
        })->values();
    }
}
```

### `app/Models/TimetableSlot.php` (complete — 110 lines)

Core slot row model: draft/published/archived lifecycle, all relations, and the scopes every reader in the module depends on (`published()` is the one every read-only view must use).

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimetableSlot extends Model
{
    use HasFactory;

    /**
     * T4b: a slot is 'published' (the live timetable, the default every
     * pre-T4b row backfilled to), 'draft' (a proposed arrangement from a
     * GenerateTimetableJob run, not yet live), or 'archived' (a formerly-
     * published slot displaced by a PUBLISH -- kept for history, excluded
     * from the uniqueness constraints entirely, see the migration that
     * added class_bell_active_key/teacher_active_key).
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'school_class_id',
        'section_id',
        'bell_timing_id',
        'subject_id',
        'teacher_id',
        'co_teacher_id',
        'combined_class_group_id',
        'room_number',
        'academic_year',
        'status',
        'timetable_generation_id',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function bellTiming()
    {
        return $this->belongsTo(BellTiming::class, 'bell_timing_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    /** T6 item 4: the optional second (team-teaching) teacher occupying this same slot alongside teacher_id. */
    public function coTeacher()
    {
        return $this->belongsTo(Teacher::class, 'co_teacher_id');
    }

    public function combinedClassGroup()
    {
        return $this->belongsTo(CombinedClassGroup::class, 'combined_class_group_id');
    }

    public function timetableGeneration()
    {
        return $this->belongsTo(TimetableGeneration::class);
    }

    /**
     * The live timetable -- every reader outside draft review/generation
     * (feasibility, substitutions, PDFs, and eventually parent views) must
     * use this, never the bare unscoped query. See timetable-T4b-report.md
     * for the audited list of readers.
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /** Phase 5 (Locked Lessons): rows Auto-Fix, a future Rebalance pass, and the generator must never move. */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }
}
```

### `app/Models/BellTiming.php` (complete — 284 lines)

Period/bell-timing definition model. Includes the `getDayOrderAttribute()` accessor `GeneratorService` and both weekly controllers depend on, and a `booted()` hook that corrects a break's `period_type` to keep it out of teaching-capacity math.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BellTiming extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',      // Monday, Tuesday, etc.
        'period_name',      // Period 1, Period 2, Lunch, Break, etc.
        'start_time',       // HH:MM:SS format
        'end_time',         // HH:MM:SS format
        'class_section',    // Specific class/section if needed
        'is_active',        // Whether this schedule is currently active
        'is_break',         // Whether this is a break time
        'period_type',      // teaching/assembly/prayer/break/zero/dispersal
        'order_index',      // Order of periods in a day
        'academic_year',    // Academic year identifier
        'semester',         // Semester identifier
        'custom_label',     // Custom label for special periods
        'color_code',       // Color for calendar representation
        'created_by',       // User who created the schedule
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'is_active' => 'boolean',
        'is_break' => 'boolean',
        'order_index' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * is_break=true and period_type='teaching' are contradictory --
     * neither BellTimingController nor its API counterpart ever set
     * period_type, so every break created through the admin UI would
     * otherwise silently fall through to the column's DB default
     * ('teaching') and get wrongly counted as teaching capacity by
     * FeasibilityService (T2b). Only overrides when the caller left
     * period_type unset or contradictorily 'teaching' -- an explicit
     * non-teaching value (e.g. is_break=true AND period_type=assembly)
     * is left alone.
     */
    protected static function booted(): void
    {
        static::saving(function (BellTiming $timing) {
            if ($timing->is_break && in_array($timing->period_type, [null, self::PERIOD_TYPE_TEACHING], true)) {
                $timing->period_type = self::PERIOD_TYPE_BREAK;
            }
        });
    }

    // Days of the week constants
    const MONDAY = 'Monday';
    const TUESDAY = 'Tuesday';
    const WEDNESDAY = 'Wednesday';
    const THURSDAY = 'Thursday';
    const FRIDAY = 'Friday';
    const SATURDAY = 'Saturday';
    const SUNDAY = 'Sunday';

    // Period types (T2b: real `period_type` column values, matching the
    // enum in the 2026_07_27_080014 migration). Replaces a same-named but
    // unused/never-called getPeriodTypeAttribute() guess-from-period_name
    // accessor that would otherwise have silently shadowed this real
    // column on every `$timing->period_type` access.
    const PERIOD_TYPE_TEACHING = 'teaching';
    const PERIOD_TYPE_ASSEMBLY = 'assembly';
    const PERIOD_TYPE_PRAYER = 'prayer';
    const PERIOD_TYPE_BREAK = 'break';
    const PERIOD_TYPE_ZERO = 'zero';
    const PERIOD_TYPE_DISPERSAL = 'dispersal';

    // Scopes for common queries
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRegular($query)
    {
        return $query->where('is_break', false);
    }

    public function scopeBreaks($query)
    {
        return $query->where('is_break', true);
    }

    public function scopeByDay($query, $day)
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeByClass($query, $classSection)
    {
        return $query->where('class_section', $classSection);
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    // Helper methods
    public function getDurationAttribute()
    {
        if ($this->start_time && $this->end_time) {
            $start = strtotime($this->start_time->format('H:i:s'));
            $end = strtotime($this->end_time->format('H:i:s'));
            
            if ($end >= $start) {
                $duration = $end - $start;
                $hours = floor($duration / 3600);
                $minutes = floor(($duration % 3600) / 60);
                return sprintf('%02d:%02d', $hours, $minutes);
            }
        }
        return '00:00';
    }

    public function getDurationFormattedAttribute()
    {
        if ($this->start_time && $this->end_time) {
            $start = strtotime($this->start_time->format('H:i:s'));
            $end = strtotime($this->end_time->format('H:i:s'));
            
            if ($end >= $start) {
                $duration = $end - $start;
                $hours = floor($duration / 3600);
                $minutes = floor(($duration % 3600) / 60);
                
                if ($hours > 0) {
                    return $hours . 'h ' . $minutes . 'm';
                } else {
                    return $minutes . 'm';
                }
            }
        }
        return '0m';
    }

    public function getDayOrderAttribute()
    {
        $daysOrder = [
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7
        ];
        
        return $daysOrder[$this->day_of_week] ?? 99;
    }

    public function isCurrentlyActive()
    {
        $now = now();
        $currentTime = $now->format('H:i:s');
        $currentDay = $now->format('l'); // Full day name
        
        return $this->day_of_week === $currentDay &&
               $currentTime >= $this->start_time->format('H:i:s') &&
               $currentTime <= $this->end_time->format('H:i:s') &&
               $this->is_active;
    }

    public static function getCurrentPeriod()
    {
        $now = now();
        $currentTime = $now->format('H:i:s');
        $currentDay = $now->format('l'); // Full day name
        
        return self::where('day_of_week', $currentDay)
                   ->where('start_time', '<=', $currentTime)
                   ->where('end_time', '>=', $currentTime)
                   ->where('is_active', true)
                   ->first();
    }

    public static function getTodaysSchedule($day = null, $classSection = null)
    {
        $day = $day ?: now()->format('l');
        
        $query = self::where('day_of_week', $day)
                     ->where('is_active', true)
                     ->orderBy('order_index');
        
        if ($classSection) {
            $query->where('class_section', $classSection);
        }
        
        return $query->get();
    }

    public static function getWeeklySchedule($classSection = null)
    {
        $query = self::where('is_active', true);
        
        if ($classSection) {
            $query->where('class_section', $classSection);
        }
        
        $results = $query->get();
        
        // Define day order mapping
        $daysOrder = [
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7
        ];
        
        // Sort by day order and then by order_index
        return $results->sortBy(function ($item) use ($daysOrder) {
            return $daysOrder[$item->day_of_week] ?? 99;
        })->sortBy('order_index')->values();
    }

    public static function getTimetableForClass($classSection, $academicYear = null)
    {
        $query = self::where('class_section', $classSection)
                     ->where('is_active', true);
        
        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }
        
        $results = $query->get();
        
        // Define day order mapping
        $daysOrder = [
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7
        ];
        
        // Sort by day order and then by order_index
        return $results->sortBy(function ($item) use ($daysOrder) {
            return $daysOrder[$item->day_of_week] ?? 99;
        })->sortBy('order_index')->values();
    }

    public function getFormattedTimeRange()
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('h:i A') . ' - ' . $this->end_time->format('h:i A');
        }
        return 'Invalid Time';
    }

    /**
     * Only 'teaching' periods count toward capacity math and solver
     * placement (T2b); assembly/prayer/break/zero/dispersal are excluded
     * there but still print in the PDF grids, shaded.
     */
    public function scopeTeachingType($query)
    {
        return $query->where('period_type', self::PERIOD_TYPE_TEACHING);
    }

    
    /**
     * Get the user who created this bell timing.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

### `app/Models/TeacherSubstitution.php` (complete — 166 lines)

Substitution/arrangement record. `booted()` auto-derives `period_name` from the linked `BellTiming` so the two can never drift apart.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubstitution extends Model
{
    use HasFactory;

    protected $fillable = [
        'substitution_date',
        'absent_teacher_id',
        'substitute_teacher_id',
        'class_id',
        'section_id',
        'subject_id',
        'period_number',
        'bell_timing_id',
        'period_name',
        'status',
        'reason',
        'created_by',
        'updated_by',
        'assigned_at',
    ];

    protected $casts = [
        'substitution_date' => 'date',
        'assigned_at' => 'datetime',
    ];

    /**
     * T3 item 1: period_name is a display duplicate of the linked
     * bell_timing's own period_name -- auto-derived here so the two
     * can never drift apart (same pattern as TeacherAvailability::day
     * and BellTiming::period_type elsewhere in this module).
     */
    protected static function booted(): void
    {
        static::saving(function (TeacherSubstitution $substitution) {
            if ($substitution->bell_timing_id) {
                $bellTiming = $substitution->relationLoaded('bellTiming')
                    ? $substitution->bellTiming
                    : BellTiming::find($substitution->bell_timing_id);
                if ($bellTiming) {
                    $substitution->period_name = $bellTiming->period_name;
                }
            }
        });
    }

    // Relationships
    public function bellTiming(): BelongsTo
    {
        return $this->belongsTo(BellTiming::class);
    }

    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'absent_teacher_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('substitution_date', $date);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where(function($q) use ($teacherId) {
            $q->where('absent_teacher_id', $teacherId)
              ->orWhere('substitute_teacher_id', $teacherId);
        });
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'assigned';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getReadableStatus(): string
    {
        $statuses = [
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'approved' => 'Approved',
            'cancelled' => 'Cancelled',
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function isToday(): bool
    {
        return $this->substitution_date->format('Y-m-d') === now()->format('Y-m-d');
    }
}
```

### `app/Models/TimetableGeneration.php` (complete — 75 lines)

One row per `GenerateTimetableJob` run — its own lifecycle status independent of any fixed DB enum.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * T4b: one row per GenerateTimetableJob run. Status is the run's own
 * lifecycle, not a fixed DB enum: queued -> running -> completed (ready
 * for review) -> published | discarded (terminal), or failed at any point
 * before completed.
 */
class TimetableGeneration extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DISCARDED = 'discarded';

    /** T6 item 2: same pattern spread across days vs one day's pattern repeated on every running day. */
    public const STYLE_ROTATING = 'rotating';
    public const STYLE_FIXED_DAILY = 'fixed_daily';

    protected $fillable = [
        'academic_year',
        'academic_session_id',
        'school_class_ids',
        'style',
        'status',
        'placed_count',
        'unplaced_count',
        'report',
        'error',
        'requested_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'school_class_ids' => 'array',
        'report' => 'array',
        'placed_count' => 'integer',
        'unplaced_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function slots()
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /** Null while there's nothing to divide by yet (queued/running). */
    public function getPlacementPercentAttribute(): ?float
    {
        $total = $this->placed_count + $this->unplaced_count;

        return $total > 0 ? round(($this->placed_count / $total) * 100, 1) : null;
    }
}
```

### `app/Models/CombinedClassGroup.php` (complete — 56 lines)

T2b item 3: one teaching event serving several classes at once.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * T2b item 3: one teaching event (one teacher, one subject, one period)
 * that serves several classes at once. See member class-sections via
 * members(); the actual TimetableSlot rows it produces are linked back
 * via TimetableSlot::combined_class_group_id.
 */
class CombinedClassGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject_id',
        'academic_session_id',
        'teacher_id',
        'periods_per_week',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * T4a: the solver's standing weekly requirement for this group (set
     * per-group, unlike ordinary lessons which get it per assignment).
     * Nullable/unset means this group isn't picked up by the generator --
     * still fully usable for T2b's manual placement flow either way.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function members()
    {
        return $this->hasMany(CombinedClassGroupMember::class);
    }

    public function slots()
    {
        return $this->hasMany(TimetableSlot::class, 'combined_class_group_id');
    }
}
```

### `app/Models/CombinedClassGroupMember.php` (complete — 32 lines)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CombinedClassGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'combined_class_group_id',
        'school_class_id',
        'section_id',
    ];

    public function group()
    {
        return $this->belongsTo(CombinedClassGroup::class, 'combined_class_group_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
```

### `app/Services/Timetable/TimetableSwapService.php` (complete — 222 lines)

Class purpose: exchanges the `bell_timing_id` of two already-placed lessons. Reuses `TimetableSuggestionService::checkSwap()` for validation; owns only the swap-specific guardrails and the atomic write.

```php
<?php

namespace App\Services\Timetable;

use App\Models\TimetableSlot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimetableSwapService
{
    private TimetableSuggestionService $suggestions;

    public function __construct(?TimetableSuggestionService $suggestions = null)
    {
        $this->suggestions = $suggestions ?? new TimetableSuggestionService();
    }

    public function preview(int $slotIdA, int $slotIdB): array
    {
        $guard = $this->guardAgainstInvalidPair($slotIdA, $slotIdB);
        if ($guard !== null) {
            return $guard;
        }

        $a = TimetableSlot::with(['subject', 'teacher', 'coTeacher', 'schoolClass', 'section', 'bellTiming'])->find($slotIdA);
        $b = TimetableSlot::with(['subject', 'teacher', 'coTeacher', 'schoolClass', 'section', 'bellTiming'])->find($slotIdB);

        $ineligible = $this->rejectIfIneligible($a, $b);
        if ($ineligible !== null) {
            return $ineligible;
        }

        $swapCheck = $this->suggestions->checkSwap($a->id, $b->id);

        return [
            'ok' => $swapCheck['ok'],
            'message' => $swapCheck['ok'] ? null : 'Swap cannot be completed.',
            'conflicts' => array_merge($swapCheck['a_result']['conflicts'] ?? [], $swapCheck['b_result']['conflicts'] ?? []),
            'slot_a' => $a,
            'slot_b' => $b,
            'a_result' => $swapCheck['a_result'],
            'b_result' => $swapCheck['b_result'],
        ];
    }

    /**
     * Applies the swap. Re-validates from scratch against live, row-locked
     * data immediately before writing. Fully atomic. The "archive-park"
     * trick below sidesteps the DB's unique indexes, which have no concept
     * of "these two rows are trading places" and would otherwise reject
     * the transient mid-swap state even though the final state is valid.
     */
    public function apply(int $slotIdA, int $slotIdB): array
    {
        $guard = $this->guardAgainstInvalidPair($slotIdA, $slotIdB);
        if ($guard !== null) {
            return ['applied' => false, 'message' => $guard['message'], 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        return DB::transaction(function () use ($slotIdA, $slotIdB) {
            // Lock in a fixed order (ascending id) regardless of which the
            // caller labelled A/B, so two concurrent swaps that share one
            // row can never lock it in opposite orders and deadlock each
            // other -- one simply waits for the other's transaction to
            // finish, then re-validates against whatever that left behind.
            [$lowId, $highId] = $slotIdA < $slotIdB ? [$slotIdA, $slotIdB] : [$slotIdB, $slotIdA];
            $low = TimetableSlot::lockForUpdate()->find($lowId);
            $high = TimetableSlot::lockForUpdate()->find($highId);

            $a = $slotIdA === $lowId ? $low : $high;
            $b = $slotIdA === $lowId ? $high : $low;

            $ineligible = $this->rejectIfIneligible($a, $b);
            if ($ineligible !== null) {
                return ['applied' => false, 'message' => $ineligible['message'], 'conflicts' => $ineligible['conflicts'], 'slot_a' => null, 'slot_b' => null];
            }

            $swapCheck = $this->suggestions->checkSwap($a->id, $b->id);

            if (!$swapCheck['ok']) {
                return [
                    'applied' => false,
                    'message' => 'Swap cannot be completed.',
                    'conflicts' => array_merge($swapCheck['a_result']['conflicts'] ?? [], $swapCheck['b_result']['conflicts'] ?? []),
                    'slot_a' => null,
                    'slot_b' => null,
                ];
            }

            $before = [
                'a' => $a->only(['id', 'bell_timing_id']),
                'b' => $b->only(['id', 'bell_timing_id']),
            ];

            $aOriginalStatus = $a->status;
            $aOriginalTiming = $a->bell_timing_id;
            $bOriginalTiming = $b->bell_timing_id;

            // The DB's own unique indexes (teacher_active_key,
            // class_bell_active_key) check every UPDATE individually --
            // they have no concept of "these two rows are trading places,"
            // unlike TimetableConflictResolver's application-level
            // ignore_slot_id. Two sequential updates (A -> B's old period,
            // then B -> A's old period) would transiently put A at B's old
            // period WHILE B is still sitting there too, violating the
            // constraint even though the FINAL state is perfectly valid --
            // this bites any swap where the two lessons share a teacher,
            // class, or room. The schema already has a documented escape
            // hatch for exactly this: an 'archived' row's active-key
            // columns are NULL, so it's excluded from both unique indexes
            // entirely (see the migration that added them). Parking A
            // there for one statement, entirely inside this transaction
            // and invisible to any other connection, removes the
            // transient collision without touching FKs or inventing a
            // fake sentinel value.
            $a->update(['status' => 'archived']);
            $b->update(['bell_timing_id' => $aOriginalTiming]);
            $a->update(['status' => $aOriginalStatus, 'bell_timing_id' => $bOriginalTiming]);

            $after = [
                'a' => $a->only(['id', 'bell_timing_id']),
                'b' => $b->only(['id', 'bell_timing_id']),
            ];

            activity()->causedBy(Auth::user())->performedOn($a)
                ->withProperties(['before' => $before, 'after' => $after, 'swapped_with_slot_id' => $b->id])
                ->log('timetable_slot_swapped');

            activity()->causedBy(Auth::user())->performedOn($b)
                ->withProperties(['before' => $before, 'after' => $after, 'swapped_with_slot_id' => $a->id])
                ->log('timetable_slot_swapped');

            return ['applied' => true, 'message' => 'Lessons swapped.', 'conflicts' => [], 'slot_a' => $a->fresh(), 'slot_b' => $b->fresh()];
        });
    }

    private function guardAgainstInvalidPair(int $slotIdA, int $slotIdB): ?array
    {
        if ($slotIdA === $slotIdB) {
            return ['ok' => false, 'applied' => false, 'message' => 'Cannot swap a lesson with itself.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null, 'a_result' => null, 'b_result' => null];
        }

        return null;
    }

    /**
     * Guardrails checkSwap() deliberately doesn't own: same lifecycle
     * bucket (both draft or both published), same academic year, and
     * (Lock Integrity audit) never a locked/archived/combined-group row on
     * either side. Called from both preview() and apply() (on the freshly
     * lockForUpdate()'d rows).
     */
    private function rejectIfIneligible(?TimetableSlot $a, ?TimetableSlot $b): ?array
    {
        if (!$a || !$b) {
            return ['ok' => false, 'message' => 'One or both lessons no longer exist -- someone may have already changed them.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        foreach (['a' => $a, 'b' => $b] as $slot) {
            if ($slot->combined_class_group_id) {
                return ['ok' => false, 'message' => 'This is a combined-group lesson -- it can\'t be swapped from a single cell. Clear it and re-place it via Combined Groups instead.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
            }

            if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
                return ['ok' => false, 'message' => 'This slot is archived history from a past publish -- it can no longer be swapped.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
            }

            if ($slot->is_locked) {
                return ['ok' => false, 'message' => 'This lesson is locked -- unlock it first to swap it.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
            }
        }

        if ($a->status !== $b->status) {
            return ['ok' => false, 'message' => 'Cannot swap a published lesson with a draft lesson -- they belong to different timetable states.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        if ($a->academic_year !== $b->academic_year) {
            return ['ok' => false, 'message' => 'Cannot swap lessons from different academic years.', 'conflicts' => [], 'slot_a' => null, 'slot_b' => null];
        }

        return null;
    }
}
```

### `app/Services/Timetable/SubstituteFinderService.php` (complete — 148 lines)

Class purpose: T3 item 2 — real substitute scoring (+40 free that period [mandatory filter], +25/+20 class/subject familiarity, +15 scaled by lightest day, mandatory exclusion if already substituting elsewhere that period).

```php
<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use Carbon\CarbonInterface;

class SubstituteFinderService
{
    public function findCandidatesForSubstitution(TeacherSubstitution $substitution): array
    {
        if (!$substitution->bellTiming || !$substitution->class || !$substitution->subject) {
            return [];
        }

        return $this->findCandidates(
            $substitution->bellTiming,
            $substitution->substitution_date,
            $substitution->class,
            $substitution->subject,
            $substitution->absent_teacher_id,
            $substitution->id
        );
    }

    public function findCandidates(
        BellTiming $bellTiming,
        string|CarbonInterface $date,
        SchoolClass $class,
        Subject $subject,
        int $absentTeacherId,
        ?int $excludeSubstitutionId = null
    ): array {
        $bellTimingId = $bellTiming->id;
        $dayOfWeek = $bellTiming->day_of_week;

        // T4b: substitution is about who's ACTUALLY busy on the live
        // timetable -- a teacher only committed in a draft proposal isn't
        // really unavailable.
        $busyTeacherIds = TimetableSlot::published()->where('bell_timing_id', $bellTimingId)
            ->pluck('teacher_id')->unique();

        $blockedTeacherIds = TeacherAvailability::where('bell_timing_id', $bellTimingId)
            ->where('is_available', false)
            ->pluck('teacher_id')->unique();

        $alreadySubstitutingTeacherIds = TeacherSubstitution::where('bell_timing_id', $bellTimingId)
            ->whereDate('substitution_date', $date)
            ->whereIn('status', ['pending', 'assigned', 'approved'])
            ->when($excludeSubstitutionId, fn ($q) => $q->where('id', '!=', $excludeSubstitutionId))
            ->pluck('substitute_teacher_id')->filter()->unique();

        $classTeacherIds = TeacherClassSubjectAssignment::where('class_id', $class->id)
            ->pluck('teacher_id')->unique();

        $subjectTeacherIds = TeacherClassSubjectAssignment::where('subject_id', $subject->id)
            ->pluck('teacher_id')->unique();

        $periodsTodayByTeacher = TimetableSlot::published()
            ->whereHas(
                'bellTiming',
                fn ($q) => $q->where('day_of_week', $dayOfWeek)
            )->selectRaw('teacher_id, COUNT(*) as c')
            ->groupBy('teacher_id')
            ->pluck('c', 'teacher_id');

        $teachers = Teacher::active()->where('id', '!=', $absentTeacherId)->get();

        $candidates = [];

        foreach ($teachers as $teacher) {
            // MANDATORY: free that period.
            if ($busyTeacherIds->contains($teacher->id) || $blockedTeacherIds->contains($teacher->id)) {
                continue;
            }

            // Exclude: already substituting elsewhere this exact period.
            if ($alreadySubstitutingTeacherIds->contains($teacher->id)) {
                continue;
            }

            $score = 40;
            $reasons = ['Free'];

            $teachesClass = $classTeacherIds->contains($teacher->id);
            $teachesSubject = $subjectTeacherIds->contains($teacher->id);

            if ($teachesClass && $teachesSubject) {
                $score += 45;
                $reasons[] = "Teaches {$class->name} {$subject->name}";
            } elseif ($teachesClass) {
                $score += 25;
                $reasons[] = "Teaches {$class->name}";
            } elseif ($teachesSubject) {
                $score += 20;
                $reasons[] = "Teaches {$subject->name}";
            }

            $periodsToday = (int) ($periodsTodayByTeacher[$teacher->id] ?? 0);
            $score += max(0, 15 - $periodsToday);
            $reasons[] = "{$periodsToday} " . ($periodsToday === 1 ? 'period' : 'periods') . ' today';

            $candidates[] = [
                'teacher' => $teacher,
                'score' => $score,
                'reasons' => $reasons,
                'reason_text' => implode(' • ', $reasons),
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates;
    }
}
```

### `app/Services/Timetable/SubstitutionDashboardService.php` (complete — 26 lines)

```php
<?php

namespace App\Services\Timetable;

use App\Models\TeacherSubstitution;

/**
 * T5 item 4: today's substitution count + unfilled arrangements for the
 * admin dashboard card. A tiny dedicated service (rather than an inline
 * query in the controller) purely so the "dashboard degrades gracefully"
 * requirement is mockable/testable the same way
 * ProfessionalDashboardService::getUpcomingEvents() already is.
 */
class SubstitutionDashboardService
{
    public function getTodaysSummary(): array
    {
        $today = TeacherSubstitution::whereDate('substitution_date', today())
            ->where('status', '!=', 'cancelled');

        return [
            'count' => (clone $today)->count(),
            'unfilled' => (clone $today)->whereNull('substitute_teacher_id')->count(),
        ];
    }
}
```

### `app/Services/Timetable/TimetableSuggestionService.php` (complete — 236 lines)

Class purpose: Phase 3 Smart Suggestions — finds concrete, already-validated alternative periods (never a bare "clash"). Every candidate is re-run through `TimetableConflictResolver::check()`; this service adds no conflict rules of its own.

*Reconciliation note: `candidateBellTimings()` below previously had its internal 5-line explanatory comment replaced by external prose in this document. It has been restored verbatim from current source — the comment is now reproduced in place, inside the method body, exactly as it exists in the file.*

```php
<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

class TimetableSuggestionService
{
    private TimetableConflictResolver $resolver;

    /**
     * candidateBellTimings() memoized per academic_year within THIS
     * instance's lifetime only -- checkConflictsApi() creates one instance
     * per request and calls both suggestForNewPlacement() and
     * suggestBlockerRelocation() on it back to back with the same
     * academic_year, which previously re-ran the identical query twice.
     * Never shared across requests/instances, so it can't go stale across
     * a mutation -- this service and its caller are read-only; nothing
     * here ever writes a TimetableSlot.
     *
     * @var array<string, Collection>
     */
    private array $candidateBellTimingsCache = [];

    public function __construct(?TimetableConflictResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new TimetableConflictResolver();
    }

    public function suggestForNewPlacement(array $placement, int $limit = 5): array
    {
        $suggestions = [];

        foreach ($this->candidateBellTimings($placement) as $candidate) {
            if ((int) $candidate->id === (int) ($placement['bell_timing_id'] ?? 0)) {
                continue;
            }

            $trial = array_merge($placement, ['bell_timing_id' => $candidate->id]);
            unset($trial['ignore_slot_id']); // a different period is a different natural key -- let check() re-resolve it.

            if (!$this->resolver->check($trial)['conflict']) {
                $suggestions[] = $this->describe($candidate, "Move to {$candidate->day_of_week} {$candidate->period_name}");
            }

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * Identifies whichever already-placed lesson is blocking the
     * requested period (the first conflict that names one -- teacher,
     * co-teacher, class/section, or room; constraint-only violations like
     * a daily-load cap or period type have no single "blocker" row to
     * relocate) and finds clean alternative periods for THAT lesson,
     * keeping its own teacher/class/subject unchanged.
     */
    public function suggestBlockerRelocation(array $placement, int $limit = 5): array
    {
        $result = $this->resolver->check($placement);
        $blockerId = null;
        foreach ($result['conflicts'] as $conflict) {
            if (!empty($conflict['blocking_slot_id'])) {
                $blockerId = $conflict['blocking_slot_id'];
                break;
            }
        }

        if (!$blockerId) {
            return [];
        }

        $blocker = TimetableSlot::find($blockerId);
        if (!$blocker) {
            return [];
        }

        $blockerPlacement = [
            'school_class_id' => $blocker->school_class_id,
            'section_id' => $blocker->section_id,
            'teacher_id' => $blocker->teacher_id,
            'co_teacher_id' => $blocker->co_teacher_id,
            'subject_id' => $blocker->subject_id,
            'room_number' => $blocker->room_number,
            'status' => $blocker->status,
            'ignore_slot_id' => $blocker->id,
        ];

        $suggestions = [];
        $wantedBellTimingId = (int) ($placement['bell_timing_id'] ?? 0);

        foreach ($this->candidateBellTimings($placement) as $candidate) {
            // The period we're trying to free up isn't a valid destination
            // for the blocker -- it would just recreate the same conflict.
            if ((int) $candidate->id === $wantedBellTimingId) {
                continue;
            }

            $trial = array_merge($blockerPlacement, ['bell_timing_id' => $candidate->id]);

            if (!$this->resolver->check($trial)['conflict']) {
                $subjectName = Subject::find($blocker->subject_id)->name ?? 'This lesson';
                $teacherName = Teacher::find($blocker->teacher_id)->name ?? '';
                $suggestions[] = array_merge(
                    $this->describe($candidate, "Move {$subjectName} ({$teacherName}) to {$candidate->day_of_week} {$candidate->period_name}, freeing this period"),
                    ['blocking_slot_id' => $blocker->id]
                );
            }

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * Validates trading two ALREADY-PLACED lessons' periods: slot A moves
     * to slot B's period and vice versa. Both must be clean in their new
     * position, with both original rows excluded from every check.
     */
    public function checkSwap(int $slotIdA, int $slotIdB): array
    {
        $a = TimetableSlot::findOrFail($slotIdA);
        $b = TimetableSlot::findOrFail($slotIdB);

        $ignoreBoth = [$a->id, $b->id];

        $aAtBsPeriod = $this->resolver->check([
            'school_class_id' => $a->school_class_id, 'section_id' => $a->section_id,
            'teacher_id' => $a->teacher_id, 'co_teacher_id' => $a->co_teacher_id,
            'subject_id' => $a->subject_id, 'room_number' => $a->room_number,
            'status' => $a->status, 'bell_timing_id' => $b->bell_timing_id,
            'ignore_slot_id' => $ignoreBoth,
        ]);

        $bAtAsPeriod = $this->resolver->check([
            'school_class_id' => $b->school_class_id, 'section_id' => $b->section_id,
            'teacher_id' => $b->teacher_id, 'co_teacher_id' => $b->co_teacher_id,
            'subject_id' => $b->subject_id, 'room_number' => $b->room_number,
            'status' => $b->status, 'bell_timing_id' => $a->bell_timing_id,
            'ignore_slot_id' => $ignoreBoth,
        ]);

        return [
            'ok' => !$aAtBsPeriod['conflict'] && !$bAtAsPeriod['conflict'],
            'a_result' => $aAtBsPeriod,
            'b_result' => $bAtAsPeriod,
        ];
    }

    /**
     * Public entry point onto the same candidate pool this service's own
     * suggestion methods use -- Auto-Fix's chain-repair search (which needs
     * to walk candidates for lessons OTHER than the one originally being
     * placed) reuses this instead of re-querying BellTiming itself, so
     * there's exactly one definition of "the legal candidate universe" for
     * every interactive caller.
     */
    public function candidatePeriodsFor(array $placement): Collection
    {
        return $this->candidateBellTimings($placement);
    }

    /**
     * Active, teaching-type bell timings as the candidate pool -- the same
     * universe GeneratorService draws candidate slots from, so a
     * suggestion is never a period the generator itself would refuse to
     * use.
     */
    private function candidateBellTimings(array $placement): Collection
    {
        // Keyed on the academic_year value alone: that's the only part of
        // $placement this query's WHERE clauses depend on (see the query
        // below), so two calls with the same year -- even for otherwise
        // different placements/candidates -- always return the identical
        // result set.
        $cacheKey = (string) ($placement['academic_year'] ?? '');

        if (!array_key_exists($cacheKey, $this->candidateBellTimingsCache)) {
            $this->candidateBellTimingsCache[$cacheKey] = BellTiming::active()
                ->teachingType()
                ->when($placement['academic_year'] ?? null, fn ($q, $year) => $q->where('academic_year', $year))
                ->orderBy('day_of_week')
                ->orderBy('order_index')
                ->get();
        }

        return $this->candidateBellTimingsCache[$cacheKey];
    }

    private function describe(BellTiming $candidate, string $description): array
    {
        return [
            'bell_timing_id' => $candidate->id,
            'day_of_week' => $candidate->day_of_week,
            'period_name' => $candidate->period_name,
            'description' => $description,
        ];
    }
}
```

### `app/Services/Timetable/TimetableRebalanceService.php` (complete — 756 lines, part 1 of 2)

Class purpose: proposes a small, bounded set of swaps/relocations within one class-section's own already-placed lessons that measurably reduce a deterministic "issue score" — never violating a hard constraint or moving a locked lesson. Introduces no new conflict engine or swap algorithm: legality is decided exclusively by `TimetableConflictResolver`/`TimetableSuggestionService::checkSwap()`, and swaps are executed by `TimetableSwapService::apply()`.

Class constants and constructor:

```php
class TimetableRebalanceService
{
    /**
     * Fixed, documented weights -- the whole score is
     * sum(component_count * weight). Every component is an integer count
     * of a concrete, explainable condition (see score()), so the total is
     * always a deterministic integer for a given timetable snapshot.
     * Higher = more issues = worse; this is reported to the admin as an
     * "issue score" (lower is better), never dressed up as a fake 0-100
     * "quality %" that would imply false precision.
     */
    private const WEIGHTS = [
        'teacher_gap_periods' => 4,
        'class_gap_periods' => 3,
        'teacher_workload_imbalance' => 2,
        'prefer_morning_violations' => 2,
        'excessive_consecutive_periods' => 3,
        'room_switch_count' => 1,
    ];

    /** A run of this many consecutive taught periods (same teacher, same day) is normal; each period beyond it is penalised. */
    private const CONSECUTIVE_THRESHOLD = 3;

    private TimetableConflictResolver $resolver;
    private TimetableSuggestionService $suggestions;
    private TimetableSwapService $swapService;

    public function __construct(
        ?TimetableConflictResolver $resolver = null,
        ?TimetableSuggestionService $suggestions = null,
        ?TimetableSwapService $swapService = null
    ) {
        $this->resolver = $resolver ?? new TimetableConflictResolver();
        $this->suggestions = $suggestions ?? new TimetableSuggestionService($this->resolver);
        $this->swapService = $swapService ?? new TimetableSwapService($this->suggestions);
    }
```

#### `analyze(int $schoolClassId, ?int $sectionId, ?string $academicYear, string $status = TimetableSlot::STATUS_PUBLISHED): array` — public
Read-only. Loads the class-section's own slots plus read-only "context" slots (other classes a shared teacher teaches), scores the baseline, then runs the bounded greedy hill-climb loop via `findBestCandidate()`.

```php
    public function analyze(int $schoolClassId, ?int $sectionId, ?string $academicYear, string $status = TimetableSlot::STATUS_PUBLISHED): array
    {
        $limits = config('timetable.rebalance');
        $startTime = microtime(true);

        $classSlots = TimetableSlot::with(['subject', 'teacher', 'coTeacher', 'bellTiming'])
            ->where('school_class_id', $schoolClassId)
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->where('status', $status)
            ->get();

        if ($classSlots->isEmpty()) {
            return $this->emptyResult('No lessons found for this class/section -- nothing to rebalance.');
        }

        $contextSlots = $this->loadContextSlots($classSlots, $status);
        [$timingMeta, $dayMeta] = $this->loadPeriodMeta($academicYear);

        $baseline = $this->score($classSlots, $contextSlots, $timingMeta, $dayMeta);

        $lockedExcludedCount = $classSlots->filter(fn ($s) => $s->is_locked)->count();
        $movableSlots = $classSlots
            ->reject(fn ($s) => $s->is_locked || $s->combined_class_group_id || $s->status === TimetableSlot::STATUS_ARCHIVED)
            ->values();

        if ($movableSlots->isEmpty()) {
            return array_merge($this->emptyResult(
                $lockedExcludedCount > 0
                    ? 'Every lesson in this class/section is locked -- nothing available to rebalance.'
                    : 'No movable lessons found for this class/section -- nothing to rebalance.'
            ), ['baseline_score' => $baseline, 'proposed_score' => $baseline, 'locked_excluded_count' => $lockedExcludedCount]);
        }

        $candidatePeriods = $this->suggestions->candidatePeriodsFor(['academic_year' => $academicYear]);

        $working = $classSlots;
        $currentScore = $baseline;
        $touchedIds = [];
        $movements = [];
        $evaluated = 0;
        $budgetExhausted = false;

        for ($iteration = 0; $iteration < $limits['max_iterations'] && count($movements) < $limits['max_movements']; $iteration++) {
            if ((microtime(true) - $startTime) >= $limits['time_budget_seconds']) {
                $budgetExhausted = true;
                break;
            }

            $best = $this->findBestCandidate(
                $movableSlots->pluck('id')->diff($touchedIds)->values(),
                $working,
                $contextSlots,
                $timingMeta,
                $dayMeta,
                $currentScore,
                $candidatePeriods,
                $academicYear,
                $evaluated,
                $limits['max_candidate_evaluations'],
                $budgetExhausted
            );

            if ($best === null) {
                break;
            }

            $working = $best['simulated'];
            $movements[] = $this->describeMovement($best, $classSlots, $currentScore['breakdown']);
            $currentScore = $best['score'];
            $touchedIds = array_merge($touchedIds, $best['type'] === 'swap' ? [$best['slot_a_id'], $best['slot_b_id']] : [$best['slot_id']]);
        }

        return [
            'ok' => true,
            'message' => empty($movements)
                ? 'No improving movement found -- this class/section is already well balanced (or every possible improvement would break a hard constraint).'
                : count($movements) . ' movement(s) proposed.',
            'baseline_score' => $baseline,
            'proposed_score' => $currentScore,
            'movements' => $movements,
            'locked_excluded_count' => $lockedExcludedCount,
            'evaluated_candidates' => $evaluated,
            'budget_exhausted' => $budgetExhausted,
        ];
    }
```

#### `apply(array $movements, ?string $academicYear = null): array` — public
Never trusts a previously-returned `analyze()` result: every movement is re-validated inside one transaction; if any is no longer valid the whole transaction throws and rolls back, applying nothing.

```php
    public function apply(array $movements, ?string $academicYear = null): array
    {
        if (empty($movements)) {
            return ['applied' => false, 'message' => 'No movements to apply.', 'movements_applied' => 0];
        }

        try {
            $count = DB::transaction(function () use ($movements) {
                $applied = 0;

                foreach ($movements as $movement) {
                    $type = $movement['type'] ?? null;

                    if ($type === 'swap') {
                        $slotAId = (int) ($movement['slot_a_id'] ?? 0);
                        $slotBId = (int) ($movement['slot_b_id'] ?? 0);
                        if (!$slotAId || !$slotBId) {
                            throw new \RuntimeException('This rebalance is no longer valid -- a movement was malformed.');
                        }

                        $result = $this->swapService->apply($slotAId, $slotBId);
                        if (!$result['applied']) {
                            throw new \RuntimeException("This rebalance is no longer valid -- {$result['message']}");
                        }

                        $applied++;
                        continue;
                    }

                    if ($type === 'relocate') {
                        $this->applyRelocation($movement);
                        $applied++;
                        continue;
                    }

                    throw new \RuntimeException('This rebalance is no longer valid -- a movement had an unrecognised type.');
                }

                return $applied;
            });

            return ['applied' => true, 'message' => "Rebalance applied -- {$count} movement(s).", 'movements_applied' => $count];
        } catch (\RuntimeException $e) {
            return ['applied' => false, 'message' => $e->getMessage(), 'movements_applied' => 0];
        }
    }
```

#### `applyRelocation(array $movement): void` — private
A relocation has no partner row: re-fetches and locks the single slot, re-checks every guard, re-validates the destination via `TimetableConflictResolver`, writes, and logs.

```php
    private function applyRelocation(array $movement): void
    {
        $slotId = (int) ($movement['slot_id'] ?? 0);
        $toBellTimingId = (int) ($movement['to_bell_timing_id'] ?? 0);
        if (!$slotId || !$toBellTimingId) {
            throw new \RuntimeException('a movement was malformed.');
        }

        $slot = TimetableSlot::lockForUpdate()->find($slotId);
        if (!$slot) {
            throw new \RuntimeException('one of the lessons it planned to move no longer exists.');
        }
        if ($slot->is_locked) {
            throw new \RuntimeException('one of the lessons it planned to move has since been locked.');
        }
        if ($slot->combined_class_group_id) {
            throw new \RuntimeException('one of the lessons it planned to move is now part of a combined group.');
        }
        if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
            throw new \RuntimeException('one of the lessons it planned to move is now archived history.');
        }

        $trial = [
            'school_class_id' => $slot->school_class_id,
            'section_id' => $slot->section_id,
            'teacher_id' => $slot->teacher_id,
            'co_teacher_id' => $slot->co_teacher_id,
            'subject_id' => $slot->subject_id,
            'room_number' => $slot->room_number,
            'status' => $slot->status,
            'academic_year' => $slot->academic_year,
            'bell_timing_id' => $toBellTimingId,
            'ignore_slot_id' => $slot->id,
        ];

        $check = $this->resolver->check($trial);
        if ($check['conflict']) {
            throw new \RuntimeException("moving one of the lessons would now conflict: {$check['message']}");
        }

        $before = $slot->bell_timing_id;
        $slot->update(['bell_timing_id' => $toBellTimingId]);

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['moved_from_bell_timing_id' => $before, 'moved_to_bell_timing_id' => $toBellTimingId])
            ->log('timetable_rebalance_relocated');
    }
```

### `app/Services/Timetable/TimetableRebalanceService.php` (part 2 of 2)

#### `loadContextSlots(Collection $classSlots, string $status): Collection` — private
Every other slot (any class/section) taught by a teacher or co-teacher who also appears in the target class-section's own slots — read-only, never a candidate to move.

```php
    private function loadContextSlots(Collection $classSlots, string $status): Collection
    {
        $teacherIds = $classSlots->pluck('teacher_id')
            ->merge($classSlots->pluck('co_teacher_id'))
            ->filter()
            ->unique()
            ->values();

        if ($teacherIds->isEmpty()) {
            return collect();
        }

        return TimetableSlot::where('status', $status)
            ->whereNotIn('id', $classSlots->pluck('id'))
            ->where(fn ($q) => $q->whereIn('teacher_id', $teacherIds)->orWhereIn('co_teacher_id', $teacherIds))
            ->get(['id', 'teacher_id', 'co_teacher_id', 'bell_timing_id']);
    }
```

#### `loadPeriodMeta(?string $academicYear): array` — private
Builds a day → position-sequence index from active teaching bell timings.

```php
    private function loadPeriodMeta(?string $academicYear): array
    {
        $timings = BellTiming::active()->teachingType()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $timingMeta = [];
        $dayMeta = [];

        foreach ($timings->groupBy('day_of_week') as $day => $group) {
            $ordered = $group->sortBy('order_index')->values();
            $sequence = [];

            foreach ($ordered as $position => $timing) {
                $timingMeta[$timing->id] = ['day' => $day, 'position' => $position, 'period_name' => $timing->period_name];
                $sequence[] = $timing->id;
            }

            $dayMeta[$day] = ['sequence' => $sequence, 'max_position' => count($sequence) - 1];
        }

        return [$timingMeta, $dayMeta];
    }
```

#### `score(Collection $classSlots, Collection $contextSlots, array $timingMeta, array $dayMeta): array` — private
Deterministic, purely arithmetic breakdown across all 6 weighted components (teacher gaps, class gaps, teacher workload imbalance, prefer-morning violations, excessive consecutive runs, room switches).

```php
    private function score(Collection $classSlots, Collection $contextSlots, array $timingMeta, array $dayMeta): array
    {
        $teacherDayPositions = [];
        $addTeacherPeriod = function ($teacherId, $btId) use (&$teacherDayPositions, $timingMeta) {
            if (!$teacherId || !isset($timingMeta[$btId])) {
                return;
            }
            $meta = $timingMeta[$btId];
            $teacherDayPositions[$teacherId][$meta['day']][$meta['position']] = true;
        };

        foreach ($classSlots as $slot) {
            $addTeacherPeriod($slot->teacher_id, $slot->bell_timing_id);
            if ($slot->co_teacher_id) {
                $addTeacherPeriod($slot->co_teacher_id, $slot->bell_timing_id);
            }
        }
        foreach ($contextSlots as $slot) {
            $addTeacherPeriod($slot->teacher_id, $slot->bell_timing_id);
            if ($slot->co_teacher_id) {
                $addTeacherPeriod($slot->co_teacher_id, $slot->bell_timing_id);
            }
        }

        $classDayPositions = [];
        foreach ($classSlots as $slot) {
            if (!isset($timingMeta[$slot->bell_timing_id])) {
                continue;
            }
            $meta = $timingMeta[$slot->bell_timing_id];
            $classDayPositions[$meta['day']][$meta['position']] = $slot;
        }

        $teacherGapPeriods = 0;
        $teacherWorkloadImbalance = 0;
        $excessiveConsecutivePeriods = 0;

        foreach ($teacherDayPositions as $days) {
            $dayCounts = [];
            foreach ($days as $day => $positions) {
                $positionKeys = array_keys($positions);
                sort($positionKeys);
                $dayCounts[$day] = count($positionKeys);

                if (count($positionKeys) >= 2) {
                    $span = end($positionKeys) - $positionKeys[0] + 1;
                    $teacherGapPeriods += $span - count($positionKeys);
                }

                $excessiveConsecutivePeriods += $this->penalizeConsecutiveRuns($positionKeys);
            }

            if (count($dayCounts) >= 2) {
                $teacherWorkloadImbalance += max($dayCounts) - min($dayCounts);
            }
        }

        $classGapPeriods = 0;
        $roomSwitchCount = 0;
        $anyRoomData = $classSlots->contains(fn ($s) => !empty($s->room_number));

        foreach ($classDayPositions as $positions) {
            $positionKeys = array_keys($positions);
            sort($positionKeys);

            if (count($positionKeys) >= 2) {
                $span = end($positionKeys) - $positionKeys[0] + 1;
                $classGapPeriods += $span - count($positionKeys);
            }

            if ($anyRoomData) {
                for ($i = 1; $i < count($positionKeys); $i++) {
                    if ($positionKeys[$i] === $positionKeys[$i - 1] + 1) {
                        $prevRoom = $positions[$positionKeys[$i - 1]]->room_number;
                        $curRoom = $positions[$positionKeys[$i]]->room_number;
                        if ($prevRoom && $curRoom && $prevRoom !== $curRoom) {
                            $roomSwitchCount++;
                        }
                    }
                }
            }
        }

        $preferMorningViolations = 0;
        foreach ($classSlots as $slot) {
            if (!$slot->subject || !$slot->subject->prefer_morning) {
                continue;
            }
            $meta = $timingMeta[$slot->bell_timing_id] ?? null;
            if (!$meta) {
                continue;
            }
            $maxPosition = $dayMeta[$meta['day']]['max_position'] ?? 0;
            if ($maxPosition > 0 && $meta['position'] > intdiv($maxPosition, 2)) {
                $preferMorningViolations++;
            }
        }

        $breakdown = [
            'teacher_gap_periods' => $teacherGapPeriods,
            'class_gap_periods' => $classGapPeriods,
            'teacher_workload_imbalance' => $teacherWorkloadImbalance,
            'prefer_morning_violations' => $preferMorningViolations,
            'excessive_consecutive_periods' => $excessiveConsecutivePeriods,
            'room_switch_count' => $roomSwitchCount,
        ];

        $total = 0;
        foreach ($breakdown as $key => $count) {
            $total += $count * self::WEIGHTS[$key];
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }
```

#### `penalizeConsecutiveRuns(array $sortedPositions): int` — private

```php
    private function penalizeConsecutiveRuns(array $sortedPositions): int
    {
        $penalty = 0;
        $runLength = 1;

        for ($i = 1; $i < count($sortedPositions); $i++) {
            if ($sortedPositions[$i] === $sortedPositions[$i - 1] + 1) {
                $runLength++;
            } else {
                $penalty += max(0, $runLength - self::CONSECUTIVE_THRESHOLD);
                $runLength = 1;
            }
        }
        $penalty += max(0, $runLength - self::CONSECUTIVE_THRESHOLD);

        return $penalty;
    }
```

#### `findBestCandidate(...): ?array` — private
One greedy iteration: the single best-scoring legal movement (swap or relocation) among the untouched candidate slots, or null if none improves the score. `$evaluated`/`$budgetExhausted` are shared by reference across the whole `analyze()` call.

```php
    private function findBestCandidate(
        Collection $candidateSlotIds,
        Collection $working,
        Collection $contextSlots,
        array $timingMeta,
        array $dayMeta,
        array $currentScore,
        Collection $candidatePeriods,
        ?string $academicYear,
        int &$evaluated,
        int $maxEvaluations,
        bool &$budgetExhausted
    ): ?array {
        $best = null;
        $occupiedBellTimingIds = $working->pluck('bell_timing_id')->all();

        foreach ($candidateSlotIds as $slotId) {
            if ($evaluated >= $maxEvaluations) {
                $budgetExhausted = true;
                break;
            }

            $slot = $working->firstWhere('id', $slotId);
            if (!$slot) {
                continue;
            }

            foreach ($candidateSlotIds as $otherId) {
                if ($otherId <= $slotId) {
                    continue; // unordered pair, evaluate once, deterministic by ascending id
                }
                if ($evaluated >= $maxEvaluations) {
                    $budgetExhausted = true;
                    break;
                }
                $evaluated++;

                $swapCheck = $this->suggestions->checkSwap($slotId, $otherId);
                if (!$swapCheck['ok']) {
                    continue;
                }

                $simulated = $this->withSwappedSlots($working, $slotId, $otherId);
                $newScore = $this->score($simulated, $contextSlots, $timingMeta, $dayMeta);
                $delta = $currentScore['total'] - $newScore['total'];

                if ($delta > 0 && ($best === null || $delta > $best['delta'])) {
                    $best = ['delta' => $delta, 'type' => 'swap', 'slot_a_id' => $slotId, 'slot_b_id' => $otherId, 'simulated' => $simulated, 'score' => $newScore];
                }
            }

            if ($budgetExhausted) {
                break;
            }

            foreach ($candidatePeriods as $period) {
                if ($evaluated >= $maxEvaluations) {
                    $budgetExhausted = true;
                    break;
                }
                if ((int) $period->id === (int) $slot->bell_timing_id) {
                    continue;
                }
                if (in_array($period->id, $occupiedBellTimingIds, true)) {
                    continue; // occupied by another slot of this class/section -- that needs a swap, not a relocation
                }
                $evaluated++;

                $trial = [
                    'school_class_id' => $slot->school_class_id,
                    'section_id' => $slot->section_id,
                    'teacher_id' => $slot->teacher_id,
                    'co_teacher_id' => $slot->co_teacher_id,
                    'subject_id' => $slot->subject_id,
                    'room_number' => $slot->room_number,
                    'status' => $slot->status,
                    'academic_year' => $academicYear,
                    'bell_timing_id' => $period->id,
                    'ignore_slot_id' => $slot->id,
                ];

                if ($this->resolver->check($trial)['conflict']) {
                    continue;
                }

                $simulated = $this->withMovedSlot($working, $slotId, $period->id);
                $newScore = $this->score($simulated, $contextSlots, $timingMeta, $dayMeta);
                $delta = $currentScore['total'] - $newScore['total'];

                if ($delta > 0 && ($best === null || $delta > $best['delta'])) {
                    $best = ['delta' => $delta, 'type' => 'relocate', 'slot_id' => $slotId, 'to_bell_timing_id' => $period->id, 'simulated' => $simulated, 'score' => $newScore];
                }
            }

            if ($budgetExhausted) {
                break;
            }
        }

        return $best;
    }
```

#### `withMovedSlot(Collection $classSlots, int $slotId, int $newBellTimingId): Collection` — private
Unsaved Eloquent clone for in-memory score comparison only — never persisted.

```php
    private function withMovedSlot(Collection $classSlots, int $slotId, int $newBellTimingId): Collection
    {
        return $classSlots->map(function ($slot) use ($slotId, $newBellTimingId) {
            if ($slot->id !== $slotId) {
                return $slot;
            }
            $clone = $slot->replicate();
            $clone->id = $slot->id;
            $clone->bell_timing_id = $newBellTimingId;
            $clone->setRelation('subject', $slot->subject);

            return $clone;
        });
    }
```

#### `withSwappedSlots(Collection $classSlots, int $slotIdA, int $slotIdB): Collection` — private

```php
    private function withSwappedSlots(Collection $classSlots, int $slotIdA, int $slotIdB): Collection
    {
        $btA = $classSlots->firstWhere('id', $slotIdA)->bell_timing_id;
        $btB = $classSlots->firstWhere('id', $slotIdB)->bell_timing_id;

        return $classSlots->map(function ($slot) use ($slotIdA, $slotIdB, $btA, $btB) {
            if ($slot->id === $slotIdA) {
                $clone = $slot->replicate();
                $clone->id = $slot->id;
                $clone->bell_timing_id = $btB;
                $clone->setRelation('subject', $slot->subject);

                return $clone;
            }
            if ($slot->id === $slotIdB) {
                $clone = $slot->replicate();
                $clone->id = $slot->id;
                $clone->bell_timing_id = $btA;
                $clone->setRelation('subject', $slot->subject);

                return $clone;
            }

            return $slot;
        });
    }
```

#### `describeMovement(array $candidate, Collection $originalSlots, array $beforeBreakdown): array` — private
Builds the human-readable movement entry the UI renders, citing exactly which score components changed.

```php
    private function describeMovement(array $candidate, Collection $originalSlots, array $beforeBreakdown): array
    {
        $afterBreakdown = $candidate['score']['breakdown'];
        $componentDeltas = [];
        foreach ($beforeBreakdown as $key => $before) {
            $after = $afterBreakdown[$key] ?? $before;
            if ($after !== $before) {
                $label = ucfirst(str_replace('_', ' ', $key));
                $componentDeltas[] = sprintf('%s %s%d', $label, $after < $before ? '-' : '+', abs($after - $before));
            }
        }
        $reason = empty($componentDeltas)
            ? "Improves overall schedule quality by {$candidate['delta']} point(s)."
            : implode('; ', $componentDeltas) . " (net improvement: {$candidate['delta']} point(s))";

        if ($candidate['type'] === 'swap') {
            $a = $originalSlots->firstWhere('id', $candidate['slot_a_id']);
            $b = $originalSlots->firstWhere('id', $candidate['slot_b_id']);

            return [
                'type' => 'swap',
                'slot_a_id' => $a->id,
                'slot_b_id' => $b->id,
                'a_subject' => $a->subject->name ?? null,
                'a_teacher' => $a->teacher->name ?? null,
                'a_from' => $this->describePeriod($a->bellTiming),
                'a_to' => $this->describePeriodById($b->bell_timing_id),
                'b_subject' => $b->subject->name ?? null,
                'b_teacher' => $b->teacher->name ?? null,
                'b_from' => $this->describePeriod($b->bellTiming),
                'b_to' => $this->describePeriodById($a->bell_timing_id),
                'delta' => $candidate['delta'],
                'reason' => "Swap: {$reason}",
            ];
        }

        $slot = $originalSlots->firstWhere('id', $candidate['slot_id']);
        $toTiming = BellTiming::find($candidate['to_bell_timing_id']);

        return [
            'type' => 'relocate',
            'slot_id' => $slot->id,
            'to_bell_timing_id' => $candidate['to_bell_timing_id'],
            'subject' => $slot->subject->name ?? null,
            'teacher' => $slot->teacher->name ?? null,
            'from' => $this->describePeriod($slot->bellTiming),
            'to' => $toTiming ? "{$toTiming->day_of_week} {$toTiming->period_name}" : null,
            'delta' => $candidate['delta'],
            'reason' => "Relocate: {$reason}",
        ];
    }
```

#### `describePeriod(?BellTiming $timing): ?string` / `describePeriodById(int $bellTimingId): ?string` / `emptyResult(string $message): array` — private

```php
    private function describePeriod(?BellTiming $timing): ?string
    {
        return $timing ? "{$timing->day_of_week} {$timing->period_name}" : null;
    }

    private function describePeriodById(int $bellTimingId): ?string
    {
        $timing = BellTiming::find($bellTimingId);

        return $timing ? "{$timing->day_of_week} {$timing->period_name}" : null;
    }

    private function emptyResult(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'baseline_score' => null,
            'proposed_score' => null,
            'movements' => [],
            'locked_excluded_count' => 0,
            'evaluated_candidates' => 0,
            'budget_exhausted' => false,
        ];
    }
}
```

*(end of TimetableRebalanceService.php — full 756-line file now completely reproduced across parts 1–2 above)*

### `app/Services/Timetable/FeasibilityService.php` (complete — 416 lines)

Class purpose: T1b read-only feasibility report — grid capacity per class-section, teacher load, a live conflict scan (proving the DB constraints hold), and class-teacher readiness notes. Never modified by any commit in this session's hardening lineage; documented here for completeness only.

#### `build(?string $academicYear): array` — public

```php
class FeasibilityService
{
    public function build(?string $academicYear): array
    {
        $activeTimings = BellTiming::query()
            ->where('is_active', true)
            ->teachingType()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $slots = TimetableSlot::with(['schoolClass', 'section', 'bellTiming', 'teacher', 'subject'])
            ->published() // T4b: the feasibility report is about the LIVE timetable, never a draft proposal
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        return [
            'academic_year' => $academicYear,
            'grid_capacity' => $this->gridCapacity($activeTimings, $slots),
            'teacher_load' => $this->teacherLoad($activeTimings, $slots),
            'conflicts' => $this->conflictScan($academicYear),
            'class_teacher_readiness' => $this->classTeacherReadiness(),
            'threshold' => (int) config('timetable.max_periods_per_week', 36),
        ];
    }
```

#### `classTeacherReadiness(): array` — private
Classes with no designated class-teacher at all (neither an `is_class_teacher`-flagged assignment nor an active legacy `ClassTeacherAssignment` row) — a plain readiness note, not an error.

```php
    private function classTeacherReadiness(): array
    {
        $classes = SchoolClass::active()->orderByOrder()->get();

        $flaggedClassIds = TeacherClassSubjectAssignment::whereIn('class_id', $classes->pluck('id'))
            ->where('is_class_teacher', true)
            ->pluck('class_id')
            ->unique();

        $legacyClassNames = ClassTeacherAssignment::whereIn('assigned_class', $classes->pluck('name'))
            ->active()
            ->get()
            ->filter(fn (ClassTeacherAssignment $a) => $a->isCurrentlyAssigned())
            ->pluck('assigned_class')
            ->unique();

        $rows = [];
        foreach ($classes as $class) {
            if ($flaggedClassIds->contains($class->id) || $legacyClassNames->contains($class->name)) {
                continue;
            }

            $rows[] = [
                'school_class_id' => $class->id,
                'class_name' => $class->name,
                'sentence' => "{$class->name} has no class teacher assigned.",
            ];
        }

        return $rows;
    }
```

#### `gridCapacity(Collection $activeTimings, Collection $slots): array` — private
Per-class capacity broken down by whichever `section_id` values actually appear in that class's placed slots.

```php
    private function gridCapacity(Collection $activeTimings, Collection $slots): array
    {
        $classes = SchoolClass::active()->orderByOrder()->get();
        $assignments = TeacherClassSubjectAssignment::whereIn('class_id', $classes->pluck('id'))->get();
        $rows = [];

        foreach ($classes as $class) {
            $classCapacity = $activeTimings
                ->filter(fn (BellTiming $t) => $t->class_section === null || $t->class_section === $class->name)
                ->filter(fn (BellTiming $t) => ! $class->last_teaching_period || $t->order_index <= $class->last_teaching_period)
                ->count();

            // A combined group's slots naturally count once per member
            // class here already -- each member class gets exactly one
            // TimetableSlot row of its own, so no dedup is needed on this
            // side (contrast with teacherLoad(), which must dedup by
            // combined_class_group_id).
            $classSlots = $slots->where('school_class_id', $class->id);
            $classAssignments = $assignments->where('class_id', $class->id);

            $sectionGroups = $classSlots->groupBy('section_id');

            if ($sectionGroups->isEmpty()) {
                $required = $this->requiredPeriods($classAssignments, null);
                $rows[] = $this->gridRow($class, null, $classCapacity, 0, $required);
                continue;
            }

            foreach ($sectionGroups as $sectionId => $sectionSlots) {
                $section = $sectionId ? Section::find($sectionId) : null;
                $required = $this->requiredPeriods($classAssignments, $sectionId);
                $rows[] = $this->gridRow($class, $section, $classCapacity, $sectionSlots->count(), $required);
            }
        }

        return $rows;
    }
```

#### `requiredPeriods(Collection $classAssignments, $sectionId): int` — private

```php
    private function requiredPeriods(Collection $classAssignments, $sectionId): int
    {
        return (int) $classAssignments
            ->filter(fn (TeacherClassSubjectAssignment $a) => $a->section_id === null || $a->section_id === $sectionId)
            ->sum('periods_per_week');
    }
```

#### `gridRow(SchoolClass $class, ?Section $section, int $capacity, int $placed, int $required = 0): array` — private

```php
    private function gridRow(SchoolClass $class, ?Section $section, int $capacity, int $placed, int $required = 0): array
    {
        $label = $section ? "{$class->name}{$section->name}" : $class->name;
        $empty = max(0, $capacity - $placed);

        if ($capacity === 0) {
            $sentence = "{$label} has no active teaching periods configured for this academic year.";
        } elseif ($required > $capacity) {
            $sentence = "{$label} requires {$required} periods but the week has {$capacity}.";
        } elseif ($placed === 0) {
            $sentence = "{$label} has 0 of {$capacity} periods placed -- the whole week is empty.";
        } elseif ($empty > 0) {
            $sentence = "{$label} has {$empty} empty " . ($empty === 1 ? 'period' : 'periods') . " out of {$capacity}.";
        } else {
            $sentence = "{$label} is fully placed ({$placed} of {$capacity} periods).";
        }

        return [
            'school_class_id' => $class->id,
            'class_name' => $class->name,
            'section_id' => $section?->id,
            'section_name' => $section?->name,
            'label' => $label,
            'capacity' => $capacity,
            'placed' => $placed,
            'empty' => $empty,
            'required' => $required,
            'over_required' => $required > $capacity,
            'sentence' => $sentence,
        ];
    }
```

#### `teacherLoad(Collection $activeTimings, Collection $slots): array` — private
Per-teacher placed/required/available periods, busiest day, days with zero free periods. Counts a teacher's own slots whether they're `teacher_id` or `co_teacher_id`, and collapses a combined group's N member rows back to the single period they represent.

```php
    private function teacherLoad(Collection $activeTimings, Collection $slots): array
    {
        $operatingDays = $activeTimings->pluck('day_of_week')->unique()->values();
        $threshold = (int) config('timetable.max_periods_per_week', 36);
        $activeTimingIds = $activeTimings->pluck('id');

        $teachers = Teacher::active()->orderBy('name')->get();
        $requiredByTeacher = TeacherClassSubjectAssignment::whereIn('teacher_id', $teachers->pluck('id'))
            ->selectRaw('teacher_id, SUM(periods_per_week) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');
        // T6 item 4 fix: a co-teacher's own required periods were invisible
        // here before -- an assignment's periods_per_week was only ever
        // summed against the PRIMARY teacher_id.
        $requiredByCoTeacher = TeacherClassSubjectAssignment::whereIn('co_teacher_id', $teachers->pluck('id'))
            ->selectRaw('co_teacher_id, SUM(periods_per_week) as total')
            ->groupBy('co_teacher_id')
            ->pluck('total', 'co_teacher_id');
        $blockedByTeacher = TeacherAvailability::whereIn('teacher_id', $teachers->pluck('id'))
            ->where('is_available', false)
            ->whereIn('bell_timing_id', $activeTimingIds)
            ->selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $rows = [];

        foreach ($teachers as $teacher) {
            // T6 item 4 fix: a team-taught slot lists this teacher in
            // EITHER teacher_id or co_teacher_id -- both must count toward
            // their own load, or a co-teacher's periods were invisible to
            // their own row entirely.
            $teacherSlots = $slots->filter(
                fn (TimetableSlot $s) => $s->teacher_id === $teacher->id || $s->co_teacher_id === $teacher->id
            );

            // A combined-class group writes one TimetableSlot row per
            // member class, all for the SAME period -- collapse those
            // back to the single period they represent on this teacher's
            // day before counting anything. Solo rows (combined_class_
            // group_id null) each keep their own identity via their own
            // row id, so they're never accidentally collapsed together.
            $distinctTeacherSlots = $teacherSlots->unique(
                fn (TimetableSlot $s) => $s->combined_class_group_id ?? 'solo_' . $s->id
            );

            $placed = $distinctTeacherSlots->count();

            $byDay = $distinctTeacherSlots->groupBy(fn (TimetableSlot $s) => $s->bellTiming?->day_of_week);

            $busiestDay = null;
            $busiestCount = 0;
            foreach ($byDay as $day => $daySlots) {
                if ($day !== null && $daySlots->count() > $busiestCount) {
                    $busiestDay = $day;
                    $busiestCount = $daySlots->count();
                }
            }

            $daysWithZeroFreePeriods = 0;
            foreach ($operatingDays as $day) {
                $dayCapacity = $activeTimings->where('day_of_week', $day)->count();
                $dayPlaced = $byDay->get($day, collect())->count();
                if ($dayCapacity > 0 && $dayPlaced >= $dayCapacity) {
                    $daysWithZeroFreePeriods++;
                }
            }

            $overThreshold = $placed > $threshold;

            $required = (int) ($requiredByTeacher[$teacher->id] ?? 0) + (int) ($requiredByCoTeacher[$teacher->id] ?? 0);
            $available = $activeTimingIds->count() - (int) ($blockedByTeacher[$teacher->id] ?? 0);
            $overAvailable = $required > $available;

            if ($overAvailable) {
                $sentence = "{$teacher->name} requires {$required} periods but is available for only {$available}.";
            } elseif ($placed === 0) {
                $sentence = "{$teacher->name} has no periods placed.";
            } elseif ($overThreshold) {
                $sentence = "{$teacher->name} is placed for {$placed} periods but the week has {$threshold} slots.";
            } else {
                $sentence = "{$teacher->name} is placed for {$placed} periods" .
                    ($busiestDay ? ", busiest on {$busiestDay} ({$busiestCount})." : '.');
            }

            $rows[] = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->name,
                'placed_periods' => $placed,
                'busiest_day' => $busiestDay,
                'busiest_day_count' => $busiestCount,
                'days_with_zero_free_periods' => $daysWithZeroFreePeriods,
                'over_threshold' => $overThreshold,
                'required_periods' => $required,
                'available_periods' => $available,
                'over_available' => $overAvailable,
                'sentence' => $sentence,
            ];
        }

        return $rows;
    }
```

#### `conflictScan(?string $academicYear): array` — private
Live proof the DB uniqueness constraints hold (duplicate-key scan, should always be empty), the T1a class-wide/section-specific overlap gap the DB index itself can't catch, plus inactive-reference checks.

```php
    private function conflictScan(?string $academicYear): array
    {
        $conflicts = [];

        // These predate the T1a unique constraints and should now be
        // structurally impossible -- this query proves it, live, rather
        // than just trusting the migration ran.
        $classDupes = TimetableSlot::query()
            ->published()
            ->select('school_class_id', 'section_id', 'bell_timing_id')
            ->selectRaw('count(*) as c')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->groupBy('school_class_id', 'section_id', 'bell_timing_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($classDupes as $dupe) {
            $conflicts[] = [
                'type' => 'class_duplicate',
                'sentence' => "Class/section {$dupe->school_class_id}/{$dupe->section_id} has {$dupe->c} slots at the same period (bell timing {$dupe->bell_timing_id}) -- this should be impossible after T1a.",
            ];
        }

        $teacherDupes = TimetableSlot::query()
            ->published()
            ->select('teacher_id', 'bell_timing_id')
            ->selectRaw('count(*) as c')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->groupBy('teacher_id', 'bell_timing_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($teacherDupes as $dupe) {
            $conflicts[] = [
                'type' => 'teacher_duplicate',
                'sentence' => "Teacher {$dupe->teacher_id} has {$dupe->c} slots at the same period (bell timing {$dupe->bell_timing_id}) -- this should be impossible after T1a.",
            ];
        }

        $slots = TimetableSlot::with(['teacher', 'subject', 'schoolClass'])
            ->published()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        // T1a's own documented gap, closed here: a class-wide slot
        // (section_id NULL) and a specific-section slot of the SAME class
        // at the SAME period have different section_id values, so the
        // exact-match groupBy above (and the DB unique index it mirrors)
        // never sees them as the same key -- they still overlap in
        // practice (a class-wide lesson covers every section). Grouped
        // in-memory off the already-loaded $slots rather than a second
        // query.
        $byClassAndPeriod = $slots->groupBy(fn ($slot) => $slot->school_class_id . '|' . $slot->bell_timing_id);
        foreach ($byClassAndPeriod as $key => $rows) {
            $classWide = $rows->firstWhere('section_id', null);
            $sectionSpecific = $rows->first(fn ($r) => $r->section_id !== null);
            if ($classWide && $sectionSpecific) {
                [$classId, $bellTimingId] = explode('|', $key);
                $className = $classWide->schoolClass->name ?? "Class {$classId}";
                $sectionName = $sectionSpecific->section->name ?? 'section-specific';
                $conflicts[] = [
                    'type' => 'class_wide_section_overlap',
                    'sentence' => "{$className} has both a whole-class slot and a {$sectionName} slot at the same period (bell timing {$bellTimingId}) -- these overlap even though they aren't an exact duplicate.",
                ];
            }
        }

        foreach ($slots as $slot) {
            if (!$slot->teacher || $slot->teacher->status !== 'active') {
                $conflicts[] = [
                    'type' => 'inactive_teacher',
                    'sentence' => "Timetable slot #{$slot->id} references " . ($slot->teacher ? "an inactive teacher ({$slot->teacher->name})" : 'a teacher that no longer exists') . '.',
                ];
            }
            if (!$slot->subject || !$slot->subject->is_active) {
                $conflicts[] = [
                    'type' => 'inactive_subject',
                    'sentence' => "Timetable slot #{$slot->id} references " . ($slot->subject ? "an inactive subject ({$slot->subject->name})" : 'a subject that no longer exists') . '.',
                ];
            }
            if (!$slot->schoolClass || !$slot->schoolClass->is_active) {
                $conflicts[] = [
                    'type' => 'inactive_class',
                    'sentence' => "Timetable slot #{$slot->id} references " . ($slot->schoolClass ? "an inactive class ({$slot->schoolClass->name})" : 'a class that no longer exists') . '.',
                ];
            }
        }

        return $conflicts;
    }
}
```

*(end of FeasibilityService.php — all 9 Timetable services now fully documented: GeneratorService, TimetableAutoFixService, TimetableConflictResolver, TimetableSwapService, TimetableRebalanceService, SubstituteFinderService, SubstitutionDashboardService, TimetableSuggestionService, FeasibilityService)*

### `app/Http/Controllers/Admin/TeacherSubstitutionController.php` (complete — 479 lines, part 1 of 2)

Class purpose: substitution CRUD, assignment workflow (assign/approve/cancel), the "Teacher absent today" flow with ranked substitute suggestions, HR-leave integration, and the daily arrangement-sheet PDF.

```php
class TeacherSubstitutionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', TeacherSubstitution::class);

        $query = TeacherSubstitution::with(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'bellTiming']);

        // Filter by date
        if ($request->filled('date')) {
            $query->forDate($request->date);
        } else {
            $query->forDate(now()->format('Y-m-d')); // Default to today
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by class
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by teacher
        if ($request->filled('teacher_id')) {
            $query->forTeacher($request->teacher_id);
        }

        $substitutions = $query->join('bell_timings', 'teacher_substitutions.bell_timing_id', '=', 'bell_timings.id')
            ->orderBy('bell_timings.order_index')
            ->select('teacher_substitutions.*')
            ->paginate(20);

        // Get filters for the view
        $classes = SchoolClass::orderBy('name')->get();
        $teachers = Teacher::with('user')->orderBy('id')->get();
        $statuses = ['pending' => 'Pending', 'assigned' => 'Assigned', 'approved' => 'Approved', 'cancelled' => 'Cancelled'];

        return view('admin.teacher-substitutions.index', compact('substitutions', 'classes', 'teachers', 'statuses'));
    }

    public function create()
    {
        $this->authorize('create', TeacherSubstitution::class);

        $teachers = Teacher::with('user')->orderBy('id')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $bellTimings = BellTiming::teachingType()->where('is_active', true)->orderBy('order_index')->get();

        return view('admin.teacher-substitutions.create', compact('teachers', 'classes', 'sections', 'subjects', 'bellTimings'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', TeacherSubstitution::class);

        $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $substitution = TeacherSubstitution::create([
            'substitution_date' => $request->substitution_date,
            'absent_teacher_id' => $request->absent_teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'bell_timing_id' => $request->bell_timing_id,
            'reason' => $request->reason,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        // Automatically suggest substitutes
        $this->suggestSubstitutes($substitution);

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution record created successfully.');
    }

    public function show(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('view', $teacherSubstitution);

        $teacherSubstitution->load(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'createdBy', 'updatedBy']);

        return view('admin.teacher-substitutions.show', compact('teacherSubstitution'));
    }

    public function edit(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('update', $teacherSubstitution);

        $teacherSubstitution->load(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'bellTiming']);

        $teachers = Teacher::with('user')->orderBy('id')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $bellTimings = BellTiming::teachingType()->where('is_active', true)->orderBy('order_index')->get();

        return view('admin.teacher-substitutions.edit', compact(
            'teacherSubstitution',
            'teachers',
            'classes',
            'sections',
            'subjects',
            'bellTimings'
        ));
    }

    public function update(Request $request, TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('update', $teacherSubstitution);

        $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'status' => 'required|in:pending,assigned,approved,cancelled',
            'substitute_teacher_id' => 'nullable|exists:teachers,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $teacherSubstitution->update([
            'substitution_date' => $request->substitution_date,
            'absent_teacher_id' => $request->absent_teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'bell_timing_id' => $request->bell_timing_id,
            'status' => $request->status,
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'reason' => $request->reason,
            'updated_by' => Auth::id(),
        ]);

        if ($request->status === 'assigned' || $request->status === 'approved') {
            $teacherSubstitution->assigned_at = now();
            $teacherSubstitution->save();
        }

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution updated successfully.');
    }

    public function destroy(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('delete', $teacherSubstitution);

        $teacherSubstitution->delete();

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution deleted successfully.');
    }

    /**
     * T3 item 2: real scoring via SubstituteFinderService, replacing the
     * former stub implementation (calculateSubjectMatchScore always
     * returned 0, hasClassExperience always returned false).
     */
    public function suggestSubstitutes(TeacherSubstitution $substitution)
    {
        $substitution->loadMissing(['bellTiming', 'class', 'subject']);
        $candidates = (new SubstituteFinderService())->findCandidatesForSubstitution($substitution);

        // Auto-suggest the top-ranked candidate; stays pending for admin review.
        if (!empty($candidates)) {
            $substitution->update([
                'substitute_teacher_id' => $candidates[0]['teacher']->id,
                'status' => 'pending',
            ]);
        }

        return $candidates;
    }

    public function assignSubstitute(Request $request, TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('assignSubstitute', $teacherSubstitution);

        $request->validate([
            'substitute_teacher_id' => 'required|exists:teachers,id'
        ]);

        $teacherSubstitution->update([
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute teacher assigned successfully.');
    }

    public function approveSubstitute(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('approveSubstitute', $teacherSubstitution);

        $teacherSubstitution->update([
            'status' => 'approved',
            'assigned_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment approved successfully.');
    }

    public function cancelSubstitute(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('cancelSubstitute', $teacherSubstitution);

        $teacherSubstitution->update([
            'status' => 'cancelled',
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment cancelled successfully.');
    }

    public function today()
    {
        $this->authorize('viewTodaySubstitutions', TeacherSubstitution::class);

        $substitutions = TeacherSubstitution::with(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'bellTiming'])
            ->forDate(now())
            ->get()
            ->sortBy(fn (TeacherSubstitution $s) => $s->bellTiming?->order_index ?? PHP_INT_MAX)
            ->values();

        return view('admin.teacher-substitutions.today', compact('substitutions'));
    }

    public function absenceOverview()
    {
        $this->authorize('viewAbsenceOverview', TeacherSubstitution::class);

        $absentTeachers = Teacher::whereHas('absentSubstitutions', function($query) {
            $query->forDate(now())->whereNotNull('absent_teacher_id');
        })
        ->with(['absentSubstitutions' => function($query) {
            $query->forDate(now())->with(['class', 'section', 'subject']);
        }])
        ->get();

        $substitutedTeachers = Teacher::whereHas('substituteSubstitutions', function($query) {
            $query->forDate(now())->whereNotNull('substitute_teacher_id');
        })
        ->with(['substituteSubstitutions' => function($query) {
            $query->forDate(now())->with(['class', 'section', 'subject']);
        }])
        ->get();

        return view('admin.teacher-substitutions.absence-overview', compact(
            'absentTeachers', 
            'substitutedTeachers'
        ));
    }

    public function substitutionRules()
    {
        $this->authorize('manageRules', TeacherSubstitution::class);

        // Return view for managing substitution rules
        return view('admin.teacher-substitutions.rules');
    }
```

### `app/Http/Controllers/Admin/TeacherSubstitutionController.php` (part 2 of 2)

```php
    /**
     * T3 item 3: "Teacher absent today" flow -- pick a teacher + date,
     * see their day's timetable slots, and a ranked list of substitute
     * suggestions per slot that doesn't already have one recorded.
     */
    public function absentToday(Request $request)
    {
        $this->authorize('manageAbsentToday', TeacherSubstitution::class);

        $teachers = Teacher::active()->orderBy('name')->get();
        $selectedTeacherId = $request->integer('teacher_id') ?: null;
        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

        // T3 item 5: HR leave integration -- read-only. Teachers with an
        // approved TeacherLeave covering the selected date are surfaced
        // as one-click shortcuts into this same flow, so admin doesn't
        // have to separately know who's on leave before setting up
        // substitutions for them. Nothing is written to teacher_leaves.
        $teachersOnApprovedLeave = Teacher::whereHas('leaves', function ($q) use ($date) {
            $q->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date);
        })->orderBy('name')->get();

        $selectedTeacher = null;
        $rows = [];

        if ($selectedTeacherId) {
            $selectedTeacher = Teacher::findOrFail($selectedTeacherId);
            $dayOfWeek = $date->format('l');

            // T4b: only the live timetable counts as "this teacher's actual
            // slot today" -- a draft proposal isn't a real commitment yet.
            $timetableSlots = TimetableSlot::with(['bellTiming', 'schoolClass', 'section', 'subject'])
                ->published()
                ->where('teacher_id', $selectedTeacherId)
                ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
                ->get()
                ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
                ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
                ->values();

            $service = new SubstituteFinderService();

            foreach ($timetableSlots as $slot) {
                $existing = TeacherSubstitution::with('substituteTeacher')
                    ->where('absent_teacher_id', $selectedTeacherId)
                    ->where('bell_timing_id', $slot->bell_timing_id)
                    ->whereDate('substitution_date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->first();

                $candidates = $existing || !$slot->schoolClass || !$slot->subject
                    ? []
                    : $service->findCandidates($slot->bellTiming, $date, $slot->schoolClass, $slot->subject, $selectedTeacherId);

                $rows[] = [
                    'slot' => $slot,
                    'existing' => $existing,
                    'candidates' => array_slice($candidates, 0, 5),
                ];
            }
        }

        return view('admin.teacher-substitutions.absent-today', compact('teachers', 'selectedTeacher', 'date', 'rows', 'teachersOnApprovedLeave'));
    }

    /**
     * T3 item 3: one-click assign from the absent-today flow -- records
     * the substitution and assigns the chosen candidate in one step
     * (skips the separate "create then suggest then assign" path the
     * manual form uses).
     */
    public function assignFromSlot(Request $request)
    {
        $this->authorize('manageAbsentToday', TeacherSubstitution::class);

        $validated = $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'substitute_teacher_id' => 'required|exists:teachers,id',
        ]);

        $alreadyRecorded = TeacherSubstitution::where('absent_teacher_id', $validated['absent_teacher_id'])
            ->where('bell_timing_id', $validated['bell_timing_id'])
            ->whereDate('substitution_date', $validated['substitution_date'])
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($alreadyRecorded) {
            return redirect()->route('admin.teacher-substitutions.absent-today', [
                'teacher_id' => $validated['absent_teacher_id'],
                'date' => $validated['substitution_date'],
            ])->with('error', 'A substitution for this teacher and period is already recorded.');
        }

        TeacherSubstitution::create([
            'substitution_date' => $validated['substitution_date'],
            'absent_teacher_id' => $validated['absent_teacher_id'],
            'substitute_teacher_id' => $validated['substitute_teacher_id'],
            'class_id' => $validated['class_id'],
            'section_id' => $validated['section_id'] ?? null,
            'subject_id' => $validated['subject_id'],
            'bell_timing_id' => $validated['bell_timing_id'],
            'status' => 'assigned',
            'assigned_at' => now(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.teacher-substitutions.absent-today', [
            'teacher_id' => $validated['absent_teacher_id'],
            'date' => $validated['substitution_date'],
        ])->with('success', 'Substitute assigned.');
    }

    /**
     * T3 item 4: the daily "arrangement sheet" -- a period x class grid
     * of ONLY the substitution changes for one day (not the whole
     * timetable), the sheet on the principal's desk at 8am. Periods are
     * that date's day-of-week active bell timings; classes are only the
     * ones with an actual change that day (an unaffected class doesn't
     * need a row).
     */
    public function arrangementSheetPdf(Request $request)
    {
        $this->authorize('viewAny', TeacherSubstitution::class);

        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
        $dayOfWeek = $date->format('l');

        $substitutions = TeacherSubstitution::with(['bellTiming', 'class', 'section', 'subject', 'absentTeacher', 'substituteTeacher'])
            ->whereDate('substitution_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($substitutions->isEmpty()) {
            return back()->with('error', "No substitutions recorded for {$date->format('d M Y')} -- nothing to print yet.");
        }

        $periods = BellTiming::where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->teachingType()
            ->orderBy('order_index')
            ->pluck('period_name')
            ->unique()
            ->values()
            ->all();

        $classes = $substitutions->pluck('class')->filter()->unique('id')->sortBy('class_order')->values();

        // [class_id][period_name] => substitution
        $grid = [];
        foreach ($substitutions as $substitution) {
            if (!$substitution->bellTiming) {
                continue;
            }
            $grid[$substitution->class_id][$substitution->bellTiming->period_name] = $substitution;
        }

        $pdf = Pdf::loadView('admin.teacher-substitutions.pdf.arrangement-sheet', [
            'date' => $date,
            'periods' => $periods,
            'classes' => $classes,
            'grid' => $grid,
        ]);
        $pdf->setPaper('A4', 'landscape');

        $safeDate = $date->format('Y-m-d');

        return $pdf->download("arrangement_sheet_{$safeDate}.pdf");
    }
}
```

*(end of TeacherSubstitutionController.php — full 479-line file now completely reproduced across parts 1–2 above)*

### `app/Http/Controllers/Admin/TimetableController.php` (complete — 1600 lines, part 1 of 5)

Class purpose: the main admin hub for the entire Timetable module — grid CRUD, the consolidated Workspace shell, generate/publish/discard, Auto-Fix/Rebalance/Swap endpoints, feasibility, PDF/Excel exports, and the interactive Teacher/Room views. The single largest file in the module.

#### `index(Request $request)` — public
T4b item 3-4: `?status=draft` switches the grid to the active draft for the selected class instead of the live timetable.

```php
class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        return view('admin.timetable.grid', $this->buildGridViewData($request));
    }
```

#### `buildGridViewData(Request $request): array` — private
Shared by `index()` and `workspace()`. Resolves the academic year from the actual slots being displayed (not just "current"), scoped bell timings, and authorizes per-class-section reads via `viewClassTimetable` (the PR #12 pilot-hardening IDOR fix).

```php
    private function buildGridViewData(Request $request): array
    {
        $schoolClassId = $request->get('school_class_id');
        $sectionId = $request->get('section_id');
        $view = $request->get('status') === 'draft' ? 'draft' : 'published';

        // Pilot-hardening (workspace authorization): index()/workspace()
        // previously only checked viewAny (role), so any teacher-role
        // account could view -- or, via ?status=draft, see the in-progress
        // draft of -- ANY class's grid by supplying an arbitrary
        // school_class_id. Reuses the exact same viewClassTimetable
        // ability already applied to classPdf/classExcelExport, which
        // itself reuses the write side's teacherAssignedToClassSection()
        // check -- no new authorization system, no schema/role change.
        // Applies identically regardless of draft/published, so drafts
        // get the same boundary as published, never a weaker one.
        if ($schoolClassId) {
            $this->authorize('viewClassTimetable', [TimetableSlot::class, (int) $schoolClassId, $sectionId ? (int) $sectionId : null]);
        }

        $classes = SchoolClass::orderBy('class_order')->get();
        $sections = Section::all();
        $teachers = Teacher::all();
        $subjects = Subject::all();

        $slots = collect();
        $activeGeneration = null;
        $hasDraft = false;

        // The grid's row/column structure must come from the SAME academic
        // year as the slots being displayed -- otherwise, once more than
        // one academic year has active bell timings at once (e.g. next
        // year's grid being set up while this year's is still live), rows
        // sharing a common period_name/day (like "Period 1" on Monday)
        // silently collide across years: the view groups bell timings by
        // period_name+day only (see the partial below), so a slot placed
        // against this year's "Period 1" can vanish behind whichever other
        // year's "Period 1" happens to be picked first.
        $academicYear = null;
        if ($schoolClassId) {
            $academicYear = TimetableSlot::where('school_class_id', $schoolClassId)
                ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
                ->when($view === 'draft', fn ($q) => $q->draft(), fn ($q) => $q->published())
                ->value('academic_year');
        }
        $academicYear = $academicYear ?? AcademicSession::current()->first()?->code;

        // Get active bell timings grouped by day of week, scoped to that
        // academic year -- the exact same scoping GeneratorService itself
        // uses, so the grid always reflects the bell-timing set the slots
        // were actually generated against.
        $bellTimings = BellTiming::active()->orderBy('order_index')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        if ($schoolClassId) {
            $baseQuery = TimetableSlot::where('school_class_id', $schoolClassId)
                ->when($sectionId, function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                })
                ->with(['subject', 'teacher', 'coTeacher', 'bellTiming', 'combinedClassGroup']);

            if ($view === 'draft') {
                $slots = (clone $baseQuery)->draft()->get();
                $activeGeneration = $slots->isNotEmpty()
                    ? TimetableGeneration::find($slots->first()->timetable_generation_id)
                    : null;
                $hasDraft = $slots->isNotEmpty();
            } else {
                $slots = (clone $baseQuery)->published()->get();
                $hasDraft = TimetableSlot::draft()->where('school_class_id', $schoolClassId)->exists();
            }
        }

        return compact(
            'classes',
            'sections',
            'teachers',
            'subjects',
            'bellTimings',
            'slots',
            'schoolClassId',
            'sectionId',
            'view',
            'activeGeneration',
            'hasDraft'
        );
    }
```

#### `workspace(Request $request, FeasibilityService $service)` — public
Timetable Editor Phase B: the single consolidated workspace. Structural shell only — reuses `FeasibilityService` for readiness and `buildGridViewData()` for Review & Edit, no duplicated logic.

```php
    public function workspace(Request $request, FeasibilityService $service)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $currentSession = AcademicSession::current()->first();
        $academicYear = $currentSession?->code;

        $report = $service->build($academicYear);

        $timetableStatus = TimetableSlot::published()->exists()
            ? 'published'
            : (TimetableSlot::draft()->exists() ? 'draft' : 'none');

        $counts = [
            'classes' => SchoolClass::active()->count(),
            'sections' => Section::count(),
            'teachers' => Teacher::count(),
            'subjects' => Subject::count(),
            'bell_timings' => BellTiming::active()->count(),
        ];

        $readinessIssueCount = count($report['conflicts'] ?? [])
            + count($report['class_teacher_readiness'] ?? []);
        // Production hardening: an empty grid_capacity here means there's
        // nothing to evaluate yet (no active classes), not a genuine
        // scheduling conflict -- previously both cases collapsed onto the
        // same 'blocked' state, so the Home tab showed a red "Blocked"
        // badge next to the literally contradictory "0 conflict(s), 0
        // readiness note(s)" text. Kept as its own distinct state so the
        // badge and explanation can accurately say why.
        $readiness = $readinessIssueCount === 0
            ? (empty($report['grid_capacity']) ? 'no_classes' : 'ready')
            : (count($report['conflicts'] ?? []) > 0 ? 'blocked' : 'warning');

        $gridData = $this->buildGridViewData($request);

        return view('admin.timetable.workspace', array_merge($gridData, [
            'currentSession' => $currentSession,
            'academicYear' => $academicYear,
            'timetableStatus' => $timetableStatus,
            'counts' => $counts,
            'report' => $report,
            'readiness' => $readiness,
        ]));
    }
```

#### `store(Request $request)` — public
Manual grid write: `updateOrCreate` keyed on the natural key (class, section, bell_timing, status). Validated through `TimetableConflictResolver` first; the DB unique-constraint catch underneath is defense-in-depth against a race.

```php
    public function store(Request $request)
    {
        $this->authorize('create', [
            TimetableSlot::class,
            $request->integer('school_class_id') ?: null,
            $request->integer('section_id') ?: null,
        ]);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
        ]);

        // T4b item 4: the manual editor works on whichever grid the user
        // is looking at -- a hidden 'status' field on the form carries the
        // page's own Draft/Published toggle state through, so editing a
        // draft never touches the live timetable and vice versa.
        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        // academic_year is never client-supplied -- it's resolved the same
        // way every other timetable read/write in this controller resolves
        // "the current year" (AcademicSession::current(), the same source
        // GenerateTimetableJob stamps onto generator-created slots), so a
        // manually-placed slot is tagged consistently with generated ones
        // instead of silently landing on null and becoming invisible to
        // any future academic-year-scoped check.
        $validated['academic_year'] = AcademicSession::current()->first()?->code;

        // Conflict checking logic before saving, scoped to the same
        // status being edited -- a draft proposal is allowed to differ
        // from what's live, but can't conflict with itself.
        $conflictCheck = $this->checkSlotConflicts($request, $status);
        if ($conflictCheck['conflict']) {
            return back()->with('error', 'Scheduling conflict: ' . $conflictCheck['message']);
        }

        // The app-level checkSlotConflicts() above is the primary guard;
        // this catch is the DB-level safety net under it (T1a) -- a race
        // between the check and the write (or any future caller that skips
        // the check) still can't create a double-booking, it just gets the
        // same friendly error instead of a 500.
        try {
            $slot = DB::transaction(function () use ($validated, $status) {
                $slot = TimetableSlot::updateOrCreate(
                    [
                        'school_class_id' => $validated['school_class_id'],
                        'section_id' => $validated['section_id'] ?? null,
                        'bell_timing_id' => $validated['bell_timing_id'],
                        'status' => $status,
                    ],
                    array_merge($validated, ['status' => $status])
                );

                activity()->causedBy(Auth::user())->performedOn($slot)
                    ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id, 'status' => $slot->status])
                    ->log($slot->wasRecentlyCreated ? 'timetable_slot_created' : 'timetable_slot_updated');

                return $slot;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Scheduling conflict: this class or teacher already has a slot at this period.')->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Timetable slot scheduled successfully.');
    }
```

### `app/Http/Controllers/Admin/TimetableController.php` (part 2 of 5)

#### `update(Request $request, TimetableSlot $slot)` — public
Timetable Editor Slice 1: edits an already-placed lesson in place by row id (not natural-key upsert, which would silently duplicate the row on a class/section/period change). Rejects combined-group, archived, and locked rows. Two-fold authorization: `update` on the current row plus `create` on the destination class/section. Validated exclusively through `TimetableConflictResolver`; a conflict returns validated alternative suggestions too.

```php
    public function update(Request $request, TimetableSlot $slot)
    {
        $this->authorize('update', $slot);

        if ($slot->combined_class_group_id) {
            return back()->with('error', 'This is a combined-group lesson -- it can\'t be edited from a single cell. Clear it and re-place it via Combined Groups instead.');
        }

        if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
            return back()->with('error', 'This slot is archived history from a past publish -- it can no longer be edited.');
        }

        if ($slot->is_locked) {
            return back()->with('error', 'This lesson is locked -- unlock it first to edit it.');
        }

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'room_number' => 'nullable|string|max:50',
        ]);

        $destinationClassId = (int) $validated['school_class_id'];
        $destinationSectionId = ! empty($validated['section_id']) ? (int) $validated['section_id'] : null;
        $destinationCoTeacherId = ! empty($validated['co_teacher_id']) ? (int) $validated['co_teacher_id'] : null;

        $this->authorize('create', [TimetableSlot::class, $destinationClassId, $destinationSectionId]);

        $proposedPlacement = [
            'school_class_id' => $destinationClassId,
            'section_id' => $destinationSectionId,
            'bell_timing_id' => (int) $validated['bell_timing_id'],
            'subject_id' => (int) $validated['subject_id'],
            'teacher_id' => (int) $validated['teacher_id'],
            'co_teacher_id' => $destinationCoTeacherId,
            'room_number' => $validated['room_number'] ?? null,
            // Preserved, never taken from the request -- an edit can move
            // WHERE/WHO/WHAT a lesson is, never which draft/version or
            // academic year it belongs to.
            'status' => $slot->status,
            'academic_year' => $slot->academic_year,
            'ignore_slot_id' => $slot->id,
        ];

        $conflictCheck = (new TimetableConflictResolver())->check($proposedPlacement);

        if ($conflictCheck['conflict']) {
            $suggestions = (new TimetableSuggestionService())->suggestForNewPlacement($proposedPlacement);

            // suggestForNewPlacement() has no concept of "this row's own
            // CURRENT period" -- for a fresh add that never arises, but for
            // an edit its own pre-edit period is naturally conflict-free
            // against itself (see resolveSelfId()) and would otherwise be
            // offered back as a no-op "alternative": move it to exactly
            // where it already is. Filtered out here, at the orchestration
            // layer -- TimetableSuggestionService itself is untouched.
            $suggestions = array_values(array_filter(
                $suggestions,
                fn ($s) => (int) $s['bell_timing_id'] !== (int) $slot->bell_timing_id
            ));

            return back()->withInput()->with('error', 'Scheduling conflict: ' . $conflictCheck['message'])->with('edit_conflict', [
                'slot_id' => $slot->id,
                'message' => $conflictCheck['message'],
                'conflicts' => $conflictCheck['conflicts'],
                'suggestions' => $suggestions,
            ]);
        }

        $before = $slot->only(['school_class_id', 'section_id', 'bell_timing_id', 'subject_id', 'teacher_id', 'co_teacher_id', 'room_number']);
        $after = [
            'school_class_id' => $proposedPlacement['school_class_id'],
            'section_id' => $proposedPlacement['section_id'],
            'bell_timing_id' => $proposedPlacement['bell_timing_id'],
            'subject_id' => $proposedPlacement['subject_id'],
            'teacher_id' => $proposedPlacement['teacher_id'],
            'co_teacher_id' => $proposedPlacement['co_teacher_id'],
            'room_number' => $proposedPlacement['room_number'],
        ];

        try {
            DB::transaction(function () use ($slot, $after, $before) {
                $slot->update($after);

                activity()->causedBy(Auth::user())->performedOn($slot)
                    ->withProperties(['before' => $before, 'after' => $after, 'status' => $slot->status, 'timetable_generation_id' => $slot->timetable_generation_id])
                    ->log('timetable_slot_edited');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Scheduling conflict: this class or teacher already has a slot at this period.')->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Lesson updated.');
    }
```

#### `storeCombined(Request $request)` — public
T2b item 3: places one combined-group teaching event — one `TimetableSlot` row per member class-section, all sharing the same teacher/period/subject. Authorization requires the acting user be allowed to write every member class-section.

```php
    public function storeCombined(Request $request)
    {
        $validated = $request->validate([
            'combined_class_group_id' => 'required|exists:combined_class_groups,id',
            'teacher_id' => 'required|exists:teachers,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
        ]);

        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        // Same reasoning as store(): academic_year is never client-supplied.
        $validated['academic_year'] = AcademicSession::current()->first()?->code;

        $group = CombinedClassGroup::with('members')->findOrFail($validated['combined_class_group_id']);

        if ($group->members->count() < 2) {
            return back()->with('error', "Combined group \"{$group->name}\" has fewer than 2 member classes -- nothing to place.")->withInput();
        }

        foreach ($group->members as $member) {
            $this->authorize('create', [TimetableSlot::class, $member->school_class_id, $member->section_id]);
        }

        $conflictCheck = $this->checkSlotConflicts($request, $status);
        if ($conflictCheck['conflict']) {
            return back()->with('error', 'Scheduling conflict: ' . $conflictCheck['message'])->withInput();
        }

        foreach ($group->members as $member) {
            $alreadyBooked = TimetableSlot::where('school_class_id', $member->school_class_id)
                ->where('section_id', $member->section_id)
                ->where('bell_timing_id', $validated['bell_timing_id'])
                ->where('status', $status)
                ->exists();

            if ($alreadyBooked) {
                $label = $member->schoolClass->name . ($member->section ? " {$member->section->name}" : '');
                return back()->with('error', "Scheduling conflict: {$label} already has a slot at this period.")->withInput();
            }
        }

        try {
            DB::transaction(function () use ($group, $validated, $status) {
                foreach ($group->members as $member) {
                    TimetableSlot::create([
                        'school_class_id' => $member->school_class_id,
                        'section_id' => $member->section_id,
                        'bell_timing_id' => $validated['bell_timing_id'],
                        'subject_id' => $group->subject_id,
                        'teacher_id' => $validated['teacher_id'],
                        'combined_class_group_id' => $group->id,
                        'room_number' => $validated['room_number'] ?? null,
                        'academic_year' => $validated['academic_year'] ?? null,
                        'status' => $status,
                    ]);
                }

                activity()->causedBy(Auth::user())->performedOn($group)
                    ->withProperties(['bell_timing_id' => $validated['bell_timing_id'], 'teacher_id' => $validated['teacher_id'], 'member_count' => $group->members->count()])
                    ->log('combined_group_placed');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Scheduling conflict: this combined group could not be placed -- a member class or the teacher already has a slot at this period.')->withInput();
            }

            throw $e;
        }

        return back()->with('success', "Combined group \"{$group->name}\" scheduled for " . $group->members->count() . ' classes.');
    }
```

### `app/Http/Controllers/Admin/TimetableController.php` (part 3 of 5)

#### `feasibility(Request $request, FeasibilityService $service)` — public

```php
    public function feasibility(Request $request, FeasibilityService $service)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $sessions = AcademicSession::orderByDesc('id')->get();
        $selectedSession = $request->filled('academic_session_id')
            ? $sessions->firstWhere('id', (int) $request->get('academic_session_id'))
            : $sessions->firstWhere('is_current', true);

        $academicYear = $selectedSession?->code;

        $report = $service->build($academicYear);

        return view('admin.timetable.feasibility', compact('sessions', 'selectedSession', 'report'));
    }
```

#### `classPdf(Request $request)` — public
A4 landscape grid PDF for one class(-section). Policy-gated via `viewClassTimetable`.

```php
    public function classPdf(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);
        $this->authorize('viewClassTimetable', [TimetableSlot::class, (int) $request->school_class_id, $request->filled('section_id') ? (int) $request->section_id : null]);

        $class = SchoolClass::findOrFail($request->school_class_id);
        $section = $request->filled('section_id') ? Section::find($request->section_id) : null;
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher'])
            ->published()
            ->where('school_class_id', $class->id)
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->section_id));

        if ((clone $slotsQuery)->doesntExist()) {
            $label = $section ? "{$class->name} {$section->name}" : $class->name;
            return back()->with('error', "No timetable slots found for {$label} -- nothing to print yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        $title = $section ? "{$class->name} - {$section->name}" : $class->name;

        $pdf = Pdf::loadView('admin.timetable.pdf.class', [
            'title' => $title,
            'session' => $session,
            'periods' => $periods,
            'days' => $days,
            'periodMeta' => $periodMeta,
            'grid' => $grid,
            'lastTeachingPeriod' => $class->last_teaching_period,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('class', $title, $session));
    }
```

#### `teacherPdf(Request $request)` — public
A4 landscape grid PDF for one teacher. Includes co-taught periods.

```php
    public function teacherPdf(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
        ]);
        $this->authorize('viewTeacherTimetable', [TimetableSlot::class, (int) $request->teacher_id]);

        $teacher = Teacher::findOrFail($request->teacher_id);
        $session = AcademicSession::current()->first();

        // T6 item 4: a co-teacher's own PDF must show their team-taught
        // periods too, not just periods where they're the primary teacher.
        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'schoolClass', 'section', 'teacher', 'coTeacher'])
            ->published()
            ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhere('co_teacher_id', $teacher->id));

        if ((clone $slotsQuery)->doesntExist()) {
            return back()->with('error', "No timetable slots found for {$teacher->name} -- nothing to print yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        $pdf = Pdf::loadView('admin.timetable.pdf.teacher', [
            'teacher' => $teacher,
            'session' => $session,
            'periods' => $periods,
            'days' => $days,
            'periodMeta' => $periodMeta,
            'grid' => $grid,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('teacher', $teacher->name, $session));
    }
```

#### `masterPdf(Request $request)` — public
Master timetable: all active classes × periods, one page per operating day.

```php
    public function masterPdf(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $data = $this->masterTimetableData();
        if ($data === null) {
            return back()->with('error', 'No timetable slots found for any class -- nothing to print yet.');
        }
        if (empty($data['days'])) {
            return back()->with('error', 'No active periods are configured for the current academic year -- nothing to print yet. Set up Bell Timings first.');
        }

        $pdf = Pdf::loadView('admin.timetable.pdf.master', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('master', 'all-classes', $data['session']));
    }
```

#### `masterTimetableData(): ?array` — private
Shared by `masterPdf()` and the Excel master export. Returns null only when there are literally no published slots at all (both callers separately guard the "zero active bell timings" case with their own wording).

```php
    private function masterTimetableData(): ?array
    {
        $session = AcademicSession::current()->first();

        $slots = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher', 'schoolClass', 'section'])->published()->get();

        if ($slots->isEmpty()) {
            return null;
        }

        [$periods, $days, $periodMeta] = $this->buildPeriodDayAxes($session?->code);
        $classes = SchoolClass::active()->orderByOrder()->get();

        $byDay = [];
        foreach ($slots as $slot) {
            $timing = $slot->bellTiming;
            if (!$timing) {
                continue;
            }
            $byDay[$timing->day_of_week][$slot->school_class_id][$timing->period_name] = $slot;
        }

        return compact('session', 'periods', 'days', 'periodMeta', 'classes', 'byDay');
    }
```

#### `classExcelExport(Request $request)` / `teacherExcelExport(Request $request)` / `masterExcelExport(Request $request)` / `roomExcelExport(Request $request)` — public
Phase 5: identical queries/grids to their PDF counterparts, just a different renderer — so Excel and PDF can never drift apart.

```php
    public function classExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);
        $this->authorize('viewClassTimetable', [TimetableSlot::class, (int) $request->school_class_id, $request->filled('section_id') ? (int) $request->section_id : null]);

        $class = SchoolClass::findOrFail($request->school_class_id);
        $section = $request->filled('section_id') ? Section::find($request->section_id) : null;
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher'])
            ->published()
            ->where('school_class_id', $class->id)
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->section_id));

        if ((clone $slotsQuery)->doesntExist()) {
            $label = $section ? "{$class->name} {$section->name}" : $class->name;
            return back()->with('error', "No timetable slots found for {$label} -- nothing to export yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);
        $title = $section ? "{$class->name} - {$section->name}" : $class->name;

        return Excel::download(
            new ClassTimetableExport($title, $session, $periods, $days, $periodMeta, $grid, $class->last_teaching_period),
            $this->excelFilename('class', $title, $session)
        );
    }

    public function teacherExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate(['teacher_id' => 'required|exists:teachers,id']);
        $this->authorize('viewTeacherTimetable', [TimetableSlot::class, (int) $request->teacher_id]);

        $teacher = Teacher::findOrFail($request->teacher_id);
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'schoolClass', 'section', 'teacher', 'coTeacher'])
            ->published()
            ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhere('co_teacher_id', $teacher->id));

        if ((clone $slotsQuery)->doesntExist()) {
            return back()->with('error', "No timetable slots found for {$teacher->name} -- nothing to export yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        return Excel::download(
            new TeacherTimetableExport($teacher->name, $session, $periods, $days, $periodMeta, $grid),
            $this->excelFilename('teacher', $teacher->name, $session)
        );
    }

    public function masterExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $data = $this->masterTimetableData();
        if ($data === null) {
            return back()->with('error', 'No timetable slots found for any class -- nothing to export yet.');
        }
        if (empty($data['days'])) {
            return back()->with('error', 'No active periods are configured for the current academic year -- nothing to export yet. Set up Bell Timings first.');
        }

        return Excel::download(new MasterTimetableExport($data), $this->excelFilename('master', 'all-classes', $data['session']));
    }

    public function roomExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate(['room' => 'required|string']);
        $room = $request->string('room')->toString();
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher', 'schoolClass', 'section'])
            ->published()
            ->where('room_number', $room);

        if ((clone $slotsQuery)->doesntExist()) {
            return back()->with('error', "No timetable slots found for Room {$room} -- nothing to export yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        return Excel::download(
            new RoomTimetableExport($room, $session, $periods, $days, $periodMeta, $grid),
            $this->excelFilename('room', $room, $session)
        );
    }

    private function excelFilename(string $type, string $name, ?AcademicSession $session): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $safeSession = preg_replace('/[^A-Za-z0-9_-]+/', '_', $session->code ?? 'na');

        return "timetable_{$type}_{$safeName}_{$safeSession}.xlsx";
    }
```

### `app/Http/Controllers/Admin/TimetableController.php` (part 4 of 5)

#### `teacherView(Request $request)` / `roomView(Request $request)` — public
Phase 5: interactive, read-only Teacher/Room timetable views, reusing `buildTimetableGrid()` so they can never drift from the PDF/Excel exports. Rooms have no modeled entity — the picker is every distinct `room_number` in use.

```php
    public function teacherView(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $teachers = Teacher::orderBy('name')->get();
        $selectedTeacher = null;
        $periods = $days = $periodMeta = $grid = [];
        $session = AcademicSession::current()->first();

        if ($request->filled('teacher_id')) {
            $request->validate(['teacher_id' => 'exists:teachers,id']);
            $this->authorize('viewTeacherTimetable', [TimetableSlot::class, (int) $request->teacher_id]);
            $selectedTeacher = Teacher::find($request->teacher_id);

            $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'schoolClass', 'section', 'teacher', 'coTeacher'])
                ->published()
                ->where(fn ($q) => $q->where('teacher_id', $selectedTeacher->id)->orWhere('co_teacher_id', $selectedTeacher->id));

            [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);
        }

        return view('admin.timetable.teacher-view', compact('teachers', 'selectedTeacher', 'periods', 'days', 'periodMeta', 'grid', 'session'));
    }

    public function roomView(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $rooms = TimetableSlot::published()->whereNotNull('room_number')->distinct()->orderBy('room_number')->pluck('room_number');
        $selectedRoom = null;
        $periods = $days = $periodMeta = $grid = [];
        $session = AcademicSession::current()->first();

        if ($request->filled('room')) {
            $selectedRoom = $request->string('room')->toString();

            $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher', 'schoolClass', 'section'])
                ->published()
                ->where('room_number', $selectedRoom);

            [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);
        }

        return view('admin.timetable.room-view', compact('rooms', 'selectedRoom', 'periods', 'days', 'periodMeta', 'grid', 'session'));
    }
```

#### `buildPeriodDayAxes(?string $academicYear): array` / `buildTimetableGrid(...)` / `pdfFilename(...)` — private
Shared grid-building infrastructure used by every PDF/Excel/interactive-view consumer in this file — one definition of "fetch + grid" instead of a copy per caller.

```php
    private function buildPeriodDayAxes(?string $academicYear): array
    {
        $activeTimings = BellTiming::active()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->orderBy('order_index')
            ->get();

        $periods = $activeTimings->pluck('period_name')->unique()->values()->all();
        $days = $activeTimings->sortBy('day_order')->pluck('day_of_week')->unique()->values()->all();

        $periodMeta = [];
        foreach ($activeTimings as $timing) {
            $periodMeta[$timing->period_name][$timing->day_of_week] = [
                'is_teaching' => $timing->period_type === BellTiming::PERIOD_TYPE_TEACHING,
                'label' => $timing->custom_label ?: ucfirst($timing->period_type),
                'order_index' => (int) $timing->order_index,
            ];
        }

        return [$periods, $days, $periodMeta];
    }

    private function buildTimetableGrid(\Illuminate\Database\Eloquent\Builder $slotsQuery, ?string $academicYear): array
    {
        $slots = $slotsQuery->get();

        [$periods, $days, $periodMeta] = $this->buildPeriodDayAxes($academicYear);

        $grid = [];
        foreach ($slots as $slot) {
            $timing = $slot->bellTiming;
            if (!$timing) {
                continue;
            }
            $grid[$timing->period_name][$timing->day_of_week] = $slot;
        }

        return [$slots, $periods, $days, $periodMeta, $grid];
    }

    private function pdfFilename(string $type, string $name, ?AcademicSession $session): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $safeSession = preg_replace('/[^A-Za-z0-9_-]+/', '_', $session->code ?? 'na');

        return "timetable_{$type}_{$safeName}_{$safeSession}.pdf";
    }
```

#### `destroy($id)` — public
Combined-group aware clear: clearing any one member's cell clears every sibling row for this exact occurrence (same group + period + status). Rejects locked rows outright, for both the clicked row and every sibling.

```php
    public function destroy($id)
    {
        $slot = TimetableSlot::findOrFail($id);
        $this->authorize('delete', $slot);

        if ($slot->is_locked) {
            return back()->with('error', 'This lesson is locked -- unlock it first to clear it.');
        }

        if ($slot->combined_class_group_id) {
            $siblings = TimetableSlot::where('combined_class_group_id', $slot->combined_class_group_id)
                ->where('bell_timing_id', $slot->bell_timing_id)
                ->where('status', $slot->status)
                ->get();

            foreach ($siblings as $sibling) {
                $this->authorize('delete', $sibling);
                if ($sibling->is_locked) {
                    return back()->with('error', 'This lesson is locked -- unlock it first to clear it.');
                }
            }

            DB::transaction(function () use ($siblings) {
                foreach ($siblings as $sibling) {
                    $sibling->delete();
                }
            });

            activity()->causedBy(Auth::user())->performedOn($slot->combinedClassGroup ?? $slot)
                ->withProperties(['bell_timing_id' => $slot->bell_timing_id, 'status' => $slot->status, 'member_count' => $siblings->count()])
                ->log('combined_group_cleared');

            return back()->with('success', 'Combined group slot cleared for all ' . $siblings->count() . ' member classes.');
        }

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id, 'status' => $slot->status])
            ->log('timetable_slot_cleared');

        $slot->delete();

        return back()->with('success', 'Timetable slot cleared.');
    }
```

#### `lockSlot(TimetableSlot $slot)` / `unlockSlot(TimetableSlot $slot)` — public
Phase 5 (Locked Lessons): pins a slot so Auto-Fix's chain search will never select it as a blocker, and the generator carries it forward unchanged.

```php
    public function lockSlot(TimetableSlot $slot)
    {
        $this->authorize('update', $slot);

        if ($slot->combined_class_group_id) {
            return back()->with('error', 'This is a combined-group lesson -- it can\'t be locked from a single cell.');
        }

        if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
            return back()->with('error', 'This slot is archived history from a past publish -- it can no longer be locked.');
        }

        $slot->update(['is_locked' => true]);

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id])
            ->log('timetable_slot_locked');

        return back()->with('success', 'Lesson locked -- Auto-Fix and future Rebalance will never move it, and it will be carried forward when this class is regenerated.');
    }

    public function unlockSlot(TimetableSlot $slot)
    {
        $this->authorize('update', $slot);

        $slot->update(['is_locked' => false]);

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id])
            ->log('timetable_slot_unlocked');

        return back()->with('success', 'Lesson unlocked.');
    }
```

#### `generate(Request $request)` — public
"Generate (Beta)": dispatches `GenerateTimetableJob` for one or more classes' current academic session in a single solver run. Creates the `TimetableGeneration` row synchronously before dispatch so the UI has an id to poll immediately.

```php
    public function generate(Request $request)
    {
        $this->authorize('generate', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_ids' => 'required|array|min:1',
            'school_class_ids.*' => 'integer|exists:school_classes,id',
            'style' => 'nullable|in:rotating,fixed_daily',
        ]);

        $session = AcademicSession::current()->first();

        $generation = TimetableGeneration::create([
            'academic_year' => $session?->code,
            'academic_session_id' => $session?->id,
            'school_class_ids' => array_values(array_unique(array_map('intval', $validated['school_class_ids']))),
            'style' => $validated['style'] ?? TimetableGeneration::STYLE_ROTATING,
            'status' => TimetableGeneration::STATUS_QUEUED,
            'requested_by' => Auth::id(),
        ]);

        GenerateTimetableJob::dispatch($generation->id);

        activity()->causedBy(Auth::user())->performedOn($generation)
            ->withProperties(['school_class_ids' => $generation->school_class_ids, 'style' => $generation->style])
            ->log('timetable_generation_requested');

        return response()->json([
            'generation_id' => $generation->id,
            'status_url' => route('timetable.generation.status', $generation),
            'review_url' => route('timetable.generation.review', $generation),
        ]);
    }
```

#### `showGenerateForm()` — public

```php
    public function showGenerateForm()
    {
        $this->authorize('generate', TimetableSlot::class);

        $classes = SchoolClass::active()->orderByOrder()->get();

        return view('admin.timetable.generate', compact('classes'));
    }
```

#### `generationReview(TimetableGeneration $generation)` — public
Batch review for a (possibly multi-class) generation: overall stats, per-class placed/unplaced breakdown, all unplaced-lesson sentences grouped in one place.

```php
    public function generationReview(TimetableGeneration $generation)
    {
        $this->authorize('viewAny', TimetableSlot::class);
        $this->authorize('viewGenerationReview', [TimetableSlot::class, $generation]);

        $classes = SchoolClass::whereIn('id', $generation->school_class_ids)->orderByOrder()->get()->keyBy('id');

        $placementsByClass = collect($generation->report['placements'] ?? [])->groupBy('school_class_id');
        $unplacedByClass = collect($generation->report['unplaced'] ?? [])
            ->flatMap(fn ($u) => collect($u['class_ids'] ?? [])->map(fn ($classId) => ['class_id' => $classId, 'reason' => $u['reason']]))
            ->groupBy('class_id');

        $perClass = collect($generation->school_class_ids)->map(function ($classId) use ($classes, $placementsByClass, $unplacedByClass) {
            return [
                'class_id' => $classId,
                'class_name' => $classes->get($classId)?->name ?? "Class #{$classId}",
                'placed' => $placementsByClass->get($classId, collect())->count(),
                'unplaced' => $unplacedByClass->get($classId, collect())->count(),
            ];
        })->sortBy('class_name')->values();

        $unplacedSentences = collect($generation->report['unplaced'] ?? [])->pluck('reason')->values();

        // T6 items 1-2: class-teacher clashes and fixed-daily-incompatible
        // assignments are reported here, not as ordinary unplaced lessons.
        $warnings = collect($generation->report['warnings'] ?? [])->values();

        return view('admin.timetable.generation-review', compact('generation', 'perClass', 'unplacedSentences', 'warnings'));
    }
```

#### `generationStatus(TimetableGeneration $generation)` — public
Polled by the "Generate (Beta)" confirm-dialog flow. Unplaced/warnings only included once the run has actually completed.

```php
    public function generationStatus(TimetableGeneration $generation)
    {
        $this->authorize('viewAny', TimetableSlot::class);
        $this->authorize('viewGenerationReview', [TimetableSlot::class, $generation]);

        return response()->json([
            'id' => $generation->id,
            'status' => $generation->status,
            'placed_count' => $generation->placed_count,
            'unplaced_count' => $generation->unplaced_count,
            'placement_percent' => $generation->placement_percent,
            'error' => $generation->error,
            'unplaced' => $generation->status === TimetableGeneration::STATUS_COMPLETED
                ? ($generation->report['unplaced'] ?? [])
                : [],
            'warnings' => $generation->status === TimetableGeneration::STATUS_COMPLETED
                ? ($generation->report['warnings'] ?? [])
                : [],
        ]);
    }
```

### `app/Http/Controllers/Admin/TimetableController.php` (part 5 of 5 — final part)

#### `publishGeneration(TimetableGeneration $generation)` — public
T4b item 5: PUBLISH — admin-only, atomic. Archives every currently-published slot for the generation's class-section set and flips this generation's draft rows to published, in one transaction.

```php
    public function publishGeneration(TimetableGeneration $generation)
    {
        $this->authorize('publish', TimetableSlot::class);

        if ($generation->status !== TimetableGeneration::STATUS_COMPLETED) {
            return back()->with('error', 'Only a completed generation can be published.');
        }

        DB::transaction(function () use ($generation) {
            TimetableSlot::published()
                ->whereIn('school_class_id', $generation->school_class_ids)
                ->update(['status' => TimetableSlot::STATUS_ARCHIVED]);

            TimetableSlot::draft()
                ->where('timetable_generation_id', $generation->id)
                ->update(['status' => TimetableSlot::STATUS_PUBLISHED]);

            $generation->update(['status' => TimetableGeneration::STATUS_PUBLISHED]);
        });

        activity()->causedBy(Auth::user())->performedOn($generation)
            ->withProperties(['school_class_ids' => $generation->school_class_ids])
            ->log('timetable_generation_published');

        return $this->redirectAfterGenerationAction($generation)
            ->with('success', 'Generation published -- this is now the live timetable for the affected classes.');
    }
```

#### `discardGeneration(TimetableGeneration $generation)` — public
T4b item 5: DISCARD — deletes only this generation's own draft rows; never touches published/archived slots.

```php
    public function discardGeneration(TimetableGeneration $generation)
    {
        $this->authorize('publish', TimetableSlot::class);

        if ($generation->status !== TimetableGeneration::STATUS_COMPLETED) {
            return back()->with('error', 'Only a completed generation can be discarded.');
        }

        DB::transaction(function () use ($generation) {
            TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->delete();
            $generation->update(['status' => TimetableGeneration::STATUS_DISCARDED]);
        });

        activity()->causedBy(Auth::user())->performedOn($generation)
            ->withProperties(['school_class_ids' => $generation->school_class_ids])
            ->log('timetable_generation_discarded');

        return $this->redirectAfterGenerationAction($generation)
            ->with('success', 'Draft discarded -- the live timetable is unchanged.');
    }
```

#### `redirectAfterGenerationAction(TimetableGeneration $generation)` — private

```php
    private function redirectAfterGenerationAction(TimetableGeneration $generation)
    {
        if (count($generation->school_class_ids) === 1) {
            return redirect()->route('timetable.index', ['school_class_id' => $generation->school_class_ids[0]]);
        }

        return redirect()->route('timetable.generation.review', $generation);
    }
```

#### `checkConflictsApi(Request $request)` — public
Phase 3 (Smart Suggestions): a conflict is never returned bare — asks `TimetableSuggestionService` for concrete, already-validated alternatives too.

```php
    public function checkConflictsApi(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $status = $request->get('status') === 'draft' ? TimetableSlot::STATUS_DRAFT : TimetableSlot::STATUS_PUBLISHED;
        $result = $this->checkSlotConflicts($request, $status);

        if ($result['conflict']) {
            // Deliberately no ignore_slot_id here -- unchanged from before:
            // a suggestion trial is evaluated against a DIFFERENT
            // bell_timing_id each time, a different natural key, so
            // whatever row the initial check excluded doesn't apply and
            // must be independently re-resolved per candidate (both
            // suggestion methods already do this internally).
            //
            // academic_year IS included, resolved once here rather than
            // inside TimetableConflictResolver::check() on every single
            // candidate evaluation -- it's invariant for the whole
            // request (same AcademicSession::current() value regardless
            // of which candidate period is being tried), so this removes
            // one repeated, identical query per candidate without
            // changing what value is ever used.
            $placement = [
                'school_class_id' => $request->get('school_class_id'),
                'section_id' => $request->get('section_id') ?: null,
                'bell_timing_id' => $request->get('bell_timing_id'),
                'teacher_id' => $request->get('teacher_id'),
                'co_teacher_id' => $request->get('co_teacher_id') ?: null,
                'subject_id' => $request->get('subject_id'),
                'room_number' => $request->get('room_number'),
                'status' => $status,
                'academic_year' => AcademicSession::current()->first()?->code,
            ];

            $suggestions = new TimetableSuggestionService();
            $result['suggestions'] = [
                'move_lesson' => $suggestions->suggestForNewPlacement($placement),
                'relocate_blocker' => $suggestions->suggestBlockerRelocation($placement),
            ];
        }

        return response()->json($result);
    }
```

#### `autoFixRelocateBlocker(Request $request)` — public
Phase 4 (Auto-Fix): applies one `relocate_blocker` suggestion as a single atomic action. Admin-only.

```php
    public function autoFixRelocateBlocker(Request $request)
    {
        $this->authorize('autoFix', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'subject_id' => 'nullable|exists:subjects,id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
            'blocking_slot_id' => 'required|integer|exists:timetable_slots,id',
            'blocker_new_bell_timing_id' => 'required|integer|exists:bell_timings,id',
        ]);

        $newPlacement = collect($validated)->except(['blocking_slot_id', 'blocker_new_bell_timing_id'])->all();

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement,
            $validated['blocking_slot_id'],
            $validated['blocker_new_bell_timing_id']
        );

        return response()->json($result, $result['applied'] ? 200 : 422);
    }
```

#### `autoFixPreviewChain(Request $request)` / `autoFixApplyChain(Request $request)` — public
Phase 4 (Auto-Fix, chain repair): read-only preview and the corresponding admin-only apply, both delegating to `TimetableAutoFixService`.

```php
    public function autoFixPreviewChain(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'subject_id' => 'nullable|exists:subjects,id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
        ]);

        $newPlacement = array_merge($validated, [
            'academic_year' => AcademicSession::current()->first()?->code,
        ]);

        $result = (new TimetableAutoFixService())->previewChainFix($newPlacement);

        return response()->json($result);
    }

    public function autoFixApplyChain(Request $request)
    {
        $this->authorize('autoFix', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'subject_id' => 'nullable|exists:subjects,id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
            'steps' => 'present|array',
            'steps.*.slot_id' => 'required_with:steps|integer|exists:timetable_slots,id',
            'steps.*.to_bell_timing_id' => 'required_with:steps|integer|exists:bell_timings,id',
        ]);

        $newPlacement = array_merge(
            collect($validated)->except('steps')->all(),
            ['academic_year' => AcademicSession::current()->first()?->code]
        );

        $result = (new TimetableAutoFixService())->applyChainFix($newPlacement, $validated['steps']);

        return response()->json($result, $result['applied'] ? 200 : 422);
    }
```

#### `rebalancePreview(Request $request)` / `rebalanceApply(Request $request)` — public
Rebalancing Engine preview (read-only, `viewAny`) and apply (admin-only `autoFix`, fully re-validated, one transaction). The `movements.*` validation was added by a production-hardening pass to convert what used to be an uncaught 500 (invalid `to_bell_timing_id`) into a clean 422.

```php
    public function rebalancePreview(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'status' => 'nullable|in:draft,published',
        ]);

        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;
        $academicYear = AcademicSession::current()->first()?->code;

        $result = (new TimetableRebalanceService())->analyze(
            (int) $validated['school_class_id'],
            $validated['section_id'] ?? null,
            $academicYear,
            $status
        );

        return response()->json($result);
    }

    public function rebalanceApply(Request $request)
    {
        $this->authorize('autoFix', TimetableSlot::class);

        // Production hardening: previously only 'movements' itself was
        // validated (present, non-empty array) -- an invalid/stale
        // to_bell_timing_id for a relocate movement was silently treated
        // by TimetableConflictResolver::check() as "no conflict" (a
        // not-found BellTiming short-circuits to an empty conflict list),
        // so TimetableRebalanceService::applyRelocation() would reach
        // $slot->update(['bell_timing_id' => $bogusId]) and let the
        // database's own foreign-key constraint reject it as an uncaught
        // QueryException (a 500), instead of the clean 422 every other
        // rejection in this class produces. Mirrors autoFixApplyChain()'s
        // own 'steps.*' validation discipline immediately above.
        $validated = $request->validate([
            'movements' => 'required|array|min:1',
            'movements.*.type' => 'required|in:swap,relocate',
            'movements.*.slot_id' => 'required_if:movements.*.type,relocate|integer|exists:timetable_slots,id',
            'movements.*.to_bell_timing_id' => 'required_if:movements.*.type,relocate|integer|exists:bell_timings,id',
            'movements.*.slot_a_id' => 'required_if:movements.*.type,swap|integer|exists:timetable_slots,id',
            'movements.*.slot_b_id' => 'required_if:movements.*.type,swap|integer|exists:timetable_slots,id',
        ]);

        $academicYear = AcademicSession::current()->first()?->code;

        $result = (new TimetableRebalanceService())->apply($validated['movements'], $academicYear);

        return response()->json($result, $result['applied'] ? 200 : 422);
    }
```

#### `swapPreviewApi(Request $request)` / `swapSlots(Request $request)` / `describeSlotForSwap(TimetableSlot $slot): array` — public/public/private
Swap Engine preview (read-only) and apply — a swap requires `update` authorization on BOTH rows individually.

```php
    public function swapPreviewApi(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $validated = $request->validate([
            'slot_a_id' => 'required|integer|exists:timetable_slots,id',
            'slot_b_id' => 'required|integer|exists:timetable_slots,id',
        ]);

        $result = (new TimetableSwapService())->preview((int) $validated['slot_a_id'], (int) $validated['slot_b_id']);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'conflicts' => $result['conflicts'],
            'slot_a' => $result['slot_a'] ? $this->describeSlotForSwap($result['slot_a']) : null,
            'slot_b' => $result['slot_b'] ? $this->describeSlotForSwap($result['slot_b']) : null,
        ]);
    }

    public function swapSlots(Request $request)
    {
        $validated = $request->validate([
            'slot_a_id' => 'required|integer|exists:timetable_slots,id',
            'slot_b_id' => 'required|integer|exists:timetable_slots,id',
        ]);

        $slotA = TimetableSlot::findOrFail($validated['slot_a_id']);
        $slotB = TimetableSlot::findOrFail($validated['slot_b_id']);

        $this->authorize('update', $slotA);
        $this->authorize('update', $slotB);

        $result = (new TimetableSwapService())->apply($slotA->id, $slotB->id);

        return response()->json($result, $result['applied'] ? 200 : 422);
    }

    /** Small, swap-preview-only view model -- avoids leaking full model internals into the JSON response. */
    private function describeSlotForSwap(TimetableSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'class' => $slot->schoolClass->name ?? null,
            'section' => $slot->section->name ?? null,
            'subject' => $slot->subject->name ?? null,
            'teacher' => $slot->teacher->name ?? null,
            'co_teacher' => $slot->coTeacher->name ?? null,
            'room_number' => $slot->room_number,
            'day_of_week' => $slot->bellTiming->day_of_week ?? null,
            'period_name' => $slot->bellTiming->period_name ?? null,
            'bell_timing_id' => $slot->bell_timing_id,
        ];
    }
```

#### `checkSlotConflicts(Request $request, string $status = TimetableSlot::STATUS_PUBLISHED): array` — private
Thin adapter from the HTTP request shape to `TimetableConflictResolver` — the actual rule evaluation lives there so manual editing, the check-conflicts AJAX endpoint, and any future caller all go through the one authoritative implementation.

```php
    private function checkSlotConflicts(Request $request, string $status = TimetableSlot::STATUS_PUBLISHED): array
    {
        return (new TimetableConflictResolver())->check([
            'school_class_id' => $request->get('school_class_id'),
            'section_id' => $request->get('section_id') ?: null,
            'bell_timing_id' => $request->get('bell_timing_id'),
            'teacher_id' => $request->get('teacher_id'),
            'co_teacher_id' => $request->get('co_teacher_id') ?: null,
            'subject_id' => $request->get('subject_id'),
            'room_number' => $request->get('room_number'),
            'status' => $status,
            // Never client-supplied, same reasoning as store()/storeCombined():
            // this is also what actually gets persisted (see store()), so the
            // preview must be checked against the exact year that will be
            // saved, not whatever the client happens to send.
            'academic_year' => AcademicSession::current()->first()?->code,
            'ignore_slot_id' => $request->get('id'),
        ]);
    }
}
```

*(end of TimetableController.php — full 1600-line file now completely reproduced across parts 1–5 above. This completes Part 3 of this document: every production PHP file in the Timetable module now has its complete, current source reproduced above.)*

### `app/Http/Controllers/BellTimingController.php` (complete — 378 lines)

Web admin CRUD + weekly/daily/current-period/bulk-create/print for bell timings. Every action is policy-gated (`BellTimingPolicy`, not separately documented above as it isn't Timetable-module-specific, but every call site is shown here).

```php
class BellTimingController extends Controller
{
    /**
     * Display a listing of the bell timings.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $query = BellTiming::with('createdBy');
        
        // Filter by day of week
        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', $request->day_of_week);
        }
        
        // Filter by class section
        if ($request->filled('class_section')) {
            $query->where('class_section', $request->class_section);
        }
        
        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        // Filter by academic year
        if ($request->filled('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }
        
        $bellTimings = $query->orderBy('day_of_week')
                            ->orderBy('order_index')
                            ->paginate(20);
        
        // Get unique values for filters
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter();
        
        return view('bell-timing.index', compact('bellTimings', 'daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Show the form for creating a new bell timing.
     */
    public function create()
    {
        $this->authorize('create', BellTiming::class);
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $currentYear = date('Y');
        $academicYears = [
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear - 1) . '-' . $currentYear,
            ($currentYear + 1) . '-' . ($currentYear + 2)
        ];
        
        return view('bell-timing.create', compact('daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Store a newly created bell timing in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', BellTiming::class);
        $request->validate([
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'period_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'class_section' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_break' => 'boolean',
            'order_index' => 'required|integer|min:0',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'custom_label' => 'nullable|string|max:100',
            'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
        ]);

        // Check for time conflicts
        $conflicts = BellTiming::where('day_of_week', $request->day_of_week)
                              ->where('class_section', $request->class_section)
                              ->where(function($query) use ($request) {
                                  $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                                        ->orWhere(function($q) use ($request) {
                                            $q->where('start_time', '<=', $request->start_time)
                                              ->where('end_time', '>=', $request->end_time);
                                        });
                              })
                              ->where('is_active', true)
                              ->get();

        if ($conflicts->count() > 0) {
            return back()->withErrors(['time_conflict' => 'Time conflict detected with existing schedule: ' . 
                                     $conflicts->first()->period_name . ' (' . 
                                     $conflicts->first()->start_time . ' - ' . 
                                     $conflicts->first()->end_time . ')']);
        }

        $bellTiming = new BellTiming();
        $bellTiming->fill($request->all());
        $bellTiming->created_by = Auth::id(); // Current authenticated user
        $bellTiming->save();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing created successfully!');
    }

    /**
     * Display the specified bell timing.
     */
    public function show(BellTiming $bellTiming)
    {
        $this->authorize('view', $bellTiming);
        $bellTiming->load('createdBy');
        return view('bell-timing.show', compact('bellTiming'));
    }

    /**
     * Show the form for editing the specified bell timing.
     */
    public function edit(BellTiming $bellTiming)
    {
        $this->authorize('update', $bellTiming);
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $currentYear = date('Y');
        $academicYears = [
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear - 1) . '-' . $currentYear,
            ($currentYear + 1) . '-' . ($currentYear + 2)
        ];
        
        return view('bell-timing.edit', compact('bellTiming', 'daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Update the specified bell timing in storage.
     */
    public function update(Request $request, BellTiming $bellTiming)
    {
        $this->authorize('update', $bellTiming);
        $request->validate([
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'period_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'class_section' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_break' => 'boolean',
            'order_index' => 'required|integer|min:0',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'custom_label' => 'nullable|string|max:100',
            'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
        ]);

        // Check for time conflicts (excluding current record)
        $conflicts = BellTiming::where('day_of_week', $request->day_of_week)
                              ->where('class_section', $request->class_section)
                              ->where('id', '!=', $bellTiming->id)
                              ->where(function($query) use ($request) {
                                  $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                                        ->orWhere(function($q) use ($request) {
                                            $q->where('start_time', '<=', $request->start_time)
                                              ->where('end_time', '>=', $request->end_time);
                                        });
                              })
                              ->where('is_active', true)
                              ->get();

        if ($conflicts->count() > 0) {
            return back()->withErrors(['time_conflict' => 'Time conflict detected with existing schedule: ' . 
                                     $conflicts->first()->period_name . ' (' . 
                                     $conflicts->first()->start_time . ' - ' . 
                                     $conflicts->first()->end_time . ')']);
        }

        $bellTiming->fill($request->all());
        $bellTiming->save();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing updated successfully!');
    }

    /**
     * Remove the specified bell timing from storage.
     */
    public function destroy(BellTiming $bellTiming)
    {
        $this->authorize('delete', $bellTiming);
        $bellTiming->delete();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing deleted successfully!');
    }

    /**
     * Display weekly timetable for a class.
     */
    public function weeklyTimetable(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $classSection = $request->class_section;
        $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);
        
        if ($classSection) {
            $timetable = BellTiming::getTimetableForClass($classSection, $academicYear);
        } else {
            $timetable = collect();
        }
        
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        $schedules = $timetable; // Rename variable to match view expectation
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter(); // Get all academic years for filter
        
        return view('bell-timing.weekly', compact('schedules', 'classSection', 'academicYear', 'classSections', 'academicYears'));
    }

    /**
     * Display today's schedule.
     */
    public function todaysSchedule(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $classSection = $request->class_section;
        $day = now()->format('l'); // Current day of week
        
        $schedule = BellTiming::getTodaysSchedule($day, $classSection);
        
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        return view('bell-timing.daily', compact('schedule', 'classSection', 'day', 'classSections'));
    }

    /**
     * Get current period (AJAX endpoint).
     */
    public function currentPeriod()
    {
        $this->authorize('viewAny', BellTiming::class);
        $currentPeriod = BellTiming::getCurrentPeriod();
        
        return response()->json([
            'current_period' => $currentPeriod,
            'current_time' => now()->format('H:i:s'),
            'current_day' => now()->format('l')
        ]);
    }

    /**
     * Bulk create schedule for a week.
     */
    public function bulkCreate(Request $request)
    {
        $this->authorize('create', BellTiming::class);
        if ($request->isMethod('get')) {
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
            $currentYear = date('Y');
            $academicYears = [
                $currentYear . '-' . ($currentYear + 1),
                ($currentYear - 1) . '-' . $currentYear,
                ($currentYear + 1) . '-' . ($currentYear + 2)
            ];
            
            return view('bell-timing.bulk-create', compact('daysOfWeek', 'classSections', 'academicYears'));
        }

        // Validate bulk creation
        $request->validate([
            'days' => 'required|array',
            'days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'class_section' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'periods' => 'required|array',
            'periods.*.period_name' => 'required|string|max:100',
            'periods.*.start_time' => 'required|date_format:H:i',
            'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
            'periods.*.is_break' => 'boolean',
            'periods.*.order_index' => 'required|integer|min:0'
        ]);

        $createdCount = 0;
        $errors = [];

        foreach ($request->days as $day) {
            foreach ($request->periods as $index => $period) {
                try {
                    // Check for time conflicts
                    $conflicts = BellTiming::where('day_of_week', $day)
                                          ->where('class_section', $request->class_section)
                                          ->where(function($query) use ($period) {
                                              $query->whereBetween('start_time', [$period['start_time'], $period['end_time']])
                                                    ->orWhereBetween('end_time', [$period['start_time'], $period['end_time']])
                                                    ->orWhere(function($q) use ($period) {
                                                        $q->where('start_time', '<=', $period['start_time'])
                                                          ->where('end_time', '>=', $period['end_time']);
                                                    });
                                          })
                                          ->where('is_active', true)
                                          ->get();

                    if ($conflicts->count() > 0) {
                        $errors[] = "Conflict on $day for " . $period['period_name'];
                        continue;
                    }

                    BellTiming::create([
                        'day_of_week' => $day,
                        'period_name' => $period['period_name'],
                        'start_time' => $period['start_time'],
                        'end_time' => $period['end_time'],
                        'class_section' => $request->class_section,
                        'is_active' => true,
                        'is_break' => $period['is_break'] ?? false,
                        'order_index' => $period['order_index'],
                        'academic_year' => $request->academic_year,
                        'semester' => $request->semester,
                        'custom_label' => $period['custom_label'] ?? null,
                        'color_code' => $period['color_code'] ?? '#007bff',
                        'created_by' => Auth::id() // Current authenticated user
                    ]);

                    $createdCount++;
                } catch (\Exception $e) {
                    $errors[] = "Error creating period on $day: " . $e->getMessage();
                }
            }
        }

        $message = "Successfully created $createdCount bell timings.";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " errors occurred.";
        }

        return redirect()->route('bell-timing.index')
                         ->with('success', $message)
                         ->with('errors', $errors);
    }

    /**
     * Display print-friendly timetable for a class.
     */
    public function printTimetable(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $classSection = $request->class_section;
        $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);

        if ($classSection) {
            $timetable = BellTiming::getTimetableForClass($classSection, $academicYear);
        } else {
            $timetable = collect();
        }

        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter();

        return view('bell-timing.print', compact('timetable', 'classSection', 'academicYear', 'classSections', 'academicYears'));
    }
}
```

### `app/Http/Controllers/API/BellTimingController.php` (complete — 332 lines)

Mobile API CRUD + weekly/current-period/today/bulk-create for bell timings.

```php
class BellTimingController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BellTiming::with('createdBy');
            
            // Filter by day of week
            if ($request->filled('day_of_week')) {
                $query->where('day_of_week', $request->day_of_week);
            }
            
            // Filter by class section
            if ($request->filled('class_section')) {
                $query->where('class_section', $request->class_section);
            }
            
            // Filter by active status
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            
            // Filter by academic year
            if ($request->filled('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }
            
            $bellTimings = $query->orderBy('day_of_week')
                                ->orderBy('order_index')
                                ->get();
            
            return $this->success($bellTimings, 'Bell timings retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve bell timings: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'period_name' => 'required|string|max:100',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'class_section' => 'nullable|string|max:50',
                'is_active' => 'boolean',
                'is_break' => 'boolean',
                'order_index' => 'required|integer|min:0',
                'academic_year' => 'nullable|string|max:20',
                'semester' => 'nullable|string|max:20',
                'custom_label' => 'nullable|string|max:100',
                'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
            ]);

            // Check for time conflicts
            $conflicts = BellTiming::where('day_of_week', $request->day_of_week)
                                  ->where('class_section', $request->class_section)
                                  ->where(function($query) use ($request) {
                                      $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                                            ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                                            ->orWhere(function($q) use ($request) {
                                                $q->where('start_time', '<=', $request->start_time)
                                                  ->where('end_time', '>=', $request->end_time);
                                            });
                                  })
                                  ->where('is_active', true)
                                  ->get();

            if ($conflicts->count() > 0) {
                return $this->error('Time conflict detected with existing schedule: ' . 
                                   $conflicts->first()->period_name . ' (' . 
                                   $conflicts->first()->start_time . ' - ' . 
                                   $conflicts->first()->end_time . ')', 409);
            }

            $bellTiming = new BellTiming();
            $bellTiming->fill($validated);
            $bellTiming->created_by = auth()->id(); // Current authenticated user
            $bellTiming->save();

            return $this->success($bellTiming, 'Bell timing created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create bell timing: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $bellTiming = BellTiming::with('createdBy')->findOrFail($id);
            return $this->success($bellTiming, 'Bell timing retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Bell timing not found: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $bellTiming = BellTiming::findOrFail($id);

            $validated = $request->validate([
                'day_of_week' => 'sometimes|required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'period_name' => 'sometimes|required|string|max:100',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
                'class_section' => 'nullable|string|max:50',
                'is_active' => 'boolean',
                'is_break' => 'boolean',
                'order_index' => 'sometimes|required|integer|min:0',
                'academic_year' => 'nullable|string|max:20',
                'semester' => 'nullable|string|max:20',
                'custom_label' => 'nullable|string|max:100',
                'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
            ]);

            // Check for time conflicts (excluding current record)
            $conflicts = BellTiming::where('day_of_week', $request->day_of_week ?? $bellTiming->day_of_week)
                                  ->where('class_section', $request->class_section ?? $bellTiming->class_section)
                                  ->where('id', '!=', $bellTiming->id)
                                  ->where(function($query) use ($request, $bellTiming) {
                                      $start_time = $request->start_time ?? $bellTiming->start_time;
                                      $end_time = $request->end_time ?? $bellTiming->end_time;
                                      
                                      $query->whereBetween('start_time', [$start_time, $end_time])
                                            ->orWhereBetween('end_time', [$start_time, $end_time])
                                            ->orWhere(function($q) use ($start_time, $end_time) {
                                                $q->where('start_time', '<=', $start_time)
                                                  ->where('end_time', '>=', $end_time);
                                            });
                                  })
                                  ->where('is_active', true)
                                  ->get();

            if ($conflicts->count() > 0) {
                return $this->error('Time conflict detected with existing schedule: ' . 
                                   $conflicts->first()->period_name . ' (' . 
                                   $conflicts->first()->start_time . ' - ' . 
                                   $conflicts->first()->end_time . ')', 409);
            }

            $bellTiming->fill($validated);
            $bellTiming->save();

            return $this->success($bellTiming, 'Bell timing updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update bell timing: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $bellTiming = BellTiming::findOrFail($id);
            $bellTiming->delete();

            return $this->success(null, 'Bell timing deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete bell timing: ' . $e->getMessage());
        }
    }

    /**
     * Display weekly timetable for a class.
     */
    public function weeklyTimetable(Request $request, string $classSection): JsonResponse
    {
        try {
            $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);
            
            $timetable = BellTiming::getTimetableForClass($classSection, $academicYear);
            
            return $this->success($timetable, 'Weekly timetable retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve weekly timetable: ' . $e->getMessage());
        }
    }

    /**
     * Get current period (AJAX endpoint).
     */
    public function currentPeriod(): JsonResponse
    {
        try {
            $currentPeriod = BellTiming::getCurrentPeriod();
            
            return $this->success([
                'current_period' => $currentPeriod,
                'current_time' => now()->format('H:i:s'),
                'current_day' => now()->format('l')
            ], 'Current period retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve current period: ' . $e->getMessage());
        }
    }

    /**
     * Get today's active schedule for a class section.
     */
    public function todaysSchedule(string $classSection): JsonResponse
    {
        try {
            $day = now()->format('l');

            $schedule = BellTiming::getTodaysSchedule($day, $classSection)
                ->map(function (BellTiming $bellTiming) {
                    return [
                        'id' => $bellTiming->id,
                        'period_name' => $bellTiming->period_name,
                        'start_time' => optional($bellTiming->start_time)->format('H:i:s'),
                        'end_time' => optional($bellTiming->end_time)->format('H:i:s'),
                        'is_break' => $bellTiming->is_break,
                        'order_index' => $bellTiming->order_index,
                        'custom_label' => $bellTiming->custom_label,
                        'color_code' => $bellTiming->color_code,
                    ];
                })
                ->values();

            return $this->success([
                'class_section' => $classSection,
                'day' => $day,
                'schedule' => $schedule,
            ], "Today's bell schedule retrieved successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve today\'s bell schedule: ' . $e->getMessage());
        }
    }

    /**
     * Bulk create schedule for a week.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'days' => 'required|array',
                'days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'class_section' => 'required|string|max:50',
                'academic_year' => 'required|string|max:20',
                'periods' => 'required|array',
                'periods.*.period_name' => 'required|string|max:100',
                'periods.*.start_time' => 'required|date_format:H:i',
                'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
                'periods.*.is_break' => 'boolean',
                'periods.*.order_index' => 'required|integer|min:0'
            ]);

            $createdCount = 0;
            $errors = [];

            foreach ($request->days as $day) {
                foreach ($request->periods as $index => $period) {
                    try {
                        // Check for time conflicts
                        $conflicts = BellTiming::where('day_of_week', $day)
                                              ->where('class_section', $request->class_section)
                                              ->where(function($query) use ($period) {
                                                  $query->whereBetween('start_time', [$period['start_time'], $period['end_time']])
                                                        ->orWhereBetween('end_time', [$period['start_time'], $period['end_time']])
                                                        ->orWhere(function($q) use ($period) {
                                                            $q->where('start_time', '<=', $period['start_time'])
                                                              ->where('end_time', '>=', $period['end_time']);
                                                        });
                                              })
                                              ->where('is_active', true)
                                              ->get();

                        if ($conflicts->count() > 0) {
                            $errors[] = "Conflict on $day for " . $period['period_name'];
                            continue;
                        }

                        BellTiming::create([
                            'day_of_week' => $day,
                            'period_name' => $period['period_name'],
                            'start_time' => $period['start_time'],
                            'end_time' => $period['end_time'],
                            'class_section' => $request->class_section,
                            'is_active' => true,
                            'is_break' => $period['is_break'] ?? false,
                            'order_index' => $period['order_index'],
                            'academic_year' => $request->academic_year,
                            'semester' => $request->semester,
                            'custom_label' => $period['custom_label'] ?? null,
                            'color_code' => $period['color_code'] ?? '#007bff',
                            'created_by' => auth()->id() // Current authenticated user
                        ]);

                        $createdCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Error creating period on $day: " . $e->getMessage();
                    }
                }
            }

            $message = "Successfully created $createdCount bell timings.";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " errors occurred.";
            }

            return $this->success(null, $message);
        } catch (\Exception $e) {
            return $this->error('Failed to bulk create schedule: ' . $e->getMessage());
        }
    }
}
```

### `app/Exports/ClassTimetableExport.php` (complete — 73 lines)

All 5 export classes read the exact same `[period][day]` grid `TimetableController::buildTimetableGrid()` builds for the equivalent PDF, so Excel and PDF can never drift apart.

```php
class ClassTimetableExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly ?AcademicSession $session,
        private readonly array $periods,
        private readonly array $days,
        private readonly array $periodMeta,
        private readonly array $grid,
        private readonly ?int $lastTeachingPeriod
    ) {
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->periods as $period) {
            $row = [$period];

            foreach ($this->days as $day) {
                $meta = $this->periodMeta[$period][$day] ?? null;
                $isNonTeaching = $meta && !$meta['is_teaching'];
                $beyondClassDay = $meta && $this->lastTeachingPeriod && $meta['order_index'] > $this->lastTeachingPeriod;
                $slot = $this->grid[$period][$day] ?? null;

                if ($beyondClassDay) {
                    $row[] = '';
                } elseif ($isNonTeaching) {
                    $row[] = $meta['label'];
                } elseif ($slot) {
                    $subject = $slot->subject->name ?? '';
                    $teacher = $slot->teacher->short_name ?? $slot->teacher->name ?? '';
                    if ($slot->coTeacher) {
                        $teacher .= ' / ' . ($slot->coTeacher->short_name ?? $slot->coTeacher->name);
                    }
                    $row[] = trim($subject . ' - ' . $teacher, ' -');
                } else {
                    $row[] = '';
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_merge(['Period'], $this->days);
    }

    public function title(): string
    {
        return substr($this->title, 0, 31) ?: 'Class Timetable';
    }
}
```

### `app/Exports/TeacherTimetableExport.php` (complete — 70 lines)

```php
class TeacherTimetableExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $teacherName,
        private readonly ?AcademicSession $session,
        private readonly array $periods,
        private readonly array $days,
        private readonly array $periodMeta,
        private readonly array $grid
    ) {
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->periods as $period) {
            $row = [$period];

            foreach ($this->days as $day) {
                $meta = $this->periodMeta[$period][$day] ?? null;
                $isNonTeaching = $meta && !$meta['is_teaching'];
                $slot = $this->grid[$period][$day] ?? null;

                if ($isNonTeaching) {
                    $row[] = $meta['label'];
                } elseif ($slot) {
                    $className = $slot->schoolClass->name ?? '';
                    $className .= $slot->section ? ' ' . $slot->section->name : '';
                    $subject = $slot->subject->name ?? '';
                    $cell = trim($className . ' - ' . $subject, ' -');
                    if ($slot->room_number) {
                        $cell .= " (Room {$slot->room_number})";
                    }
                    $row[] = $cell;
                } else {
                    $row[] = '';
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_merge(['Period'], $this->days);
    }

    public function title(): string
    {
        return substr($this->teacherName, 0, 31) ?: 'Teacher Timetable';
    }
}
```

### `app/Exports/RoomTimetableExport.php` (complete — 67 lines)

```php
class RoomTimetableExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $room,
        private readonly ?AcademicSession $session,
        private readonly array $periods,
        private readonly array $days,
        private readonly array $periodMeta,
        private readonly array $grid
    ) {
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->periods as $period) {
            $row = [$period];

            foreach ($this->days as $day) {
                $meta = $this->periodMeta[$period][$day] ?? null;
                $isNonTeaching = $meta && !$meta['is_teaching'];
                $slot = $this->grid[$period][$day] ?? null;

                if ($isNonTeaching) {
                    $row[] = $meta['label'];
                } elseif ($slot) {
                    $className = $slot->schoolClass->name ?? '';
                    $className .= $slot->section ? ' ' . $slot->section->name : '';
                    $subject = $slot->subject->name ?? '';
                    $teacher = $slot->teacher->name ?? '';
                    $row[] = trim("{$className} - {$subject} ({$teacher})", ' -()');
                } else {
                    $row[] = '';
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_merge(['Period'], $this->days);
    }

    public function title(): string
    {
        return substr('Room ' . $this->room, 0, 31) ?: 'Room Timetable';
    }
}
```

### `app/Exports/MasterTimetableExport.php` (complete — 36 lines)

```php
class MasterTimetableExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data)
    {
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->data['days'] as $day) {
            $sheets[] = new MasterTimetableDaySheetExport(
                $day,
                $this->data['periods'],
                $this->data['periodMeta'],
                $this->data['classes'],
                $this->data['byDay'][$day] ?? []
            );
        }

        return $sheets;
    }
}
```

### `app/Exports/MasterTimetableDaySheetExport.php` (complete — 67 lines)

One sheet per operating day for `MasterTimetableExport`, mirroring `masterPdf()`'s "one page per day" layout.

```php
class MasterTimetableDaySheetExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $day,
        private readonly array $periods,
        private readonly array $periodMeta,
        private readonly Collection $classes,
        private readonly array $dayGrid
    ) {
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->periods as $period) {
            $row = [$period];
            $meta = $this->periodMeta[$period][$this->day] ?? null;
            $isNonTeaching = $meta && !$meta['is_teaching'];

            foreach ($this->classes as $class) {
                if ($isNonTeaching) {
                    $row[] = $meta['label'];
                    continue;
                }

                $slot = $this->dayGrid[$class->id][$period] ?? null;
                if (!$slot) {
                    $row[] = '';
                    continue;
                }

                $subject = $slot->subject->code ?? $slot->subject->name ?? '';
                $teacher = $slot->teacher->short_name ?? $slot->teacher->name ?? '';
                $row[] = trim($subject . ' / ' . $teacher, ' /');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_merge(['Period'], $this->classes->pluck('name')->all());
    }

    public function title(): string
    {
        return substr($this->day, 0, 31) ?: 'Day';
    }
}
```

### `app/Http/Controllers/Admin/TimetableWizardController.php` (complete — 291 lines)

Class purpose: T6 item 5 (revised) — guided "Set Up Timetable" flow. UI orchestration only; every step writes through the same tables/services the individual admin pages already use. Section-aware: walks one class-section at a time via `buildSequence()`.

```php
class TimetableWizardController extends Controller
{
    private function authorizeWizard(): void
    {
        $this->authorize('generate', \App\Models\TimetableSlot::class);
    }

    private function currentAcademicYear(): ?string
    {
        return AcademicSession::current()->first()?->code;
    }

    /** Landing page: jump straight to the first class-section in the sequence. */
    public function index()
    {
        $this->authorizeWizard();

        $sequence = $this->buildSequence();
        $first = $sequence->first();

        if (! $first) {
            return view('admin.timetable.wizard.empty');
        }

        return redirect()->to($this->stepOneUrl($first['class'], $first['section_id']));
    }

    /**
     * STEP 1 -- Subjects & Class Teachers, one class-section at a time: a
     * subject/teacher/periods grid, with the class-teacher's row being
     * whichever row has "Class Teacher's Subject" checked. Nothing is
     * pre-filled that can't be read back from real, already-saved data.
     */
    public function step1(SchoolClass $class, ?Section $section = null)
    {
        $this->authorizeWizard();

        $sectionId = $section?->id;
        $sequence = $this->buildSequence();
        $position = $this->resolvePosition($sequence, $class, $sectionId);

        $academicYear = $this->currentAcademicYear();
        $assignments = TeacherClassSubjectAssignment::with(['teacher', 'subject'])
            ->where('class_id', $class->id)
            ->where('section_id', $sectionId)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $subjects = Subject::active()->orderBy('name')->get();
        $teachers = Teacher::active()->orderBy('name')->get();
        $allSections = Section::orderBy('name')->get();

        $next = $sequence->get($position + 1);
        $previous = $position > 0 ? $sequence->get($position - 1) : null;
        $stepLabel = 'Class-section ' . ($position + 1) . ' of ' . $sequence->count();

        return view('admin.timetable.wizard.step1', compact(
            'class', 'section', 'sectionId', 'assignments', 'subjects', 'teachers',
            'allSections', 'next', 'previous', 'stepLabel', 'academicYear'
        ));
    }

    public function step1Store(Request $request, SchoolClass $class, ?Section $section = null)
    {
        $this->authorizeWizard();

        $request->validate([
            'rows' => 'array',
            'rows.*.subject_id' => 'nullable|integer|exists:subjects,id',
            'rows.*.teacher_id' => 'nullable|integer|exists:teachers,id',
            'rows.*.periods_per_week' => 'nullable|integer|min:1|max:12',
            'rows.*.is_class_teacher' => 'nullable|boolean',
            'rows.*.require_consecutive' => 'nullable|boolean',
        ]);

        $sectionId = $section?->id;
        $academicYear = $this->currentAcademicYear() ?? (date('Y') . '-' . (date('Y') + 1));
        $rows = collect($request->input('rows', []))
            ->filter(fn ($row) => !empty($row['subject_id']) && !empty($row['teacher_id']));

        DB::transaction(function () use ($rows, $class, $sectionId, $academicYear) {
            foreach ($rows as $row) {
                $isClassTeacher = !empty($row['is_class_teacher']);

                if ($isClassTeacher) {
                    // Same one-class-teacher-row-per-class-SECTION rule the
                    // standalone assignment form enforces.
                    TeacherClassSubjectAssignment::where('class_id', $class->id)
                        ->where('section_id', $sectionId)
                        ->where('academic_year', $academicYear)
                        ->where('is_class_teacher', true)
                        ->update(['is_class_teacher' => false]);
                }

                TeacherClassSubjectAssignment::updateOrCreate(
                    [
                        'teacher_id' => $row['teacher_id'],
                        'class_id' => $class->id,
                        'section_id' => $sectionId,
                        'subject_id' => $row['subject_id'],
                        'academic_year' => $academicYear,
                    ],
                    [
                        'is_class_teacher' => $isClassTeacher,
                        'periods_per_week' => $row['periods_per_week'] ?? null,
                        'require_consecutive' => !empty($row['require_consecutive']),
                    ]
                );
            }
        });

        $sequence = $this->buildSequence();
        $position = $this->resolvePosition($sequence, $class, $sectionId);
        $next = $sequence->get($position + 1);

        $label = $class->name . ($section ? " ({$section->name})" : '');

        return $next
            ? redirect()->to($this->stepOneUrl($next['class'], $next['section_id']))->with('success', "Saved {$label}.")
            : redirect()->route('timetable.wizard.step2')->with('success', "Saved {$label}. All class-sections done.");
    }

    /** STEP 2 -- Style: fixed_daily vs rotating, one plain-language sentence each. */
    public function step2()
    {
        $this->authorizeWizard();

        $sequence = $this->buildSequence();
        $first = $sequence->first();

        return view('admin.timetable.wizard.step2', ['firstUrl' => $first ? $this->stepOneUrl($first['class'], $first['section_id']) : null]);
    }

    /**
     * STEP 3 -- Review readiness: FeasibilityService's own report, in
     * plain language, BEFORE generating. Never blocks -- generation is
     * reachable regardless of what this shows, exactly like the
     * standalone feasibility page never blocks Generate today.
     */
    public function step3(Request $request, FeasibilityService $service)
    {
        $this->authorizeWizard();

        $style = in_array($request->query('style'), [GeneratorService::STYLE_ROTATING, GeneratorService::STYLE_FIXED_DAILY], true)
            ? $request->query('style')
            : GeneratorService::STYLE_ROTATING;

        $academicYear = $this->currentAcademicYear();
        $report = $service->build($academicYear);

        return view('admin.timetable.wizard.step3', compact('report', 'style'));
    }

    /** @return string a fully-qualified Step 1 URL for a given class(+section). */
    private function stepOneUrl(SchoolClass $class, ?int $sectionId): string
    {
        return $sectionId
            ? route('timetable.wizard.step1', [$class, $sectionId])
            : route('timetable.wizard.step1', [$class]);
    }

    /**
     * The full, ordered class-section walkthrough sequence: every active
     * class, each split into one entry per section actually in use for it
     * (or a single section_id-null entry if none are).
     *
     * @return Collection<int, array{class: SchoolClass, section_id: ?int}>
     */
    private function buildSequence(): Collection
    {
        return SchoolClass::active()->orderByOrder()->get()
            ->flatMap(function (SchoolClass $class) {
                $sectionIds = $this->sectionIdsForClass($class);

                if ($sectionIds->isEmpty()) {
                    return [['class' => $class, 'section_id' => null]];
                }

                return $sectionIds->map(fn ($sectionId) => ['class' => $class, 'section_id' => $sectionId])->all();
            })
            ->values();
    }

    /**
     * @return Collection<int, int> section_ids actually in use for this
     *   class, ordered by section name. Scoped to the current academic
     *   year (tolerant -- only applied when a current session exists) so
     *   a retired/other-year assignment (e.g. leftover test/walkthrough
     *   data) can't make a class appear split into sections it doesn't
     *   actually have this year.
     */
    private function sectionIdsForClass(SchoolClass $class): Collection
    {
        $academicYear = $this->currentAcademicYear();

        $ids = TeacherClassSubjectAssignment::where('class_id', $class->id)
            ->whereNotNull('section_id')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->distinct()
            ->pluck('section_id')
            ->merge(
                Student::where('school_class_id', $class->id)->whereNotNull('section_id')->distinct()->pluck('section_id')
            )
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $ids;
        }

        return Section::whereIn('id', $ids)->orderBy('name')->pluck('id');
    }

    /**
     * Finds this class+section's position in the sequence, inserting it
     * (mutating $sequence) if it's a not-yet-saved class-section reached
     * via the "Add a section" picker -- so Next/Previous and the progress
     * count stay sane even before any row has been saved for it.
     */
    private function resolvePosition(Collection $sequence, SchoolClass $class, ?int $sectionId): int
    {
        $position = $sequence->search(fn ($row) => $row['class']->id === $class->id && $row['section_id'] === $sectionId);
        if ($position !== false) {
            return $position;
        }

        $insertAt = $sequence->search(fn ($row) => $row['class']->id === $class->id);
        $insertAt = $insertAt === false ? $sequence->count() : $insertAt;
        $sequence->splice($insertAt, 0, [['class' => $class, 'section_id' => $sectionId]]);

        return $insertAt;
    }
}
```

### `app/Jobs/GenerateTimetableJob.php` (complete — 114 lines)

Class purpose: T4b item 2 — runs `GeneratorService` and writes its proposal as `draft` rows only; the live (`published`) timetable is never touched by this job.

```php
class GenerateTimetableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout;

    /**
     * config('timetable.generator.time_budget_seconds') (default 60) caps
     * the SOLVER's own search time regardless of how many classes are in
     * this run -- whole-school generation doesn't scale that part
     * linearly, it just returns best-effort at the same mark. This
     * timeout is headroom for the surrounding DB work (deleting old
     * drafts, inserting every placement row), sized generously
     * (job_timeout_seconds, default 300s) so a genuinely large school's
     * batch insert never races the job's own kill switch.
     */
    public function __construct(public int $generationId)
    {
        $this->timeout = (int) config('timetable.generator.job_timeout_seconds', 300);
    }

    public function handle(GeneratorService $service): void
    {
        $generation = TimetableGeneration::find($this->generationId);
        if (!$generation) {
            Log::error("GenerateTimetableJob: TimetableGeneration record not found: {$this->generationId}");
            return;
        }

        $generation->update(['status' => TimetableGeneration::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $classIds = $generation->school_class_ids;
            $classes = SchoolClass::whereIn('id', $classIds)->get();

            $result = $service->generate($generation->academic_year, $classes, $generation->academic_session_id, $generation->style);

            DB::transaction(function () use ($generation, $classIds, $result) {
                // "Drafts for a session/class replace previous drafts only" --
                // never touches 'published'/'archived' rows.
                TimetableSlot::draft()
                    ->whereIn('school_class_id', $classIds)
                    ->where('academic_year', $generation->academic_year)
                    ->delete();

                foreach ($result['placements'] as $placement) {
                    foreach ($placement['bell_timing_ids'] as $bellTimingId) {
                        TimetableSlot::create([
                            'school_class_id' => $placement['school_class_id'],
                            'section_id' => $placement['section_id'],
                            'bell_timing_id' => $bellTimingId,
                            'subject_id' => $placement['subject_id'],
                            'teacher_id' => $placement['teacher_id'],
                            'co_teacher_id' => $placement['co_teacher_id'] ?? null,
                            'combined_class_group_id' => $placement['combined_class_group_id'],
                            'academic_year' => $generation->academic_year,
                            'status' => TimetableSlot::STATUS_DRAFT,
                            'timetable_generation_id' => $generation->id,
                            // Phase 5 (Locked Lessons): a locked slot carried
                            // forward by GeneratorService::reserveLockedSlots()
                            // stays locked in the new draft too -- the lock
                            // survives regeneration, it isn't a one-time pin.
                            'is_locked' => $placement['is_locked'] ?? false,
                            'room_number' => $placement['room_number'] ?? null,
                        ]);
                    }
                }
            });

            $generation->update([
                'status' => TimetableGeneration::STATUS_COMPLETED,
                'placed_count' => $result['stats']['placed_lessons'],
                'unplaced_count' => $result['stats']['unplaced_lessons'],
                'report' => $result,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateTimetableJob failed: ' . $e->getMessage());
            $generation->update([
                'status' => TimetableGeneration::STATUS_FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            if (app()->environment('testing')) {
                throw $e;
            }
        }
    }
}
```

### `app/Policies/TimetableSlotPolicy.php` (complete — 205 lines)

The central authorization gate for slot read/write actions — including the `viewTeacherTimetable`/`viewClassTimetable`/`viewGenerationReview` abilities added by the PR #12 pilot-hardening pass that closed the cross-teacher/cross-class IDOR gap.

```php
class TimetableSlotPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TimetableSlot $timetableSlot): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can create models. TimetableController::
     * store() doesn't have a model instance yet (it's an updateOrCreate
     * keyed on school_class_id/section_id/bell_timing_id), so the
     * class/section being written is passed as extra authorize() args --
     * previously any teacher could write to any class's timetable;
     * narrowed to only class-sections the teacher is actually assigned to
     * per teacher_class_subject_assignments (remediation deferred
     * register item).
     */
    public function create(User $user, ?int $schoolClassId = null, ?int $sectionId = null): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher') || !$schoolClassId) {
            return false;
        }

        return $this->teacherAssignedToClassSection($user, $schoolClassId, $sectionId);
    }

    /**
     * Determine whether the user can update the model. Not currently
     * invoked by TimetableController (store() only authorizes 'create'
     * for its updateOrCreate), but narrowed for consistency and for
     * future phases (T2+) that may add a dedicated update action.
     */
    public function update(User $user, TimetableSlot $timetableSlot): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher')) {
            return false;
        }

        return $this->teacherAssignedToClassSection($user, $timetableSlot->school_class_id, $timetableSlot->section_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TimetableSlot $timetableSlot): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * T4b: triggering GenerateTimetableJob for a whole class is a bigger
     * action than placing one slot (it replaces that class's entire draft
     * proposal), and publishing/discarding one is exactly the action the
     * plan calls "admin-only" for PUBLISH -- both gated the same way here
     * for consistency, rather than letting a teacher force-regenerate or
     * discard a class's draft.
     */
    public function generate(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function publish(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Phase 4 (Auto-Fix): applying a suggested fix moves ANOTHER
     * class-section's lesson (the blocker) as well as the caller's own --
     * a teacher fixing their own class's clash has no particular claim
     * over the blocker's class, so this is gated the same as generate()/
     * publish() rather than the narrower per-class-section create()/
     * update() checks.
     */
    public function autoFix(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Pilot-hardening (authorization): viewAny only checks role, so any
     * teacher-role account could previously view/print/export ANY other
     * teacher's, class's, or generation's timetable data by passing an
     * arbitrary id -- these three abilities narrow the read-only,
     * single-entity views/exports/generation-review actions specifically,
     * reusing the same teacherAssignedToClassSection() ownership check the
     * write side already relies on. Deliberately NOT applied to
     * masterPdf/masterExcelExport (whole-school published data, not a
     * specific person's/class's) or roomView/roomExcelExport (rooms have
     * no ownership concept in this codebase), and NOT applied to
     * index()/workspace() (the write side there is already correctly
     * gated; broadening the read-side restriction to the whole editing
     * grid was judged out of scope for this pass).
     */
    public function viewTeacherTimetable(User $user, int $teacherId): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher')) {
            return false;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();

        return $teacher && $teacher->id === $teacherId;
    }

    public function viewClassTimetable(User $user, int $schoolClassId, ?int $sectionId): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher')) {
            return false;
        }

        return $this->teacherAssignedToClassSection($user, $schoolClassId, $sectionId);
    }

    /**
     * A generation covers a whole set of classes at once (not necessarily
     * one class-section) -- a teacher may review it if they hold ANY
     * assignment (any section) for at least one of the generation's
     * classes, not scoped to one specific section the way a single
     * class-section view is.
     */
    public function viewGenerationReview(User $user, TimetableGeneration $generation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher')) {
            return false;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (!$teacher) {
            return false;
        }

        return TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->whereIn('class_id', $generation->school_class_ids ?? [])
            ->exists();
    }

    /**
     * A teacher may write a slot for a class-section they hold any
     * assignment for. Assignments with a null section_id are treated as
     * covering the whole class (matches any requested section, including
     * none); a null requested section_id (writing at the class level,
     * unscoped) is satisfied by any assignment to that class.
     */
    private function teacherAssignedToClassSection(User $user, int $schoolClassId, ?int $sectionId): bool
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return false;
        }

        return TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->where('class_id', $schoolClassId)
            ->where(function ($query) use ($sectionId) {
                $query->whereNull('section_id');
                if ($sectionId) {
                    $query->orWhere('section_id', $sectionId);
                }
            })
            ->exists();
    }
}
```

### `app/Policies/TeacherAvailabilityPolicy.php` (complete — 41 lines)

```php
class TeacherAvailabilityPolicy
{
    /**
     * Determine whether the user can view the teacher-availability screens
     * at all (the index/list of teachers, or their own grid).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can view/edit a specific teacher's
     * availability grid. No model instance exists for this ability (the
     * "resource" is a teacher's set of TeacherAvailability rows, not one
     * row), so the target teacher id is passed as an extra authorize()
     * arg -- same pattern as TimetableSlotPolicy::create(). Admin may
     * manage any teacher; a teacher may only manage their own.
     */
    public function manage(User $user, ?int $teacherId = null): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher') || !$teacherId) {
            return false;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();

        return $teacher && $teacher->id === $teacherId;
    }
}
```

### `app/Policies/CombinedClassGroupPolicy.php` (complete — 29 lines)

```php
class CombinedClassGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    public function view(User $user, CombinedClassGroup $group): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    public function delete(User $user, CombinedClassGroup $group): bool
    {
        return $user->hasRole('admin');
    }
}
```

### `app/Http/Controllers/API/ParentTimetableController.php` (complete — 101 lines)

Class purpose: T5 item 2 — mobile API counterpart to `Parent\TimetableController::today()`. Token-gated (`auth:sanctum` + throttle + `ApiAccessControl`). Bridges the Sanctum-token identity to a parent record via email (a documented pre-existing gap in this codebase's parent-identity model — the `parents` table has no working link to `Student` via the Guardian system; not fixed here).

#### `today(Request $request, int $studentId): JsonResponse` — public

```php
class ParentTimetableController extends BaseApiController
{
    public function today(Request $request, int $studentId): JsonResponse
    {
        $user = $request->user();

        $parentRecord = ParentModel::where('email', $user->email)->first();
        $allowedStudentIds = $parentRecord
            ? $parentRecord->students->pluck('id')->push($parentRecord->student_id)->filter()->unique()
            : collect();

        if (!$parentRecord || !$allowedStudentIds->contains($studentId)) {
            return $this->error("You are not authorized to view this student's timetable.", 403);
        }

        $student = Student::find($studentId);
        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $classId = $student->canonicalClassId();
        if (!$classId) {
            return $this->success(['date' => now()->toDateString(), 'periods' => []], 'No class assigned to this student yet.');
        }

        $today = now();
        $dayOfWeek = $today->format('l');
        $sectionId = $student->section_id;

        $slots = TimetableSlot::published()
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
            }))
            ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
            ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
            ->values();

        $substitutions = TeacherSubstitution::where('class_id', $classId)
            ->whereDate('substitution_date', $today)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('bell_timing_id');

        $periods = $slots->map(function (TimetableSlot $slot) use ($substitutions) {
            $sub = $substitutions->get($slot->bell_timing_id);
            $isArrangement = (bool) ($sub && $sub->substitute_teacher_id);

            return [
                'period_name' => $slot->bellTiming->period_name,
                'start_time' => $slot->bellTiming->start_time?->format('H:i'),
                'end_time' => $slot->bellTiming->end_time?->format('H:i'),
                'subject' => $slot->subject->name ?? null,
                'teacher' => $isArrangement ? ($sub->substituteTeacher->name ?? null) : ($slot->teacher->name ?? null),
                'is_arrangement' => $isArrangement,
            ];
        })->values();

        return $this->success([
            'date' => $today->toDateString(),
            'periods' => $periods,
        ], "Today's periods retrieved successfully");
    }
}
```

### `app/Http/Controllers/Admin/CombinedClassGroupController.php` (complete — 93 lines)

Class purpose: CRUD for T2b combined class groups.

```php
class CombinedClassGroupController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', CombinedClassGroup::class);

        $groups = CombinedClassGroup::with(['subject', 'academicSession', 'members.schoolClass', 'members.section'])
            ->orderBy('name')
            ->get();

        $teachers = Teacher::active()->orderBy('name')->get();
        $bellTimings = BellTiming::where('is_active', true)->orderBy('order_index')->get();

        return view('admin.timetable.combined-groups.index', compact('groups', 'teachers', 'bellTimings'));
    }

    public function create()
    {
        $this->authorize('create', CombinedClassGroup::class);

        $subjects = Subject::orderBy('name')->get();
        $sessions = AcademicSession::orderByDesc('id')->get();
        $classes = SchoolClass::active()->orderByOrder()->get();
        $sections = Section::orderBy('name')->get();

        return view('admin.timetable.combined-groups.create', compact('subjects', 'sessions', 'classes', 'sections'));
    }

    /**
     * A group needs at least 2 member class-sections -- a "combined
     * group" of one class isn't combined.
     */
    public function store(Request $request)
    {
        $this->authorize('create', CombinedClassGroup::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'members' => 'required|array|min:2',
            'members.*.school_class_id' => 'required|exists:school_classes,id',
            'members.*.section_id' => 'nullable|exists:sections,id',
        ]);

        $group = DB::transaction(function () use ($validated) {
            $group = CombinedClassGroup::create([
                'name' => $validated['name'],
                'subject_id' => $validated['subject_id'],
                'academic_session_id' => $validated['academic_session_id'],
            ]);

            foreach ($validated['members'] as $member) {
                CombinedClassGroupMember::create([
                    'combined_class_group_id' => $group->id,
                    'school_class_id' => $member['school_class_id'],
                    'section_id' => $member['section_id'] ?? null,
                ]);
            }

            return $group;
        });

        return redirect()->route('combined-class-groups.index')
            ->with('success', "Combined group \"{$group->name}\" created with " . count($validated['members']) . ' member classes.');
    }

    public function destroy(CombinedClassGroup $combinedClassGroup)
    {
        $this->authorize('delete', $combinedClassGroup);

        $combinedClassGroup->delete();

        return redirect()->route('combined-class-groups.index')
            ->with('success', 'Combined group removed. Any timetable slots already placed for it were kept but detached from the group.');
    }
}
```

### `app/Http/Controllers/Teacher/TeacherDashboardController.php` — `todaysPeriodsForTeacher()` only (shared file, Timetable-relevant method)

The security pattern every other Timetable-adjacent controller in this module cites and follows (`Auth::guard('teacher')` → `teacher_id`, never a request parameter — see the call site at line 149, `$this->todaysPeriodsForTeacher($teacherForToday->id)`, itself fed by `Auth::guard('teacher')->user()->teacher_id`).

```php
    private function todaysPeriodsForTeacher(int $teacherId)
    {
        $dayOfWeek = now()->format('l');

        return TimetableSlot::published()
            ->where(fn ($q) => $q->where('teacher_id', $teacherId)->orWhere('co_teacher_id', $teacherId))
            ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
            ->with(['subject', 'schoolClass', 'section', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
            ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
            ->values();
    }
```

---

## Part 4 — Reconciliation Additions (post-audit)

A prior read-only audit against this document found confirmed gaps: two security-relevant classes never inventoried (`BellTimingPolicy`, and the `TeacherAuth`/`ParentAuth` middleware that actually enforce every "authenticated teacher/parent identity" claim made elsewhere in this document), and two controller methods that read/relate to `TimetableSlot` data but were never inventoried (`AISmartFeaturesController::autoTimetable()`/`generateBasicTimetable()`, `AttendanceController::todaysTimetableForClass()`). All five are reproduced completely below, verbatim from current working-tree source, with no summarization.

### `app/Policies/BellTimingPolicy.php` (complete — 66 lines)

Authorization gate for every `BellTiming` CRUD action in both `app/Http/Controllers/BellTimingController.php` (web admin) and, indirectly via role checks duplicated in the API controller's own logic, the bell-timing surface of the module. Every `$this->authorize(...)` call in the web `BellTimingController` resolves here.

```php
<?php

namespace App\Policies;

use App\Models\BellTiming;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BellTimingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BellTiming $bellTiming): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BellTiming $bellTiming): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BellTiming $bellTiming): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BellTiming $bellTiming): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BellTiming $bellTiming): bool
    {
        return $user->hasRole('admin');
    }
}
```

**Note on scope:** every ability here is a plain role check (`admin`, or `admin`-or-`teacher` for reads/writes short of delete/restore/forceDelete) — there is no per-teacher ownership scoping on `BellTiming` the way `TimetableSlotPolicy::teacherAssignedToClassSection()` scopes `TimetableSlot`. Any authenticated teacher-role account can create/update/view any bell timing; only delete/restore/forceDelete are admin-only. This is the actual current authorization boundary for bell timings — not narrower than this document previously implied by omission.

### `app/Http/Middleware/TeacherAuth.php` (complete — 39 lines)

The middleware registered on every `Route::middleware(App\Http\Middleware\TeacherAuth::class)->group(...)` block in `routes/web.php` — this is what actually enforces "authenticated teacher identity" for `/teacher/dashboard`, `/teacher/timetable` (Teacher Weekly Timetable, Commit `3c24b82`), and every other teacher-portal route. It is the mechanism behind, not a duplicate of, `Auth::guard('teacher')` checks inside controllers — a request never reaches `TeacherTimetableController::index()` at all unless this middleware's `handle()` has already let it through.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('teacher')->check()) {
            return redirect()->route('teacher.login');
        }

        $teacherLogin = Auth::guard('teacher')->user();

        if (
            $teacherLogin->force_password_change
            && !$request->routeIs('teacher.password.change')
            && !$request->routeIs('teacher.password.update')
            && !$request->routeIs('teacher.logout')
        ) {
            return redirect()->route('teacher.password.change')
                ->with('warning', 'You must change your password before continuing.');
        }

        return $next($request);
    }
}
```

**Role, explained after the source above:** two gates, in order — (1) must be authenticated on the `teacher` guard, or redirect to login; (2) if the authenticated teacher login has `force_password_change` set, every route except the password-change flow itself and logout is redirected there first. No class/section/id scoping happens here at all — that authorization is entirely the job of `TimetableSlotPolicy` and the controllers' own `Auth::guard('teacher')->user()->teacher_id` resolution documented elsewhere in this file. This middleware only answers "is someone logged in as a teacher, and have they done their mandatory password reset" — it is not itself a source of any per-resource authorization decision.

### `app/Http/Middleware/ParentAuth.php` (complete — 37 lines)

The `'parent.auth'` middleware alias registered on the parent route group in `routes/web.php` — enforces "authenticated parent identity" for `/parent/timetable/today` and `/parent/timetable/weekly` (Parent Weekly Timetable, Commit `f523944`), and every other parent-portal route. Structurally identical in shape to `TeacherAuth` above, on the `parent` guard.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParentAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('parent')->check()) {
            return redirect()->route('parent.login');
        }

        $parent = Auth::guard('parent')->user();

        if (
            $parent->must_reset_password
            && !$request->routeIs('parent.password.reset')
            && !$request->routeIs('parent.password.update')
            && !$request->routeIs('parent.logout')
        ) {
            return redirect()->route('parent.password.reset');
        }

        return $next($request);
    }
}
```

**Role, explained after the source above:** same two-gate shape as `TeacherAuth` — must be authenticated on the `parent` guard (else redirect to `parent.login`), then a `must_reset_password` flag forces a password-reset redirect ahead of every other route except the reset flow and logout. Like `TeacherAuth`, this middleware carries no child-ownership logic — selected-child authorization, sibling isolation, and cross-family isolation are entirely `ParentModel::getStudentAttribute()`'s and `ParentAuthController::switchStudent()`'s job, both already fully documented earlier in this file. This middleware only answers "is someone logged in as a parent, and have they done their mandatory password reset."

### `app/Http/Controllers/Admin/AISmartFeaturesController.php` — `autoTimetable()` and `generateBasicTimetable()` only (shared file; 420 lines total, only these two methods are Timetable-relevant)

**Relationship to `GeneratorService` — do not conflate the two.** This is a **separate, independent, structurally trivial implementation** that has no connection to `GeneratorService`, `TimetableConflictResolver`, `TimetableSlot`, `BellTiming`, or any part of the real Timetable module's data model. It builds a plain in-memory PHP array (never persisted to `timetable_slots` or any table) by assigning a **random** subject and a **random** teacher to every period of every day for every class, with no constraint checking of any kind (no teacher-double-booking check, no availability check, no load cap, no subject-per-day cap, no period-type awareness). It is not a lightweight/basic version of the real generator; it does not share code, logic, or data with it.

**Reachability — read-only, established from the routes and views:**
- `grep`-ing `routes/web.php` and `routes/api.php` for `AISmartFeaturesController` or `autoTimetable`: **zero matches**. No route is registered for this method at all.
- `resources/views/admin/ai-smart-features/` (the view directory `autoTimetable()` renders, `admin.ai-smart-features.timetable`): **does not exist** on disk.
- The only repository references to `AISmartFeaturesController` outside its own file are in `docs/timetable-system-documentation.md` (a pre-existing document, used here only as corroborating structural evidence, not as a source of current-status claims) and `analyses/controller_summary.txt`, both of which independently record the same finding: no route, no view, dead code.

**Conclusion: this method is unreachable by any URL in the running application.** It is orphaned/dead code, not a live parallel generation path, and not part of any workflow a user (admin, teacher, or otherwise) can actually trigger. Neither implementation has been changed, removed, or touched by this reconciliation — this section is documentation only, per instruction.

Class context needed to place these two methods (constructor + surrounding class):

```php
class AISmartFeaturesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
```

#### `autoTimetable()` — public
Entry point (unreachable — no route). Loads all classes/teachers/subjects, delegates to `generateBasicTimetable()`, renders a view that does not exist on disk.

```php
    /**
     * Auto generate timetable
     */
    public function autoTimetable()
    {
        // Get all classes, teachers, and subjects
        $classes = \App\Models\SchoolClass::with(['sections'])->get();
        $teachers = Teacher::with(['subjects'])->get();
        $subjects = \App\Models\Subject::all();

        // Generate a basic timetable structure
        $timetable = $this->generateBasicTimetable($classes, $teachers, $subjects);

        return view('admin.ai-smart-features.timetable', compact('timetable', 'classes', 'teachers', 'subjects'));
    }
```

#### `generateBasicTimetable($classes, $teachers, $subjects)` — private
Builds a hard-coded 6-day × 7-period grid and fills every cell with `$subjects->random()` / `$teachers->random()` — no constraint logic, no persistence, no relation to `TimetableSlot`/`BellTiming`/`GeneratorService` whatsoever.

```php
    /**
     * Generate basic timetable
     */
    private function generateBasicTimetable($classes, $teachers, $subjects)
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $periods = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th'];

        $timetable = [];

        foreach ($classes as $class) {
            $timetable[$class->name] = [];
            foreach ($days as $day) {
                $timetable[$class->name][$day] = [];
                foreach ($periods as $period) {
                    // Assign a random subject/teacher combination
                    $subject = $subjects->random();
                    $teacher = $teachers->random();
                    
                    $timetable[$class->name][$day][$period] = [
                        'subject' => $subject->name ?? 'General',
                        'teacher' => $teacher->name ?? 'TBA',
                        'room' => 'Room ' . rand(101, 210)
                    ];
                }
            }
        }

        return $timetable;
    }
```

### `app/Http/Controllers/AttendanceController.php` — `todaysTimetableForClass()` only (shared file; 670 lines total, only this method is Timetable-relevant)

**Relationship to `TimetableSlot` and published-timetable data:** T5 item 3 — a read-only reference panel shown on the attendance-marking screen (`AttendanceController::index()`) so whoever is marking attendance can see what's actually scheduled, without that panel changing anything about how attendance is marked (subject/period on the attendance form stay manually chosen, exactly as before this existed). Uses `TimetableSlot::published()` exclusively — the same published-only discipline as every other reader in this module — and overlays the day's substitutions as "Arrangement," the identical pattern `Teacher\TeacherTimetableController`, `Parent\TimetableController`, and `API\ParentTimetableController` all use. Resolves the class via `Student::resolveCanonicalSchoolClass()` (Phase A closure's canonical-class resolution) rather than string-matching the free-text `class` group value, for the same reliability reason the rest of the app relies on `school_class_id` as the source of truth.

Call site, immediately preceding the method (confirms this never breaks attendance-marking itself — any failure here degrades to an empty panel, logged, swallowed):

```php
        // T5 item 3: read-only reference panel -- today's PUBLISHED
        // timetable for this class, so whoever is marking attendance can
        // see what's actually scheduled without it changing anything
        // about how attendance itself is marked (subject/period stay
        // manually chosen, exactly as before). Never allowed to break
        // this screen: any failure here (unresolvable class, missing
        // timetable data, whatever) just means an empty panel.
        $timetableToday = collect();
        try {
            $timetableToday = $this->todaysTimetableForClass($students->first(), $date);
        } catch (\Throwable $e) {
            Log::error('Failed to load timetable reference panel for attendance marking: ' . $e->getMessage());
        }
```

#### `todaysTimetableForClass(?Student $sampleStudent, string $date): \Illuminate\Support\Collection` — private

```php
    /**
     * T5 item 3: today's (well, $date's) PUBLISHED timetable for the class
     * one of its own students canonically resolves to -- resolveCanonicalSchoolClass()
     * is more reliable than string-matching the free-text $class group
     * value directly against SchoolClass::name, since it uses the same
     * school_class_id-is-master resolution the rest of the app already
     * relies on (Phase A closure). Substitutions for the date are applied
     * the same way the parent view does: substitute teacher shown, marked
     * as an arrangement.
     */
    private function todaysTimetableForClass(?Student $sampleStudent, string $date): \Illuminate\Support\Collection
    {
        if (!$sampleStudent) {
            return collect();
        }

        $schoolClass = $sampleStudent->resolveCanonicalSchoolClass();
        if (!$schoolClass) {
            return collect();
        }

        $dayOfWeek = \Carbon\Carbon::parse($date)->format('l');

        $slots = TimetableSlot::published()
            ->where('school_class_id', $schoolClass->id)
            ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
            ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
            ->values();

        $substitutions = TeacherSubstitution::where('class_id', $schoolClass->id)
            ->whereDate('substitution_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('bell_timing_id');

        return $slots->map(function (TimetableSlot $slot) use ($substitutions) {
            $sub = $substitutions->get($slot->bell_timing_id);
            $isArrangement = (bool) ($sub && $sub->substitute_teacher_id);

            return (object) [
                'period_name' => $slot->bellTiming->period_name,
                'subject_name' => $slot->subject->name ?? 'N/A',
                'teacher_name' => $isArrangement ? ($sub->substituteTeacher->name ?? 'N/A') : ($slot->teacher->name ?? 'N/A'),
                'is_arrangement' => $isArrangement,
            ];
        })->values();
    }
```

Relevant imports confirmed present at the top of this file (`namespace App\Http\Controllers;`): `use App\Models\TeacherSubstitution;` and `use App\Models\TimetableSlot;` (both already used identically throughout this document's other controller sections).

### `app/Policies/TeacherSubstitutionPolicy.php` (complete — 108 lines)

Reconciliation addition (production-readiness audit follow-up item 1). Authorization gate for every `TeacherSubstitutionController` action — permission-based rather than pure role-based like `BellTimingPolicy`: most abilities accept either the `admin` role or a specific granular permission (`view-teachers` for reads, `manage-substitutions` for writes), independent of any per-teacher ownership scoping. `delete` and `manageRules` remain admin-only with no permission escape hatch.

```php
<?php

namespace App\Policies;

use App\Models\TeacherSubstitution;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeacherSubstitutionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can assign substitutes.
     */
    public function assignSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can approve substitutes.
     */
    public function approveSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can cancel substitutes.
     */
    public function cancelSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * T3 item 3: the "teacher absent today" flow -- view the day's slots
     * and ranked suggestions, and one-click assign. Same admin +
     * manage-substitutions gate as the rest of the write actions above.
     */
    public function manageAbsentToday(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can view today's substitutions.
     */
    public function viewTodaySubstitutions(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can view absence overview.
     */
    public function viewAbsenceOverview(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can manage substitution rules.
     */
    public function manageRules(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
```

**Method count note:** this class has **12** methods (`viewAny`, `view`, `create`, `update`, `delete`, `assignSubstitute`, `approveSubstitute`, `cancelSubstitute`, `manageAbsentToday`, `viewTodaySubstitutions`, `viewAbsenceOverview`, `manageRules`) — all 12 reproduced above, verbatim, complete.

*(end of Part 4 — all 5 previously-confirmed production-readiness-audit gaps closed: `BellTimingPolicy`, `TeacherAuth`, `ParentAuth`, `AISmartFeaturesController::autoTimetable()`/`generateBasicTimetable()`, `AttendanceController::todaysTimetableForClass()`, plus this follow-up addition: `TeacherSubstitutionPolicy`)*
