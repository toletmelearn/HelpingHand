# Phase 1B Fee Write Path Audit

Date: 2026-06-03  
Scope: audit and small safe fixes for remaining `FeeCollectionController` money write paths

## 1. Files Inspected

- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Models/FeeCollection.php`
- `app/Models/FeeCollectionItem.php`
- `routes/web.php`
- `resources/views/admin/fees/collect-form.blade.php`
- `resources/views/admin/fees/create.blade.php`
- `tests/` fee-related paths via search

## 2. Files Changed

- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `docs/project-autopsy/PHASE_1B_FEE_WRITE_PATH_AUDIT.md`

## 3. All Fee Write Methods Found

| Method | Money write? | Notes |
| --- | --- | --- |
| `collectFee(Request $request)` | Yes | Creates one `FeeCollection` and one `FeeCollectionItem`; returns JSON. |
| `processCollection(Request $request)` | Yes | Creates one `FeeCollection` and multiple `FeeCollectionItem` rows; redirects to receipt. |
| `store(Request $request)` | Yes | Creates one `FeeCollection` and multiple `FeeCollectionItem` rows; fixed in Phase 1A and rechecked in Phase 1B. |

Read-only methods such as `index()`, `searchStudents()`, `getStudentFeeDashboard()`, `show()`, `getReceipt()`, `receipt()`, and `getReceiptPdf()` do not create or update `FeeCollection` or `FeeCollectionItem` records.

## 4. Methods Creating Or Updating `FeeCollection`

| Method | Creates `FeeCollection` | Updates `FeeCollection` |
| --- | --- | --- |
| `collectFee()` | Yes | No |
| `processCollection()` | Yes | No |
| `store()` | Yes | No |

No `FeeCollection::update()` call was found in `FeeCollectionController`.

## 5. Methods Creating Or Updating `FeeCollectionItem`

| Method | Creates `FeeCollectionItem` | Updates `FeeCollectionItem` |
| --- | --- | --- |
| `collectFee()` | Yes, single item | No |
| `processCollection()` | Yes, multiple items | No |
| `store()` | Yes, multiple items | No |

No `FeeCollectionItem::update()` call was found in `FeeCollectionController`.

## 6. Collector Attribution Status

| Method | Before Phase 1B | After Phase 1B |
| --- | --- | --- |
| `collectFee()` | Used `auth()->id` without parentheses, storing an invalid value instead of the user ID. | Uses `$collectorId = auth('web')->id();`, aborts with 403 if missing, stores `$collectorId`. |
| `processCollection()` | Used `auth()->id` without parentheses, storing an invalid value instead of the user ID. | Uses `$collectorId = auth('web')->id();`, aborts with 403 if missing, stores `$collectorId`. |
| `store()` | Already fixed in Phase 1A. | Rechecked; still uses `auth('web')->id()` with 403 fail-safe and stores `$collectorId`. |

Guard used: `web`

Reason: fee collection routes are inside the admin web group:

```php
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
```

The controller also applies:

```php
$this->middleware('auth');
```

## 7. Transaction Status

| Method | Transaction before Phase 1B | Transaction after Phase 1B |
| --- | --- | --- |
| `collectFee()` | Already had `DB::beginTransaction()`, `DB::commit()`, and `DB::rollback()`. | Unchanged; write block remains transaction-protected. |
| `processCollection()` | Already had `DB::beginTransaction()`, `DB::commit()`, and `DB::rollback()`. | Unchanged; write block remains transaction-protected. |
| `store()` | Fixed in Phase 1A with `DB::transaction(...)`. | Rechecked; transaction remains in place. |

No additional transaction wrapper was added in Phase 1B because the remaining write paths were already transaction-safe.

## 8. `auth()->id` Syntax Bugs Found And Fixed

Fixed two incorrect property-style usages:

```php
'collected_by' => auth()->id
```

Corrected to:

```php
$collectorId = auth('web')->id();

if (!$collectorId) {
    abort(403, 'Authenticated admin user required for fee collection.');
}
```

And:

```php
'collected_by' => $collectorId
```

Affected methods:

- `collectFee()`
- `processCollection()`

## 9. Duplicated Fee Route Findings

The legacy route definitions are inside an `admin` prefix group but also include `/admin` in the route path:

```php
Route::post('/admin/fees/store', [FeeCollectionController::class, 'store'])->name('admin.fees.store');
Route::get('/admin/fees/receipt/{id}', [FeeCollectionController::class, 'receipt'])->name('admin.fees.receipt');
```

Route list confirms duplicated paths/names such as:

```text
POST admin/admin/fees/store admin.admin.fees.store
GET  admin/admin/fees/receipt/{id} admin.admin.fees.receipt
```

This was documented only. Route structure was not changed in Phase 1B.

## 10. Commands Run

```powershell
Get-Content app\Http\Controllers\Admin\FeeCollectionController.php
Get-Content app\Models\FeeCollection.php
Get-Content app\Models\FeeCollectionItem.php
rg -n "collected_by|auth\(\)->id|auth\('web'\)->id\(\)|FeeCollection::create|FeeCollectionItem::create|DB::transaction|\$request->all\(" app\Http\Controllers\Admin\FeeCollectionController.php app\Models\FeeCollection.php app\Models\FeeCollectionItem.php routes\web.php resources\views\admin\fees tests -g "*.php"
Get-Content resources\views\admin\fees\create.blade.php
Get-Content routes\web.php
Get-ChildItem -Recurse tests
php -l app\Http\Controllers\Admin\FeeCollectionController.php
php artisan route:list | Select-String fee
```

## 11. Verification Summary

Syntax verification:

```text
No syntax errors detected in app\Http\Controllers\Admin\FeeCollectionController.php
```

Route verification:

- Fee routes were listed successfully.
- `admin/fees`, `admin/fees/process-collection`, and duplicated legacy `admin/admin/fees/store` routes are present.

No migration, database reset, Composer setup, or database-changing test command was run.

## 12. Remaining Risks

- No database-backed feature tests were run because migration state is known to be inconsistent.
- `collectFee()`, `processCollection()`, and `store()` still use inline validation; FormRequests should be added later.
- Route duplication remains for `admin/admin/fees/store` and related receipt routes.
- `collect-form.blade.php` references `amount_{$feeId}` IDs in JavaScript, but the inspected inputs use `name="amount[]"` and no matching `id`; this may affect frontend amount calculation, but it was outside Phase 1B scope.
- Other fee controllers, such as installment or professional fee modules, may have separate money write paths and should be audited in a later phase.

## 13. Recommended Next Step

Phase 1C should audit all other fee-related controllers outside `FeeCollectionController`, especially installment and professional fee modules, for the same three controls:

- authenticated collector attribution
- transaction safety
- route/form consistency

