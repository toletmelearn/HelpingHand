<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\FeeType;
use App\Models\ImportSession;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Services\LedgerService;
use App\Services\StructureAdjustmentService;

/**
 * Lets a school that starts using this system mid-session (e.g. session
 * started in April, they onboard in July) record fees a student already
 * paid BEFORE that -- in cash, in a register, whatever -- so Outstanding
 * Balance / Defaulter Registry / Reconciliation are accurate from day one
 * instead of showing every pre-onboarding month as unpaid.
 *
 * One row = one already-paid period for one student (e.g. "Tuition Fee,
 * April" or "Annual Charges, Annual"), matched against the exact debit
 * BulkFeeAssignmentService already posted when the fee structure was
 * created/assigned. The same sheet handles monthly, quarterly, and
 * annual/session-wise fee heads side by side -- the admin just types
 * whichever period label applies to that row's fee head.
 */
class FeeOpeningBalanceImportDefinition implements ImportDefinitionInterface
{
    private const PERIOD_ALIASES = [
        'apr' => 'April', 'april' => 'April',
        'may' => 'May',
        'jun' => 'June', 'june' => 'June',
        'jul' => 'July', 'july' => 'July',
        'aug' => 'August', 'august' => 'August',
        'sep' => 'September', 'sept' => 'September', 'september' => 'September',
        'oct' => 'October', 'october' => 'October',
        'nov' => 'November', 'november' => 'November',
        'dec' => 'December', 'december' => 'December',
        'jan' => 'January', 'january' => 'January',
        'feb' => 'February', 'february' => 'February',
        'mar' => 'March', 'march' => 'March',
        'q1' => 'Q1', 'quarter 1' => 'Q1', 'quarter1' => 'Q1',
        'q2' => 'Q2', 'quarter 2' => 'Q2', 'quarter2' => 'Q2',
        'q3' => 'Q3', 'quarter 3' => 'Q3', 'quarter3' => 'Q3',
        'q4' => 'Q4', 'quarter 4' => 'Q4', 'quarter4' => 'Q4',
        'annual' => 'Annual', 'full year' => 'Annual', 'fullyear' => 'Annual',
        'yearly' => 'Annual', 'year' => 'Annual',
    ];

    public function getTargetModel(): string
    {
        return StudentFeeLedger::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'admission_no' => 'required|string|exists:students,admission_no',
            'fee_head' => 'required|string|max:255',
            'period' => 'required|string|max:50',
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'academic_year' => 'nullable|string|max:20',
            'remarks' => 'nullable|string|max:255',
        ];
    }

    public function getCustomFields(): array
    {
        return [];
    }

    public function getLookupCacheDefinitions(): array
    {
        return [];
    }

    public function getDuplicateWeights(): array
    {
        // Multiple rows per student are the normal case (one per already-paid
        // period) -- there is no "duplicate record" concept here to detect.
        return [];
    }

    public function getTemplateHeaders(): array
    {
        return ['Admission No', 'Fee Head', 'Period (Month/Quarter/Annual)', 'Amount Paid', 'Payment Date', 'Academic Year', 'Remarks'];
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        $student = Student::where('admission_no', trim((string) $rowData['admission_no']))->first();
        if (!$student) {
            throw new \Exception("No student found with Admission No '{$rowData['admission_no']}'.");
        }

        $feeHeadName = trim((string) $rowData['fee_head']);
        $feeType = FeeType::whereRaw('LOWER(name) = ?', [strtolower($feeHeadName)])->first();
        if (!$feeType) {
            throw new \Exception("Fee head '{$feeHeadName}' does not match any configured fee type.");
        }

        $period = $this->normalizePeriod((string) $rowData['period']);
        $academicYear = !empty($rowData['academic_year']) ? trim((string) $rowData['academic_year']) : null;

        $debitQuery = StudentFeeLedger::where('student_id', $student->id)
            ->where('fee_type_id', $feeType->id)
            ->where('reference_type', 'fee_structure_item')
            ->where('debit', '>', 0)
            ->where('description', 'like', '%- ' . $period)
            ->orderBy('date', 'asc');

        if ($academicYear) {
            $debitQuery->where('academic_year', $academicYear);
        }

        $debit = $debitQuery->first();

        if (!$debit) {
            throw new \Exception(
                "No '{$feeType->name} - {$period}' charge found for {$student->name} (Admission No {$student->admission_no}). "
                . "Make sure this student's fee structure has already been created and assigned before uploading their opening balance."
            );
        }

        if ((float) $debit->unpaid_amount <= 0.00) {
            return [
                'status' => 'skipped',
                'id' => $debit->id,
                'message' => "{$feeType->name} - {$period} for {$student->name} is already fully paid; nothing to record.",
            ];
        }

        $requestedAmount = (float) $rowData['amount_paid'];
        $amountToApply = min($requestedAmount, (float) $debit->unpaid_amount);
        $paymentDate = !empty($rowData['payment_date']) ? (string) $rowData['payment_date'] : now()->format('Y-m-d');
        $remarks = !empty($rowData['remarks']) ? trim((string) $rowData['remarks']) : null;

        LedgerService::$manualAllocationsToRegister = [
            ['debit' => $debit, 'amount' => $amountToApply],
        ];

        $description = "Opening Balance: {$feeType->name} - {$period} (Pre-System Payment)" . ($remarks ? " -- {$remarks}" : '');

        $creditEntry = LedgerService::postCredit(
            $student->id,
            $paymentDate,
            $description,
            'opening_balance',
            $debit->id,
            $amountToApply
        );

        if (!$creditEntry) {
            throw new \Exception("Failed to record opening balance payment for {$student->name} ({$feeType->name} - {$period}).");
        }

        $settings = $session->settings ?? [];
        $createdIds = $settings['created_ledger_ids'] ?? [];
        $createdIds[] = $creditEntry->id;
        $settings['created_ledger_ids'] = $createdIds;

        $affectedStudentIds = $settings['affected_student_ids'] ?? [];
        if (!in_array($student->id, $affectedStudentIds, true)) {
            $affectedStudentIds[] = $student->id;
        }
        $settings['affected_student_ids'] = $affectedStudentIds;

        $session->update(['settings' => $settings]);

        $message = $amountToApply < $requestedAmount
            ? sprintf(
                'Recorded ₹%.2f (of ₹%.2f entered -- capped to the ₹%.2f still due) for %s - %s.',
                $amountToApply, $requestedAmount, $debit->unpaid_amount + $amountToApply, $feeType->name, $period
            )
            : sprintf('Recorded ₹%.2f opening balance payment for %s - %s.', $amountToApply, $feeType->name, $period);

        return ['status' => 'created', 'id' => $creditEntry->id, 'message' => $message];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_ledger_ids'] ?? [];
        $affectedStudentIds = $settings['affected_student_ids'] ?? [];

        if (empty($createdIds)) {
            return;
        }

        // Scoped to reference_type = 'opening_balance' as a safety net --
        // this must never delete a real fee_collection credit even if IDs
        // somehow drifted.
        StudentFeeLedger::whereIn('id', $createdIds)
            ->where('reference_type', 'opening_balance')
            ->delete();

        foreach ($affectedStudentIds as $studentId) {
            LedgerService::rebuildUnpaidAmounts($studentId);
            StructureAdjustmentService::rebuildRunningBalances($studentId);
        }
    }

    private function normalizePeriod(string $raw): string
    {
        $key = strtolower(trim($raw));
        return self::PERIOD_ALIASES[$key] ?? trim($raw);
    }
}
