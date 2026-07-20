# Phase 1H - Fee Route Exposure Lockdown

## 1. Files Inspected

- `routes/web.php`
- `app/Http/Controllers/Admin/FeeStructureController.php`
- `app/Http/Controllers/Admin/ProfessionalFeeManagementController.php`
- `app/Http/Controllers/Admin/InstallmentFeeController.php`
- `app/Services/ProfessionalFeeManagementService.php`
- `resources/views/layouts/sidebar.blade.php`
- `resources/views/admin/fee-structures`
- `resources/views/admin/fee-management`
- `resources/views/admin/fees`
- `resources/views/admin-dashboard.blade.php`
- `tests`, via route/helper reference search

## 2. Files Changed

- `routes/web.php`
- `docs/project-autopsy/PHASE_1H_FEE_ROUTE_EXPOSURE_LOCKDOWN.md`

## 3. Route Exposure Map

### Fee Structures

| Method | URI | Route name | Controller | Classification | UI/helper status |
| --- | --- | --- | --- | --- | --- |
| `GET` | `admin/fee-structures` | `admin.fee-structures.index` | `FeeStructureController@index` | Read/list | Linked in dashboard/sidebar/views |
| `GET` | `admin/fee-structures/create` | `admin.fee-structures.create` | `FeeStructureController@create` | Read/form | Linked in fee-structures views |
| `POST` | `admin/fee-structures` | `admin.fee-structures.store` | `FeeStructureController@store` | Write | Linked in create form |
| `GET` | `admin/fee-structures/{fee_structure}` | `admin.fee-structures.show` | `FeeStructureController@show` | Read/API-style JSON | Used by JS hardcoded `/admin/fee-structures/{id}` |
| `GET` | `admin/fee-structures/{fee_structure}/edit` | `admin.fee-structures.edit` | `FeeStructureController@edit` | Read/form | Linked in fee-structures views |
| `PUT/PATCH` | `admin/fee-structures/{fee_structure}` | `admin.fee-structures.update` | `FeeStructureController@update` | Write | Linked in edit form |
| `DELETE` | `admin/fee-structures/{fee_structure}` | `admin.fee-structures.destroy` | `FeeStructureController@destroy` | Write | Linked in index delete form |
| `PUT` | `admin/fee-structures/{id}/activate` | `admin.fee-structures.activate` | `FeeStructureController@activate` | Write/status | Used by JS hardcoded `/admin/fee-structures/{id}/{action}` |
| `PUT` | `admin/fee-structures/{id}/deactivate` | `admin.fee-structures.deactivate` | `FeeStructureController@deactivate` | Write/status | Used by JS hardcoded `/admin/fee-structures/{id}/{action}` |

### Professional Fee Management

| Method | URI | Route name | Controller | Classification | Phase 1H status |
| --- | --- | --- | --- | --- | --- |
| `GET` | `admin/fee-management/dashboard` | `admin.fee-management.dashboard` | `ProfessionalFeeManagementController@dashboard` | Read/dashboard | Active |
| `GET` | `admin/fee-management/fee-heads` | `admin.fee-management.fee-heads` | `ProfessionalFeeManagementController@feeHeads` | Read/list | Active |
| `POST` | `admin/fee-management/fee-heads` | `admin.fee-management.fee-heads.store` | `ProfessionalFeeManagementController@createFeeHead` | Write | Quarantined |
| `GET` | `admin/fee-management/structures/create` | `admin.fee-management.structures.create` | `ProfessionalFeeManagementController@createFeeStructure` | Read/form | Active |
| `POST` | `admin/fee-management/structures` | `admin.fee-management.structures.store` | `ProfessionalFeeManagementController@storeFeeStructure` | Write | Quarantined |
| `POST` | `admin/fee-management/assign-student` | `admin.fee-management.assign-student` | `ProfessionalFeeManagementController@assignToStudent` | Write | Quarantined |
| `GET` | `admin/fee-management/reports/collections` | `admin.fee-management.reports.collections` | `ProfessionalFeeManagementController@collectionReport` | Read/report | Active |
| `GET` | `admin/fee-management/defaulters` | `admin.fee-management.defaulters` | `ProfessionalFeeManagementController@defaulters` | Read/report | Active |
| `GET` | `admin/fee-management/receipt/{collectionId}` | `admin.fee-management.receipt` | `ProfessionalFeeManagementController@generateReceipt` | Read/receipt | Active |
| `GET` | `admin/fee-management/forecasting` | `admin.fee-management.forecasting` | `ProfessionalFeeManagementController@forecasting` | Read/report | Active |
| `POST` | `admin/fee-management/bulk-assign` | `admin.fee-management.bulk-assign` | `ProfessionalFeeManagementController@bulkAssign` | Write | Quarantined |
| `GET` | `admin/fee-management/preview/{feeStructureId}` | `admin.fee-management.preview` | `ProfessionalFeeManagementController@previewStructure` | Read/preview | Active |
| `GET` | `admin/fee-management/export` | `admin.fee-management.export` | `ProfessionalFeeManagementController@exportData` | Read/export placeholder | Active |

### Installment Fees

No active `installment-fees` route-list entries were found.

`InstallmentFeeController` contains read and write methods, but no active `routes/web.php` registration was found for that controller.

## 4. Duplicate Fee-Structures Findings

`Route::resource('fee-structures', FeeStructureController::class)` appeared twice:

- First registration: `routes/web.php:406`
- Duplicate registration: previously at `routes/web.php:806`

The duplicate was an exact same URI/name/controller resource registration inside the admin group. Active helpers and UI references use the canonical `admin.fee-structures.*` names, and route-list output continued to show the canonical fee-structure route set after the duplicate was commented.

## 5. Professional Fee-Management Route Findings

Professional write routes found:

- `POST admin/fee-management/fee-heads`
- `POST admin/fee-management/structures`
- `POST admin/fee-management/assign-student`
- `POST admin/fee-management/bulk-assign`

These routes call `ProfessionalFeeManagementController`, which delegates money-domain writes to `ProfessionalFeeManagementService`.

Write safety concerns:

- Uses non-canonical models/contracts such as `FeeHead` and `FeeStructureDetail`.
- Service writes to `FeeStructure`, `FeeStructureDetail`, and `StudentFeeAssignment` in flows previously flagged as schema/model unsafe.
- Controller uses `$request->all()` in `createFeeHead()` and `storeFeeStructure()`.
- Professional fee dashboard view exists, but only GET/read links were found.
- No active Blade/controller/JS helper references were found for the four professional POST write route names.

The four professional POST write routes were quarantined.

## 6. Installment Route Findings

`InstallmentFeeController` methods found:

- Read methods: `index`, `create`, `edit`, `generateInstallmentSchedule`, `paymentHistory`, `generateReport`
- Write methods: `store`, `update`, `assignToClass`, `processInstallmentPayment`, `autoGenerateLateFines`

Route exposure:

- No active `installment` route-list entries were found.
- No `routes/web.php` references to `InstallmentFeeController` were found.
- No Blade/sidebar/dashboard links to `admin.installment-fees.*` were found outside the controller's own redirects/view names.

No installment routes were changed because none were actively exposed.

## 7. Routes Commented or Quarantined

Commented in `routes/web.php`:

- Duplicate `Route::resource('fee-structures', FeeStructureController::class)` registration
- `POST fee-management/fee-heads`
- `POST fee-management/structures`
- `POST fee-management/assign-student`
- `POST fee-management/bulk-assign`

Each quarantine comment names Phase 1H and the reason for the change.

## 8. Routes Documented Only

Documented only:

- Professional fee-management GET/read routes remain active.
- Canonical fee-structures resource write routes remain active because they are linked in UI and belong to the selected canonical fee system.
- Fee-structure activate/deactivate routes remain active because the index view uses matching hardcoded JS endpoints.
- Installment controller write methods remain documented only because no routes expose them.

## 9. UI and Helper References Found

Fee-structures linked references:

- `resources/views/admin-dashboard.blade.php`
- `resources/views/layouts/sidebar.blade.php`
- `resources/views/admin/fees/dashboard.blade.php`
- `resources/views/admin/fee-structures/create.blade.php`
- `resources/views/admin/fee-structures/edit.blade.php`
- `resources/views/admin/fee-structures/index.blade.php`
- `resources/views/admin/fee-structures/show.blade.php`
- `app/Http/Controllers/Admin/FeeStructureController.php`
- `app/Console/Commands/AdminSidebarAudit.php`

Professional fee-management linked references:

- `resources/views/admin/fee-management/dashboard.blade.php`

Important helper mismatch:

- `resources/views/admin/fee-management/dashboard.blade.php` uses route helpers such as `route('fee-management.structures.create')`.
- Active route names are admin-prefixed, such as `admin.fee-management.structures.create`.
- The view/helper mismatch was documented only because Phase 1H focused on route exposure lockdown, not UI repair.

Installment references:

- Only `InstallmentFeeController` itself references `admin.installment-fees.*` route names and `admin.installment-fees.*` views.
- No active route registration or UI link was found.

## 10. Commands Run

Safe commands only:

```powershell
rg -n "Route::resource\('fee-structures'|fee-structures|fee-management|ProfessionalFeeManagementController|InstallmentFeeController|installment-fees" routes app resources tests -g "*.php" -g "*.blade.php" -g "*.js"
php artisan route:list | Select-String "fee-structures"
php artisan route:list | Select-String "fee-management"
php artisan route:list | Select-String "installment"
rg -n "fee-management\.(fee-heads\.store|structures\.store|assign-student|bulk-assign)|admin\.fee-management\.(fee-heads\.store|structures\.store|assign-student|bulk-assign)" resources app routes tests -g "*.php" -g "*.blade.php" -g "*.js"
php artisan route:list --path=admin/fee-structures
rg -n "Route::.*InstallmentFeeController|installment-fees|admin\.installment-fees" routes resources app tests -g "*.php" -g "*.blade.php" -g "*.js"
php -l routes\web.php
php artisan route:list | Select-String fee
php artisan route:list | Select-String "fee-management"
php artisan route:list | Select-String "installment"
rg -n "fee-structures|fee-management|ProfessionalFeeManagementController|InstallmentFeeController|installment-fees" routes app resources tests -g "*.php" -g "*.blade.php" -g "*.js"
```

No migrations, schema changes, composer setup, or database-changing tests were run.

## 11. Verification Summary

- `php -l routes\web.php` passed.
- `php artisan route:list | Select-String fee` shows canonical fee-structure and fee-collection routes still active.
- `php artisan route:list | Select-String fee` no longer shows professional fee-management POST write routes.
- `php artisan route:list | Select-String "fee-management"` shows only GET/read professional fee-management routes.
- `php artisan route:list | Select-String "installment"` returned no active installment routes.
- Final reference search shows the quarantined professional POST routes only as commented lines in `routes/web.php`.

## 12. Remaining Risks

- `resources/views/admin/fee-management/dashboard.blade.php` appears to use non-admin-prefixed route names while active route names are `admin.fee-management.*`.
- Professional fee-management GET routes may still fail if they return views that do not exist; only `resources/views/admin/fee-management/dashboard.blade.php` was present during directory inspection.
- Canonical fee-structures routes remain active and should receive their own FormRequest and schema-contract hardening later.
- Installment write methods still exist in the controller and could become risky if routes are added without a dedicated schema-safety review.
- The professional fee service still uses `$request->all()` inputs indirectly and non-canonical model contracts; it should remain quarantined for writes.

## 13. Recommended Next Step

Run Phase 1I as a read-only professional fee-management UI repair audit. It should map each active professional GET route to its expected Blade view, fix or document route-helper prefix mismatches, and keep professional write routes quarantined until schema contracts are repaired.
