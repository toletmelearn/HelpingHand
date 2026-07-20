# Phase 5U - Attendance Null-Period Policy Audit

Date: 2026-06-05

Scope: Read-only audit of attendance `period` nullability and duplicate policy across schema, web/API/teacher write paths, reports/views, and preflight. No application code, routes, controllers, services, models, tests, migrations, database data, attendance actions, biometric sync, or device commands were modified or run.

## Files Inspected

- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `app/Services/Attendance/AttendanceBulkPreflightService.php`
- `app/Services/AttendanceService.php`
- `app/Models/Attendance.php`
- `resources/views/attendance/create.blade.php`
- `resources/views/attendance/bulk_mark.blade.php`
- `resources/views/attendance/edit.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`
- `resources/views/attendance/reports.blade.php`
- `resources/views/attendance/preflight-result.blade.php`
- `resources/views/attendance/student_report.blade.php`
- `database/migrations/2026_01_21_083000_create_attendances_table.php`
- `database/migrations/2026_01_21_084000_create_attendances_temp_table.php`
- `database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php`
- `tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`
- `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md`
- `docs/project-autopsy/PHASE_5S_API_ATTENDANCE_STORE_DUPLICATE_RACE_AUDIT.md`
- `docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md`

Note: the requested path `tests/Feature/Attendance/AttendanceBulkPreflightServiceTest.php` does not exist. The service test is present at `tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`.

## Commands Run

- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Http/Controllers/API/AttendanceController.php`
- `Get-Content app/Http/Controllers/Teacher/TeacherAttendanceController.php`
- `Get-Content app/Services/Attendance/AttendanceBulkPreflightService.php`
- `Get-Content app/Services/AttendanceService.php`
- `Get-Content app/Models/Attendance.php`
- `Get-ChildItem resources/views/attendance`
- `Get-Content routes/web.php`
- `Get-Content routes/api.php`
- `Get-Content tests/Feature/Attendance/AttendanceApiStoreDuplicateHandlingTest.php`
- `rg -n "period|Period|full_day|full-day|all_day|All Day|Full Day|isMarked|attendance_rows|whereNull\('period'\)|where\('period'|groupBy\('period'|subject" resources/views/attendance app/Http/Controllers app/Services app/Models database/migrations tests/Feature/Attendance/AttendanceBulkPreflightServiceTest.php docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md docs/project-autopsy/PHASE_5S_API_ATTENDANCE_STORE_DUPLICATE_RACE_AUDIT.md docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md`
- `Get-Content database/migrations/2026_01_21_083000_create_attendances_table.php`
- `Get-Content database/migrations/2026_01_21_084000_create_attendances_temp_table.php`
- `Get-Content database/migrations/2026_01_21_120001_add_relationships_to_existing_tables.php`
- `Get-Content tests/Feature/Attendance/AttendanceBulkPreflightServiceTest.php` - failed because the file does not exist
- `Get-ChildItem tests -Recurse -Filter *Preflight*`
- `Get-Content resources/views/attendance/create.blade.php`
- `Get-Content resources/views/attendance/bulk_mark.blade.php`
- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content resources/views/attendance/show.blade.php`
- `Get-Content resources/views/attendance/edit.blade.php`
- `Get-Content resources/views/attendance/preflight-result.blade.php`
- `Get-Content resources/views/attendance/student_report.blade.php`
- `Get-Content tests/Unit/Services/AttendanceBulkPreflightServiceTest.php`
- `php -l app/Http/Controllers/AttendanceController.php` - passed
- `php -l app/Http/Controllers/API/AttendanceController.php` - passed
- `php -l app/Services/Attendance/AttendanceBulkPreflightService.php` - passed
- `php -l app/Services/AttendanceService.php` - passed
- `php artisan route | Select-String "attendance"` - failed because this application exposes `route:list`; `route` is an Artisan namespace, not a route-listing command
- `rg -n "Attendance::(create|insert|updateOrCreate)|->attendances\(\)|DB::table\('attendances'\)|period\s*=>|period'|biometric|sync|import|webhook|Attendance::isMarked|student_attendance" app routes database resources tests docs/project-autopsy/PHASE_5A_ATTENDANCE_MODULE_AUDIT.md docs/project-autopsy/PHASE_5T_API_ATTENDANCE_STORE_DUPLICATE_EXCEPTION_GUARD.md`

No live database counts were run in this phase.

## Schema Null-Period Findings

- `attendances.period` is nullable: `string('period')->nullable()`.
- `attendances.period` has no default value.
- `attendances` has a unique key on `student_id,date,period`.
- `attendances` also has a standalone `period` index.
- `attendances_temp.period` is also nullable and indexed, but `attendances_temp` has no unique key.
- `Attendance::$fillable` includes `period`.
- `Attendance::$casts` does not cast or normalize `period`.
- The schema does not define a sentinel value such as `full_day`, `full-day`, or `all_day`.
- Because this project uses MySQL locally, the nullable `period` inside a unique key is a policy risk: MySQL permits multiple unique-index rows when one indexed component is `NULL`. Therefore `student_id,date,NULL` is not guaranteed to be unique by the database.

## Write-Path Null-Period Findings

### Web `AttendanceController@store`

- The guarded bulk `classes[] + default_status` branch validates `period` as `nullable|string`, but Phase 5G returns before any write.
- The unreachable legacy bulk write code would write `period => $request->period`.
- The individual/per-student branch validates `period` as `nullable|string`.
- The individual branch writes `period => $request->period` through raw `Attendance::insert`.
- If the period field is omitted, the stored value is effectively `NULL`.
- If an empty string is submitted, validation allows it and the write path can store `''`.
- Duplicate prevention uses `Attendance::isMarked($class, $date, $period)`.
- `Attendance::isMarked()` treats a falsy period as a full-day/class-date check, but it does not normalize the stored value before insert.
- This creates a mismatch: duplicate checking can treat empty string like no period, while storage may preserve empty string.

### Web `ensureAllStudentsPresent`

- This helper writes `period => null` and `subject => 'General'`.
- Its duplicate check calls `Attendance::isMarked($class, $date)` with no period.
- The method is no longer called from `create()`, but it still exists as a full-day write helper.

### Web `AttendanceController@update`

- The web update action still validates and writes `period`.
- This means web edits can still convert between null, empty string, and named period values.
- Phase 5P only guarded API update period mutation; web update was not changed in those phases.

### API `AttendanceController@store`

- API store validates `period` as `nullable|string|max:50`.
- The duplicate pre-check uses `where('period', $validated['period'] ?? null)`.
- In Laravel this can compile to a null-aware query, but the policy is implicit and not centralized.
- `Attendance::create($validated)` preserves the supplied value, so omitted period can become `NULL` and empty string can remain `''`.
- Phase 5T added controlled duplicate-key `QueryException` handling, but MySQL may not throw a duplicate-key exception for repeated `NULL` period rows.
- API store does not normalize period.

### API `bulkMark`

- API bulkMark is guarded and returns HTTP 423 before validation or insert.
- The old guarded code accepted `period` as nullable and would have written caller-supplied period values.
- Because the method is currently disabled, it is not an active null-period write path.

### Teacher Attendance Flow

- `TeacherAttendanceController@storeAttendance` validates class/date/student statuses but does not accept a period input.
- It writes through `AttendanceService::markAttendance`.
- `AttendanceService::markAttendance` uses `Attendance::updateOrCreate(['student_id' => ..., 'date' => ...], ...)`, with no period in identity or values.
- This path behaves conceptually like full-day attendance by student/date.
- It does not participate in the `student_id,date,period` uniqueness policy because it ignores period.
- It also uses `class_id` values against the `Attendance` model, while the `attendances` table stores legacy `class`; that is an adjacent schema mismatch already identified in earlier phases.

### Biometric / Import / Sync Paths

- Biometric sync routes and services exist, including device sync and webhook surfaces.
- The searched biometric services focus on teacher biometric records, not direct writes to `attendances.period`.
- No biometric sync or device command was executed.
- No attendance import/sync path was executed.

## Read / Report Null-Period Findings

- `resources/views/attendance/create.blade.php` presents a period option with empty value labeled `Full Day`.
- `resources/views/attendance/show.blade.php` displays `period ?? 'Full Day'`, so `NULL` is shown as `Full Day`.
- `resources/views/attendance/index.blade.php` displays `Period X` when period is truthy, otherwise `N/A`.
- `resources/views/attendance/reports.blade.php` displays `Period X` when period is truthy, otherwise `-`.
- `resources/views/attendance/edit.blade.php` uses a text input and can submit blank period values.
- `resources/views/attendance/bulk_mark.blade.php` uses an optional period input but does not explicitly label blank as `Full Day`.
- `resources/views/attendance/preflight-result.blade.php` does not display period in the row details or summary.
- `resources/views/attendance/student_report.blade.php` does not display period.
- `AttendanceController@exportToCsv` writes the raw period value, so `NULL` becomes a blank CSV field rather than a `Full Day` label.
- `Attendance::getStudentMonthlyReport()` returns date/status/remarks and omits period.
- `Attendance::getAttendanceStats()` counts all rows for class/date and does not distinguish full-day from period-specific records.
- Display semantics are inconsistent: create/show imply `NULL` means `Full Day`, while index/report/export render it as blank, dash, or N/A.
- Empty string is not equivalent to `NULL` in display code using `??`; an empty string can render as blank rather than `Full Day`.

## Preflight Null-Period Findings

- `AttendanceBulkPreflightService` reads `$period = Arr::get($payload, 'period')`; missing period becomes `NULL`.
- Existing duplicate detection uses explicit null handling:
  - `whereNull('period')` when `$period === null`
  - `where('period', $period)` otherwise
- This can detect existing null-period attendance rows for a preview payload with missing/null period.
- Payload duplicate keys are built as `student_id|date|period`; PHP string concatenation turns `NULL` into an empty segment.
- That key construction can blur `NULL` and `''` duplicate semantics inside preflight.
- The service is read-only and cannot prevent concurrent duplicate writes.
- If the null-period policy changes, the service must be updated together with the controller duplicate logic, tests, and report display behavior.
- The unit tests cover non-null duplicate detection but do not appear to include an explicit null-period duplicate case.

## Live Read-Only Counts

Not checked in this phase. No read-only database count/schema command was run.

Recommended future read-only checks before any migration or normalization:

- Total attendance rows.
- Rows where `period IS NULL`.
- Rows where `period = ''`.
- Duplicate groups by `student_id,date` where `period IS NULL`.
- Duplicate groups by `student_id,date,period`.
- Distinct period values.
- Rows whose period resembles `full_day`, `full-day`, or `all_day`.

## Policy Options

### Option A - Keep `NULL` as Full-Day

- Lowest immediate compatibility impact.
- Keeps existing create/show semantics.
- Still weak in MySQL because unique keys do not enforce uniqueness for repeated `NULL` values.
- Would require stronger application-level checks and likely reconciliation reporting.

### Option B - Normalize Full-Day to Sentinel String

- Example sentinel: `full_day`.
- Allows the existing `student_id,date,period` unique key to protect full-day rows.
- Requires migration/backfill, write-path normalization, report/display updates, API compatibility review, and tests.
- Must handle existing `NULL` and `''` rows carefully.

### Option C - Generated Column / Functional Unique Index

- Can normalize `NULL` for uniqueness at the database layer.
- More DB-specific and requires compatibility review for the project's MySQL/MariaDB version.
- Still requires application display and query policy cleanup.

### Option D - Make Period Required Everywhere

- Avoids nullable uniqueness behavior.
- Likely breaks full-day attendance workflows because the current UI explicitly offers `Full Day` as an empty period.

### Option E - Add Attendance Type

- Separate full-day and period attendance with an explicit type column.
- More expressive, but it is a larger redesign touching schema, UI, API, reports, and imports.

## Risk Classification

### RED

- MySQL unique key on `student_id,date,period` does not reliably prevent duplicate full-day rows when `period IS NULL`.
- API store duplicate-key handling added in Phase 5T cannot catch duplicates that MySQL permits because of `NULL`.
- Web individual store can still write null/empty period values without normalization.
- Web update can still mutate period and can create policy inconsistencies.
- `attendances_temp` has nullable period and no unique key.

### YELLOW

- Empty string and `NULL` are not consistently normalized or displayed.
- Preflight duplicate key construction can blur `NULL` and `''`.
- Reports and CSV exports do not consistently label null period as `Full Day`.
- Teacher attendance service ignores period and uses student/date identity only.
- API and web paths use slightly different duplicate query styles.
- Subject-level attendance may conflict with period-only uniqueness because `subject` is not part of the unique key.

### GREEN

- API bulkMark is guarded and cannot currently write null-period rows.
- API update can no longer mutate period.
- Preflight uses explicit `whereNull('period')` for null-period existing attendance checks.
- `Attendance::isMarked()` intentionally treats missing period as class/date full-day coverage in web class-level checks.

## Top 10 Null-Period Risks

1. Duplicate full-day rows can exist in MySQL because `period` is nullable inside the unique key.
2. API store can still create repeated `student_id,date,NULL` rows if concurrent or app-level checks miss them.
3. Phase 5T duplicate-key handling cannot help when MySQL allows the duplicate because of `NULL`.
4. Empty string can be stored separately from `NULL`, creating two representations of full-day/no-period attendance.
5. Web individual store does not normalize period before duplicate checking and insert.
6. Web update can still mutate period and produce null/empty/named-period inconsistencies.
7. Display semantics differ between create/show/index/report/export.
8. Preflight can detect `NULL` rows but does not solve race safety and may blur null vs empty duplicate keys.
9. `attendances_temp` has nullable period with no unique constraint, making temp/import-style rows vulnerable to duplication.
10. Teacher attendance service uses student/date identity only, which does not align cleanly with the period-based uniqueness policy.

## Recommended Phase 5V First Code Task

Do not change schema or normalize data yet.

The safest Phase 5V first task is to add a read-only null-period diagnostics/reporting surface or command that inventories:

- `NULL` periods
- empty-string periods
- distinct period values
- duplicate full-day groups by `student_id,date WHERE period IS NULL`
- duplicate exact groups by `student_id,date,period`
- suspicious sentinel-like values

Reason: the next schema or normalization choice depends on live data shape. If there are few or no null-period rows, the project can move toward a sentinel normalization plan. If there are many null-period rows, the safer sequence is reconciliation/reporting first, then a staged migration and display/API compatibility plan.

After the inventory, the likely implementation direction is:

1. Define one canonical full-day period policy.
2. Add a shared period normalization helper.
3. Update preflight and duplicate checks to use that policy.
4. Update report/display labels.
5. Only then consider schema/backfill changes.

## Confirmation

- No application code was modified.
- No routes were modified.
- No controllers, services, models, tests, or migrations were modified.
- No attendance write route was executed.
- No API store/update/destroy/bulkMark route was executed.
- No database write, seed, import, sync, or repair was run.
- No live MySQL data was touched.
- No biometric sync or device command was run.
- No full test suite was run.
