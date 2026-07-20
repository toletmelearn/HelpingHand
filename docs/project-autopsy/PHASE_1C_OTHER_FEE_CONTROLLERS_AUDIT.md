# Phase 1C Other Fee Controllers Audit

Date: 2026-06-03  
Scope: audit fee-related controllers/services outside `FeeCollectionController` for money-write safety

## 1. Files Inspected

- `app/Http/Controllers/Admin/FeeAutomationController.php`
- `app/Http/Controllers/Admin/FeeController.php`
- `app/Http/Controllers/Admin/FeeReceiptController.php`
- `app/Http/Controllers/Admin/FeeStructureController.php`
- `app/Http/Controllers/Admin/InstallmentFeeController.php`
- `app/Http/Controllers/Admin/ProfessionalFeeManagementController.php`
- `app/Services/ProfessionalFeeManagementService.php`
- `app/Services/Payment/StripePaymentService.php` search hit only; not modified
- `app/Services/Sms/TwilioSmsService.php` search hit only; not modified
- `app/Models/Fee.php`
- `app/Models/FeeCollection.php`
- `app/Models/FeeCollectionItem.php`
- `app/Models/FeeHead.php`
- `app/Models/FeeStructure.php`
- `app/Models/FeeStructureDetail.php`
- `app/Models/FeeStructureItem.php`
- `app/Models/FeeType.php`
- `app/Models/StudentFeeAssignment.php`
- `routes/web.php`
- `resources/views/admin/fees`
- `resources/views/admin/fee-management`
- `resources/views/admin/fee-structures`
- `tests/` fee-related search

## 2. Files Changed

- `app/Http/Controllers/Admin/InstallmentFeeController.php`
- `docs/project-autopsy/PHASE_1C_OTHER_FEE_CONTROLLERS_AUDIT.md`

## 3. Fee-Related Controllers And Services Found

Controllers:

- `Admin\FeeAutomationController`
- `Admin\FeeCollectionController`
- `Admin\FeeController`
- `Admin\FeeReceiptController`
- `Admin\FeeStructureController`
- `Admin\InstallmentFeeController`
- `Admin\ProfessionalFeeManagementController`
- `PaymentController` was found via Stripe/payment search but was not modified per task rules.

Services:

- `ProfessionalFeeManagementService`
- `Payment\StripePaymentService` was found via Stripe search but was not modified.
- `Sms\TwilioSmsService` was found via Twilio reminder/provider search but was not modified.

## 4. Money Write Methods Found

| File | Method | Write behavior |
| --- | --- | --- |
| `Admin\FeeController` | `store()` | Creates legacy `Fee` record with `Fee::create($request->all())`, then calls `updateStatus()`. |
| `Admin\FeeController` | `update()` | Updates legacy `Fee` record with `$fee->update($request->all())`, then calls `updateStatus()`. |
| `Admin\FeeController` | `destroy()` | Deletes legacy `Fee` record. |
| `Admin\FeeStructureController` | `store()` | Creates `FeeStructure`, `FeeStructureItem` rows, and auto-created `StudentFeeAssignment` rows inside transaction. |
| `Admin\FeeStructureController` | `update()` | Updates `FeeStructure`, deletes old items, creates replacement `FeeStructureItem` rows inside transaction. |
| `Admin\FeeStructureController` | `destroy()` | Deletes fee structure if no collections exist. |
| `Admin\FeeStructureController` | `activate()` / `deactivate()` | Updates fee structure status. |
| `Admin\InstallmentFeeController` | `store()` | Creates installment `FeeStructure` plus `FeeStructureItem` rows. |
| `Admin\InstallmentFeeController` | `update()` | Updates installment structure, deletes old items, creates replacement items. |
| `Admin\InstallmentFeeController` | `assignToClass()` | Creates `StudentFeeAssignment` rows for many students. |
| `Admin\InstallmentFeeController` | `processInstallmentPayment()` | Creates `FeeCollection` plus related `FeeCollectionItem` rows. |
| `Admin\ProfessionalFeeManagementController` | `createFeeHead()` | Delegates fee head creation to service. |
| `Admin\ProfessionalFeeManagementController` | `storeFeeStructure()` | Delegates fee structure plus detail creation to service. |
| `Admin\ProfessionalFeeManagementController` | `assignToStudent()` | Delegates student assignment creation to service. |
| `Admin\ProfessionalFeeManagementController` | `bulkAssign()` | Wraps repeated service assignments in transaction. |
| `ProfessionalFeeManagementService` | `createFeeHead()` | Creates `FeeHead` inside transaction. |
| `ProfessionalFeeManagementService` | `createFeeStructure()` | Creates `FeeStructure` plus `FeeStructureDetail` rows inside transaction. |
| `ProfessionalFeeManagementService` | `assignFeeStructureToStudent()` | Creates `StudentFeeAssignment`, optional custom `FeeStructureDetail` rows, and updates assignment amount inside transaction. |

## 5. Hardcoded User ID Issues Found/Fixed

No new hardcoded user ID values such as `created_by => 1`, `collected_by => 1`, or `assigned_by => 1` were found outside the already-fixed `FeeCollectionController`.

Phase 1C fixed non-hardcoded but unsafe generic attribution in `InstallmentFeeController` by requiring a real web-authenticated user before money writes.

## 6. Invalid `auth()->id` Syntax Issues Found/Fixed

No invalid `auth()->id` property syntax was found outside `FeeCollectionController`.

The remaining Phase 1C changes used the required safe pattern where admin attribution was relevant:

```php
$userId = auth('web')->id();

if (!$userId) {
    abort(403, 'Authenticated admin user required.');
}
```

## 7. Transaction Safety Status

Fixed in Phase 1C:

- `InstallmentFeeController::store()` now wraps `FeeStructure::create()` and related `FeeStructureItem::create()` calls in `DB::transaction()`.
- `InstallmentFeeController::update()` now wraps the structure update, item deletion, and replacement item creation in `DB::transaction()`.
- `InstallmentFeeController::assignToClass()` now wraps all assignment creates in `DB::transaction()`.
- `InstallmentFeeController::processInstallmentPayment()` now wraps `FeeCollection::create()` and related `FeeCollectionItem::create()` calls in `DB::transaction()`.

Already transaction-safe:

- `FeeStructureController::store()`
- `FeeStructureController::update()`
- `ProfessionalFeeManagementController::bulkAssign()`
- `ProfessionalFeeManagementService::createFeeHead()`
- `ProfessionalFeeManagementService::createFeeStructure()`
- `ProfessionalFeeManagementService::assignFeeStructureToStudent()`

Not transaction-safe, documented only:

- `FeeController::store()` and `FeeController::update()` each write one `Fee` row and then call `updateStatus()`, which performs a second save. These are legacy fee writes and should be wrapped later if that controller remains active.

## 8. `$request->all()` Risk List

Found:

- `Admin\FeeController::store()` uses `Fee::create($request->all())`.
- `Admin\FeeController::update()` uses `$fee->update($request->all())`.
- `Admin\ProfessionalFeeManagementController::createFeeHead()` passes `$request->all()` into `ProfessionalFeeManagementService::createFeeHead()`.
- `Admin\ProfessionalFeeManagementController::storeFeeStructure()` passes `$request->all()` into `ProfessionalFeeManagementService::createFeeStructure()`.

Risk:

- Current validation reduces risk, and model fillable guards provide some protection, but `$request->all()` is still too broad for financial flows.
- This was documented only because Phase 1C explicitly did not redesign validation or add FormRequests.

## 9. Stripe/Payment Areas Found But Not Modified

Found:

- `app/Http/Controllers/PaymentController.php`
- `app/Services/Payment/StripePaymentService.php`
- `routes/web.php` contains `POST fees/payment ... fees.payment › Admin\FeeController@payment`

Notes:

- Stripe webhook routing and payment gateway logic were not changed.
- `Admin\FeeController` does not currently contain a `payment()` method even though `routes/web.php` references `Admin\FeeController@payment`; this is a route/controller mismatch to handle in a later phase.

## 10. Route Duplication/Naming Issues Found

Confirmed:

- Legacy fee route duplication remains:

```text
POST admin/admin/fees/store admin.admin.fees.store
GET  admin/admin/fees/receipt/{id} admin.admin.fees.receipt
```

- `Route::resource('fee-structures', FeeStructureController::class)` appears in more than one admin area of `routes/web.php`.
- `Route::resource('fees', FeeController::class)` is commented out to avoid conflict with `FeeCollectionController`.
- `POST fees/payment` points to `Admin\FeeController@payment`, but no such method was found in the inspected controller.
- Installment fee controller routes were not found in the fee route listing, suggesting that some installment controller methods may be currently unrouted or routed elsewhere under non-fee names.

No route structure was changed.

## 11. Commands Run

```powershell
Get-ChildItem app\Http\Controllers -Recurse -Filter *Fee*.php
Get-ChildItem app\Services -Recurse -Filter *Fee*.php
Get-ChildItem app\Models -Filter Fee*.php
rg -n "FeeCollection::create|FeeCollectionItem::create|Fee::create|FeeStructure::create|StudentFeeAssignment::create|payment_status|paid_amount|due_amount|collected_by|created_by|updated_by|auth\(\)->id|auth\('web'\)->id\(\)|DB::beginTransaction|DB::transaction|\$request->all\(|Stripe|Twilio" app\Http\Controllers app\Services app\Models routes\web.php resources\views\admin\fees resources\views\admin -g "*.php"
Get-Content app\Http\Controllers\Admin\FeeController.php
Get-Content app\Http\Controllers\Admin\InstallmentFeeController.php
Get-Content app\Http\Controllers\Admin\FeeStructureController.php
Get-Content app\Http\Controllers\Admin\ProfessionalFeeManagementController.php
Get-Content app\Services\ProfessionalFeeManagementService.php
Get-Content app\Http\Controllers\Admin\FeeAutomationController.php
Get-Content app\Http\Controllers\Admin\FeeReceiptController.php
Get-Content app\Models\FeeStructure.php
Get-Content app\Models\StudentFeeAssignment.php
Get-Content app\Models\Fee.php
Get-Content app\Models\FeeStructureItem.php
rg -n "create_.*student_fee_assignments|student_fee_assignments|assigned_by|assigned_date|assigned_at" database\migrations app\Models\StudentFeeAssignment.php app\Services\ProfessionalFeeManagementService.php app\Http\Controllers\Admin\InstallmentFeeController.php
php -l app\Http\Controllers\Admin\InstallmentFeeController.php
php artisan route:list | Select-String fee
rg -n "installment-fees|fee-management|fees/payment|FeeController|InstallmentFeeController|ProfessionalFeeManagementController|FeeStructureController|FeeAutomationController|FeeReceiptController" routes\web.php
Get-ChildItem -Recurse resources\views\admin
Get-ChildItem -Recurse tests
```

One attempted `rg` command used Windows wildcard path syntax that `rg` rejected. It was rerun safely with `-g` filters.

## 12. Verification Summary

PHP syntax:

```text
No syntax errors detected in app\Http\Controllers\Admin\InstallmentFeeController.php
```

Route listing:

- `php artisan route:list | Select-String fee` completed successfully.
- Fee route duplication and the `fees/payment` mismatch remain documented.

No database-changing command was run.

## 13. Remaining Risks

- `StudentFeeAssignment` model fillable only allows `student_id`, `fee_structure_id`, and `academic_year`, while controllers/services pass fields such as `assigned_by`, `assigned_date`, `assigned_at`, `valid_from`, `valid_until`, `is_active`, discounts, additional charges, and notes.
- Student fee assignment migrations are inconsistent, so model fillable was not changed in this phase.
- `FeeStructureItem` model fillable does not include `name` or `description`, while `InstallmentFeeController` passes those fields.
- `FeeController` uses `$request->all()` and performs multi-step status updates without transaction protection.
- `ProfessionalFeeManagementController` passes `$request->all()` to service methods.
- Professional fee service uses schema fields that may not exist in the currently migrated database because professional fee migrations were previously reported pending/inconsistent.
- No fee-related database feature tests were run because migration state is unsafe.

## 14. Recommended Next Step

Phase 1D should reconcile the fee domain schema and model fillable contracts without running migrations:

- Compare active migrations against `Fee`, `FeeStructure`, `FeeStructureItem`, `StudentFeeAssignment`, `FeeCollection`, and professional fee models.
- Decide which fee system is canonical: legacy `Fee`, collection-based fees, installment fees, or professional fee management.
- Remove or quarantine unrouted/dead fee write paths after route confirmation.
- Replace `$request->all()` in active financial controllers with validated data arrays or FormRequests.

