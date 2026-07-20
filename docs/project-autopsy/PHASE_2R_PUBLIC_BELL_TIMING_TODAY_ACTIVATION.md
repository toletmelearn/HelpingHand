# Phase 2R - Public Bell Timing Today API Activation

## 1. Files Inspected

- `app/Http/Middleware/ApiAccessControl.php`
- `app/Http/Controllers/API/BellTimingController.php`
- `tests/Feature/API/BellTimingTodayRouteTest.php`
- `docs/project-autopsy/PHASE_2Q_BELL_TIMING_TODAY_IMPLEMENTATION.md`

## 2. Files Changed

- `app/Http/Middleware/ApiAccessControl.php`
- `tests/Feature/API/BellTimingTodayRouteTest.php`
- `docs/project-autopsy/PHASE_2R_PUBLIC_BELL_TIMING_TODAY_ACTIVATION.md`

## 3. Blocklist Change Summary

Removed only this route from `ApiAccessControl::publicTemporaryBlocklist()`:

- `api.bell-timing.today`

Kept these routes blocked:

- `api.exam-papers.available-for-class`
- `api.exam-papers.search`

During verification, removing `api.bell-timing.today` from the temporary blocklist was not sufficient because `ApiAccessControl` denies unauthenticated routes unless they are explicitly allowlisted. The smallest activation fix was then applied:

- Added `api.bell-timing.today` to `ApiAccessControl::publicAllowlist()`.

No other authorization branches were changed.

## 4. Bell Timing Public Status

`api.bell-timing.today` is now public through `ApiAccessControl::publicAllowlist()`.

Current public allowlist:

- `api.login`
- `api.register`
- `api.bell-timing.today`

## 5. Exam-Paper Public Routes Blocked Confirmation

Exam-paper public routes remain blocked by `ApiAccessControl::publicTemporaryBlocklist()`:

- `GET /api/v1/exam-papers/available/10-A` returns `403`
- `POST /api/v1/exam-papers/search` returns `403`

The API exam-paper methods are still not implemented and were not opened in this phase.

## 6. Tests Updated

Updated `tests/Feature/API/BellTimingTodayRouteTest.php`.

Changed the old blocked-route test into:

- `bell_timing_today_route_returns_public_safe_schedule`

This test:

- creates isolated in-memory `bell_timings` rows;
- calls `GET /api/v1/bell-timing/today/10-A`;
- asserts HTTP `200`;
- asserts `success = true`;
- asserts `class_section`, `day`, and `schedule`;
- confirms inactive rows are excluded;
- confirms other class-section rows are excluded;
- confirms other day rows are excluded;
- confirms schedule order follows `order_index`;
- confirms only safe schedule fields are exposed.

Added/kept:

- `exam_paper_public_routes_remain_blocked_by_api_access_control`

This test confirms both exam-paper public routes still return `403`.

The test file still does not use `RefreshDatabase` and does not run full project migrations.

## 7. Commands Run

```powershell
php -l app/Http/Middleware/ApiAccessControl.php
php -l tests/Feature/API/BellTimingTodayRouteTest.php
php artisan route --path=api/v1 | Select-String "bell-timing"
php artisan test --filter=BellTimingTodayRouteTest --env=testing
php artisan test --filter=ApiAccessControlAbilityTest --env=testing
php artisan test --filter=SanctumTokenAbilityTest --env=testing
rg -n "publicAllowlist|publicTemporaryBlocklist|api.bell-timing.today|api.exam-papers.available-for-class|api.exam-papers.search" app/Http/Middleware/ApiAccessControl.php tests/Feature/API/BellTimingTodayRouteTest.php docs/project-autopsy -g "*.php" -g "*.md"
```

## 8. Test Result Summary

Syntax checks:

- `ApiAccessControl.php`: passed
- `BellTimingTodayRouteTest.php`: passed

Targeted tests:

- `BellTimingTodayRouteTest`: passed, 2 tests / 18 assertions
- `ApiAccessControlAbilityTest`: passed, 10 tests / 10 assertions
- `SanctumTokenAbilityTest`: passed, 6 tests / 19 assertions

The test runner emitted existing PHPUnit doc-comment metadata deprecation warnings from unrelated tests. These warnings did not fail the targeted test filters.

## 9. Failures And Fixes

Initial targeted run failed:

- `BellTimingTodayRouteTest::bell_timing_today_route_returns_public_safe_schedule`
- Expected `200`, received `403`.

Cause:

- `api.bell-timing.today` had been removed from the temporary blocklist, but it was not in `publicAllowlist()`.
- `ApiAccessControl` denies unauthenticated routes that are neither blocklisted nor public-allowlisted.

Fix:

- Added only `api.bell-timing.today` to `publicAllowlist()`.
- Re-ran the targeted test successfully.

## 10. Route Command Note

The requested command:

```powershell
php artisan route --path=api/v1 | Select-String "bell-timing"
```

is not a valid Artisan command form in this application. Laravel listed the valid `route:*` commands and no application/database state was changed.

The route behavior was verified by targeted HTTP feature tests instead.

## 11. Full Suite / Migration / Database Safety Confirmation

- Full test suite was not run.
- Project migrations were not run.
- `migrate`, `migrate:fresh`, `db:wipe`, and composer setup were not run.
- Real `.env` was not changed.
- Real/local MySQL data was not touched.
- The updated test uses only isolated SQLite-memory tables during targeted test execution.

## 12. Remaining Risks

- `api.bell-timing.today` is now public and should be monitored for rate-limit behavior under real traffic.
- The route still accepts free-form `{classSection}` strings; the current query is Eloquent-bound and safe, but product-level validation/normalization may be useful later.
- `api.exam-papers.available-for-class` and `api.exam-papers.search` remain registered but blocked because their API controller methods are still missing.

## 13. Recommended Next Step

Phase 2S should keep exam-paper public routes blocked and either:

1. implement `api.exam-papers.available-for-class` with strict public metadata filters and isolated tests; or
2. formally convert exam-paper public routes to authenticated-only routes in a dedicated API route/security phase.
