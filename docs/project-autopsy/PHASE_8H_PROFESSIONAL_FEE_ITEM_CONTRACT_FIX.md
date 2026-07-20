# Phase 8H — Professional Fee Item Contract Fix Autopsy Report

## 1. Files Inspected
- `app/Services/ProfessionalFeeManagementService.php`
- `app/Models/FeeCollectionItem.php`
- `app/Models/FeeCollection.php`
- `app/Models/FeeType.php`
- `app/Models/FeeStructureItem.php`
- `app/Models/FeeStructure.php`
- `app/Http/Controllers/Admin/InstallmentFeeController.php`
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Http/Controllers/Admin/RoleDashboardController.php`
- `tests/Feature/FeeFinance/ProfessionalFeeItemContractTest.php`

## 2. Files Changed
- `app/Http/Controllers/Admin/InstallmentFeeController.php` (Mapped `fee_type_id` to `$item->fee_type_id` and removed unsupported `description` field in `processInstallmentPayment`)
- `app/Http/Controllers/Admin/RoleDashboardController.php` (Mapped `$item->id` to `$item->fee_type_id` in `calculatePendingFees` query logic to resolve dashboard discrepancy)
- `tests/Feature/FeeFinance/ProfessionalFeeItemContractTest.php` (Created: 7 test cases covering the contract fix and regressions)
- `docs/project-autopsy/PHASE_8H_PROFESSIONAL_FEE_ITEM_CONTRACT_FIX.md` (Created: This report)

## 3. Previous Foreign-Key / Field-Contract Risk
- **Foreign Key Violation**: In `InstallmentFeeController::processInstallmentPayment()`, the loops creating `FeeCollectionItem` records incorrectly mapped `'fee_type_id' => $item->id`. Since `$item` represents a `FeeStructureItem` model, `$item->id` is the ID of a record in the `fee_structure_items` table. However, the database schema configures `fee_collection_items.fee_type_id` as a foreign key pointing to `fee_types.id`. This mismatch violated referential integrity and caused issues rendering receipt labels (since the item could not resolve its `feeType` relationship properly).
- **Unsupported Mass-Assignment**: The controller also passed `'description' => $item->name` when calling `FeeCollectionItem::create()`. The `fee_collection_items` table does not contain a `description` column, and the `FeeCollectionItem` model's `$fillable` array does not include `description`. This resulted in discarded payload fields.

## 4. Current Schema
### `fee_structure_items` table
- `id` (Primary Key)
- `fee_structure_id` (Foreign Key -> `fee_structures.id`)
- `fee_type_id` (Foreign Key -> `fee_types.id`)
- `amount` (Decimal)
- `due_day` (Integer)
- `timestamps`

### `fee_collection_items` table
- `id` (Primary Key)
- `fee_collection_id` (Foreign Key -> `fee_collections.id`)
- `fee_type_id` (Foreign Key -> `fee_types.id`)
- `amount` (Decimal)
- `timestamps`

### `fee_types` table
- `id` (Primary Key)
- `name` (String)
- `timestamps`
- `deleted_at` (Soft Delete)

## 5. Verification Results
All tests were executed in an isolated SQLite in-memory configuration using `--env=testing`.

```text
Tests:    54 passed (112 assertions)
Duration: 10.39s
```

The newly added test suite `ProfessionalFeeItemContractTest.php` verified:
- Correct mapping of `fee_type_id` when creating fee collection items.
- Absence of `description` field from the creation payload without DB schema additions.
- Resolution of receipt labels via `ProfessionalFeeManagementService::generateFeeReceipt`.
- Regression check validation (Route guards, hardeners, duplicates, payment dates).
