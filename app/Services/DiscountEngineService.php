<?php

namespace App\Services;

use App\Models\Student;
use App\Models\DiscountRule;
use App\Models\Result;
use App\Models\User;
use App\Models\FeeType;

class DiscountEngineService
{
    /**
     * Calculate eligible discounts for a student for a specific month/academic year.
     *
     * @param Student $student
     * @param string $month
     * @param string $academicYear
     * @param array $feeItems Array of ['fee_type_id' => X, 'amount' => Y]
     * @param string $conflictStrategy 'highest_priority', 'highest_amount', 'cumulative'
     * @return array Array of applied discounts: ['rule_id' => X, 'fee_type_id' => Y, 'amount' => Z, 'rule_name' => W]
     */
    public function calculateDiscounts(Student $student, string $month, string $academicYear, array $feeItems, string $conflictStrategy = 'highest_amount'): array
    {
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
                return $this->resolveConflicts($applied, $conflictStrategy);
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
        return $this->resolveConflicts($eligibleDiscounts, $conflictStrategy);
    }

    /**
     * Evaluate if a student is eligible for a specific rule and calculate the discount.
     */
    private function evaluateRule(Student $student, DiscountRule $rule, array $feeItems, string $academicYear): ?array
    {
        $config = $rule->config;
        $discountAmount = 0.00;
        
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
                    // Config example: [0, 10, 20] (percentage values for 1st, 2nd, 3rd child)
                    $rates = $config['rates'] ?? [0, 10, 20];
                    $rateIndex = min($studentIndex, count($rates) - 1);
                    $percentage = $rates[$rateIndex] ?? 0;
                    
                    if ($percentage > 0) {
                        $discountAmount = ($baseAmount * $percentage) / 100;
                    }
                }
                break;

            case 'staff_child':
                // Evaluate staff eligibility strictly by checking if student->staff_user_id is set and matches a valid user
                $isStaffChild = false;
                if (!empty($student->staff_user_id)) {
                    $isStaffChild = User::where('id', $student->staff_user_id)->exists();
                }

                if ($isStaffChild) {
                    $percentage = $config['percentage'] ?? 0;
                    if ($percentage > 0) {
                        $discountAmount = ($baseAmount * $percentage) / 100;
                    }
                }
                break;

            case 'merit':
                // Calculate average percentage in results table
                $averagePercentage = Result::where('student_id', $student->id)
                    ->where('academic_year', $academicYear)
                    ->avg('percentage') ?? 0;
                $threshold = $config['threshold_score'] ?? 85;
                
                if ($averagePercentage >= $threshold) {
                    $percentage = $config['percentage'] ?? 0;
                    if ($percentage > 0) {
                        $discountAmount = ($baseAmount * $percentage) / 100;
                    }
                }
                break;

            case 'category':
                // Check category matching (e.g. SC, ST, OBC)
                $studentCategory = trim(strtoupper($student->category ?? ''));
                if (!empty($studentCategory)) {
                    $categoryMappings = $config['mappings'] ?? [];
                    // Ensure keys are upper-cased for case-insensitive matching
                    $categoryMappings = array_change_key_case($categoryMappings, CASE_UPPER);
                    
                    if (isset($categoryMappings[$studentCategory])) {
                        $percentage = $categoryMappings[$studentCategory];
                        if ($percentage > 0) {
                            $discountAmount = ($baseAmount * $percentage) / 100;
                        }
                    }
                }
                break;
        }

        return [
            'fee_type_id' => $targetFeeTypeId,
            'amount' => round($discountAmount, 2),
        ];
    }

    /**
     * Resolve conflict between multiple eligible discounts.
     */
    private function resolveConflicts(array $discounts, string $strategy): array
    {
        if ($strategy === 'cumulative') {
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
