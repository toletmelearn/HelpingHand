# Phase 8I — Fee Ledger and Reconciliation Policy Autopsy Report

## 1. Files Inspected
- `app/Models/FeeCollection.php`
- `app/Models/FeeCollectionItem.php`
- `app/Models/FeeStructure.php`
- `app/Models/FeeStructureItem.php`
- `app/Models/FeeType.php`
- `app/Models/StudentFeeAssignment.php`
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Http/Controllers/Admin/FeeAutomationController.php`
- `app/Http/Controllers/Admin/ProfessionalFeeManagementController.php`
- `app/Http/Controllers/Admin/RoleDashboardController.php`
- `app/Http/Controllers/Admin/AdminDashboardController.php`
- `app/Services/ProfessionalFeeManagementService.php`
- `resources/views/admin/fees/collect-form.blade.php`
- migrations related to:
  - `fee_collections`
  - `fee_collection_items`
  - `fee_structures`
  - `fee_structure_items`
  - `fee_types`
- previous reports:
  - `docs/project-autopsy/PHASE_8A_FEE_FINANCE_WRITE_PATH_AUDIT.md`
  - `docs/project-autopsy/PHASE_8E_RECEIPT_NUMBER_COLLISION_GAP_HARDENING.md`
  - `docs/project-autopsy/PHASE_8G_FEE_DUPLICATE_SUBMISSION_GUARD.md`
  - `docs/project-autopsy/PHASE_8H_PROFESSIONAL_FEE_ITEM_CONTRACT_FIX.md`

## 2. Files Changed
- `tests/Feature/FeeFinance/FeeLedgerConsistencyAuditTest.php` (Created: Consistency checks validating date column, ledger totals sum, and delete route quarantine)
- `docs/project-autopsy/PHASE_8I_FEE_LEDGER_RECONCILIATION_POLICY_AUDIT.md` (Created: This report)

## 3. Financial Source-of-Truth Table & Policy
- **Transaction Table**: `fee_collections` represents the payment header (storing `receipt_no`, `total_amount`, `discount`, `late_fine`, `final_amount`, `payment_date`, `payment_mode`, and the collector `collected_by`).
- **Line-Item Table**: `fee_collection_items` represents individual fee items paid (storing `fee_collection_id`, `fee_type_id`, `amount`).
- **No Separate Ledger Table**: No separate double-entry ledger, invoice ledger, or sub-ledger table exists. The system relies directly on the sum of values stored in `fee_collections` and `fee_collection_items` to calculate dashboard totals and report figures.
- **Persisted Receipts**: Receipts are compiled dynamically by query lookups against persisted `fee_collections` and `fee_collection_items` records (via `ProfessionalFeeManagementService::generateFeeReceipt` or `FeeCollectionController::getReceipt`).

## 4. Reporting & Dashboard Consumers Audited
The following controllers and methods query totals and pending/collected amounts:

| Consumer | Method | Target Table(s) | Date Field | Uses `total_amount` | Sums `amount` |
| --- | --- | --- | --- | --- | --- |
| `AdminDashboardController` | `calculatePendingFees` | `students`, `fee_assignments`, `fee_structures`, `fee_structure_items`, `fee_collections`, `fee_collection_items` | None | No | Yes (sums `fee_collection_items.amount`) |
| `RoleDashboardController` | `calculatePendingFees` | `students`, `fee_assignments`, `fee_structures`, `fee_structure_items`, `fee_collections`, `fee_collection_items` | None | No | Yes (sums `fee_collection_items.amount`) |
| `FeeAutomationController` | `calculatePendingFees` | `students`, `fee_assignments`, `fee_structures`, `fee_structure_items`, `fee_collections`, `fee_collection_items` | None | No | Yes (sums `fee_collection_items.amount`) |
| `FeeAutomationController` | `feeDashboard` | `fee_collections` | `payment_date` (via scopes `today` and `currentMonth`) | Yes (sums `final_amount` / `total_amount`) | No |
| `FeeAutomationController` | `getMonthlyChartData` | `fee_collections` | `payment_date` | Yes (sums `final_amount`) | No |
| `FeeAutomationController` | `getClassWiseData` | `fee_collections` | None | Yes (sums `final_amount`) | No |
| `ProfessionalFeeManagementController` | `getMonthlyCollections` | `fee_collections` | `payment_date` | Yes (sums `total_amount`) | No |

## 5. Key Audit Findings & Risks

### A. Date Field Consistency
- All query consumers consistently query `payment_date` (no active controllers, models, views, or reports query the obsolete/deleted `collection_date` column). This aligns with the Phase 8B column normalization.

### B. Status & Payment State Policy
- **Transient Status Calculation**: There is no database column representing payment status (e.g. `status` or `payment_status` is not stored in `fee_collections` or `fee_collection_items` tables).
- **In-Memory Calculations**: Payment statuses (like `paid`, `partial`, `pending`, `overdue`) are calculated dynamically at runtime by comparing the student's accumulated payment items (`fee_collection_items.amount` sum) against their assigned structure items (`fee_structure_items.amount`).
- **Active Structure Indicator Discrepancy**: There is a mismatch in how "active" fee structures are queried. `ProfessionalFeeManagementController` queries `FeeStructure::where('is_active', true)`, but the database column in the reconciled migration is `status` (values: `'active'`, `'inactive'`). The `FeeStructure` model defines a scope `scopeActive` checking `status = 'active'`, but `where('is_active', true)` is still present in the controller, which can cause runtime crashes or index mismatches depending on which migration created the table.

### C. Delete, Reversal, and Refund Policy
- **No Delete/Reversal Path**: The system has no active route, controller method, or UI element for deleting, reversing, or refunding fee payments.
- **Active Soft Deletes**: While `FeeCollection` uses `SoftDeletes`, there is no controller route implementing `destroy()`. The unsafe legacy resource routes (like `fees.destroy`) were quarantined in Phase 8F.
- **Orphaned Items Risk**: Since `FeeCollectionItem` does not use `SoftDeletes`, if a fee collection is soft-deleted, its related collection item records are orphaned in the database.

### D. HTML Form Mismatch / Out of Sync Values
- In `resources/views/admin/fees/collect-form.blade.php`, the amount input has name `amount[]` but is missing an ID.
- The Javascript handler tries to update the amount value via `const amountInput = $('#amount_' + feeId);`, which selects nothing.
- Consequently, unchecked checkbox values are not set to `0.00` in the amount array submitted by the browser. The browser dispatches all fee amounts in order.
- Since checkboxes only submit checked values, the controller (`FeeCollectionController::store`) maps `$request->fee_type_id[$i]` (which only contains checked indices) to `$request->amount[$i]` (which contains all indices in sequence). This causes index-shifting where wrong amount values are stored in wrong fee types, creating discrepancies where `fee_collections.total_amount` does not equal the sum of its items.

## 6. Recommendations

### Must-Fix before Production
1. **Fix Amount Input ID in Form**: Add `id="amount_{{ $feeType->id }}"` to the amount input in `collect-form.blade.php` to enable correct JavaScript synchronization and prevent index shifting when submitting the form.
2. **Standardize Active Structures Lookup**: Replace `FeeStructure::where('is_active', true)` with `FeeStructure::active()` or `FeeStructure::where('status', 'active')` in `ProfessionalFeeManagementController.php` to match the canonical migrations.

### Can-Defer
1. **Soft Delete Cascade**: Implement database-level or model-level cascade soft deletes to clean up `fee_collection_items` when a parent `fee_collections` row is soft-deleted.
2. **Reversal Flow**: Implement a cancellation/reversal status/flow if accountants need to void a receipt.

---

## 7. Verification Results
All tests were executed in an isolated SQLite in-memory configuration using `--env=testing`.

```text
Tests:    57 passed (116 assertions)
Duration: 11.25s
```

All regression tests continued to pass successfully.
