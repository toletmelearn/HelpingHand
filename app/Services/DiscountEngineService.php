<?php

namespace App\Services;

use App\Models\Student;
use App\Models\DiscountRule;
use App\Models\Family;
use App\Models\Result;
use App\Models\User;
use App\Models\FeeType;
use App\Models\AdminConfiguration;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class DiscountEngineService
{
    /**
     * Calculate eligible discounts for a student for a specific month/academic year.
     *
     * @param Student $student
     * @param string $month
     * @param string $academicYear
     * @param array $feeItems Array of ['fee_type_id' => X, 'amount' => Y]
     * @param string|null $conflictStrategy 'highest_priority', 'highest_amount', 'cumulative', 'capped_cumulative'.
     *   Null resolves from the per-school 'concession_stacking_policy' AdminConfiguration setting
     *   (highest_single_wins -> highest_amount, stack_with_cap -> capped_cumulative), default highest_amount.
     * @return array Array of applied discounts: ['rule_id' => X, 'fee_type_id' => Y, 'amount' => Z, 'rule_name' => W]
     */
    public function calculateDiscounts(Student $student, string $month, string $academicYear, array $feeItems, ?string $conflictStrategy = null): array
    {
        if ($conflictStrategy === null) {
            $conflictStrategy = $this->resolveDefaultStrategy();
        }

        try {
            // Check for pre-existing snapshot discounts to guarantee isolation from future state changes
            $snapshots = \App\Models\StudentDiscountApplied::with('discountRule')
                ->where('student_id', $student->id)
                ->where('month', $month)
                ->where('academic_year', $academicYear)
                ->get();

            if ($snapshots->isNotEmpty()) {
                $applied = [];
                foreach ($snapshots as $snap) {
                    $applied[] = [
                        'rule_id' => $snap->discount_rule_id,
                        'rule_name' => $snap->discountRule ? $snap->discountRule->name : 'Applied Discount',
                        'rule_priority' => $snap->discountRule ? $snap->discountRule->priority : 0,
                        'fee_type_id' => $snap->fee_type_id,
                        'amount' => floatval($snap->amount),
                    ];
                }
                return $this->resolveConflicts($applied, $conflictStrategy, $feeItems);
            }
        } catch (\Exception $e) {
            // Silence early DB or setup exceptions in tests
        }

        $activeRules = DiscountRule::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        $eligibleDiscounts = [];

        foreach ($activeRules as $rule) {
            $discountInfo = $this->evaluateRule($student, $rule, $feeItems, $academicYear);
            if ($discountInfo && $discountInfo['amount'] > 0) {
                $eligibleDiscounts[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'rule_priority' => $rule->priority,
                    'fee_type_id' => $discountInfo['fee_type_id'],
                    'amount' => $discountInfo['amount'],
                ];
            }
        }

        if (empty($eligibleDiscounts)) {
            return [];
        }

        // Apply conflict resolution strategy
        return $this->resolveConflicts($eligibleDiscounts, $conflictStrategy, $feeItems);
    }

    private function resolveDefaultStrategy(): string
    {
        try {
            $policy = AdminConfiguration::get('fee', 'concession_stacking_policy', 'highest_single_wins');
        } catch (\Exception $e) {
            $policy = 'highest_single_wins';
        }

        return $policy === 'stack_with_cap' ? 'capped_cumulative' : 'highest_amount';
    }

    /**
     * Evaluate if a student is eligible for a specific rule and calculate the discount.
     */
    private function evaluateRule(Student $student, DiscountRule $rule, array $feeItems, string $academicYear): ?array
    {
        // A rule outside its configured validity window is treated exactly
        // like an inactive one -- e.g. an early-payment discount that
        // expired last month, or a scholarship scoped to a single academic
        // year, should stop applying without the admin having to remember
        // to toggle is_active manually.
        $today = now()->toDateString();
        if ($rule->valid_from && $today < $rule->valid_from->toDateString()) {
            return null;
        }
        if ($rule->valid_until && $today > $rule->valid_until->toDateString()) {
            return null;
        }

        $config = $rule->config;
        $discountAmount = 0.00;

        // Optional class scoping (e.g. "only new Class 11 admissions get
        // this") -- absent/empty means the rule applies school-wide, same
        // as before this existed. Stores class *names*, resolved to IDs
        // here, the same convention applicable_fee_types already uses for
        // fee heads. Checks both class_id and school_class_id, the same
        // split every other class-matching query in this codebase has to
        // account for.
        if (!empty($config['applicable_classes'])) {
            $studentClassId = $student->school_class_id ?? $student->class_id;
            $allowedClassIds = \App\Models\SchoolClass::whereIn('name', $config['applicable_classes'])->pluck('id')->toArray();
            if (!$studentClassId || !in_array((int) $studentClassId, $allowedClassIds, true)) {
                return null;
            }
        }

        // Support array of applicable fee types, fallback to single fee_type, default to Tuition
        $applicableTypes = $config['applicable_fee_types'] ?? [$config['fee_type'] ?? 'Tuition'];
        if (!is_array($applicableTypes)) {
            $applicableTypes = [$applicableTypes];
        }

        $applicableFeeTypes = FeeType::whereIn('name', $applicableTypes)->get();
        if ($applicableFeeTypes->isEmpty()) {
            return null;
        }

        $targetFeeTypeId = $applicableFeeTypes->first()->id;
        $applicableIds = $applicableFeeTypes->pluck('id')->toArray();

        // Sum matching base fee amounts from fee items
        $baseAmount = 0;
        foreach ($feeItems as $item) {
            if (in_array($item['fee_type_id'], $applicableIds)) {
                $baseAmount += floatval($item['amount']);
            }
        }

        if ($baseAmount <= 0) {
            return null;
        }

        // Every case below used to compute $baseAmount * $percentage / 100
        // inline -- centralizing the unit conversion here is what lets
        // discount_mode (percentage vs a flat rupee amount) apply uniformly
        // to every rule type, old and new, without touching each case's
        // eligibility logic. Callers pass whichever raw number applies in
        // the rule's current mode (a % in percentage mode, a rupee value in
        // flat mode) and this just converts it to a rupee amount, capped at
        // the fee it's discounting.
        $amountFor = function (float $value) use ($rule, $baseAmount): float {
            if ($rule->discount_mode === 'flat_amount') {
                return min($value, $baseAmount);
            }
            return ($baseAmount * $value) / 100;
        };

        // Simple (non-ranked) types share one config['percentage'] field in
        // percentage mode, or the rule's own flat_amount field in flat mode.
        $simpleTypeValue = $rule->discount_mode === 'flat_amount'
            ? (float) ($rule->flat_amount ?? 0)
            : (float) ($config['percentage'] ?? 0);

        switch ($rule->type) {
            case 'sibling':
                // Check siblings (same father name and mobile/phone)
                if (empty($student->father_name) || empty($student->mobile)) {
                    break;
                }

                $siblings = Student::active()
                    ->where('father_name', $student->father_name)
                    ->where('mobile', $student->mobile)
                    ->orderBy('id', 'asc') // Sort by ID to establish birth/admission order
                    ->get();

                if ($siblings->count() <= 1) {
                    break; // No siblings
                }

                // Find index of current student
                $studentIndex = $siblings->pluck('id')->search($student->id);

                if ($studentIndex !== false && $studentIndex >= 0) {
                    // Config example: [0, 10, 20] -- percentages in percentage
                    // mode, rupee amounts in flat_amount mode -- for 1st,
                    // 2nd, 3rd child.
                    $rates = $config['rates'] ?? [0, 10, 20];
                    $rateIndex = min($studentIndex, count($rates) - 1);
                    $rateValue = (float) ($rates[$rateIndex] ?? 0);

                    if ($rateValue > 0) {
                        $discountAmount = $amountFor($rateValue);
                    }
                }
                break;

            case 'family_sibling':
                // Real family_id-based ranking -- unlike 'sibling' above
                // (kept as-is for backward compatibility with any live
                // rule rows), this uses the families table populated only
                // through explicit admin confirmation, not a father_name+
                // mobile string match.
                if (empty($student->family_id)) {
                    break;
                }

                $family = Family::find($student->family_id);
                if (!$family) {
                    break;
                }

                $rankBy = $config['rank_by'] ?? 'age'; // 'age' | 'class'
                $ranked = \App\Services\FamilyDiscountService::rankFamily($family, $rankBy);

                if ($ranked->count() <= 1) {
                    break; // No (currently enrolled) siblings
                }

                $studentIndex = $ranked->pluck('id')->search($student->id);
                if ($studentIndex === false) {
                    break;
                }

                if (!empty($config['youngest_child_only'])) {
                    // Only the last-ranked (youngest/most-junior) sibling
                    // gets the discount; everyone else pays full price.
                    if ($studentIndex === $ranked->count() - 1 && $simpleTypeValue > 0) {
                        $discountAmount = $amountFor($simpleTypeValue);
                    }
                } else {
                    // Config example: [0, 25, 50] -- percentages or rupee
                    // amounts, same convention as 'sibling' above -- for
                    // 1st, 2nd, 3rd child.
                    $rates = $config['rates'] ?? [0, 25, 50];
                    $rateIndex = min($studentIndex, count($rates) - 1);
                    $rateValue = (float) ($rates[$rateIndex] ?? 0);

                    if ($rateValue > 0) {
                        $discountAmount = $amountFor($rateValue);
                    }
                }
                break;

            case 'staff_child':
                // Evaluate staff eligibility strictly by checking if student->staff_user_id is set and matches a valid user
                $isStaffChild = false;
                if (!empty($student->staff_user_id)) {
                    $isStaffChild = User::where('id', $student->staff_user_id)->exists();
                }

                if ($isStaffChild && $simpleTypeValue > 0) {
                    $discountAmount = $amountFor($simpleTypeValue);
                }
                break;

            case 'merit':
                // Calculate average percentage in results table
                $averagePercentage = Result::where('student_id', $student->id)
                    ->where('academic_year', $academicYear)
                    ->avg('percentage') ?? 0;

                if (!empty($config['tiers'])) {
                    // Tiered scholarship: multiple threshold->value bands
                    // (e.g. 95%+ => 50%, 90%+ => 30%, 85%+ => 15%) instead of
                    // one flat threshold -- the common real-world shape for
                    // merit scholarships. Highest qualifying tier wins.
                    $bestValue = 0;
                    foreach ($config['tiers'] as $tier) {
                        $tierThreshold = (float) ($tier['threshold'] ?? 0);
                        $tierValue = (float) ($tier['value'] ?? 0);
                        if ($averagePercentage >= $tierThreshold && $tierValue > $bestValue) {
                            $bestValue = $tierValue;
                        }
                    }
                    if ($bestValue > 0) {
                        $discountAmount = $amountFor($bestValue);
                    }
                } else {
                    // Single-threshold form, kept for backward compatibility
                    // with any rule created before tiers existed.
                    $threshold = $config['threshold_score'] ?? 85;
                    if ($averagePercentage >= $threshold && $simpleTypeValue > 0) {
                        $discountAmount = $amountFor($simpleTypeValue);
                    }
                }
                break;

            case 'loyalty':
                // Long-tenure / returning-student discount, banded by years
                // enrolled -- e.g. 5+ years => 15%, 3+ years => 10%, 1+ year
                // => 5%. Uses created_at as the enrollment-date proxy, the
                // same signal AccountantDashboardController already treats
                // as "when this student joined" (there's no dedicated
                // admission-date column in this schema).
                if (!$student->created_at) {
                    break;
                }
                $yearsEnrolled = $student->created_at->diffInYears(now());
                $tiers = $config['tiers'] ?? [];
                $bestValue = 0;
                foreach ($tiers as $tier) {
                    $tierYears = (float) ($tier['years'] ?? 0);
                    $tierValue = (float) ($tier['value'] ?? 0);
                    if ($yearsEnrolled >= $tierYears && $tierValue > $bestValue) {
                        $bestValue = $tierValue;
                    }
                }
                if ($bestValue > 0) {
                    $discountAmount = $amountFor($bestValue);
                }
                break;

            case 'referral':
                // A student admitted on an existing family's referral --
                // office staff record the referring admission number at
                // admission time; presence alone is the eligibility signal.
                if (!empty($student->referred_by_admission_no) && $simpleTypeValue > 0) {
                    $discountAmount = $amountFor($simpleTypeValue);
                }
                break;

            case 'category':
                // Check category matching (e.g. SC, ST, OBC -- but also a
                // general-purpose way to model any school-specific category:
                // Defence/Armed Forces ward, Single Parent, Alumni, etc. are
                // all just another value in this same free-text field with
                // their own mapped rate, no dedicated rule type needed.
                $studentCategory = trim(strtoupper($student->category ?? ''));
                if (!empty($studentCategory)) {
                    $categoryMappings = $config['mappings'] ?? [];
                    // Ensure keys are upper-cased for case-insensitive matching
                    $categoryMappings = array_change_key_case($categoryMappings, CASE_UPPER);

                    if (isset($categoryMappings[$studentCategory])) {
                        $rateValue = (float) $categoryMappings[$studentCategory];
                        if ($rateValue > 0) {
                            $discountAmount = $amountFor($rateValue);
                        }
                    }
                }
                break;

            case 'rte_quota':
                // RTE (Right to Education) admissions -- previously a manual,
                // one-off billing correction with no reusable flag anywhere;
                // now a real student attribute. Typically 100% off
                // everything except Security Deposit, configured via
                // applicable_fee_types the same way every other type is.
                if ($student->is_rte && $simpleTypeValue > 0) {
                    $discountAmount = $amountFor($simpleTypeValue);
                }
                break;

            case 'special_needs':
                // Fee concession for students with disabilities -- mirrors
                // rte_quota's treatment (a real flag, not a free-text
                // category value), since this is commonly a distinct,
                // legally-relevant policy of its own.
                if ($student->is_special_needs && $simpleTypeValue > 0) {
                    $discountAmount = $amountFor($simpleTypeValue);
                }
                break;

            case 'early_payment':
                // Cutoff-date discount: eligible only if collection is
                // happening on or before a configured date (e.g. "pay by
                // April 30th, get 5% off"). Deliberately date-based rather
                // than "paid the full year in one transaction" -- the engine
                // evaluates a fixed $feeItems list per call with no visibility
                // into the total transaction amount, so a cutoff date is the
                // reliable signal that generalizes to any school's policy
                // (whole-year or single-installment early payers alike).
                if (!empty($config['tiers'])) {
                    // Tiered form: multiple cutoff dates, e.g. pay by March
                    // 31 => 10%, pay by April 30 => 5%. Earliest (soonest)
                    // cutoff a payer still qualifies for wins the highest
                    // value; ties broken by the larger value.
                    $todayDate = now()->toDateString();
                    $bestValue = 0;
                    foreach ($config['tiers'] as $tier) {
                        $tierCutoff = $tier['cutoff_date'] ?? null;
                        $tierValue = (float) ($tier['value'] ?? 0);
                        if ($tierCutoff && $todayDate <= $tierCutoff && $tierValue > $bestValue) {
                            $bestValue = $tierValue;
                        }
                    }
                    if ($bestValue > 0) {
                        $discountAmount = $amountFor($bestValue);
                    }
                } else {
                    $cutoff = $config['cutoff_date'] ?? null;
                    if ($cutoff && now()->toDateString() <= $cutoff && $simpleTypeValue > 0) {
                        $discountAmount = $amountFor($simpleTypeValue);
                    }
                }
                break;

            case 'gender_based':
                // Girl-child (or boy-child) fee concessions are a school-wide
                // policy tied to a real, always-present column, not a
                // free-text category value -- distinct enough from
                // 'category' above to deserve its own type.
                $targetGender = strtolower($config['gender'] ?? 'female');
                if (strtolower($student->gender ?? '') === $targetGender && $simpleTypeValue > 0) {
                    $discountAmount = $amountFor($simpleTypeValue);
                }
                break;

            case 'attendance_based':
                // Good-attendance incentive for the academic year, banded
                // the same way merit/loyalty are. Reuses Attendance::
                // scopePresent(), the same "present" status convention the
                // rest of the app already uses.
                $totalDays = Attendance::where('student_id', $student->id)
                    ->where('session', $academicYear)
                    ->count();
                if ($totalDays === 0) {
                    break;
                }
                $presentDays = Attendance::where('student_id', $student->id)
                    ->where('session', $academicYear)
                    ->present()
                    ->count();
                $attendancePercentage = ($presentDays / $totalDays) * 100;

                if (!empty($config['tiers'])) {
                    $bestValue = 0;
                    foreach ($config['tiers'] as $tier) {
                        $tierThreshold = (float) ($tier['threshold'] ?? 0);
                        $tierValue = (float) ($tier['value'] ?? 0);
                        if ($attendancePercentage >= $tierThreshold && $tierValue > $bestValue) {
                            $bestValue = $tierValue;
                        }
                    }
                    if ($bestValue > 0) {
                        $discountAmount = $amountFor($bestValue);
                    }
                } else {
                    $threshold = $config['threshold_score'] ?? 90;
                    if ($attendancePercentage >= $threshold && $simpleTypeValue > 0) {
                        $discountAmount = $amountFor($simpleTypeValue);
                    }
                }
                break;
        }

        if ($rule->max_cap_amount) {
            $discountAmount = min($discountAmount, (float) $rule->max_cap_amount);
        }

        return [
            'fee_type_id' => $targetFeeTypeId,
            'amount' => round($discountAmount, 2),
        ];
    }

    /**
     * Resolve conflict between multiple eligible discounts.
     *
     * @param array $feeItems Only needed by 'capped_cumulative', to look up
     *   each fee_type_id's base amount for computing the cap.
     */
    private function resolveConflicts(array $discounts, string $strategy, array $feeItems = []): array
    {
        if ($strategy === 'cumulative' || $strategy === 'capped_cumulative') {
            // Group by fee_type_id and sum amounts
            $grouped = [];
            foreach ($discounts as $d) {
                $feeTypeId = $d['fee_type_id'];
                if (!isset($grouped[$feeTypeId])) {
                    $grouped[$feeTypeId] = [
                        'rule_id' => $d['rule_id'],
                        'rule_name' => 'Combined Discount',
                        'fee_type_id' => $feeTypeId,
                        'amount' => 0.00,
                    ];
                }
                $grouped[$feeTypeId]['amount'] += $d['amount'];
            }

            if ($strategy === 'capped_cumulative') {
                try {
                    $capPercent = (float) AdminConfiguration::get('fee', 'concession_stacking_cap_percent', 100);
                } catch (\Exception $e) {
                    $capPercent = 100;
                }

                foreach ($grouped as $feeTypeId => &$group) {
                    $baseAmount = 0.00;
                    foreach ($feeItems as $item) {
                        if ((int) $item['fee_type_id'] === (int) $feeTypeId) {
                            $baseAmount += floatval($item['amount']);
                        }
                    }
                    $capAmount = $baseAmount * $capPercent / 100;
                    $group['amount'] = round(min($group['amount'], $capAmount), 2);
                }
                unset($group);
            }

            return array_values($grouped);
        }

        if ($strategy === 'highest_priority') {
            // Sort by rule_priority desc, then by amount desc
            usort($discounts, function ($a, $b) {
                if ($a['rule_priority'] == $b['rule_priority']) {
                    return $b['amount'] <=> $a['amount'];
                }
                return $b['rule_priority'] <=> $a['rule_priority'];
            });
            // Return only the highest priority discount
            return [$discounts[0]];
        }

        // Default: 'highest_amount'
        usort($discounts, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        return [$discounts[0]];
    }
}
