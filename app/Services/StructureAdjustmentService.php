<?php

namespace App\Services;

use App\Models\Student;
use App\Models\FeeStructure;
use App\Models\StudentFeeAssignment;
use App\Models\StudentFeeLedger;
use App\Models\SchoolClass;
use App\Models\SecurityDeposit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class StructureAdjustmentService
{
    /**
     * Rebuild the running balances for a student sequentially based on transaction dates.
     */
    public static function rebuildRunningBalances(int $studentId): void
    {
        $entries = StudentFeeLedger::where('student_id', $studentId)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $balance = 0.00;
        foreach ($entries as $entry) {
            $balance += floatval($entry->debit) - floatval($entry->credit);
            $entry->running_balance = round($balance, 2);
            $entry->saveQuietly();
        }
    }

    /**
     * Process mid-year student withdrawal, dropping all future-dated unpaid debits.
     */
    public function withdrawStudent(Student $student, string $withdrawalDate): void
    {
        DB::transaction(function () use ($student, $withdrawalDate) {
            // Compute advance-rebate clawback BEFORE pruning future debits --
            // it needs to sum the still-unbilled portion those debits
            // represent, which the delete below would otherwise wipe out.
            $clawbackResults = Schema::hasTable('student_advance_rebates')
                ? \App\Services\AdvanceRebateService::computeClawback($student, $withdrawalDate)
                : [];

            // Find and delete future-dated debits that are unpaid
            // Since credits are matched using FIFO and not linked directly, any debit after withdrawal date can be dropped.
            StudentFeeLedger::where('student_id', $student->id)
                ->where('date', '>', $withdrawalDate)
                ->where('debit', '>', 0)
                ->delete();

            // Rebuild the ledger running balances
            self::rebuildRunningBalances($student->id);

            // Move any held security deposits to refund_pending -- never
            // straight to refunded, per the requirement that deposit
            // returns always require manual accountant/admin approval.
            // Refundable amount is the deposit minus whatever the student
            // still owes for already-due (past-dated) periods -- future
            // dues were just pruned above, so what's left in
            // getOutstandingBalance() at this point is genuinely owed, not
            // billing that will never come due now.
            if (Schema::hasTable('security_deposits')) {
                $remainingOutstanding = max(0.00, \App\Services\LedgerService::getOutstandingBalance($student->id));

                $heldDeposits = SecurityDeposit::where('student_id', $student->id)
                    ->where('status', 'held')
                    ->orderBy('id')
                    ->get();

                // Pass 1: outstanding dues first (unchanged priority) --
                // tracked per-deposit but not yet persisted, since pass 2
                // below may further reduce what's left.
                $depositRemaining = [];
                foreach ($heldDeposits as $deposit) {
                    $available = (float) $deposit->amount;
                    $deducted = min($available, $remainingOutstanding);
                    $remainingOutstanding -= $deducted;
                    $depositRemaining[$deposit->id] = round($available - $deducted, 2);
                }

                // Pass 2: advance-rebate clawback, per rule/snapshot, drawn
                // from whatever's left in the deposit pool after dues. Any
                // uncovered remainder becomes a new outstanding due.
                foreach ($clawbackResults as $result) {
                    $snapshot = $result['snapshot'];
                    $clawbackNeeded = $result['clawback_amount'];

                    foreach ($heldDeposits as $deposit) {
                        if ($clawbackNeeded <= 0) {
                            break;
                        }
                        $available = $depositRemaining[$deposit->id];
                        if ($available <= 0) {
                            continue;
                        }
                        $deducted = min($available, $clawbackNeeded);
                        $depositRemaining[$deposit->id] -= $deducted;
                        $clawbackNeeded -= $deducted;
                    }

                    $shortfall = round($clawbackNeeded, 2);
                    $snapshot->update([
                        'status' => 'clawed_back',
                        'clawback_amount' => $result['clawback_amount'],
                        'clawback_shortfall_amount' => $shortfall,
                        'clawed_back_at' => now(),
                    ]);

                    if ($shortfall > 0.00) {
                        \App\Services\LedgerService::postDebit(
                            $student->id,
                            $withdrawalDate,
                            "Advance Rebate Clawback (unbilled months, Rule: {$snapshot->rule?->name})",
                            'advance_rebate_clawback',
                            $snapshot->id,
                            $shortfall
                        );
                    }
                }

                foreach ($heldDeposits as $deposit) {
                    $deposit->update([
                        'status' => 'refund_pending',
                        'refund_amount' => $depositRemaining[$deposit->id],
                    ]);
                }
            }

            // Log adjustment
            Log::info("Student withdrawn: ID {$student->id}, dropped future dues after {$withdrawalDate}");
        });
    }

    /**
     * Handle mid-year fee structure changes (e.g. Hostel to Day Scholar).
     */
    public function changeFeeStructure(Student $student, FeeStructure $newStructure, string $changeDate): void
    {
        DB::transaction(function () use ($student, $newStructure, $changeDate) {
            // 1. Drop old future-dated debits
            StudentFeeLedger::where('student_id', $student->id)
                ->where('date', '>', $changeDate)
                ->where('debit', '>', 0)
                ->delete();

            // 2. Remove old fee assignment
            StudentFeeAssignment::where('student_id', $student->id)->delete();

            // 3. Create new assignment without triggering model observers
            $assignment = StudentFeeAssignment::withoutEvents(function () use ($student, $newStructure) {
                return StudentFeeAssignment::create([
                    'student_id' => $student->id,
                    'fee_structure_id' => $newStructure->id,
                    'academic_year' => $newStructure->academic_year
                ]);
            });

            // 4. Resolve class ID
            $classVal = SchoolClass::where('name', $newStructure->class_name)->first();
            $classId = $classVal ? $classVal->id : null;

            // 5. Post future-dated debits for the new structure
            foreach ($newStructure->feeStructureItems as $item) {
                // Admission-only / one-time items (Admission Fee, Security
                // Deposit) must not be re-billed just because the student
                // is being moved onto a class that already has an active
                // fee structure -- a structure change is never itself a
                // new admission event.
                if (!\App\Services\FeeItemEligibilityService::isBillable($item, $student, $newStructure->academic_year)) {
                    continue;
                }

                $itemMonths = [];
                if (!empty($item->billing_frequency) && !empty($item->charge_months)) {
                    $itemMonths = is_array($item->charge_months) ? $item->charge_months : json_decode($item->charge_months, true);
                }

                if (empty($itemMonths)) {
                    switch ($newStructure->frequency) {
                        case 'monthly':
                            $itemMonths = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
                            break;
                        case 'quarterly':
                            $itemMonths = ['Q1', 'Q2', 'Q3', 'Q4'];
                            break;
                        case 'yearly':
                            $itemMonths = ['Annual'];
                            break;
                        default:
                            $itemMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            break;
                    }
                }

                foreach ($itemMonths as $month) {
                    $year = date('Y');
                    $monthMap = [
                        'January' => '01-01', 'February' => '02-01', 'March' => '03-01',
                        'April' => '04-01', 'May' => '05-01', 'June' => '06-01',
                        'July' => '07-01', 'August' => '08-01', 'September' => '09-01',
                        'October' => '10-01', 'November' => '11-01', 'December' => '12-01',
                        'Q1' => '04-01', 'Q2' => '07-01', 'Q3' => '10-01', 'Q4' => '01-01',
                        'Annual' => '04-01', 'OneTime' => '04-01'
                    ];
                    $datePart = $monthMap[$month] ?? '04-01';
                    if (in_array($month, ['January', 'February', 'March', 'Q4'])) {
                        $year = date('Y', strtotime('+1 year'));
                    }
                    $dateStr = "$year-$datePart";

                    // Only generate debit if it falls after the changeover date
                    if ($dateStr > $changeDate) {
                        \App\Services\LedgerService::postDebit(
                            $student->id,
                            $dateStr,
                            "Fee Charge: {$item->feeType->name} - $month",
                            'fee_structure_item',
                            $item->id,
                            $item->amount
                        );
                    }
                }
            }

            // 6. Rebuild running balances
            self::rebuildRunningBalances($student->id);

            Log::info("Student structure changed: ID {$student->id} shifted to {$newStructure->name} on {$changeDate}");
        });
    }
}
