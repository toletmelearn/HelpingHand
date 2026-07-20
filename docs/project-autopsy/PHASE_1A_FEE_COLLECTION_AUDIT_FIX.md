# Phase 1A Fee Collection Audit Fix

Date: 2026-06-03  
Scope: code-only fix for `FeeCollectionController::store()` audit accountability bug

## 1. Files Inspected

- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Models/FeeCollection.php`
- `routes/web.php`
- `resources/views/admin/fees/collect-form.blade.php`
- `resources/views/admin/fees/create.blade.php`
- `tests/` fee collection references via search

## 2. Files Changed

- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `docs/project-autopsy/PHASE_1A_FEE_COLLECTION_AUDIT_FIX.md`

## 3. Exact Old Code Problem

`FeeCollectionController::store()` created fee collection records with a hardcoded collector:

```php
'collected_by' => 1
```

This made every fee receipt appear to be collected by user ID `1`, destroying audit accountability for real admin users.

## 4. Exact New Code Solution

The method now resolves the authenticated web user explicitly:

```php
$collectorId = auth('web')->id();
```

If no authenticated web user exists, it fails safely:

```php
abort(403, 'Authenticated admin user required for fee collection.');
```

The fee collection now saves the actual authenticated collector:

```php
'collected_by' => $collectorId
```

## 5. Guard Used

Guard used: `web`

Reason:

- The relevant routes are inside the admin web route group:

```php
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
```

- The controller constructor also applies:

```php
$this->middleware('auth');
```

## 6. DB Transaction Status

`store()` did not already wrap the fee collection record and related fee collection item creation in a transaction.

Added:

```php
$collection = DB::transaction(function () use ($request, $student, $receiptNo, $total, $collectorId) {
    // FeeCollection::create(...)
    // FeeCollectionItem::create(...)
    return $collection;
});
```

Only the creation of the fee collection and related fee collection items was wrapped.

## 7. Commands Run

```powershell
Get-Content app\Http\Controllers\Admin\FeeCollectionController.php
Get-Content app\Models\FeeCollection.php
rg -n "FeeCollectionController|fee-collections|fee collection|collect" routes\web.php resources\views tests app\Http\Controllers -g "*.php"
Get-Content routes\web.php
rg -n "FeeCollection|fees\.store|admin\.fees\.store|collectFee|processCollection" tests app\Http\Controllers resources\views\admin\fees -g "*.php"
Get-Content resources\views\admin\fees\collect-form.blade.php
php -l app\Http\Controllers\Admin\FeeCollectionController.php
php artisan route:list | findstr fee
php artisan route:list | Select-String fee
```

Notes:

- `php artisan route:list | findstr fee` was attempted because it was explicitly listed as safe, but `findstr` is not available in this PowerShell session.
- The safe PowerShell equivalent `Select-String fee` was used to verify route listing output.

## 8. Verification Result Summary

Syntax check:

```text
No syntax errors detected in app\Http\Controllers\Admin\FeeCollectionController.php
```

Route verification:

- Fee routes are present.
- Relevant route appears as:

```text
POST admin/admin/fees/store admin.admin.fees.store
```

The doubled `admin/admin` path comes from defining `/admin/fees/store` inside an existing `admin` prefix group. This was not changed because route redesign was outside Phase 1A scope.

## 9. Remaining Risks

- Other methods in the same controller still use `auth()->id` without parentheses, but this task was limited to the hardcoded `store()` audit bug.
- Fee route paths include duplicated `admin/admin` for the legacy named route because the route path itself includes `/admin` inside an admin-prefixed group.
- `store()` still uses lightweight inline request checks rather than a dedicated FormRequest.
- No database-backed feature test was run because the migration state is known to be inconsistent and the task forbids database-changing verification.

## 10. Recommended Next Step

Phase 1B should audit the remaining fee collection entry points, especially `collectFee()` and `processCollection()`, to ensure all collector attribution uses a real authenticated user and that every fee write path is transaction-safe.

