<?php

namespace App\Services;

use App\Models\AdvanceRebateRule;
use App\Models\FeeCollection;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentAdvanceRebate;
use App\Models\StudentFeeLedger;
use Illuminate\Support\Facades\Schema;

/**
 * Annual advance-payment rebate: auto-applies when a payment fully covers
 * a session's applicable fee heads before a configured cutoff. Snapshot-
 * immutable once applied (student_advance_rebates), same reasoning as
 * DiscountEngineService/StudentDiscountApplied -- a later rule change must
 * never retroactively alter what was already granted.
 */
class AdvanceRebateService
{
    /**
     * Called from FeeCollection::booted()'s created hook, same place the
     * discount-snapshot logic lives.
     */
    public static function evaluateAndApply(FeeCollection $collection): void
    {
        if (!Schema::hasTable('advance_rebate_rules')) {
            return;
        }

        try {
            $student = $collection->student ?? Student::find($collection->student_id);
            if (!$student) {
                return;
            }

            $feeStructure = $collection->feeStructure;
            $academicYear = $feeStructure->academic_year ?? null;
            if (!$academicYear) {
                return;
            }

            $rules = AdvanceRebateRule::where('is_active', true)->get();

            foreach ($rules as $rule) {
                self::evaluateRuleForStudent($rule, $student, $collection, $academicYear);
            }
        } catch (\Exception $e) {
            // Silence early DB/setup exceptions, same pattern as the
            // discount-snapshot block this sits alongside.
        }
    }

    private static function evaluateRuleForStudent(AdvanceRebateRule $rule, Student $student, FeeCollection $collection, string $academicYear): void
    {
        // Idempotent -- a second full payment in the same session must not
        // double-apply the rebate.
        $alreadyApplied = StudentAdvanceRebate::where('student_id', $student->id)
            ->where('advance_rebate_rule_id', $rule->id)
            ->where('academic_year', $academicYear)
            ->where('status', 'applied')
            ->exists();
        if ($alreadyApplied) {
            return;
        }

        $paymentDate = $collection->payment_date ? $collection->payment_date->copy() : now();
        if (!self::isBeforeCutoff($paymentDate, $academicYear, $rule->cutoff_month_day)) {
            return;
        }

        $feeTypeIds = self::resolveApplicableFeeTypeIds($rule);
        if (empty($feeTypeIds)) {
            return;
        }

        $ledgerRows = StudentFeeLedger::where('student_id', $student->id)
            ->where('academic_year', $academicYear)
            ->where('reference_type', 'fee_structure_item')
            ->whereIn('fee_type_id', $feeTypeIds)
            ->get();

        if ($ledgerRows->isEmpty()) {
            return;
        }

        // "min coverage = full session" -- every applicable debit this
        // session must be fully paid off (unpaid_amount = 0), not just
        // paid up to today.
        if ($rule->min_coverage === 'full_session') {
            $stillUnpaid = $ledgerRows->sum('unpaid_amount');
            if ($stillUnpaid > 0.00) {
                return;
            }
        }

        $totalApplicable = (float) $ledgerRows->sum('debit');
        if ($totalApplicable <= 0) {
            return;
        }

        $rebateAmount = $rule->type === 'percent'
            ? round($totalApplicable * (float) $rule->value / 100, 2)
            : min((float) $rule->value, $totalApplicable);
        $rebateAmount = round($rebateAmount, 2);

        if ($rebateAmount <= 0) {
            return;
        }

        $snapshot = StudentAdvanceRebate::create([
            'student_id' => $student->id,
            'advance_rebate_rule_id' => $rule->id,
            'academic_year' => $academicYear,
            'fee_collection_id' => $collection->id,
            'fee_type_id' => $feeTypeIds[0],
            'rebate_amount' => $rebateAmount,
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        LedgerService::postCredit(
            $student->id,
            $paymentDate->format('Y-m-d'),
            "Advance Rebate Applied (Rule: {$rule->name})",
            'advance_rebate',
            $snapshot->id,
            $rebateAmount
        );
    }

    /**
     * Called from StructureAdjustmentService::withdrawStudent() before the
     * future-debit prune, so applicable amounts can still be summed.
     * Returns the clawback amount computed (0 if nothing applied this
     * session) -- the caller is responsible for deducting it from any
     * remaining deposit balance and posting a debit for the shortfall.
     */
    public static function computeClawback(Student $student, string $withdrawalDate): array
    {
        if (!Schema::hasTable('student_advance_rebates')) {
            return [];
        }

        $applied = StudentAdvanceRebate::where('student_id', $student->id)
            ->where('status', 'applied')
            ->get();

        $results = [];

        foreach ($applied as $snapshot) {
            $rule = $snapshot->rule;
            if (!$rule) {
                continue;
            }

            $feeTypeIds = self::resolveApplicableFeeTypeIds($rule);

            $totalApplicable = (float) StudentFeeLedger::where('student_id', $student->id)
                ->where('academic_year', $snapshot->academic_year)
                ->where('reference_type', 'fee_structure_item')
                ->whereIn('fee_type_id', $feeTypeIds)
                ->sum('debit');

            $prunedApplicable = (float) StudentFeeLedger::where('student_id', $student->id)
                ->where('academic_year', $snapshot->academic_year)
                ->where('reference_type', 'fee_structure_item')
                ->whereIn('fee_type_id', $feeTypeIds)
                ->where('date', '>', $withdrawalDate)
                ->where('debit', '>', 0)
                ->sum('debit');

            if ($totalApplicable <= 0 || $prunedApplicable <= 0) {
                continue;
            }

            $clawbackAmount = round(
                (float) $snapshot->rebate_amount * ($prunedApplicable / $totalApplicable),
                2
            );

            if ($clawbackAmount <= 0) {
                continue;
            }

            $results[] = ['snapshot' => $snapshot, 'clawback_amount' => $clawbackAmount];
        }

        return $results;
    }

    public static function resolveApplicableFeeTypeIds(AdvanceRebateRule $rule): array
    {
        if (!empty($rule->applicable_fee_type_ids)) {
            return $rule->applicable_fee_type_ids;
        }

        $tuition = FeeType::where('name', 'Tuition')->first();
        return $tuition ? [$tuition->id] : [];
    }

    private static function isBeforeCutoff(\Carbon\Carbon $paymentDate, string $academicYear, string $cutoffMonthDay): bool
    {
        if (!preg_match('/^(\d{4})/', $academicYear, $matches)) {
            return true; // Can't resolve the session start year -- don't silently block a rebate over a formatting quirk.
        }

        $startYear = (int) $matches[1];
        [$month, $day] = array_map('intval', explode('-', $cutoffMonthDay));

        // Academic years starting in April: a cutoff month before April
        // (e.g. Jan-Mar) falls in the following calendar year.
        $cutoffYear = $month < 4 ? $startYear + 1 : $startYear;
        $cutoffDate = \Carbon\Carbon::createFromDate($cutoffYear, $month, $day)->endOfDay();

        return $paymentDate->lte($cutoffDate);
    }
}
