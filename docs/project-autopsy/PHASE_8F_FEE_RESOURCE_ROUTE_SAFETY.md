# Phase 8F — Fee Resource Route Safety Quarantine Autopsy Report

## 1. Files Inspected
- `routes/web.php`
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `resources/views/admin/fees/index.blade.php`
- `resources/views/admin/fees/collect-form.blade.php`
- `tests/Feature/FeeFinance/FeeRouteAuthorizationGuardTest.php`
- `tests/Feature/FeeFinance/FeeReceiptNumberHardeningTest.php`
- `docs/project-autopsy/PHASE_8A_FEE_FINANCE_WRITE_PATH_AUDIT.md`
- `docs/project-autopsy/PHASE_8D_FEE_ROUTE_PERMISSION_GUARD.md`
- `docs/project-autopsy/PHASE_8E_RECEIPT_NUMBER_COLLISION_GAP_HARDENING.md`

## 2. Files Changed
- `routes/web.php` (Modified)
- `tests/Feature/FeeFinance/FeeResourceRouteSafetyTest.php` (New)
- `docs/project-autopsy/PHASE_8F_FEE_RESOURCE_ROUTE_SAFETY.md` (New)

## 3. Resource Routes Before Fix
The `Route::resource('fees', FeeCollectionController::class)` statement registered:
- `admin.fees.index` (GET `admin/fees`)
- `admin.fees.create` (GET `admin/fees/create`)
- `admin.fees.store` (POST `admin/fees`)
- `admin.fees.show` (GET `admin/fees/{fee}`)
- `admin.fees.edit` (GET `admin/fees/{fee}/edit`)
- `admin.fees.update` (PUT/PATCH `admin/fees/{fee}`)
- `admin.fees.destroy` (DELETE `admin/fees/{fee}`)

## 4. Controller Methods Confirmed
In `FeeCollectionController.php`, the only implemented resource-mapped methods are:
- `index()`
- `store()`
- `show()`
Unimplemented (missing) resource methods:
- `create()`
- `edit()`
- `update()`
- `destroy()`

## 5. Views Route References Checked
- Verified `fees.create` is not referenced in active views.
- Verified `fees.destroy` is not referenced in active views.
- Checked `fees.edit` and `fees.update` references: only found in legacy, unused views (`admin/fees/show.blade.php` and `admin/fees/edit.blade.php`) belonging to the disabled legacy `FeeController`.

## 6. Routes Preserved
All active and custom routes for fee operations remain completely registered:
- `admin.fees.index` (GET)
- `admin.fees.store` (POST)
- `admin.fees.show` (GET)
- `admin.fees.collect.form` (GET)
- `admin.fees.process.collection` (POST)
- `admin.fees.receipt` (GET)
- `admin.fees.receipt.pdf` (GET)
- `admin.fees.search.students` (POST)
- `admin.fees.student-dashboard` (GET)
- `admin.fees.pending` (GET)
- `admin.fees.defaulters` (GET)
- `admin.fee-dashboard` (GET)

## 7. Routes Removed / Quarantined
The following unimplemented methods were quarantined by limiting the resource registration:
- `admin.fees.create`
- `admin.fees.edit`
- `admin.fees.update`
- `admin.fees.destroy`

## 8. Previous Fixes Verification

### A. Route Permission Guards (Phase 8D)
Confirmed `role:accountant` middleware wraps the group containing all preserved fee routes. Permission checks remain untouched and secure.

### B. Receipt Number Hardening (Phase 8E)
Unchanged; next sequence calculations (`SCH-REC-XXXX`) and transaction retry loops for collisions remain intact.

### C. Payment Date & Relation Fixes (Phase 8B & 8C)
Intact; queries continue to use `payment_date` (not `collection_date`), and relationships on `FeeCollectionItem` map safely to `feeType`.

---

## 9. Tests Created
Created `tests/Feature/FeeFinance/FeeResourceRouteSafetyTest.php` to verify:
1. `test_fees_index_route_remains_registered`
2. `test_fees_create_route_remains_registered` (Asserts false)
3. `test_fees_store_route_remains_registered_if_currently_used`
4. `test_fees_show_route_remains_registered_if_currently_used`
5. `test_fees_edit_route_is_not_registered_or_is_safely_quarantined`
6. `test_fees_update_route_is_not_registered_or_is_safely_quarantined`
7. `test_fees_destroy_route_is_not_registered_or_is_safely_quarantined`
8. `test_fee_collection_custom_routes_remain_registered`
9. `test_fee_route_authorization_guard_still_passes`
10. `test_receipt_number_hardening_test_still_passes`

---

## 10. Commands Run
```powershell
php -l routes/web.php
php -l app/Http/Controllers/Admin/FeeCollectionController.php
php -l tests/Feature/FeeFinance/FeeResourceRouteSafetyTest.php

php artisan config:clear
php artisan route:clear
php artisan cache:clear

php artisan test --filter=FeeResourceRouteSafetyTest --env=testing
php artisan test --filter=FeeRouteAuthorizationGuardTest --env=testing
php artisan test --filter=FeeReceiptNumberHardeningTest --env=testing
php artisan test --filter=ProfessionalFeePaymentDateColumnTest --env=testing
php artisan test --filter=ProfessionalFeeReceiptRelationshipTest --env=testing
```

---

## 11. Test Results Summary
All targeted test filters executed successfully against SQLite-memory connections:
- `FeeResourceRouteSafetyTest`: **10 Passed**
- `FeeRouteAuthorizationGuardTest`: **10 Passed**
- `FeeReceiptNumberHardeningTest`: **8 Passed**
- `ProfessionalFeePaymentDateColumnTest`: **6 Passed**
- `ProfessionalFeeReceiptRelationshipTest`: **5 Passed**

Total: **39 tests passed**.
No full test suite was run.

---

## 12. Safety Compliance Confirmations
- **No local MySQL data touched**: Checked route registration in sqlite memory database only.
- **No writes executed on MySQL**: Isolated environment.
- **No PDF generator or reminders run on local MySQL**.
- **No migrations or database schema changes touched**.

---

## 13. Remaining Finance Risks
- **Duplicate Payment / Double-Submit Risk**: There is no browser-level or server-level transaction locking on the fee collection forms. An accountant double-clicking the "Generate Receipt" button could submit parallel payment transactions, creating twin collection records.

## 14. Recommended Next Step
- **Phase 8G**: Harden the payment forms and controller endpoints against double-submission / duplicate payment risks using token-locking or form submission throttling.
