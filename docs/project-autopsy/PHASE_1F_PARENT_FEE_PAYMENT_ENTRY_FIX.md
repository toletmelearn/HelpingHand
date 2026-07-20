# Phase 1F Parent Fee Payment Entry Fix

Date: 2026-06-03  
Scope: safely remove the broken parent online payment entry point without adding payment processing

## 1. Files Inspected

- `docs/project-autopsy/PHASE_1E_FEE_DOMAIN_LOCKDOWN.md`
- `routes/web.php`
- `resources/views/parent-dashboard.blade.php`
- `resources/views/parent/dashboard.blade.php`
- `resources/views/parent/payment-history.blade.php`
- `resources/views/parent/fee-structure.blade.php`
- `app/Http/Controllers/Admin/FeeController.php`
- `app/Http/Controllers/Admin/FeeCollectionController.php`
- `app/Http/Controllers/Parent/ParentDashboardController.php`

## 2. Files Changed

- `resources/views/parent-dashboard.blade.php`
- `routes/web.php`
- `docs/project-autopsy/PHASE_1F_PARENT_FEE_PAYMENT_ENTRY_FIX.md`

## 3. How `fees.payment` Was Used

`resources/views/parent-dashboard.blade.php` used `fees.payment` as a button link:

```php
<a href="{{ route('fees.payment') ?? '#' }}" class="btn btn-warning w-100">Pay Fees</a>
```

It was not a form action, AJAX call, or real payment submission.

## 4. Safe Replacement Applied

The broken payment CTA was replaced with the existing parent payment history route:

```php
<a href="{{ route('parent.payment.history') }}" class="btn btn-warning w-100">Fee History</a>
```

Reason:

- `parent.payment.history` already exists.
- It is backed by `ParentDashboardController::paymentHistory()`.
- It displays fee receipts/history.
- It does not process payments or touch Stripe/payment gateway logic.

## 5. Whether `POST fees/payment` Was Quarantined

Yes.

After removing the only real `route('fees.payment')` view reference, the broken route was commented out in `routes/web.php`:

```php
// Quarantined in Phase 1F: points to missing Admin\FeeController@payment and online payment flow is not ready.
// Route::post('fees/payment', [App\Http\Controllers\Admin\FeeController::class, 'payment'])->name('fees.payment');
```

## 6. Remaining References To `fees.payment`

No active `route('fees.payment')` references remain.

The final search still shows:

- the quarantined route comment in `routes/web.php`
- `admin.installment-fees.payment-history` in `InstallmentFeeController`, which is a different route/view name containing the substring `fees.payment`

## 7. Commands Run

```powershell
Get-Content docs\project-autopsy\PHASE_1E_FEE_DOMAIN_LOCKDOWN.md
Get-Content resources\views\parent-dashboard.blade.php
Get-Content app\Http\Controllers\Parent\ParentDashboardController.php
rg -n "fees\.payment|FeeController@payment|parent\.payment|parent\.fee|parent\.receipt|fee-structure|payment-history|receipt" routes\web.php app\Http\Controllers\Parent resources\views\parent* resources\views\parent -g "*.php" -g "*.blade.php"
Get-Content routes\web.php
Get-Content resources\views\parent\payment-history.blade.php
Get-Content resources\views\parent\fee-structure.blade.php
Get-Content resources\views\parent\dashboard.blade.php
rg -n "fees\.payment|FeeController@payment" routes app resources tests -g "*.php" -g "*.blade.php" -g "*.js"
php -l routes\web.php
php -l resources\views\parent-dashboard.blade.php
php artisan route:list | Select-String fee
```

One `rg` command included a Windows wildcard path that produced a path error for `resources\views\parent*`; useful results were still returned, and the final verification search was rerun with safe paths.

## 8. Verification Summary

Syntax:

- `php -l routes\web.php` passed.
- `php -l resources\views\parent-dashboard.blade.php` passed.

Route list:

- `php artisan route:list | Select-String fee` passed.
- `POST fees/payment` no longer appears in the active route list.
- Existing canonical/admin fee routes remain.

Reference scan:

- No active parent dashboard reference to `fees.payment` remains.
- The only `FeeController@payment` occurrence is the quarantined route comment.

## 9. Remaining Risks

- The real parent dashboard view appears to be `resources/views/parent/dashboard.blade.php`, which already linked to payment history and fee structure; this phase fixed the legacy `resources/views/parent-dashboard.blade.php` requested by Phase 1F.
- Online payment is still unavailable by design.
- Stripe/payment gateway work remains separate and untouched.
- Duplicate `admin/admin/fees/*` routes still exist.
- Professional fee write routes remain active and risky from Phase 1D/1E.

## 10. Recommended Next Step

Phase 1G should normalize duplicated `admin/admin/fees/*` routes while preserving route helper compatibility, especially:

- `admin.fees.store`
- `admin.fees.receipt`
- `admin.fees.receipt.pdf`

Do this only after mapping every route helper reference and confirming the canonical replacement paths.

