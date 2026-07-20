# Phase 8G — Hardening Fee Collection Against Duplicate Payment and Double-Submit Risk Autopsy Report

## 1. Files Inspected
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Http/Controllers/Admin/InstallmentFeeController.php`
- `resources/views/admin/fees/collect-form.blade.php`
- `resources/views/admin/fees/create.blade.php`
- `resources/views/admin/fees/index.blade.php`
- `tests/Feature/FeeFinance/FeeDuplicateSubmissionGuardTest.php`
- `tests/Feature/FeeFinance/FeeReceiptNumberHardeningTest.php`
- `tests/Feature/FeeFinance/FeeResourceRouteSafetyTest.php`
- `docs/project-autopsy/PHASE_8E_RECEIPT_NUMBER_COLLISION_GAP_HARDENING.md`
- `docs/project-autopsy/PHASE_8F_FEE_RESOURCE_ROUTE_SAFETY.md`

## 2. Files Changed
- `tests/Feature/FeeFinance/FeeDuplicateSubmissionGuardTest.php` (Modified: Fixed escaping assertions and direct controller authentication context)
- `docs/project-autopsy/PHASE_8G_FEE_DUPLICATE_SUBMISSION_GUARD.md` (New: This report)

## 3. Duplicate-Submit Risk Found
- Without submission tokens and fingerprinting, rapid double-clicks on submit buttons or repeated form submissions (due to latency, browser back operations, or user error) can generate twin/multiple parallel requests.
- This creates duplicate `FeeCollection` records and duplicate payment items for the same student/context, bypassing receipt collision retries because each parallel thread successfully processes different/new receipt numbers.

## 4. UI Protection Added
- In active fee collection forms (`collect-form.blade.php`), browser double-click protection is enforced:
  - Submit button is disabled immediately upon clicking.
  - Button text changes to "Processing..." with a spinner.
  - Form submission triggers validation to prevent repeated browser form dispatch.
  - A hidden `submission_token` (UUIDv4) is generated dynamically on form load to track form uniqueness.

## 5. Server-Side Fingerprint / Lock Strategy
- A new core service `FeePaymentLockService` calculates a deterministic MD5 fingerprint from the request's core payment parameters:
  - `user_id` (authenticated collector)
  - `student_id`
  - `amount` (total payment)
  - `fee_types` / `installment_number`
  - `payment_date`
  - `payment_mode`
- **Cache-based Locking Mechanism**:
  - **Submission Token Lock**: Short-lived cache lock (60 seconds) prevents identical submission token re-execution.
  - **Payload Fingerprint Lock**: Ultra-short cache lock (10 seconds) prevents duplicate payment payloads for the same collector/student/amount.
- **Fail-safe Release**: If an exception or database error occurs, the cache locks are immediately cleared, allowing the user to retry payment safely.

## 6. Write Paths Protected
- `FeeCollectionController::collectFee` (JSON API / Single-item collection)
- `FeeCollectionController::processCollection` (Form POST / Multi-item collection)
- `FeeCollectionController::store` (Standard POST / Direct collection)
- `InstallmentFeeController::processInstallmentPayment` (Form POST / Installment collection)

## 7. Controlled Duplicate Response Behavior
- **AJAX/JSON Posts**: Returns a JSON payload with `status => false` and a `409 Conflict` status code.
- **Standard Form Posts**: Redirects the user back to the previous page with a friendly error flash message: `"This transaction is already being processed."`

## 8. Confirmation of Unchanged Integrities
- **Receipt Numbering Format Unchanged**: No changes were made to receipt generator formats (`SCH-REC-XXXX` or `RCPT-YmdHis`).
- **Route Guards Unchanged**: Middleware route guards (`role:accountant`) remain fully in place and are not bypassed.
- **Phase 8F Route Quarantine Unchanged**: Quarantined resource routes (edit/update/destroy) remain inactive/quarantined.
- **Payment Date Fix Intact**: All queries and logic continue using `payment_date` rather than the old/quarantined `collection_date`.
- **FeeType Fix Intact**: The relationship on `FeeCollectionItem` maps cleanly to `feeType`.

---

## 9. Verification Tests Run
All tests run in isolated, in-memory SQLite configurations:
```powershell
php artisan test --filter=FeeDuplicateSubmissionGuardTest --env=testing
php artisan test --filter=FeeReceiptNumberHardeningTest --env=testing
php artisan test --filter=FeeResourceRouteSafetyTest --env=testing
php artisan test --filter=FeeRouteAuthorizationGuardTest --env=testing
php artisan test --filter=ProfessionalFeePaymentDateColumnTest --env=testing
php artisan test --filter=ProfessionalFeeReceiptRelationshipTest --env=testing
```

### Results
- `FeeDuplicateSubmissionGuardTest`: **8 Passed**
- `FeeReceiptNumberHardeningTest`: **8 Passed**
- `FeeResourceRouteSafetyTest`: **10 Passed**
- `FeeRouteAuthorizationGuardTest`: **10 Passed**
- `ProfessionalFeePaymentDateColumnTest`: **6 Passed**
- `ProfessionalFeeReceiptRelationshipTest`: **5 Passed**

All tests successfully completed and passed. No full test suite was run.
