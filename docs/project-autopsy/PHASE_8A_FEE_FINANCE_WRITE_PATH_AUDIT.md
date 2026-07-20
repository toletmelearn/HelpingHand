# PHASE 8A — Fee/Finance Module Route & Write Path Audit

This report documents the route, database, transaction, and write-path audit of the HelpingHand School ERP Fee/Finance module.

## 1. Files Inspected
* `routes/web.php`
* `app/Http/Controllers/Admin/FeeCollectionController.php`
* `app/Http/Controllers/Admin/FeeAutomationController.php`
* `app/Http/Controllers/Admin/FeeController.php` (commented out)
* `app/Http/Controllers/Admin/InstallmentFeeController.php`
* `app/Http/Controllers/Admin/ProfessionalFeeManagementController.php`
* `app/Services/ProfessionalFeeManagementService.php`
* `app/Services/ProfessionalDashboardService.php`
* `app/Models/FeeCollection.php`
* `app/Models/FeeCollectionItem.php`
* `app/Models/FeeHead.php`
* `app/Models/FeeStructure.php`
* `app/Models/FeeStructureDetail.php`
* `app/Models/StudentFeeAssignment.php`
* `database/migrations/2026_02_12_100004_create_fee_collections_table.php`
* `database/migrations/2026_02_12_100005_create_fee_collection_items_table.php`

## 2. Commands & Search Audits Run
* Analyzed active routes mapping `fees` and `fee-management`.
* Audited all controllers and services managing financial writes for transactions and validation patterns.
* Checked schema definitions and index constraints on migrations.
* Inspected receipt numbers and date parameters across all active controllers.

---

## 3. Audit Findings

### A. Fee Collection Routes
Active admin fee routes include:
1. `fees.index` / `fees.create` (mapped via resource on `FeeCollectionController`)
2. `fees.search.students`
3. `fees.student-dashboard`
4. `fees.collect.form` (renders collection screen)
5. `fees.process.collection` (handles form submissions)
6. `fees.receipt` / `fees.receipt.pdf`
7. `fees.pending` / `fees.defaulters` / `fee-dashboard` (mapped via `FeeAutomationController`)
8. `fee-management/dashboard` / `fee-management/fee-heads` (mapped via `ProfessionalFeeManagementController`)

### B. Payment Create/Update/Delete Routes
* **Create (Direct Collection)**: Implemented in `FeeCollectionController::processCollection` (processes multi-item forms), `collectFee` (API/JSON submission), and `InstallmentFeeController::processInstallmentPayment`.
* **Update**: None of the active controllers (`FeeCollectionController`, `InstallmentFeeController`, `ProfessionalFeeManagementController`) implement `update` or `edit` methods.
  * *Bug risk*: The resource mapping `Route::resource('fees', ...)` registers `PUT/PATCH` routes pointing to `update`, which will throw a method-not-found 500 error if hit directly.
* **Delete**: There is no active payment/collection delete path. The main CRUD `FeeController@destroy` resource is commented out in `routes/web.php`. `FeeCollectionController` has no `destroy` method defined.
  * *Bug risk*: The resource mapping registers the `DELETE` route, which will crash if triggered.

### C. Receipt Generation
1. `fees.receipt` / `receipt` returns HTML receipts.
2. `fees.receipt.pdf` / `downloadPdf` generates downloaded PDFs via `Barryvdh\DomPDF`.
3. `ProfessionalFeeManagementController::generateReceipt` compiles professional invoices using its service helper.

### D. Invoice/Ledger Sync
* No database tables or models exist for `invoices` or a financial `ledger`.
* Payments are directly written to `fee_collections` and `fee_collection_items` tables, which represent the ledger in this database architecture.

### E. Transaction Usage
* **Manual Transactions**: `FeeCollectionController::collectFee` and `processCollection` use manual `DB::beginTransaction()`, `DB::commit()`, and `DB::rollback()`.
* **Closure-based Transactions**: `FeeCollectionController::store`, `InstallmentFeeController` methods, and `ProfessionalFeeManagementService` write methods use `DB::transaction(function() { ... })`.

### F. Duplicate Payment & Collision Risks
* **Receipt Number Collisions**:
  * `receipt_no` has a `.unique()` constraint in the `fee_collections` table, protecting database integrity but causing a database query crash / 500 error for users on collision.
  * `generateReceiptNumber()` calculates the next number by getting the latest record:
    `$number = $latest ? intval(substr($latest->receipt_no, -4)) + 1 : 1;`
  * If a payment is created via `store()` method, it generates `RCPT-YmdHis` (e.g. `RCPT-20260610111205`). The generator reads the last 4 characters (`1205`) and jumps the receipt number to `SCH-REC-1206`, creating large numbering gaps and collision risks with existing receipts.
  * No double-click protection exists on collection forms, risking double-submission of payments.

### G. Rollback Failure Risks
* Standard DB transactions handle rollbacks correctly.
* Reminders (`sendWhatsappReminder`) return a redirect URL (`https://wa.me/{mobile}?text=...`) and do not execute server-side API calls, posing no rollback mismatch risk.

### H. Role Permissions
* **Exposure**: The parent route group wrapping fee controllers (`Route::prefix('admin')` in `web.php:385`) only checks `['auth', 'verified']`.
* **No internal authorization**: Neither `FeeCollectionController` nor `InstallmentFeeController` defines Spatie role checks or policy checks in their constructors or active methods. Any logged-in and verified staff/teacher can access and execute fee collections.

### I. Critical Code & Database Schema Mismatches
1. **Wrong date column (High-Risk Crash)**:
   * The database migration defines the column `payment_date`.
   * `ProfessionalFeeManagementController`, `ProfessionalFeeManagementService`, and `ProfessionalDashboardService` write and query using `collection_date` (e.g. `FeeCollection::whereMonth('collection_date', ...)`).
   * *Impact*: Any administrator visiting the Professional Dashboard, Defaulters List, or Collection Reports will trigger a database query crash: `Column not found: 1054 Unknown column 'collection_date'`.
2. **Missing model relationships**:
   * `ProfessionalFeeManagementService::generateFeeReceipt` queries the `feeHead` relationship on `FeeCollectionItem`.
   * The `FeeCollectionItem` model defines a `feeType` relation, but **no** `feeHead` relation exists.
   * *Impact*: Receipt generation in professional fee management will crash with a `RelationNotFoundException`.
3. **Data type mismatch**:
   * `ProfessionalFeeManagementService` writes `fee_structure_items.id` directly into the `fee_type_id` column of `fee_collection_items`, violating foreign key logic since `fee_type_id` expects a record from the `fee_types` table.
   * `FeeCollectionItem::create` passes a `description` field, which is neither fillable in the model nor exists in the database schema.

---

## 4. Next Phase (Phase 8B) Implementation Recommendations
1. **Differentiate real MySQL**: All development and testing must run strictly in SQLite `:memory:` connections.
2. **Normalize columns**: Replace all incorrect references to `collection_date` with the actual database column `payment_date`.
3. **Fix receipt number logic**: Standardize receipt number generation to avoid gaps or collisions.
4. **Implement middleware checks**: Add role authorization checks (e.g. Spatie permissions/roles) to restrict fee routes to authorized accounts only.
5. **Reconcile models**: Fix the `feeHead` vs `feeType` relation mismatch in the professional fee module and prevent writing foreign key IDs into unrelated columns.
