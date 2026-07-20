# Fee Module — Complete Overview (as it actually runs, main branch)

**Prepared:** 2026-07-13. **Scope:** Every part of the system involved in charging, collecting, tracking, discounting, refunding, and reporting on student fees, plus the security-deposit and UPI-reconciliation pieces that sit next to it.

**A note on "main"**: this document describes what is really running on disk when the `main` branch is checked out — not just what git's commit history for `main` contains. That distinction matters and is explained in §0 below.

---

## 0. Read this first — an urgent, repository-wide risk (not specific to the Fee module)

While researching this document, I discovered that **git's commit history for `main` is missing the majority of the working application** — not just Fee-module files. When I checked out a clean copy of `main` from git alone, entire controllers, service classes, and view folders that the Fee module depends on (the payment allocation engine, the receipt-number generator, the discount engine, the defaulter workflow, cashier closing, transport billing, finance reporting, year-end closing, student financial accounts — roughly a dozen major pieces) appeared to not exist at all.

They do exist — on disk, in the real project folder — but **they were never committed to git**. Specifically, as of today:

- **624 files across the whole application have never been committed to any branch, ever.** They exist only on this one machine's disk.
- **156 more files that ARE tracked by git have uncommitted local edits** sitting on top of whatever was last committed.
- **64 tracked files have been deleted from disk** but git still thinks they exist (mostly old documentation `.md` files, not application code).

This document describes the system **as it actually runs** (i.e., including all of the above), because that's what a principal, accountant, or parent actually experiences. But the underlying risk is serious and independent of anything to do with fees specifically: **if this machine's disk were lost, corrupted, or reset, an enormous amount of working code — most of the Fee module included — would vanish with no way to recover it from git.** There is currently no backup of this work other than the one folder it lives in.

**Recommendation, separate from the rest of this document:** before any further feature work, this should be committed to git properly (in reviewable batches, not one giant commit) so the actual working system has a real history and a real backup. I'm flagging this here because you asked me to flag it prominently — happy to help with that as a distinct piece of work whenever you want to tackle it.

Every "fully working" verdict below was verified against the real, complete code (including the uncommitted files). Section 4 calls out the handful of places where I found genuine defects in that real code, as distinct from the git-tracking problem above.

---

## 1. Inventory — what exists right now

### 1.1 Database tables

| Table | Purpose | Status |
|---|---|---|
| `fee_types` | Master list of fee heads (Tuition, Transport, Exam Fee, etc.), each with a category, default billing frequency, and optional default late-fee rule | Fully working |
| `fee_structures` | One row per class + academic year + billing frequency — the fee "template" for a class | Fully working |
| `fee_structure_items` | One row per fee head inside a structure (e.g. "Tuition ₹2000/month" within the Class 5 2026-27 structure), with its own frequency/charge-months/due-day/late-fee rule | Fully working |
| `fee_structure_item_installments` | Custom month-by-month amount override for a line item, when a fee isn't evenly split across months | Fully working |
| `student_fee_assignments` | Links one student to one fee structure for one academic year — creating this row is what triggers real charges to be generated | Fully working |
| `student_fee_ledgers` | **The single source of truth for what a student owes and has paid.** Every charge (debit) and every payment/discount/refund (credit) is one row here, with a running balance | Fully working |
| `fee_collections` | One row per payment receipt (a "collection event") | Fully working |
| `fee_collection_items` | Breakdown of a receipt by fee head (Tuition ₹1500 + Exam Fee ₹500 on one receipt = two rows) | Fully working |
| `discount_rules` | Configurable discount policies: sibling discount, staff-child discount, merit scholarship, category-based concession | Fully working |
| `student_discounts_applied` | Frozen snapshot of a discount once it's actually been billed to a student for a given month — protects against a later rule change silently altering history | Fully working |
| `discount_approvals` | A human-verified discount request queue (an accountant/clerk uploads a verification slip before the discount goes live) | Fully working |
| `late_fee_rules` | Configurable late-payment penalty policies (flat / daily-increasing / slab-based) | Fully working |
| `fee_refunds` | Audit record of money returned to a student (a straight refund, a reversal, or a security-deposit refund) | Fully working |
| `fee_reversal_requests` | A clerk-submitted "please reverse this receipt" request awaiting admin approval | Fully working |
| `transport_fees` (originally `student_transport_dues`, renamed) | Per-student-per-month transport due/payment record | Fully working |
| `vehicles`, `routes`, `drivers`, `route_stops`, `student_transport` | Transport route/vehicle/assignment master data feeding the above | Fully working |
| `transport_adjustments` | Manual correction entries against a transport due | Fully working |
| `payment_allocations` | Records exactly which payment (credit) paid off which specific charge (debit) — the FIFO-matching audit trail | Fully working |
| `cashier_closings` | End-of-day cash-drawer reconciliation, one row per cashier per day | Fully working |
| `defaulter_stages` | Current escalation stage per student who owes money (Reminder → Phone Call → Warning → Principal Notice → Exam Restriction → Result Hold → TC Hold → Cleared) | Fully working |
| `defaulter_logs` | Audit trail of every reminder/call/notice sent to a defaulting family | Fully working |
| `financial_year_closings` | Tracks a year-end rollover batch job (stage → confirm, carrying forward balances/advances/scholarships/refunds to the new session) | Fully working |
| `import_sessions` (+ `import_mapping_profiles`, `import_templates`, `import_errors`) | Generic bulk-import job tracker, used by the bank-statement upload feature among others | Fully working |
| `fee_reminders` | Dunning/reminder dispatch log | **Built but not wired up** — see §1.5 |
| `security_deposits` | Refundable caution-money lifecycle: held → refund_pending → refunded/adjusted | Fully working |
| `payment_claims` | A parent's "I paid via UPI, here's my UTR" claim, pending reconciliation | Fully working |
| `bank_statement_rows` | Imported bank transaction rows, matched against `payment_claims` | Fully working |
| `admin_configurations` | Generic module/key/value settings store; the Fee module uses it for the school's UPI VPA, bank account details, and several toggles | Fully working |
| `fees` | **Legacy.** The original, pre-ledger per-student-per-term fee record | **Legacy-deprecated** — see §1.2 |

**Tables that were removed on purpose** (an earlier "Professional Fee Management" rebuild attempt, fully retired): `fee_heads`, `fee_structure_details`, `fee_receipts`, `fee_discounts`, `student_fee_discounts`. A migration explicitly drops these; no code references them.

### 1.2 Models (`app/Models/`)

| Model | Table | Status |
|---|---|---|
| `FeeType` | `fee_types` | Fully working |
| `FeeStructure` | `fee_structures` | Fully working |
| `FeeStructureItem` | `fee_structure_items` | Fully working |
| `FeeStructureItemInstallment` | `fee_structure_item_installments` | Fully working |
| `StudentFeeAssignment` | `student_fee_assignments` | Fully working |
| `StudentFeeLedger` | `student_fee_ledgers` | Fully working |
| `FeeCollection` | `fee_collections` | Fully working — this model's own internal logic is the real "engine" (see §2) |
| `FeeCollectionItem` | `fee_collection_items` | Fully working |
| `DiscountRule` | `discount_rules` | Fully working |
| `StudentDiscountApplied` | `student_discounts_applied` | Fully working |
| `DiscountApproval` | `discount_approvals` | Fully working |
| `LateFeeRule` | `late_fee_rules` | Fully working |
| `FeeRefund` | `fee_refunds` | Fully working |
| `FeeReversalRequest` | `fee_reversal_requests` | Fully working |
| `TransportFee` and `StudentTransportDue` | both point at the **same** `transport_fees` table | Fully working, but see §4 for the naming duplication |
| `PaymentAllocation` | `payment_allocations` | Fully working |
| `CashierClosing` | `cashier_closings` | Fully working |
| `DefaulterStage` / `DefaulterLog` | `defaulter_stages` / `defaulter_logs` | Fully working |
| `FinancialYearClosing` | `financial_year_closings` | Fully working |
| `ImportSession` | `import_sessions` | Fully working |
| `FeeReminder` | `fee_reminders` | Built but not wired up (see §1.5) |
| `SecurityDeposit` | `security_deposits` | Fully working |
| `PaymentClaim` | `payment_claims` | Fully working |
| `BankStatementRow` | `bank_statement_rows` | Fully working |
| `AdminConfiguration` | `admin_configurations` | Fully working |
| `Fee` | `fees` | **Legacy-deprecated** — kept only because one admin dashboard tile still reads a count from it (`AdminDashboardController`); nothing writes to this table anymore |

### 1.3 Controllers (`app/Http/Controllers/`)

| Controller | Role(s) | Status |
|---|---|---|
| `Admin\FeeCollectionController` | Accountant/Clerk | Fully working — counter collection, receipts, demand/collection registers, reversal requests |
| `Admin\FeeStructureController` | Admin | Fully working — build/edit fee structures, copy a structure to a new class, fee-type master defaults |
| `Admin\FeeTypeController` | — | **Not built on main** — see §1.8 (Planned but not built) |
| `Admin\PaymentClaimMatchingController` | Accountant | Fully working — the UPI matching queue |
| `Admin\PaymentInfoController` | Accountant | Fully working — printable QR + bank details for walk-ins |
| `Admin\SecurityDepositController` | Accountant | Fully working — deposit refund/adjust queue |
| `Admin\CashierClosingController` | Accountant | Fully working — day-close reconciliation |
| `Admin\DefaulterController` | Accountant | Fully working — defaulter dashboard, escalation actions |
| `Admin\FinanceReportController` | Accountant | Fully working — 17 report types (see §2) |
| `Admin\FinancialYearClosingController` | Admin | Fully working — stage/confirm/rollback year-end closing |
| `Admin\StudentFinancialAccountController` | Accountant | Fully working — per-student ledger statement, manual adjustments, PDF/Excel export |
| `Admin\FinanceReconciliationController` | Accountant | Fully working — unresolved/overpayments/refunds/orphans/mismatches reconciliation views, issue-refund action |
| `Admin\TransportFeeController` | Accountant | Fully working — collect a transport due, trigger monthly due generation |
| `Admin\DiscountApprovalController` | Accountant/Clerk | Fully working — verify a manually-requested discount before it activates |
| `Admin\AdminConfigurationController` | Admin | Fully working — general settings screen, includes the Fee module's real UPI VPA / bank-detail settings |
| `Admin\PaymentSettingsController` | Admin | Fully working, **but writes to a different place than what parents see** — see §4 |
| `Admin\InstallmentFeeController` | — | **Built but not wired up** — file exists, has full logic, but has zero registered routes; completely unreachable |
| `Admin\ProfessionalFeeManagementController` | — | **Legacy-deprecated** — does not exist as a file; all its routes are commented out |
| `Parent\ParentDashboardController` | Parent | Fully working — dashboard with fee summary |
| `Parent\ParentPaymentController` | Parent | Fully working — UPI QR pay flow, UTR submission |
| `Admin\AdmissionEnquiryController` (payment part only) | Front-office/Admin | Fully working — pre-admission enquiry payments (a separate, smaller flow, not part of the main fee ledger) |

### 1.4 Services (`app/Services/`)

| Service | Purpose | Status |
|---|---|---|
| `LedgerService` | The core ledger engine — post a debit/credit, get a student's outstanding balance, FIFO-allocate a payment across debts, rebuild running balances | Fully working |
| `PaymentAllocationEngine` | Decides which outstanding charges a payment should be applied against first (mandatory heads, current session, current month, configurable priority list, oldest-due-first), both automatically and for manual override | Fully working |
| `DiscountEngineService` | Evaluates all active discount rules for a student/month and applies the sibling/staff-child/merit/category logic, snapshotting the result once billed | Fully working |
| `FeeReceiptNumberService` | Generates the next sequential receipt number | Fully working |
| `FeePaymentLockService` | Prevents a cashier's double-click or a network retry from creating two receipts for one payment | Fully working |
| `RefundService` | Reverses a receipt (with ledger + security-deposit knock-on effects) or issues a refund for an overpaid balance | Fully working |
| `FinanceCalculationService` | Computes the "total yearly fee / paid / remaining" figures shown on a receipt, honoring each fee head's real billing months and subtracting approved discounts | Fully working |
| `StructureAdjustmentService` | Handles mid-year fee-structure changes, and the "student is leaving" (TC/passed-out) withdrawal logic — dropping future unbilled charges and moving security deposits to refund-pending | Fully working |
| `BulkFeeAssignmentService` | Assigns a fee structure to every student in a class at once, generating the actual ledger charges | Fully working |
| `UpiQrService` | Builds the UPI QR code (deep link + image) used by both the admin walk-in page and the parent payment page | Fully working |
| `PaymentClaimMatchingService` | The four-tier matching engine for UPI claims against bank statement rows (exact / narration / fuzzy / unmatched) | Fully working |
| `FinancialYearClosingService` | Year-end rollover logic; also the guard that blocks new postings against a closed academic session | Fully working |
| `DefaulterService` | Defaulter stage escalation and communication dispatch | Fully working |
| `FinanceAccountService` | Per-student ledger statement/summary data behind `StudentFinancialAccountController` | Fully working |
| `ReminderEngineService` | Sends/retries due-date reminders | **Built but not wired up** — see §1.5 |

### 1.5 Console commands / scheduled jobs (`app/Console/Commands/`)

| Command | Purpose | Status |
|---|---|---|
| `transport:generate-dues {month?} {year?}` (`GenerateMonthlyTransportDues`) | Generates that month's transport charges for every assigned student | Built and working, but **manual-trigger only** — an accountant clicks "Generate Dues" on the Transport Fees page; it does not run on its own |
| `reminders:send-all` (`SendFeeReminders`) | Sends all pending due-date reminders via `ReminderEngineService` | Built, but **completely unreachable** — no button, no menu, nothing calls it except a manual terminal command |
| `reminders:retry-failed` (`RetryFailedReminders`) | Retries reminders that previously failed to send | Built and **is** wired to a button in Operations settings |
| `ledger:migrate-historical {year?}` (`MigrateHistoricalLedger`) | One-time tool to rebuild the ledger from historical assignment/collection records | Built, manual/one-off use only |

**No fee-related job of any kind is on an automatic schedule.** There is no scheduler configured for the Fee module at all — every recurring task (transport dues generation, reminders) requires either an admin clicking a button or someone running a command by hand. This is a real operational gap worth knowing about, not a bug — see §4.

### 1.6 Routes

**Admin web routes** (all under `/admin`, gated by role): fee structures (`admin.fee-structures.*`), counter collection (`admin.fees.*` — index/collect/process/receipt/reverse/reversal-requests), demand & collection registers, cashier closings, defaulter dashboard/registry, year-end closing, finance reports, reconciliation center, security deposits, bank-statement upload, UPI matching queue (`admin.payment-claims.*`), payment info, transport fees, discount approvals, student financial accounts, payment settings, general configuration (`admin.configurations.*`).

**Parent web routes**: `parent.dashboard`, `parent.payment.history`, `parent.fee.structure`, `parent.payments.pay-fees`, `parent.payments.upi-qr` (AJAX), `parent.payments.submit-claim`, `parent.payments.stripe-checkout` (intentionally disabled, see §4), `parent.payments.stripe-success`.

**API routes** (`routes/api.php`): exactly one — `GET /api/v1/students/{id}/fees`, and it returns the **legacy** `Fee` model relation, not the real ledger. There is no other fee/payment API endpoint of any kind.

### 1.7 Blade views

All admin fee views live under `resources/views/admin/fees/` (23 files: index, receipt, receipt PDF, collect-form, demand register + print/PDF, collection register + print/PDF, reversal requests, student dashboard, cashier-closings/, defaulters/, reports/, year-closing/), plus separate top-level folders for `fee-structures/` (5 files), `security-deposits/`, `payment-claims/`, `payment-info/`, `discount-approvals/`, `transport-fees/`, `settings/payment.blade.php`, `configurations/`, `reconciliation/` (5 files), `financial-accounts/` (3 files). Parent-facing: `parent/dashboard.blade.php`, `parent/payments/pay-fees.blade.php`, `parent/payment-history.blade.php`, `parent/fee-structure.blade.php`. All confirmed present and correctly rendered by their controllers.

**Pages that work but have no menu link** (reachable only if you know/type the exact web address): Transport Fees, Student Financial Accounts, Payment Settings, the individual Discount Approvals screen, and three of the four reconciliation-center tabs (only "Overpayments" is directly linked; the others are reachable from tabs inside that page).

### 1.8 Planned but not built

Everything below was designed and discussed with you but **does not exist on `main`** — it lives only on an unmerged development branch (`fee-module-phase-4`) that has not been brought into the main system yet:

- **Family entity + real sibling-discount linking** — a `families` table, admin-confirmed sibling-link suggestions, ranking by age/class, a "youngest child only" discount mode. Today, sibling discounts are still matched by comparing father's name + mobile number as text — no real family record exists.
- **Annual advance-payment rebate** with automatic mid-session TC clawback.
- **Bank cash-deposit claims** (a third payment path alongside counter cash and UPI — "I deposited cash at the bank branch, here's my slip") and the matching tier for it.
- **A dedicated Fee Head master screen** (create/rename/deactivate a fee type through the UI) — today, fee types are seeded by migration; there is no create-a-new-fee-type screen.
- **Editable partial-amount UPI QR** for parents — today the parent QR is always locked to the full outstanding balance (counter/cashier partial payment already works fine; only the parent's UPI flow is locked to full amount).
- **Removing Transport from the academic fee-structure builder** as a formal step — though in practice Transport was never wired into that builder to begin with, so this is largely a formality once merged.

None of the above is broken or half-built on `main` — it simply isn't there yet. Whenever you're ready, this branch is ready to review/merge.

---

## 2. Function-by-function walkthrough, by role

### Admin / Principal

**Fee head management.** There is no dedicated "create a new fee head" screen today (see §1.8) — fee heads (Tuition, Transport, Exam Fee, etc.) are pre-loaded when the system is set up. An admin *can* set each head's default billing frequency, default charge months, and default late-fee rule from the "Fee Type Master" screen (reached from the Fee Structures page). What happens: the admin picks a frequency (monthly/quarterly/yearly/custom), ticks which months it applies to, optionally picks a late-fee rule, and saves — this updates the `fee_types` row so that *future* fee structures built for that head start with sensible defaults, but doesn't retroactively change anything already billed.

**Fee structure setup (class × session × frequency).** From "Fee Structures," the admin clicks "Create," picks a class and academic year, then adds one or more fee heads with an amount, a billing frequency, and (if custom) which months it's charged in. Saving creates one `fee_structures` row plus one `fee_structure_items` row per head. If students are already enrolled in that class, the system immediately (in the same action) generates the actual month-by-month charges into every enrolled student's ledger — this is not a separate step. An admin can also "Copy Structure" to clone an existing class's structure into a new class or session, optionally with a percentage increase, which re-runs the same billing step for the new target.

**Rebate/discount rule creation.** From the discount-rules area, an admin defines a rule: a name, a type (sibling / staff-child / merit / category), a priority, and a JSON configuration block specific to that type (e.g. sibling rates per birth order, merit threshold score and percentage, category-to-percentage mapping). The rule is evaluated live every time a fee is calculated for an eligible student — nothing needs to be "run" to activate it, it just starts applying to the next bill. Once a discount is actually billed to a student for a specific month, it's frozen (snapshotted) — changing the rule afterward never rewrites history.

**Sibling/family setup.** There is no real "family" record today. Sibling discounts are detected automatically by matching a student's father's name and mobile number against other enrolled students — if they match, the system treats them as siblings and ranks them by enrollment order for the sibling-discount rule. There's nothing for an admin to manually "set up" here; it's implicit.

**Payment settings (VPA, bank details, QR).** This is the one place with a genuine trap: there are **two different settings screens** that look like they'd do the same thing, and only one of them actually affects what parents see.
- The one that matters: **Admin Configuration → Fee module** (`admin.configurations.index`) — this is where `upi_vpa`, `bank_account_name`, `bank_account_number`, `bank_ifsc`, and `bank_name` are actually set, and it's what the parent payment page and the admin walk-in QR page both read from.
- The one that doesn't (much): "Payment Settings" (`admin/settings/payment`) — this only has a UPI ID field and a QR image upload, and it saves them into the server's configuration file rather than the same settings store. The counter/cashier UPI QR generator checks this as a fallback only if the Admin Configuration value is blank.

**Practical instruction for whoever manages this:** always use Admin Configuration → Fee module to set the school's UPI VPA and bank details. Don't rely on the separate "Payment Settings" page.

**Approvals the admin owns.** Year-end closing (stage → review → confirm-or-rollback) is admin-gated. Discount verification can be done by either an accountant or a clerk (see below) — it's not admin-exclusive.

### Accountant

**Bank statement upload.** From "Upload Bank Statement," the accountant uploads a CSV/Excel export from the bank, maps its columns (date, amount, UTR, narration) if needed, and confirms the import. Every row lands in `bank_statement_rows` with status "unmatched." Immediately after the import finishes, the matching engine runs automatically once against all unmatched rows and all pending parent claims.

**The UPI matching queue — all four confidence tiers.**
1. **Exact match** (UTR + amount both match a parent's claim exactly) — this is the only tier that **auto-confirms**: the moment the statement import runs, a matching row+claim pair is immediately turned into a real receipt, the parent's ledger is credited, and a notification is sent. The accountant never sees this one in the queue — it's already done.
2. **Narration match** (the bank's transaction description contains the claim's reference code or the student's admission number) — this is only *suggested*. It shows up in the queue and needs one click ("Approve") from the accountant before it becomes a real receipt.
3. **Fuzzy match** (same amount, transaction date within a few days of the claim) — also only suggested, same one-click approval needed.
4. **No match** — the row and the claim both sit in separate "unmatched" lists; the accountant can manually pair a specific row to a specific claim by ID.

For any suggested or manual match, clicking "Approve" runs the exact same confirm logic as an exact match: creates the receipt, credits the ledger, notifies the parent. "Reject" (with a required reason) either un-suggests a wrong pairing (if nothing was confirmed yet) or, if a match had already been confirmed, reverses the real receipt and requires the same reason.

**Cash-deposit claim approval.** Not present on `main` today (§1.8) — this is one of the planned-but-unmerged pieces.

**Security deposit refund approval.** From "Security Deposits," the accountant sees every deposit sitting in "refund_pending" (this status is set automatically when a student leaves — see the System section below). Two actions: "Refund" (only allowed once the family owes nothing else — pays the calculated refundable amount out as cash/bank and logs it) or "Adjust" (for a family that still owes money — applies the deposit as a credit against what they owe instead of paying cash out). Both require the accountant to record how the money moved and are logged.

**Receipt cancellation.** From a printed receipt, an accountant (or admin) can reverse a collection with a required reason — this soft-deletes the receipt and posts offsetting ledger entries so the student's balance goes back to what it was. Clerks don't reverse directly; theirs is a two-step request-then-approve flow (see below).

**Day-close / cashier reports.** "Cashier Closing" — for a given day, the system shows what it *expects* the cashier to have collected, broken down by payment mode (cash/UPI/bank/cheque/online), computed from that day's real receipts. The cashier/accountant then enters what was *actually* counted in each category, along with a discrepancy explanation if the numbers don't match. This is a one-time submission per cashier per day (not a self-service report; someone types the actual counted numbers in).

### Clerk / Cashier

**Counter collection flow (cash/cheque).** The clerk searches for a student, opens their fee-collection screen (which shows the outstanding balance and a breakdown of what's due), enters the amount being paid and the payment mode, and submits. Behind the scenes: the system automatically decides which specific outstanding charges the payment should be applied against first (mandatory heads before optional ones, current session before old arrears, current month before future months, then oldest-due-first) — the clerk doesn't have to manually pick line items unless they choose to override the automatic split. A receipt (`FeeCollection` + one `FeeCollectionItem` per fee head actually paid) is created, the ledger is credited, and a receipt number is generated.

**Partial payment handling.** Fully supported at the counter — a clerk can enter any amount less than the full balance, and the system applies it against the highest-priority charges first, leaving the rest still marked outstanding for next time. There's no special "partial payment" button; it's just whatever amount is typed in.

**Receipt printing/duplicate.** Every receipt has a print view (with an A4/A5 layout toggle), a PDF download, and a "Send WhatsApp" link that opens a pre-filled confirmation message. Reprinting is just re-opening the same receipt by its number — nothing prevents printing it again.

**Defaulter list.** A clerk with the right role can see the Defaulter Registry — a filterable list of every student who owes money, their current escalation stage, and how much they owe, aged into buckets (1–30 / 31–60 / 61–90 / 90+ days). Taking action (a phone call, an SMS, a formal notice) logs it and can move the student to the next stage; there's also a bulk-action option for acting on several students at once.

**Reversal requests (clerk-specific).** Unlike an accountant/admin, a clerk cannot reverse a receipt directly — instead they submit a reversal *request* with a reason, which lands in a queue for an admin/accountant to approve or reject.

### Parent

**Dashboard dues view.** On login, the parent's dashboard shows: total yearly fee assigned, total paid so far, and the current pending (outstanding) amount — all computed live from the ledger, not a stale snapshot. A "Pay Fees" button and a "Fee Structure" button sit right on this page.

**The QR pay flow, end to end.**
1. Parent clicks "Pay Fees," landing on a page showing their outstanding balance as one line item and a "Show UPI QR" button.
2. Clicking it generates a QR code on the spot, sized to their **full** current outstanding balance (there is currently no way to request a smaller custom amount through this screen — see §1.8/§4).
3. Parent scans the QR in their banking app and pays — this step happens entirely outside the system.
4. Back on the page, the parent types in the 12-digit UTR/reference number from their banking app (required, exactly 12 digits) and can optionally attach a screenshot of the payment.
5. Submitting creates a claim with status "claimed" — **nothing about the parent's dues changes yet.** No receipt exists, no credit has been posted.
6. The page now shows an "Under verification" banner: *"You've submitted N UPI payment(s) awaiting confirmation. Your dues will update automatically once matched against the bank statement."*
7. Once an accountant uploads the bank statement (or if the match happens to be exact and instant), the claim gets matched, a real receipt is generated, the ledger is credited, and the parent receives a notification. Their dashboard/pending-amount figures update automatically the next time they load the page, and a proper printable receipt now exists for that payment.

**Partial payment via QR.** Not available today for parents — the QR always represents the full balance. (The counter/cashier partial-payment flow above is unrelated and works fine; this limitation is specific to the parent self-service QR.)

**Payment history.** A separate "Payment History" page lists past receipts.

**A dead end, by design, not by accident:** there used to be a "Pay with Card" button using a payment gateway; that gateway was removed, and clicking it today simply and honestly tells the parent "Online card payment is not available yet — please contact the school office," rather than silently failing. This was a deliberate choice, not an oversight.

### System (automatic)

**Due generation.** Nothing runs automatically on a schedule (see §1.5). New charges are generated at three specific *trigger points*, not on a calendar:
1. **When a fee structure is created or copied** for a class that already has enrolled students — every enrolled student's ledger is charged immediately.
2. **When a new student is admitted** — the moment admission is confirmed, the system finds the active fee structure for that class/session and generates that student's charges, in the same transaction as the admission itself (this was specifically fixed at some point to guarantee a new admission never slips through unbilled).
3. **Transport dues specifically** are generated by an admin manually clicking "Generate Dues" on the Transport Fees page (which runs the `transport:generate-dues` command) — this is the one billing trigger that is neither automatic nor tied to an enrollment event; someone has to remember to click it each month.

**Promotion behavior.** When a student is promoted to a new class, the system looks up the new class's active fee structure for the target session and switches the student onto it — dropping any of the old structure's still-future unbilled charges and starting to bill the new structure from the promotion date forward. Already-billed history from the old class is left untouched.

**TC behavior.** Two events trigger the exact same "student is leaving" logic: publishing a Transfer Certificate, and marking a student "passed out." Both call the same withdrawal routine, which:
- Deletes any future-dated charges that haven't come due yet (so a student who leaves in December doesn't keep accruing January–March charges).
- Leaves every already-due, already-billed charge exactly as it was (whether paid or still owed).
- For any security deposit still marked "held," moves it to "refund_pending" — **never straight to "refunded."** The refundable amount is calculated as the deposit minus whatever the family still genuinely owes at that point; if dues exceed the deposit, the refundable amount floors at zero. A human (accountant) must always take the final action from here.
- (Advance-rebate clawback on TC — planned but not built yet, §1.8.)

**Discount auto-recompute on family changes.** There's no real "family" entity to trigger a recompute from today. Because sibling discounts are calculated live (matched by name+mobile) every time a bill is generated rather than cached, a change in enrollment (a sibling leaving, a new sibling admitted) naturally changes what the discount engine computes the *next* time it runs for the remaining siblings — but nothing proactively "recomputes" already-billed history, and there's no `families` table to hook a recompute onto in the first place (planned, not built).

---

## 3. Lifecycle trace — Rahul's story

*Rahul, Class 7, admitted July 2026, has a sibling in Class 3, pays ₹8,000 of ₹10,000 in August via UPI, family deposits cash at bank in September, takes TC in December.*

**July 2026 — Admission.**
Rahul is admitted. The moment his admission is confirmed:
- A `students` row is created for Rahul.
- The system looks up the active `fee_structures` row for "Class 7, 2026-2027." Say it has one `fee_structure_items` row: Tuition, ₹10,000, billed annually.
- A `student_fee_assignments` row is created linking Rahul to that structure.
- That triggers a single `student_fee_ledgers` **debit** row: Rahul owes ₹10,000 for Tuition, dated at the start of the session, `unpaid_amount = 10,000`.
- Because Rahul's father's name + mobile number now matches an existing student (his sibling in Class 3), the sibling-discount rule becomes eligible for both of them the next time either of their fees is actually billed/calculated — nothing changes on Rahul's own ledger row yet, since discounts apply at bill-calculation time, not at assignment time, and no collection has happened yet.

**August 2026 — Partial UPI payment (₹8,000 of ₹10,000).**
1. Rahul's parent logs in, sees "Outstanding: ₹10,000" (or less, if the sibling discount already reduced it — say it landed at ₹9,000 after a 10% second-child discount was calculated and snapshotted into a `student_discounts_applied` row the first time it was evaluated).
2. Parent clicks "Show UPI QR" — today this always generates a QR for the **full current balance**, not a chosen partial amount. So the QR would show, say, ₹9,000 — there's no way for the parent to request a QR for exactly ₹8,000 through the self-service page.
3. Parent pays ₹8,000 in their banking app anyway (paying less than the QR asked for is between the parent and their bank app — the system has no visibility into this until a UTR is submitted).
4. Parent submits the UTR, typing `amount = 8000` manually in the claim form. A `payment_claims` row is created: `amount = 8000, status = claimed, utr = <12 digits>`. **No ledger change yet.**
5. Rahul's dashboard now shows an "Under verification" banner. His outstanding balance is still shown as the pre-payment figure — the ₹8,000 hasn't been credited.
6. Once the school's bank statement is uploaded (or an exact instant match fires), the matching engine pairs the ₹8,000 bank row to Rahul's claim. If it's an exact UTR+amount match, this happens automatically the moment the statement is imported; otherwise it lands in the accountant's suggested-matches queue for one click.
7. On confirmation: a `fee_collections` row is created (`total_amount = 8000`, `final_amount = 8000`), a `fee_collection_items` row for Tuition (₹8,000), a `student_fee_ledgers` **credit** of ₹8,000 is posted, the claim flips to `status = matched` with `fee_collection_id` set, and Rahul's dashboard now shows Paid: ₹8,000, Remaining: ₹1,000 (assuming the ₹9,000-after-discount figure). A receipt now exists and is downloadable.

**September 2026 — Family deposits cash at the bank branch.**
This is the "bank cash-deposit claim" flow described in §1.8 — **not present on `main` today.** As designed for the unmerged branch: the parent would submit a claim with a deposit date, branch name, amount, and a required photo of the deposit slip (no UTR, since cash deposits don't generate one); the matching engine would look for a bank-statement row with matching amount, branch, and a date within one working day, and would always route it to the accountant's queue for manual approval with the slip visible — never auto-confirm, since there's no UTR to prove it. **On `main` as it stands today, this payment simply can't be self-reported this way** — the family would need to have the school clerk record it as a manual counter payment (cash mode) once the money is confirmed to have arrived, or the accountant would need to match it by hand once it appears on the bank statement, treating it like any other unmatched bank row.

**December 2026 — TC issued.**
1. The admin publishes Rahul's Transfer Certificate. This flips his student status to "tc_issued" and immediately triggers the withdrawal routine.
2. Any of Rahul's ledger debits dated *after* the TC date (e.g., if his fee structure had quarterly installments and a Q4 charge hadn't come due yet) are deleted outright — he's never billed for time he won't attend.
3. Whatever he genuinely still owed as of the TC date (say his final ₹1,000 was never fully paid) stays exactly as it was — that debt doesn't disappear.
4. If Rahul had a security deposit on file (say ₹5,000, `status = held`), it now flips to `status = refund_pending`. The system calculates: `refund_amount = max(0, 5000 - 1000 remaining_owed) = 4,000`. His deposit row now sits in the accountant's Security Deposits queue waiting for a human decision — either "Refund ₹4,000 in cash/bank" (only possible once the ₹1,000 is separately cleared) or "Adjust — apply the deposit against the ₹1,000 owed," which would post a ₹1,000 ledger credit (clearing his debt) and presumably return the remaining ₹4,000, or the accountant could choose to adjust the full amount and refund whatever's left — the exact split is the accountant's call at resolution time, not automatic.
5. (Advance-rebate clawback: not applicable here since Rahul never had an advance-rebate rule applied — that feature isn't merged yet anyway.)

---

## 4. Gaps and risks

Ordered roughly by how much it matters, with exact file locations so you can review each one directly.

1. **The 624/156/64 uncommitted-files situation described in §0.** This is the single biggest risk in the whole system, and it isn't fee-specific — I'm repeating it here because you asked for it flagged in the gaps section too, not just at the top.

2. **Two different "payment settings" stores that look like they do the same thing.** `app/Http/Controllers/Admin/PaymentSettingsController.php` (route `admin/settings/payment`) writes a UPI ID and QR image straight into the server's `.env` configuration file. The screen that actually controls what parents and the walk-in QR page see is `AdminConfigurationController`'s `'fee'` module (`admin.configurations.index`), read by `ParentPaymentController.php:82` and `PaymentInfoController`. `FeeCollectionController::generateUpiQr()` (line 977) checks Admin Configuration first and only falls back to `.env` if that's blank — so in the current state, whichever was set first "wins" until someone notices the mismatch. I'd suggest either removing the `.env`-based Payment Settings screen or clearly relabeling it, since as it stands it's a trap for whoever manages this next.

3. **No automatic scheduling for anything fee-related.** Confirmed via `bootstrap/app.php`, `routes/console.php`, and a full search for `Schedule::` calls — there is no scheduler wired up at all in this application (not just for fees). Transport dues generation and reminders are 100% manual-trigger. If nobody remembers to click "Generate Dues" on the 1st of the month, transport charges simply don't happen that month.

4. **`SendFeeReminders` (`reminders:send-all`) is fully built but completely unreachable.** `app/Console/Commands/SendFeeReminders.php` calls a real, working `ReminderEngineService`, but nothing — no button, no scheduled job, no other command — ever invokes it. It can currently only be run by someone typing the command directly on the server. If due-date reminders are meant to be going out to parents, they currently are not, unless someone is running this by hand regularly.

5. **`InstallmentFeeController` is fully built but has zero routes anywhere.** `app/Http/Controllers/Admin/InstallmentFeeController.php` has complete logic (create installment schedules, process installment payments, generate reports) but `grep`-ing the entire route file for it returns nothing. It is unreachable through any URL. If installment-based billing (as opposed to the frequency/charge-months approach in the main fee-structure builder) was meant to be a feature, it currently isn't accessible to anyone.

6. **`TransportFee` and `StudentTransportDue` are two separate Eloquent model classes pointing at the exact same `transport_fees` table.** `TransportFeeController`/`TransportAdjustmentService` use `TransportFee`; `LedgerService`, `GenerateMonthlyTransportDues`, `MigrateHistoricalLedger`, and `FeeCollection` use `StudentTransportDue`. Both work correctly today since they map to the same table, but this is a real duplication risk — a future change to one model's relations/casts/hooks could silently not apply to the other, and any developer reading only one of the two could miss real behavior defined on the other.

7. **The legacy `Fee` model (`fees` table) is orphaned but not fully removed.** Nothing writes to it anymore (confirmed — no `Fee::create`/`new Fee(` anywhere in live code), but `AdminDashboardController.php:21` still reads a "pending fees" count from it, which will always show a stale or zero figure since the table stops receiving new data. Worth either removing that dashboard tile or re-pointing it at the real ledger.

8. **Parents cannot request a partial amount through the UPI QR self-service flow.** `ParentPaymentController::generateUpiQr()` always sizes the QR to the full live outstanding balance; there's no amount field on the pay-fees page. Counter/cashier partial payment works fine — this is specific to the parent self-service page. (A fix for this already exists on the unmerged `fee-module-phase-4` branch, §1.8.)

9. **Sibling-discount matching is done by comparing free-text father's name + mobile number, not a real linked record.** `DiscountEngineService.php`'s sibling rule (`case 'sibling':`) and `LedgerService::getFamilyLedger()` both independently re-derive "these two students are siblings" by string-matching `father_name` and `mobile` — a typo in either field, or a family that uses different phrasing for the father's name across two admission forms, would silently break the match with no error or warning. A real `families` table with admin-confirmed linking is designed and built on the unmerged branch but not yet merged.

10. **Bank cash-deposit claims have no dedicated path today.** As covered in the Rahul walkthrough — a family that pays cash at a bank branch (rather than counter cash or UPI) has no self-service way to report it; it relies on the accountant noticing and manually matching an unmatched bank-statement row, or a staff member recording it as a manual counter transaction after the fact.

11. **The API only exposes the legacy fee data.** `GET /api/v1/students/{id}/fees` (`routes/api.php`) returns the old `Fee` model relation, not real ledger/collection data. Any external integration or mobile app relying on this endpoint today would be reading stale/incomplete information.

12. **A handful of admin screens exist and work correctly but have no menu link**, reachable only by typing the exact URL: Transport Fees (`admin.transport-fees.*`), Student Financial Accounts (`admin.financial-accounts.*`), Payment Settings (`admin.settings.payment`, though per item 2 above it's arguably better *not* linked prominently), the standalone Discount Approvals screen, and three of the four Reconciliation Center tabs (Unresolved/Refunds/Orphans/Mismatches — only "Overpayments" has a direct sidebar link, the rest are reached from tabs inside that page). None of these are broken; they're just undiscoverable without already knowing the URL.

13. **Assumptions I made while researching this document, for you to confirm or correct:** I treated the *current on-disk state* (including uncommitted files) as "the real system" per your instruction. If any of those 624+ uncommitted files were actually abandoned experiments rather than working code someone intends to keep, some of the "fully working" verdicts above could be describing something you don't actually want kept. I have no way to distinguish "forgotten to commit" from "deliberately abandoned" just by reading the code — that's a judgment call only you can make, ideally as part of the git-cleanup work in item 1.
