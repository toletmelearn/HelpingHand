# Phase 1G - Admin Fee Route Normalization

## 1. Files Inspected

- `routes/web.php`
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `resources/views/admin/fees/create.blade.php`
- `resources/views/admin/fees/collect-form.blade.php`
- `resources/views/admin/fees/student-dashboard.blade.php`
- `resources/views/admin/fees/index.blade.php`
- `resources/views/admin/fees/receipt.blade.php`
- `resources/views/admin/fee-structures`
- `resources/views/layouts/sidebar.blade.php`
- `tests` fee route references, via search

## 2. Files Changed

- `routes/web.php`
- `docs/project-autopsy/PHASE_1G_ADMIN_FEE_ROUTE_NORMALIZATION.md`

## 3. Current Duplicated Route Map

| Legacy duplicated URI before Phase 1G | Legacy route name before Phase 1G | Controller method | Canonical replacement | References found |
| --- | --- | --- | --- | --- |
| `POST admin/admin/fees/store` | `admin.admin.fees.store` | `Admin\FeeCollectionController@store` | `POST admin/fees`, route name `admin.fees.store` | No active helper references found |
| `GET admin/admin/fees/receipt/{id}` | `admin.admin.fees.receipt` | `Admin\FeeCollectionController@receipt` | `GET admin/fees/receipt/{id}`, route name `admin.fees.receipt` | No active helper references found |
| `GET admin/admin/fees/receipt/{id}/pdf` | `admin.admin.fees.receipt.pdf` | `Admin\FeeReceiptController@downloadPdf` | `GET admin/fees/receipt/{id}/pdf`, route name `admin.fees.receipt.pdf` | No active helper references found |

Cause confirmed: these routes were declared with `/admin/fees/...` paths inside an already-admin-prefixed route group, producing `admin/admin/fees/...` URIs and `admin.admin.fees.*` route names.

## 4. All Route Helper References Found

Canonical fee route references found:

- `resources/views/admin/fees/create.blade.php:14` uses `route('admin.fees.store')`
- `resources/views/admin/fees/collect-form.blade.php:103` uses `route('admin.fees.store')`
- `resources/views/admin/fees/student-dashboard.blade.php:149` uses `route('admin.fees.receipt')`
- `resources/views/admin/fees/student-dashboard.blade.php:177` uses `route('admin.fees.receipt')`
- `resources/views/admin/fees/receipt.blade.php:199` uses `route('admin.fees.receipt.pdf')`
- `app/Http/Controllers/Admin/FeeCollectionController.php:437` redirects to `route('admin.fees.receipt', ...)`
- `app/Http/Controllers/Admin/FeeCollectionController.php:500` redirects to `route('admin.fees.receipt', ...)`
- `resources/views/admin/fees/index.blade.php:489` uses the hardcoded canonical path `/admin/fees/receipt/${receiptId}`

Legacy duplicated route helper references:

- No active references to `admin.admin.fees.store`
- No active references to `admin.admin.fees.receipt`
- No active references to `admin.admin.fees.receipt.pdf`

After quarantine, the only remaining `admin.admin.fees.*` occurrences are the commented legacy route definitions in `routes/web.php`.

## 5. Canonical Replacement Routes

Canonical replacement routes already existed before this phase:

- `POST admin/fees` named `admin.fees.store`, handled by `Admin\FeeCollectionController@store`
- `GET admin/fees/receipt/{id}` named `admin.fees.receipt`, handled by `Admin\FeeCollectionController@getReceipt`
- `GET admin/fees/receipt/{id}/pdf` named `admin.fees.receipt.pdf`, handled by `Admin\FeeReceiptController@downloadPdf`

The canonical routes matched all active Blade/controller helper references found during the audit.

## 6. Routes Changed or Commented

The duplicated `admin/admin/fees/*` routes were safely commented out in `routes/web.php`:

```php
// Quarantined in Phase 1G: duplicated admin/admin fee routes; canonical admin.fees.* routes already exist.
// Route::post('/admin/fees/store', [App\Http\Controllers\Admin\FeeCollectionController::class, 'store'])->name('admin.fees.store');
// Route::get('/admin/fees/receipt/{id}', [App\Http\Controllers\Admin\FeeCollectionController::class, 'receipt'])->name('admin.fees.receipt');
// Route::get('/admin/fees/receipt/{id}/pdf', [App\Http\Controllers\Admin\FeeReceiptController::class, 'downloadPdf'])->name('admin.fees.receipt.pdf');
```

No controller business logic was changed.

## 7. Routes Documented Only

Professional fee routes, installment fee routes, and fee-structure route duplication concerns were not changed in this phase.

They remain documented-only areas because Phase 1G was scoped specifically to duplicated `admin/admin/fees/*` collection and receipt routes.

## 8. Compatibility Risks

- External bookmarks or manually typed URLs using `/admin/admin/fees/store` or `/admin/admin/fees/receipt/...` will no longer resolve.
- This is expected because no internal Blade, controller, JS, or route helper references were found for the duplicated paths.
- Canonical receipt rendering now relies on `Admin\FeeCollectionController@getReceipt` through `admin.fees.receipt`. The duplicate route previously pointed to `receipt`; inspection showed both methods target the same receipt workflow/view shape.
- Commented legacy definitions remain searchable in `routes/web.php` for audit traceability.
- Other fee route risks remain out of scope, especially professional fee write routes and duplicate fee-structure route exposure.

## 9. Commands Run

Safe commands only:

```powershell
rg -n "admin\.admin\.fees|admin\.fees\.store|admin\.fees\.receipt|admin/fees/store|admin/admin/fees/store|admin/fees/receipt|admin/admin/fees/receipt" routes app resources tests -g "*.php" -g "*.blade.php" -g "*.js"
php -l routes\web.php
php artisan route:list | Select-String fee
rg -n "admin\.admin\.fees|admin\.fees\.store|admin\.fees\.receipt|admin/fees/store|admin/admin/fees/store" routes app resources tests -g "*.php" -g "*.blade.php" -g "*.js"
```

No migrations, schema changes, composer setup, or database-changing tests were run.

## 10. Verification Summary

- `php -l routes\web.php` passed with no syntax errors.
- `php artisan route:list | Select-String fee` showed canonical `admin/fees` collection and receipt routes still present.
- `php artisan route:list | Select-String fee` no longer showed active `admin/admin/fees/*` routes.
- Final reference search showed no active `admin.admin.fees.*` route helper usage.
- Final reference search showed active usage only of canonical `admin.fees.store`, `admin.fees.receipt`, and `admin.fees.receipt.pdf`.

## 11. Remaining Risks

- Professional fee write routes still require a dedicated schema and write-safety lockdown phase.
- Installment fee route exposure and schema safety remain unresolved from prior audits.
- Fee-structure route duplication remains outside this phase and should be normalized only after route helper usage is mapped.
- The fee domain still has legacy `Fee` model/controller risk for future feature work; new money writes should remain on the canonical fee system only.

## 12. Recommended Next Step

Run a dedicated Phase 1H route audit for `fee-structures`, professional fee management, and installment fee exposure. That phase should map helper usage first, then quarantine or normalize only routes proven safe to change.
