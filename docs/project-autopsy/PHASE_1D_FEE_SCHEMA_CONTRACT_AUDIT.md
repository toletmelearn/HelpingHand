# Phase 1D Fee Schema Contract Audit

Date: 2026-06-03  
Scope: read-only audit of fee-domain schema, model contracts, controllers, services, and routes

## 1. Files Inspected

Models:

- `app/Models/Fee.php`
- `app/Models/FeeCollection.php`
- `app/Models/FeeCollectionItem.php`
- `app/Models/FeeStructure.php`
- `app/Models/FeeStructureItem.php`
- `app/Models/FeeStructureDetail.php`
- `app/Models/StudentFeeAssignment.php`
- `app/Models/FeeHead.php`
- `app/Models/FeeType.php`

Controllers/services:

- `app/Http/Controllers/Admin/FeeController.php`
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Http/Controllers/Admin/FeeStructureController.php`
- `app/Http/Controllers/Admin/InstallmentFeeController.php`
- `app/Http/Controllers/Admin/ProfessionalFeeManagementController.php`
- `app/Http/Controllers/Admin/FeeAutomationController.php`
- `app/Http/Controllers/Admin/FeeReceiptController.php`
- `app/Services/ProfessionalFeeManagementService.php`
- `app/Services/Payment/StripePaymentService.php` by search only
- `app/Services/Sms/TwilioSmsService.php` by search only

Routes/views/migrations:

- `routes/web.php`
- `database/migrations/*fee*`
- `database/migrations/*student_fee*`
- `database/migrations/*payment*`
- `resources/views/admin/fees`
- `resources/views/admin/fee-management`
- `resources/views/admin/fee-structures`

## 2. Commands Run

```powershell
Get-Content C:\Users\hp\.codex\attachments\150787a0-081d-414e-90f1-ca22ee6bdb22\pasted-text.txt
Get-ChildItem database\migrations | Where-Object { $_.Name -match 'fee|student_fee|payment' }
rg -n "Schema::create|Schema::table|table\(|foreignId|decimal|string|enum|boolean|date|timestamp|softDeletes|dropIfExists" database\migrations -g "*fee*" -g "*student_fee*" -g "*payment*"
php artisan migrate:status
php artisan route:list | Select-String fee
php artisan tinker --execute="dump(Schema::getColumnListing('student_fee_assignments'));"
php artisan tinker --execute="dump(Schema::getColumnListing('fee_structure_items'));"
php artisan tinker --execute="dump(Schema::getColumnListing('fee_structures'));"
php artisan tinker --execute="dump(Schema::getColumnListing('fee_collections'));"
php artisan tinker --execute="dump(Schema::hasTable('fee_heads')); if (Schema::hasTable('fee_heads')) dump(Schema::getColumnListing('fee_heads'));"
php artisan tinker --execute="dump(Schema::hasTable('fee_structure_details')); if (Schema::hasTable('fee_structure_details')) dump(Schema::getColumnListing('fee_structure_details'));"
php artisan tinker --execute="dump(Schema::getColumnListing('fee_collection_items'));"
php artisan tinker --execute="dump(Schema::getColumnListing('fees'));"
rg -n "protected \$fillable|protected \$casts|function .*\(|::create\(|->update\(|->fill\(|\$request->all\(|\$request->only\("
Get-Content app\Models\FeeStructureDetail.php
Get-Content app\Models\FeeHead.php
Get-Content app\Models\FeeType.php
rg -n "FeeController@payment|Route::resource\('fee-structures'|Route::resource\('fees'|admin/fees/store|InstallmentFeeController|fee-management|fees/payment" routes\web.php
Get-Content selected fee migrations
```

Only read/list/schema-inspection commands were run.

## 3. Fee Tables And Migrations Map

| Table | Migration source | Migration status observed | Current DB columns observed |
| --- | --- | --- | --- |
| `fees` | `2026_01_22_050134_create_fees_table.php`; FK added by `2026_01_22_060505...` | Ran | `id`, `student_id`, `fee_structure_id`, `academic_year`, `term`, `amount`, `paid_amount`, `due_amount`, `status`, `due_date`, `payment_date`, `notes`, timestamps |
| `fee_structures` | `2026_01_22_050157_create_fee_structures_table.php`; altered by `2026_02_12_075922...`, `2026_02_12_080221...`; duplicate create migration `2026_02_12_100001...` pending | Original and alter migrations ran; duplicate create pending | `id`, `name`, `class_name`, `term`, `amount`, `description`, `is_active`, `frequency`, `valid_from`, `valid_until`, timestamps, `deleted_at`, `academic_year`, `installment_count`, `installment_frequency`, `status`, `created_by` |
| `fee_types` | `2026_02_12_100000_create_fee_types_table.php` | Ran | Not column-checked directly, migration defines `name`, `description`, `is_optional`, `status`, soft deletes |
| `fee_structure_items` | `2026_02_12_100002_create_fee_structure_items_table.php` | Ran | `id`, `fee_structure_id`, `fee_type_id`, `amount`, `due_day`, timestamps |
| `student_fee_assignments` | `2026_02_12_000000_create_professional_fee_management_system.php`; duplicate `2026_02_12_100003...` pending | Professional migration listed pending, but current DB has professional-style columns | `id`, `student_id`, `fee_structure_id`, `academic_year`, `assigned_date`, `effective_from`, `effective_until`, `is_active`, `notes`, timestamps |
| `fee_collections` | `2026_02_12_100004_create_fee_collections_table.php`; `2026_02_12_100006_add_fee_month...` pending; professional migration also defines conflicting table pending | Collection migration ran; `fee_month` migration pending | `id`, `receipt_no`, `student_id`, `fee_structure_id`, `total_amount`, `discount`, `late_fine`, `final_amount`, `payment_date`, `payment_mode`, `remarks`, `collected_by`, timestamps, `deleted_at` |
| `fee_collection_items` | `2026_02_12_100005_create_fee_collection_items_table.php` | Ran | `id`, `fee_collection_id`, `fee_type_id`, `amount`, timestamps |
| `fee_heads` | `2026_02_12_000000_create_professional_fee_management_system.php` | Migration listed pending, table exists | `id`, `name`, `code`, `description`, `type`, `is_active`, timestamps |
| `fee_structure_details` | `2026_02_12_000000_create_professional_fee_management_system.php` | Migration listed pending, table exists | `id`, `fee_structure_id`, `fee_head_id`, `amount`, `frequency`, `applicable_months`, `is_late_fee_applicable`, `late_fee_amount`, `late_fee_days_after`, `is_mandatory`, timestamps |
| `fee_receipts` | `2026_02_12_000000_create_professional_fee_management_system.php` | Migration listed pending | Not checked; service references receipt-like fields indirectly on collections |
| `fee_discounts` / `student_fee_discounts` | `2026_02_12_000000_create_professional_fee_management_system.php` | Migration listed pending | Not checked |

Critical observation: `php artisan migrate:status` says `2026_02_12_000000_create_professional_fee_management_system` is pending, yet tables from that migration exist. This means migration history and actual schema are out of sync.

## 4. Fee Models Map

| Model | Intended table | Main fillable contract | Primary users |
| --- | --- | --- | --- |
| `Fee` | `fees` | `student_id`, `fee_structure_id`, `academic_year`, `term`, `amount`, `paid_amount`, `due_amount`, `status`, `due_date`, `payment_date`, `notes` | `Admin\FeeController`, `PaymentController`, parent/API reads |
| `FeeStructure` | `fee_structures` | `class_name`, `academic_year`, `frequency`, `installment_count`, `installment_frequency`, `status`, `created_by` | `FeeStructureController`, `InstallmentFeeController`, `ProfessionalFeeManagementService`, `FeeController` |
| `FeeStructureItem` | `fee_structure_items` | `fee_structure_id`, `fee_type_id`, `amount`, `due_day` | `FeeStructureController`, `InstallmentFeeController`, `FeeCollectionController` |
| `FeeType` | `fee_types` | `name`, `description`, `is_optional`, `status` | `FeeStructureController`, `FeeCollectionController` |
| `StudentFeeAssignment` | `student_fee_assignments` | `student_id`, `fee_structure_id`, `academic_year` | `FeeStructureController`, `InstallmentFeeController`, `ProfessionalFeeManagementService` |
| `FeeCollection` | `fee_collections` | `receipt_no`, `student_id`, `fee_structure_id`, `total_amount`, `discount`, `late_fine`, `final_amount`, `payment_date`, `payment_mode`, `remarks`, `collected_by` | `FeeCollectionController`, `InstallmentFeeController`, dashboards/reports |
| `FeeCollectionItem` | `fee_collection_items` | `fee_collection_id`, `fee_type_id`, `amount` | `FeeCollectionController`, `InstallmentFeeController` |
| `FeeHead` | `fee_heads` | `name`, `code`, `description`, `type`, `is_active` | `ProfessionalFeeManagementService` |
| `FeeStructureDetail` | `fee_structure_details` | `fee_structure_id`, `fee_head_id`, `amount`, `frequency`, `applicable_months`, `is_late_fee_applicable`, `late_fee_amount`, `late_fee_days_after`, `is_mandatory` | `ProfessionalFeeManagementService` |

## 5. Fee Controllers And Services Map

| Component | Role | Status |
| --- | --- | --- |
| `Admin\FeeCollectionController` | Main active fee collection CRUD/dashboard/receipt flow | Active and routed |
| `Admin\FeeStructureController` | Creates class/year fee structures and fee structure items | Active and routed |
| `Admin\FeeAutomationController` | Pending/defaulter dashboard and WhatsApp URL generator | Active and routed |
| `Admin\FeeReceiptController` | PDF receipt download for `FeeCollection` | Active and routed |
| `Admin\FeeController` | Legacy `Fee` CRUD controller | Mostly unrouted; one broken `fees/payment` route references missing method |
| `Admin\InstallmentFeeController` | Installment-specific fee structures, assignment, payment | Controller exists, not visible in `fee` route listing |
| `Admin\ProfessionalFeeManagementController` | Professional fee management interface | Active and routed |
| `ProfessionalFeeManagementService` | Fee heads, fee structures, assignments, analytics/forecasting | Active through professional controller, but schema-contract drift is severe |
| `PaymentController` / `StripePaymentService` | Stripe/payment flow | Found but not modified; route exposure appears incomplete/mismatched |

## 6. Fee Routes Map

Active fee collection routes:

- `admin/fees` resource -> `Admin\FeeCollectionController`
- `admin/fees/search-students`
- `admin/fees/student/{id}/dashboard`
- `admin/fees/collect/{studentId}`
- `admin/fees/process-collection`
- `admin/fees/receipt/{id}`
- `admin/fees/receipt/{id}/pdf`
- `admin/fees/pending`
- `admin/fees/defaulters`
- `admin/fee-dashboard`
- `admin/fees/send-whatsapp-reminder`

Problem routes:

- `POST fees/payment` -> `Admin\FeeController@payment`, but `FeeController` has no `payment()` method.
- `POST admin/admin/fees/store` -> duplicated prefix caused by `/admin/fees/store` inside an `admin` route group.
- `GET admin/admin/fees/receipt/{id}` and PDF variant have the same duplicated prefix problem.
- `Route::resource('fee-structures', FeeStructureController::class)` appears in two places in `routes/web.php`.

Professional fee routes:

- `admin/fee-management/dashboard`
- `admin/fee-management/fee-heads`
- `admin/fee-management/structures`
- `admin/fee-management/assign-student`
- `admin/fee-management/bulk-assign`
- reports, receipt, forecasting, preview, export

Installment fee route status:

- `InstallmentFeeController` methods were not visible in `php artisan route:list | Select-String fee`.
- Treat as unrouted or routed under non-obvious names until proven otherwise.

## 7. Active Vs Legacy Fee System Classification

| System | Classification | Recommendation |
| --- | --- | --- |
| Legacy `Fee` system | Unrouted/partially broken | Quarantine later. Do not build new features on it. |
| `FeeCollection` system | Active and closest to current working flow | Keep as canonical money collection ledger. |
| `FeeStructure` / `FeeStructureItem` system | Active and needed, but schema/model mismatch exists | Keep, repair contracts. |
| Installment fee system | Controller exists; route exposure unclear; overlaps with fee structure and collection systems | Quarantine until routed intentionally and schema fields are reconciled. |
| Professional fee management | Active routes exist, but service uses fields not present/fillable in current schema | Quarantine or mark experimental until schema is normalized. |
| Stripe/payment system | Found but not cleanly wired | Keep separate for later payment phase; do not mix with collection cleanup yet. |

Recommended canonical system: `FeeStructure` + `FeeStructureItem` + `StudentFeeAssignment` + `FeeCollection` + `FeeCollectionItem`.

Do not make `fees`/`Fee` canonical. It is older, overlaps with `FeeCollection`, and the route file already comments it out to avoid conflicts.

## 8. Fillable Mismatch Table

| Model | Fields passed by code but not fillable | Impact |
| --- | --- | --- |
| `Fee` | None obvious from legacy controller because it uses `$request->all()` and model fillable includes validated fields. | Still risky because `$request->all()` is broad and no transaction wraps `updateStatus()`. |
| `FeeStructure` | Professional service passes `name`, `class_id`, `term`, `is_active`, `description`, `valid_from`, `valid_until`, `late_fee_days_after`, `discount_percentage`; model fillable lacks all of these except partial overlap through current DB. | Professional structure creation silently drops many fields or fails business expectations. |
| `FeeStructureItem` | `InstallmentFeeController` passes `name`; payment path passes item descriptions indirectly on collection items. | `name` is not fillable and not in `fee_structure_items` schema, so installment item names are discarded. |
| `FeeStructureDetail` | Professional service passes `discount_percentage`, `notes`, `is_custom`; model fillable lacks these. | Custom professional fee detail data is silently discarded or unusable. |
| `StudentFeeAssignment` | Controllers/services pass `assigned_by`, `assigned_date`, `assigned_at`, `valid_from`, `valid_until`, `is_active`, `discount_percentage`, `additional_charges`, `notes`, then service sets `total_amount`. Model fillable only allows `student_id`, `fee_structure_id`, `academic_year`. | Most assignment metadata is not mass assignable; some fields do not exist in current schema. |
| `FeeCollection` | `processCollection()` passes `transaction_id` and `fee_month`; model fillable lacks both. `FeeCollectionController::store()` does not pass `fee_month`. | Transaction IDs and fee month may be silently discarded. Current DB also lacks `fee_month` because migration is pending. |
| `FeeCollectionItem` | `InstallmentFeeController` passes `description`; model fillable lacks it. | Description is discarded and column does not exist. |
| `FeeHead` | Professional service passes `is_mandatory`, `min_amount`, `max_amount`, `is_late_fee_applicable`, `late_fee_percentage`, `due_date_days`; model fillable lacks them. | Data is discarded; current DB columns also do not exist. |

## 9. Schema/Migration Mismatch Table

| Area | Mismatch | Severity |
| --- | --- | --- |
| Migration history vs actual DB | Professional migration is pending, but `fee_heads`, `fee_structure_details`, and professional-style `student_fee_assignments` columns exist. | Critical |
| `student_fee_assignments` | Current DB has `assigned_date`, `effective_from`, `effective_until`, `is_active`, `notes`; model fillable omits them. Code also passes `assigned_by`, `assigned_at`, discounts, charges, and `total_amount`, which current DB does not show. | Critical |
| `fee_structures` | Current DB has many legacy/professional/installment columns, but `FeeStructure` fillable exposes only the installment-style subset. Professional service expects additional fields like `class_id`, `late_fee_days_after`, `discount_percentage`. | Critical |
| `fee_structure_items` | Current DB only supports `fee_type_id`, `amount`, `due_day`; installment controller passes `name`; professional service uses separate `fee_structure_details`. | High |
| `fee_structure_details` | DB lacks `discount_percentage`, `notes`, `is_custom` used by service. | High |
| `fee_collections` | Current DB is collection-ledger style; professional service expects `collection_date`, `receipt_number`, `paid_amount`, `balance_amount`, `due_amount`; current DB has `receipt_no`, `payment_date`, `final_amount`. | Critical |
| `fee_collection_items` | DB lacks `description`, `fee_head_id`, and `discount_amount` used by installment/professional receipt logic. | High |
| `fee_heads` | DB lacks extra service fields: `is_mandatory`, `min_amount`, `max_amount`, late-fee fields, due-date fields. | High |
| `fee_month` | Code passes `fee_month`; migration to add it is pending and current DB column check did not show it. | Critical |

Critical mismatch count: 6

Counted criticals:

1. Migration history says professional migration pending while its tables/columns exist.
2. `StudentFeeAssignment` model/code/schema contract mismatch.
3. `FeeStructure` model/service/schema mismatch.
4. `FeeCollection` current schema vs professional service field mismatch.
5. Code passes `fee_month` while migration is pending/current DB lacks it.
6. `fees/payment` route points to missing controller method.

## 10. Route/Controller Mismatch Table

| Route or controller | Mismatch | Action later |
| --- | --- | --- |
| `POST fees/payment` | Routes to `Admin\FeeController@payment`; method not found. | Remove route or implement only during payment phase. |
| `admin/admin/fees/store` | Duplicated admin prefix caused by defining `/admin/fees/store` inside admin group. | Rename path later, keep route name compatibility carefully. |
| `admin/admin/fees/receipt/{id}` | Same duplicated prefix problem. | Normalize later. |
| `fee-structures` resource | Registered twice in `routes/web.php`. | Consolidate to one registration. |
| `InstallmentFeeController` | Controller has many write methods but no obvious fee route listing. | Treat as dormant until routes are intentionally added or controller is removed. |
| `ProfessionalFeeManagementController` | Active routes call service methods that do not match current model/schema contracts. | Temporarily quarantine or disable writes until schema is repaired. |

## 11. Canonical Fee System Recommendation

Recommended canonical fee system:

```text
FeeStructure
FeeStructureItem
FeeType
StudentFeeAssignment
FeeCollection
FeeCollectionItem
```

Why:

- `FeeCollectionController` is already the active routed collection flow.
- `FeeCollection` has receipt, collector, payment mode, and item-level breakdown semantics.
- `FeeStructureController` and `FeeStructureItem` support class/year fee setup.
- This system is closer to the currently active database than the legacy `Fee` model or the professional service model.

Keep active:

- `Admin\FeeCollectionController`
- `Admin\FeeStructureController`
- `Admin\FeeAutomationController`
- `Admin\FeeReceiptController`

Mark legacy/deprecated later:

- `Admin\FeeController`
- `Fee` model for new money writes
- `fees/payment` route until payment design is clarified

Quarantine later:

- `Admin\InstallmentFeeController`
- `Admin\ProfessionalFeeManagementController` write routes
- `ProfessionalFeeManagementService` write methods

Keep separate for a later payment phase:

- `PaymentController`
- `StripePaymentService`
- Stripe webhook logic

## 12. Safe Next Fix Order

1. Freeze canonical fee choice in documentation: use `FeeCollection` ledger, not legacy `Fee`.
2. Fix the route mismatch `fees/payment -> Admin\FeeController@payment` by removing or quarantining the route in a dedicated route cleanup phase.
3. Normalize duplicated `admin/admin/fees/*` routes without changing controller behavior.
4. Fix `FeeCollection` contract first:
   - decide whether `transaction_id` and `fee_month` are real canonical fields;
   - update schema/model only after migration strategy is safe.
5. Fix `StudentFeeAssignment` contract:
   - choose one schema;
   - align model fillable, services, and controllers.
6. Quarantine professional fee write routes until `FeeStructure`, `FeeStructureDetail`, `FeeHead`, and `FeeCollection` fields match the service.
7. Replace `$request->all()` in `FeeController` and professional controller with validated arrays or FormRequests after schema cleanup.
8. Add feature tests only after migration status is repaired.

## 13. Confirmation

No application code was modified.

No migrations were modified.

No routes were modified.

No models were modified.

No data-changing commands were run.

Only this report file was created:

`docs/project-autopsy/PHASE_1D_FEE_SCHEMA_CONTRACT_AUDIT.md`

