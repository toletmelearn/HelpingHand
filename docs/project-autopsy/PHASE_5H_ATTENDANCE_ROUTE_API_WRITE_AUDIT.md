# Phase 5H - Attendance Route Order and API Bulk-Mark Risk Audit

Date: 2026-06-05

Scope: Read-only audit of attendance web route order and API attendance write routes after Phase 5G guarded direct web bulk attendance writes.

## Read-Only Confirmation

- No application code, routes, views, controllers, services, tests, or migrations were modified.
- No attendance write route was executed.
- No API bulk-mark/store/update/destroy route was executed.
- No biometric sync or device command was run.
- No database data was created, updated, deleted, seeded, imported, synced, or otherwise mutated.
- No migrations, composer setup, or full test suite were run.
- No real/local MySQL data was touched.
- Only this report file was created: `docs/project-autopsy/PHASE_5H_ATTENDANCE_ROUTE_API_WRITE_AUDIT.md`.

## Files Inspected

- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `app/Models/Attendance.php`
- `resources/views/attendance/bulk_mark.blade.php`
- `resources/views/attendance/preflight-result.blade.php`
- `tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `docs/project-autopsy/PHASE_5G_ATTENDANCE_BULK_DIRECT_WRITE_GUARD.md`
- `docs/project-autopsy/PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT.md`
- `docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md`

## Commands Run

- `Get-Content routes/web.php`
- `Get-Content routes/api.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Http/Controllers/API/AttendanceController.php`
- `Get-Content app/Services/Attendance/AttendanceBulkPreflightService.php`
- `Get-Content app/Models/Attendance.php`
- `Get-Content resources/views/attendance/bulk_mark.blade.php`
- `Get-Content resources/views/attendance/preflight-result.blade.php`
- `Get-Content tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php`
- `Get-Content tests/Feature/Attendance/AttendancePreflightUiTest.php`
- `Get-Content tests/Feature/Attendance/AttendanceBulkPreflightEndpointTest.php`
- `Get-Content tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `Get-Content docs/project-autopsy/PHASE_5G_ATTENDANCE_BULK_DIRECT_WRITE_GUARD.md`
- `Get-Content docs/project-autopsy/PHASE_5F_ATTENDANCE_STORE_WRITE_PATH_AUDIT.md`
- `Get-Content docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md`
- `rg -n "attendance/(bulk-mark|preflight|preflight-view)|Route::resource\('attendance'|apiResource\('attendance'|bulkMark|public function store|public function update|public function destroy|studentMonthlyReport|dailyReport|Attendance::insert|Attendance::create|marked_by" routes/web.php routes/api.php app/Http/Controllers/AttendanceController.php app/Http/Controllers/API/AttendanceController.php resources/views/attendance/bulk_mark.blade.php resources/views/attendance/preflight-result.blade.php tests/Feature/Attendance/AttendanceBulkDirectWriteGuardTest.php docs/project-autopsy/PHASE_5G_ATTENDANCE_BULK_DIRECT_WRITE_GUARD.md`
- `rg -n "Route::get\('/attendance/(reports|export|bulk-mark)|Route::resource\('attendance'|Route::post\('attendance/preflight|Route::apiResource\('attendance'|Route::post\('/attendance/bulk-mark|Route::get\('/attendance/(student|class)" routes/web.php routes/api.php`
- `php -l app/Http/Controllers/AttendanceController.php`
- `php -l app/Http/Controllers/API/AttendanceController.php`
- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php`
- `php artisan route | Select-String "attendance"` - failed; this Laravel app exposes `route:list`, not a bare `route` listing command.
- `php artisan route --path=attendance` - failed for the same reason.
- `php artisan route --path=api/v1/attendance` - failed for the same reason.

No live DB checks were run in this phase.

## Web Route Order Findings

### Admin Attendance Cluster

Evidence from `routes/web.php`:

- Line 456: `Route::post('attendance/preflight', [AttendanceController::class, 'preflight'])->name('attendance.preflight');`
- Line 457: `Route::post('attendance/preflight-view', [AttendanceController::class, 'preflightView'])->name('attendance.preflight-view');`
- Line 458: `Route::resource('attendance', AttendanceController::class);`

Findings:

- Admin preflight custom routes are registered before the admin resource route.
- This protects `admin/attendance/preflight` and `admin/attendance/preflight-view` from being swallowed by `admin/attendance/{attendance}`.
- There is no admin `attendance/bulk-mark` custom route in this inspected cluster.

### Unprefixed Attendance Cluster

Evidence from `routes/web.php`:

- Line 1087: `Route::get('/attendance/reports', [AttendanceController::class, 'reports'])->name('attendance.reports');`
- Line 1088: `Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');`
- Line 1089: `Route::resource('attendance', AttendanceController::class)->except(['reports', 'export']);`
- Line 1090: `Route::get('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk-mark');`
- Line 1091: `Route::get('/attendance/student/{studentId}/report', [AttendanceController::class, 'studentReport'])->name('attendance.student.report');`

Findings:

- `attendance/bulk-mark` is registered after `Route::resource('attendance', ...)`.
- The resource route includes `GET attendance/{attendance}` for `AttendanceController@show`.
- Because Laravel matches routes in registration order, `GET /attendance/bulk-mark` can match the earlier `attendance/{attendance}` route before the later custom `attendance/bulk-mark` route.
- Phase 5G's direct route-render test observed this exact collision in the test environment: `/attendance/bulk-mark` was interpreted by the resource show route and attempted to render `attendance.show`.
- This is a route-order bug in the source route registration order.

### Route List Command Status

The requested route commands were run but did not produce route rows:

- `php artisan route | Select-String "attendance"` failed because `route` is a namespace, not a list command in this app.
- `php artisan route --path=attendance` failed for the same reason.
- `php artisan route --path=api/v1/attendance` failed for the same reason.

Because the allowed route commands fail in this Laravel installation, this audit does not claim a live `route:list --path` output. The route-order risk is still confirmed by source order and the Phase 5G observed route collision.

### Named Route Resolution

- The route name in source is `attendance.bulk-mark` with a hyphen.
- The prompt mentions `attendance.bulk_mark` with an underscore; that underscore route name was not found in inspected source.
- The named route can exist while URL matching remains unsafe, because named URL generation and incoming URL dispatch are different concerns.
- `resources/views/attendance/bulk_mark.blade.php` currently uses `route('attendance.index')`, `route('attendance.create')`, and a preview action that falls back between `attendance.preflight-view` and `admin.attendance.preflight-view`.
- The bulk form itself does not currently call `route('attendance.bulk-mark')`.

### Safest Route Fix

The safest eventual route fix is to move custom attendance routes above the resource route, specifically:

- Move `/attendance/bulk-mark` before `Route::resource('attendance', ...)`.
- Also consider moving `/attendance/student/{studentId}/report` before the resource route, because it also appears after `attendance/{attendance}` and can be swallowed by the resource show route.

No route order was changed in this phase.

## Web Bulk Guard Confirmation

Confirmed from `AttendanceController@store()`:

- The bulk branch condition remains:
  - `$request->filled('classes') && $request->filled('default_status')`
- At the start of that branch, the method returns with:
  - "Direct bulk attendance marking is temporarily disabled. Please use Preview first. Safe bulk apply is not enabled yet."
- This return occurs before validation, student expansion, or any `Attendance::insert()` in the branch.

Confirmed from `resources/views/attendance/bulk_mark.blade.php`:

- The direct submit button with save icon/text was removed.
- The Preview button remains.
- Helper text says direct bulk marking is disabled until safe apply is enabled.

Confirmed from `resources/views/attendance/preflight-result.blade.php`:

- No Apply button.
- No Confirm button.
- No Mark Attendance button.
- Only Back links are present.

## API Attendance Route Surface Map

All API routes below are inside:

- Prefix: `/api/v1`
- Middleware group: `auth:sanctum`, `throttle:60,1`, `App\Http\Middleware\ApiAccessControl`

| Method | URI | Route name | Controller method | Read/write | Risk |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/attendance` | `attendance.index` | `API\AttendanceController@index` | Read | YELLOW |
| POST | `/api/v1/attendance` | `attendance.store` | `API\AttendanceController@store` | Write | RED |
| GET | `/api/v1/attendance/{attendance}` | `attendance.show` | `API\AttendanceController@show` | Read | GREEN/YELLOW |
| PUT/PATCH | `/api/v1/attendance/{attendance}` | `attendance.update` | `API\AttendanceController@update` | Write | RED |
| DELETE | `/api/v1/attendance/{attendance}` | `attendance.destroy` | `API\AttendanceController@destroy` | Write | RED |
| GET | `/api/v1/attendance/student/{studentId}/monthly/{month}/{year}` | `api.attendance.student-monthly` | `API\AttendanceController@studentMonthlyReport` | Read | GREEN |
| GET | `/api/v1/attendance/class/{classSection}/daily/{date}` | `api.attendance.daily-report` | `API\AttendanceController@dailyReport` | Read | YELLOW |
| POST | `/api/v1/attendance/bulk-mark` | `api.attendance.bulk-mark` | `API\AttendanceController@bulkMark` | Write | RED |

Notes:

- `Route::apiResource('attendance', AttendanceController::class)` is registered before custom report and bulk-mark API routes in `routes/api.php`.
- `POST /attendance/bulk-mark` is not likely swallowed by resource `show` because resource `show` is GET-only and resource `store` is POST `/attendance`, not POST `/attendance/{attendance}`.
- The custom GET routes under `/attendance/student/...` and `/attendance/class/...` are registered after `GET /attendance/{attendance}` from the API resource. Because `{attendance}` only consumes one segment, these multi-segment GET routes are less likely to be swallowed than the one-segment web `bulk-mark`, but placing custom static/prefix routes above resource routes is still cleaner.
- Route names from `apiResource('attendance', ...)` are generic (`attendance.store`, etc.), not prefixed with `api.` in the inspected source, which can be confusing alongside web route names.

## API `bulkMark()` Findings

Location: `app/Http/Controllers/API/AttendanceController.php`, around line 145.

Input fields:

- `date`
- `class`
- `subject`
- `period`
- `student_ids`
- `statuses`
- `remarks` read opportunistically but not validated
- `marked_by`

Validation rules:

- `date`: required date
- `class`: required string max 50
- `subject`: required string max 100
- `period`: nullable string max 50
- `student_ids`: required array
- `student_ids.*`: exists students id
- `statuses`: required array
- `statuses.*`: in `present,absent,late,half_day`
- `marked_by`: required exists users id

Detailed findings:

- `student_ids.*` is validated for existence.
- `statuses.*` is validated for allowed status values.
- Row count/index alignment between `student_ids` and `statuses` is not checked.
- Duplicate `student_ids` inside the same payload are not checked.
- Existing attendance duplicates in the database are not prechecked.
- Terminal/inactive students are not excluded.
- Student class membership is not checked against submitted legacy `class`.
- `remarks.*` is not validated even though remarks are read.
- It does not use `AttendanceBulkPreflightService`.
- It does not use a transaction.
- It uses raw `Attendance::insert($attendances)`.
- It returns full success if insert succeeds.
- It catches generic `Exception` and returns generic failure if insert fails.
- It does not return row-level partial success/failure information.
- It can violate unique constraints if any row already exists for the same `student_id,date,period`.
- It writes legacy `class` string, not canonical `class_id`.
- It trusts caller-supplied `marked_by`.

Risk classification: RED.

## API Single `store()` Findings

Location: `app/Http/Controllers/API/AttendanceController.php`, around line 28.

Duplicate behavior:

- It checks for an existing row by `student_id`, `date`, and `period`.
- If found, it returns an error with HTTP 409.
- It then calls `Attendance::create($validated)`.

Transaction/race risk:

- There is no transaction.
- There is no lock or atomic upsert.
- Concurrent requests can pass the duplicate check at the same time and race into create.
- DB unique constraints may catch the duplicate, but the controller catches generic exceptions and returns generic failure.

Terminal/inactive handling:

- No terminal/inactive student status check.
- No `student_statuses` inspection.

Class string versus canonical class id:

- Requires legacy `class` string.
- Does not accept or validate `class_id`.
- Does not confirm submitted class matches the student's actual legacy or canonical class.

`marked_by` trust risk:

- `marked_by` is required and only checked as an existing user id.
- The API caller can submit another user's id.
- The controller does not force `marked_by` to the authenticated API user.
- The update route also allows `marked_by` changes when supplied.

Risk classification: RED/YELLOW.

## Live Read-Only Counts

No live database counts were checked in Phase 5H. Prior Phase 5F read-only counts remain relevant but were not refreshed here.

## RED/YELLOW/GREEN Risk Classification

### RED

- Unprefixed `/attendance/bulk-mark` is registered after `Route::resource('attendance', ...)`, so it can collide with `attendance/{attendance}`.
- API `bulkMark()` remains an unguarded raw bulk insert path.
- API `bulkMark()` does not precheck existing attendance duplicates.
- API `bulkMark()` does not exclude terminal/inactive students.
- API `store()` and `update()` trust caller-supplied `marked_by`.
- API `destroy()` is exposed as an attendance write/delete route.

### YELLOW

- API `store()` has a duplicate precheck but no transaction or atomic upsert.
- API attendance routes use generic route names from `apiResource('attendance', ...)`, which can be confusing alongside web routes.
- API read routes and reports still rely on legacy `class` string.
- API custom routes are registered after `apiResource`, which is less immediately broken than the web one-segment collision but still not ideal route hygiene.
- Web individual/per-student store remains unchanged and still uses raw insert.

### GREEN

- Web bulk `classes[] + default_status` direct write is guarded server-side.
- Bulk UI no longer renders a direct Mark Attendance submit button.
- Preview remains available.
- Preflight result page remains read-only.
- `AttendanceBulkPreflightService` remains read-only and can detect payload duplicates, existing attendance, and terminal/inactive student status.

## Top 10 Route/API Attendance Risks

1. `/attendance/bulk-mark` is registered after `attendance/{attendance}` and can be routed to `show`.
2. `/attendance/student/{studentId}/report` is also registered after the resource route and may be at risk from `attendance/{attendance}` ordering.
3. API `bulkMark()` uses raw `Attendance::insert()` with no transaction.
4. API `bulkMark()` does not precheck database duplicates.
5. API `bulkMark()` does not detect duplicate students inside the same payload.
6. API `bulkMark()` does not exclude terminal/inactive students.
7. API `bulkMark()` trusts submitted legacy `class` without checking student class membership.
8. API `store()` and `update()` allow caller-supplied `marked_by`, enabling spoofing of the marker identity.
9. API `store()` duplicate check is non-atomic and race-prone.
10. API `destroy()` can delete attendance records and was not changed by Phases 5G/5H.

## Recommended Phase 5I First Code Task

Recommended first task: Option A - fix web route order first.

Rationale:

- The route-order bug is confirmed by source order and was observed during Phase 5G testing.
- It is small, localized, and affects access to the safe preview-only bulk UI.
- If `/attendance/bulk-mark` resolves to `show`, users may not reliably reach the preview-only bulk page.
- Fixing route order does not require enabling any write/apply behavior.

Recommended Phase 5I route-order fix:

- Move custom unprefixed attendance routes above the unprefixed resource route:
  - `/attendance/bulk-mark`
  - `/attendance/student/{studentId}/report`
  - keep `/attendance/reports` and `/attendance/export` above resource as they already are
- Keep preflight routes before resource in the admin cluster.
- Add a narrow route test proving `/attendance/bulk-mark` dispatches to `AttendanceController@bulkMark`, not `show`.

Next task after route order:

- Option B - guard API `bulkMark()` by returning a controlled disabled response until safe API preflight/apply exists.

Not recommended as immediate first task:

- Option D, converting API `bulkMark()` to preflight-backed insert, changes write semantics and should wait until the API apply contract is designed.
- Option E, safe web apply design, is important but should follow the route-order fix and API bulk guard.

## Final Confirmation

- No code was modified.
- No route order was changed.
- No views were changed.
- No controllers/services/tests were changed.
- No attendance write was executed.
- No API write was executed.
- No database data was touched.
- No biometric sync or device command was run.
