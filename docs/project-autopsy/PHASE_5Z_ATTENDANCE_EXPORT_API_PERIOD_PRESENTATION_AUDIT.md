# Phase 5Z - Attendance Export / API Period Presentation Audit

Date: 2026-06-06

Scope: Read-only audit of attendance export and API response period presentation after Phase 5Y aligned Blade read views. No code, routes, controllers, services, models, tests, migrations, database data, attendance write routes, export routes, API routes, biometric sync, or device commands were modified or executed.

## Files Inspected

- `app/Support/Attendance/AttendancePeriodPresenter.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Models/Attendance.php`
- `app/Services/AttendanceService.php`
- `resources/views/attendance/show.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`
- `resources/views/attendance/student_report.blade.php`
- `routes/web.php`
- `routes/api.php`
- `docs/project-autopsy/PHASE_5Y_ATTENDANCE_PERIOD_DISPLAY_ALIGNMENT.md`
- `docs/project-autopsy/PHASE_5X_ATTENDANCE_PERIOD_PRESENTER_HELPER.md`
- `docs/project-autopsy/PHASE_5W_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_RESULT.md`

## Commands Run

- `Get-Content app/Support/Attendance/AttendancePeriodPresenter.php`
- `Get-Content app/Http/Controllers/AttendanceController.php`
- `Get-Content app/Http/Controllers/API/AttendanceController.php`
- `Get-Content app/Models/Attendance.php`
- `Get-Content app/Services/AttendanceService.php`
- `Get-Content routes/web.php`
- `Get-Content routes/api.php`
- `Get-Content resources/views/attendance/show.blade.php`
- `Get-Content resources/views/attendance/index.blade.php`
- `Get-Content resources/views/attendance/reports.blade.php`
- `Get-Content resources/views/attendance/student_report.blade.php`
- `Get-Content docs/project-autopsy/PHASE_5Y_ATTENDANCE_PERIOD_DISPLAY_ALIGNMENT.md`
- `Get-Content docs/project-autopsy/PHASE_5X_ATTENDANCE_PERIOD_PRESENTER_HELPER.md`
- `Get-Content docs/project-autopsy/PHASE_5W_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_RESULT.md`
- `rg -n "export|Export|fputcsv|period|period_display|AttendancePeriodPresenter|Attendance::getStudentMonthlyReport|getStudentAttendanceStats|dailyReport|studentMonthlyReport|attendance-data|attendance\(" app resources routes tests docs/project-autopsy/PHASE_5Y_ATTENDANCE_PERIOD_DISPLAY_ALIGNMENT.md docs/project-autopsy/PHASE_5X_ATTENDANCE_PERIOD_PRESENTER_HELPER.md docs/project-autopsy/PHASE_5W_ATTENDANCE_NULL_PERIOD_DIAGNOSTICS_RESULT.md`
  - Result: timed out due large output, but surfaced key export/API matches before timeout.
- `rg -n "exportToCsv|function export|fputcsv|Period|period" app/Http/Controllers app/Services app/Exports resources/views/attendance routes/web.php routes/api.php`
- `rg -n "return \$this->success|Attendance::with|Attendance::where|studentMonthlyReport|dailyReport|getStudentMonthlyReport|getStudentAttendanceStats|period_display|period" app/Http/Controllers/API app/Models/Attendance.php app/Services/AttendanceService.php routes/api.php`
- `php -l app/Http/Controllers/AttendanceController.php`
  - Result: passed.
- `php -l app/Http/Controllers/API/AttendanceController.php`
  - Result: passed.
- `php -l app/Support/Attendance/AttendancePeriodPresenter.php`
  - Result: passed.
- `php artisan route | Select-String "attendance"`
  - Result: failed because this app exposes `route:list`; `route` is an Artisan namespace, not a route-listing command. No alternate route command was run.

No optional database counts were run in this phase.

## Web Export Period Findings

### Routes

From `routes/web.php`:

- `GET /attendance/export`
- Route name: `attendance.export`
- Controller: `AttendanceController@export`

The same view surfaces link to this route from:

- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`

### Export Method

`AttendanceController@export(Request $request)`:

- authorizes `viewAny`
- filters by `from_date`, `to_date`, and `class`
- loads `student` and `markedBy`
- calls `exportToCsv($attendances)`

### CSV Output

`AttendanceController::exportToCsv()` writes the following CSV headers:

```text
Date, Class, Student Name, Roll Number, Status, Subject, Period, Remarks, Marked By, IP Address
```

The `Period` value is written as:

```php
$attendance->period
```

Findings:

- Export includes period.
- Export uses raw `$attendance->period`.
- `NULL` period values will become blank CSV cells.
- Literal `Full Day` rows remain `Full Day`.
- Named periods remain raw.
- `AttendancePeriodPresenter::display()` is not used in export.
- No dedicated attendance export class was found in `app/Exports`; the web CSV helper is in `AttendanceController`.

### Export Contract Risk

Changing the existing `Period` column from raw value to display value could be breaking if the CSV is used for:

- manual reconciliation against stored values
- re-import workflows
- downstream scripts expecting blank/null period as full-day
- audits where raw database value matters

Safest later approach:

- keep raw `Period`
- add a separate `Period Display` column using `AttendancePeriodPresenter::display($attendance->period)`

This would be presentation-friendly while preserving the raw contract.

## Web Report / Student Report Period Findings

### Blade Read Surfaces

Phase 5Y already updated:

- `resources/views/attendance/show.blade.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/reports.blade.php`

These now use:

```php
\App\Support\Attendance\AttendancePeriodPresenter::display($attendance->period)
```

Current behavior:

- `NULL` displays as `Full Day`.
- empty/whitespace displays as `Full Day`.
- literal `Full Day` and sentinel-like labels display as `Full Day`.
- named periods display trimmed.

### Student Report

`AttendanceController@studentReport()` calls:

```php
Attendance::getStudentMonthlyReport($studentId, $month, $year)
```

`Attendance::getStudentMonthlyReport()` currently returns each detail row with:

- `date`
- `status`
- `remarks`

It does not include `period`.

`resources/views/attendance/student_report.blade.php` displays:

- date
- status
- remarks

It does not display period. Therefore it is not currently inconsistent, but it also cannot distinguish full-day versus period-specific attendance.

### Report Calculations

`Attendance::getAttendanceStats()` and related report calculations count attendance rows by date/class/status. They do not group by period or distinguish full-day from period-specific attendance.

`AttendanceService` statistics also count records by date ranges and status, not period display classification.

Risk:

- If both full-day and period-specific attendance rows exist for the same student/day, summary statistics may treat them as multiple attendance days/records.
- This is a calculation-policy risk, not just presentation.

## API Response Period Findings

### API Routes

From `routes/api.php`, protected API attendance routes include:

- `GET /api/v1/attendance` via `Route::apiResource('attendance', AttendanceController::class)` -> `API\AttendanceController@index`
- `GET /api/v1/attendance/{attendance}` -> `API\AttendanceController@show`
- `POST /api/v1/attendance` -> `API\AttendanceController@store`
- `PUT/PATCH /api/v1/attendance/{attendance}` -> `API\AttendanceController@update`
- `DELETE /api/v1/attendance/{attendance}` -> `API\AttendanceController@destroy`
- `GET /api/v1/attendance/student/{studentId}/monthly/{month}/{year}` -> `API\AttendanceController@studentMonthlyReport`
- `GET /api/v1/attendance/class/{classSection}/daily/{date}` -> `API\AttendanceController@dailyReport`
- `POST /api/v1/attendance/bulk-mark` -> `API\AttendanceController@bulkMark`

### API Index / Show

`API\AttendanceController@index()` returns:

```php
Attendance::with(['student', 'teacher', 'markedBy'])->get()
```

`API\AttendanceController@show()` returns:

```php
Attendance::with(['student', 'teacher', 'markedBy'])->findOrFail($id)
```

Findings:

- Raw Eloquent attendance models are returned through `$this->success(...)`.
- The raw `period` attribute is exposed.
- No `period_display` field is added.
- No API resource/transformer is used.
- `AttendancePeriodPresenter` is not used in API responses.

### API Store / Update Responses

`API\AttendanceController@store()` returns the newly created `Attendance` model:

```php
return $this->success($attendance, 'Attendance marked successfully', 201);
```

`API\AttendanceController@update()` returns the updated `Attendance` model:

```php
return $this->success($attendance, 'Attendance record updated successfully');
```

Findings:

- Both responses expose raw `period` if present on the model.
- No `period_display` is included.
- Changing `period` itself would break API clients that rely on raw stored value.
- Adding `period_display` would be a safer non-breaking enhancement.

### API Daily Report

`API\AttendanceController@dailyReport()` returns:

```php
Attendance::where('class', $classSection)
    ->where('date', $date)
    ->with('student')
    ->get()
```

Findings:

- Daily report exposes raw attendance models.
- Raw `period` is included.
- No `period_display` is included.

### API Monthly Student Report

`API\AttendanceController@studentMonthlyReport()` returns:

```php
Attendance::getStudentMonthlyReport($studentId, $month, $year)
```

`Attendance::getStudentMonthlyReport()` currently omits period from detail rows.

Findings:

- The monthly API report does not expose raw period.
- It also does not expose display period.
- If clients need period-aware attendance, this endpoint is currently lossy.

### Other API Attendance Summaries

Search found dashboard/stat-style API methods that count attendance rows by status/date and do not expose period presentation fields. These are not directly inconsistent in display, but they may inherit the same calculation-policy risk if full-day and period-specific rows coexist.

## Attendance Model / Accessor Findings

`Attendance` currently has:

- no `getPeriodDisplayAttribute()`
- no `$appends` entry for period display
- no accessor that normalizes or presents period
- `period` in `$fillable`
- no period cast

Adding a model accessor later is possible:

```php
public function getPeriodDisplayAttribute(): string
```

However, globally appending it via `$appends` could affect every serialized `Attendance` model, including API responses and any consumers expecting only current fields.

Safer later options:

- Add an accessor but do not append globally.
- Add `period_display` explicitly in API controller/resource response arrays.
- Add `Period Display` explicitly in CSV export while preserving raw `Period`.
- Introduce API resources/transformers if broader response shaping becomes necessary.

Controller-level explicit fields are safer than global model appends for the next step because they reduce accidental public-contract changes.

## Risk Classification

### RED

- None for immediate display after Phase 5Y on primary Blade views.

### YELLOW

- CSV export still writes raw period, so `NULL` appears blank while literal `Full Day` appears as `Full Day`.
- API index/show/store/update/daily report responses expose raw period only.
- API monthly report omits period entirely, making period-specific attendance invisible in that response.
- Adding a globally appended model accessor could unexpectedly change API serialization.
- Report/stat calculations do not distinguish full-day from period-specific attendance.

### GREEN

- Blade show/index/reports now use `AttendancePeriodPresenter::display()`.
- Export route is protected by authorization and was not executed in this phase.
- API bulkMark remains guarded from earlier phases.
- API update cannot mutate period from earlier phases.
- The presenter helper is pure and read-only.

## Recommended Next Code Task

Safest next implementation:

Add `period_display` as a non-breaking extra field in API attendance responses while keeping raw `period` unchanged.

Recommended scope:

- API `index`
- API `show`
- API `store` response
- API `update` response
- API `dailyReport`

Approach:

- Use `AttendancePeriodPresenter::display($attendance->period)`.
- Do not change the raw `period` field.
- Prefer explicit controller/resource transformation over global `$appends`.

CSV/export should be handled after the API response contract:

- If export is presentation-only, use display value.
- If export may be used for re-import or audit, preserve raw `Period` and add `Period Display` as a new column.

Do not implement schema migration, data repair, or write normalization yet.

## Confirmation

- No application code was modified.
- No routes were modified.
- No controllers, services, models, tests, or migrations were modified.
- No database data was read through optional DB count checks.
- No database data was modified.
- No attendance write route was executed.
- No API store/update/destroy/bulkMark route was executed.
- No export route was executed.
- No CSV file was generated.
- No biometric sync or device command was run.
- No full test suite was run.
