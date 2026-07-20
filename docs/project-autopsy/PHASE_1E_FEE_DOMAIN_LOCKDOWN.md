# Phase 1E Fee Domain Lockdown

Date: 2026-06-03  
Scope: canonical fee decision plus safe route quarantine audit

## 1. Files Inspected

- `docs/project-autopsy/PHASE_1D_FEE_SCHEMA_CONTRACT_AUDIT.md`
- `routes/web.php`
- `app/Http/Controllers/Admin/FeeController.php`
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Http/Controllers/Admin/FeeStructureController.php`
- `app/Http/Controllers/Admin/InstallmentFeeController.php`
- `app/Http/Controllers/Admin/ProfessionalFeeManagementController.php`
- `app/Services/ProfessionalFeeManagementService.php`
- `resources/views/admin/fees`
- `resources/views/admin/fee-management`
- `resources/views/admin/fee-structures`
- `resources/views/layouts/sidebar.blade.php`
- `resources/views/parent-dashboard.blade.php`
- route/menu references under `app`, `resources`, `routes`, and `tests`

## 2. Files Changed

- `docs/project-autopsy/FEE_CANONICAL_SYSTEM_DECISION.md`
- `docs/project-autopsy/PHASE_1E_FEE_DOMAIN_LOCKDOWN.md`

No application code, routes, models, migrations, controllers, services, or views were changed.

## 3. Canonical Fee Decision Summary

Canonical fee system:

- `FeeStructure`
- `FeeStructureItem`
- `FeeType`
- `StudentFeeAssignment`
- `FeeCollection`
- `FeeCollectionItem`

Legacy/deprecated for new money writes:

- `Fee`
- `Admin\FeeController`

Quarantined/experimental:

- `InstallmentFeeController` write flows until routes/schema are clarified
- `ProfessionalFeeManagementController` write flows until schema contracts are repaired
- `ProfessionalFeeManagementService` write methods until model/schema contracts are repaired

Payment/Stripe:

- Keep separate for a later dedicated payment phase.

Hard rule:

- No new fee feature should be built on the legacy `Fee` model.

## 4. Route References Searched

Searched for:

- `fees.payment`
- `FeeController@payment`
- `admin.admin.fees.store`
- `admin.admin.fees.receipt`
- `admin.fees.store`
- `admin.fees.receipt`
- `fee-structures`
- `fee-management`
- `ProfessionalFeeManagementController`
- `InstallmentFeeController`

Search areas:

- `routes`
- `app`
- `resources`
- `tests`
- prior project autopsy docs for context

## 5. Route Issues Found

| Issue | Finding | Action |
| --- | --- | --- |
| `fees/payment` route | `routes/web.php` points to `Admin\FeeController@payment`, but `FeeController` has no `payment()` method. | Documented only because `fees.payment` is referenced by a Blade view. |
| `fees.payment` reference | `resources/views/parent-dashboard.blade.php` calls `route('fees.payment')`. | Route was not commented out to avoid breaking this helper. |
| Duplicated admin prefix | `Route::post('/admin/fees/store', ...)` is inside the admin prefix group, producing `admin/admin/fees/store`. Receipt routes have the same issue. | Documented only because `admin.fees.store` and `admin.fees.receipt` are referenced by views/controllers. |
| Duplicate `fee-structures` resource | `Route::resource('fee-structures', FeeStructureController::class)` appears twice in `routes/web.php`. | Documented only; not removed because route list stability was not proven in this phase. |
| Professional fee routes | `fee-management` write routes are active. | Documented only; not removed per task rules. |
| Installment fee controller | Controller exists, but no obvious `fee` route-list exposure was found. | Documented only. |

## 6. Routes Quarantined

None.

`fees/payment` was not quarantined in code because it is referenced here:

- `resources/views/parent-dashboard.blade.php`

The route remains broken because it points to a missing controller method, but removing/commenting it would break the existing route helper.

## 7. Routes Documented Only

Documented only:

- `POST fees/payment`
- `POST admin/admin/fees/store`
- `GET admin/admin/fees/receipt/{id}`
- `GET admin/admin/fees/receipt/{id}/pdf`
- duplicate `fee-structures` resource routes
- all active `fee-management` write routes

## 8. Professional Fee Routes Risk Summary

Active professional fee write routes:

- `POST admin/fee-management/fee-heads`
- `POST admin/fee-management/structures`
- `POST admin/fee-management/assign-student`
- `POST admin/fee-management/bulk-assign`

Risk:

- These routes call service methods with known schema/model contract mismatches from Phase 1D.
- The service expects fields such as `collection_date`, `receipt_number`, `paid_amount`, `balance_amount`, `discount_percentage`, and custom assignment fields that do not cleanly match the current canonical schema.

Recommendation:

- Do not use professional fee write routes for production money workflows until schema contracts are repaired.

## 9. Installment Fee Routes Risk Summary

`InstallmentFeeController` contains write flows for:

- installment fee structure creation
- installment fee structure update
- fee assignment to class
- installment payment collection

Risk:

- Route exposure is unclear from `php artisan route:list | Select-String fee`.
- The controller passes fields such as `name`/`description` that do not match current `FeeStructureItem` / `FeeCollectionItem` schema contracts.

Recommendation:

- Keep installment fee flows quarantined until route exposure and schema contracts are clarified.

## 10. Commands Run

```powershell
Get-Content docs\project-autopsy\PHASE_1D_FEE_SCHEMA_CONTRACT_AUDIT.md
Get-Content routes\web.php
rg -n "fees\.payment|FeeController@payment|admin\.admin\.fees\.store|admin\.admin\.fees\.receipt|admin\.fees\.store|admin\.fees\.receipt|fee-structures|fee-management|ProfessionalFeeManagementController|InstallmentFeeController" routes app resources docs tests -g "*.php" -g "*.blade.php" -g "*.md" -g "*.js"
Get-Content app\Http\Controllers\Admin\FeeController.php
Get-Content app\Http\Controllers\Admin\ProfessionalFeeManagementController.php
Get-Content app\Http\Controllers\Admin\InstallmentFeeController.php
Get-Content app\Services\ProfessionalFeeManagementService.php
Get-Content app\Http\Controllers\Admin\FeeCollectionController.php
Get-Content app\Http\Controllers\Admin\FeeStructureController.php
Get-Content resources\views\layouts\sidebar.blade.php
Get-Content resources\views\parent-dashboard.blade.php
rg -n "fees\.payment|FeeController@payment|admin\.admin\.fees\.store|admin\.admin\.fees\.receipt|admin\.fees\.store|admin\.fees\.receipt|fee-structures|fee-management|ProfessionalFeeManagementController|InstallmentFeeController" routes app resources tests -g "*.php" -g "*.blade.php" -g "*.js"
Get-ChildItem resources\views\admin\fee-management -Recurse
Get-ChildItem resources\views\admin\fee-structures -Recurse
Get-ChildItem resources\views\admin\fees -Recurse
php -l routes\web.php
php artisan route:list | Select-String fee
```

## 11. Verification Summary

`php -l routes/web.php`:

- Passed.

`php artisan route:list | Select-String fee`:

- Passed.
- Confirmed `fees/payment` still exists.
- Confirmed duplicated `admin/admin/fees/*` paths still exist.
- Confirmed `fee-management` routes remain active.

No route changes were made.

## 12. Remaining Risks

- `fees/payment` still points to a missing method and will fail if submitted.
- `parent-dashboard.blade.php` still references `fees.payment`.
- `admin/admin/fees/store` and receipt routes still exist because views/controllers depend on their route names.
- Duplicate `fee-structures` resource registration remains.
- Professional fee write routes remain active despite schema contract risks.
- Installment fee write flows remain present in code, with unclear route exposure.

## 13. Recommended Next Step

Phase 1F should safely fix the parent fee payment entry point without touching Stripe/webhook logic:

1. Replace the `parent-dashboard.blade.php` `fees.payment` helper with a safe canonical fee dashboard or receipt/payment-history route.
2. Then quarantine/comment `POST fees/payment` if no references remain.
3. After that, normalize duplicated `admin/admin/fees/*` routes while preserving route names or updating all helpers together.

