<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\AdminConfiguration;
use App\Models\ImportSession;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Services\LedgerService;
use App\Services\StructureAdjustmentService;

/**
 * Unlike FeeOpeningBalanceImportDefinition (one row per already-paid
 * fee-head+period, credited against a specific matching debit), this
 * definition exists for a school's real historical register: one row per
 * STUDENT with a single lump total-paid figure and no fee-head breakdown
 * at all. The paid amount is auto-allocated across whatever the student
 * already owes (same engine every live fee collection already uses),
 * rather than attributed to a specific fee head/period this data simply
 * doesn't distinguish.
 *
 * A real historical spreadsheet is typically wide (fee heads and payment
 * dates as columns, one row per student) rather than this feature's clean
 * 3-column template -- transformRows() below reshapes that automatically,
 * the same pattern BankStatementImportDefinition uses for a real bank
 * statement export.
 */
class FeeOpeningBalanceSummaryImportDefinition implements ImportDefinitionInterface
{
    /**
     * Starting point for the downloadable template -- matches the real
     * historical fee register this feature was built to import (school
     * roster columns, per-fee-head amounts, per-month payment columns)
     * even though transformRows() below only actually reads 3 of these
     * (Enrl No., TOTAL PAID, PENDING AMOUNT) by keyword; the rest are
     * carried through purely so the downloadable template matches what
     * the school's own register already looks like.
     *
     * Admin can add/remove fields from this list without a code change
     * via the "Manage Template Fields" page (UniversalImportController::
     * templateFields()/updateTemplateFields()) -- see getTemplateHeaders().
     */
    private const DEFAULT_TEMPLATE_HEADERS = [
        'S.NO.', 'S. No.', 'Enrl No.', 'Name of Student', 'Gen.', 'New', 'Class', 'Sec.',
        'Adm. fee', 'Security fee', 'Almanic fee', 'Robotics fee', 'Tution fee Qtr',
        'Full Year Tuition Fees', 'Disc.5%', 'Admission fee paid', 'Security fee paid',
        'Feb-26', 'Mar-26', 'Apr-26', 'May-26', 'Jun-26', 'Jul-26', 'Aug-26', 'Sep-26',
        'Oct-26', 'Nov-26', 'Dec-26', 'Jan-27', 'Feb-27', 'Mar-27', 'Apr-27',
        'Total Fee Amount', 'TOTAL PAID', 'Balance', 'PENDING AMOUNT  2025-26',
        'ADVANCE AMOUNT  2025-26', 'FINE RECVD',
    ];

    public function getTargetModel(): string
    {
        return StudentFeeLedger::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'admission_no' => 'required|string|exists:students,admission_no',
            // Both nullable, not required -- a real row can have a prior-
            // year pending amount with zero paid so far (e.g. a student
            // who hasn't paid anything yet this system but still owes
            // last session's carried-forward dues), and a flat "required"
            // on total_paid would silently drop that student's pending
            // debit from the import entirely.
            'total_paid' => 'nullable|numeric|min:0',
            'prior_year_pending' => 'nullable|numeric|min:0',
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
        // One row per student, no "duplicate record" concept to detect.
        return [];
    }

    public function getTemplateHeaders(): array
    {
        $configured = AdminConfiguration::get('imports', 'fee_opening_balance_summary_template_headers');

        return (is_array($configured) && !empty($configured))
            ? $configured
            : self::DEFAULT_TEMPLATE_HEADERS;
    }

    /**
     * Duck-typed hook ImportEngine calls (if present) right after reading
     * and header-detecting the raw file, before any mapping/validation.
     * A hand-built 3-column template already matches getTemplateHeaders()
     * exactly and passes through untouched.
     */
    public function transformRows(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $header = $rows[0];
        $admissionIndex = $this->findColumnIndex($header, 'enrl');
        $paidIndex = $this->findColumnIndex($header, 'total paid');
        $pendingIndex = $this->findColumnIndex($header, 'pending amount');

        // No "Enrl No." + "TOTAL PAID" wide-format columns found -- either
        // the clean template or something this hook doesn't need to touch.
        if ($admissionIndex === null || $paidIndex === null) {
            return $rows;
        }

        $newHeader = ['Admission No', 'Total Paid', 'Prior Year Pending'];
        $transformed = [$newHeader];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $admissionNo = $row[$admissionIndex] ?? null;
            if ($admissionNo === null || trim((string) $admissionNo) === '') {
                continue;
            }

            $totalPaid = $row[$paidIndex] ?? null;
            $pending = $pendingIndex !== null ? ($row[$pendingIndex] ?? null) : null;

            $hasPaid = is_numeric($totalPaid) && (float) $totalPaid > 0;
            $hasPending = is_numeric($pending) && (float) $pending > 0;

            // Nothing to record for this student.
            if (!$hasPaid && !$hasPending) {
                continue;
            }

            $transformed[] = [
                trim((string) $admissionNo),
                $hasPaid ? $totalPaid : null,
                $hasPending ? $pending : null,
            ];
        }

        return $transformed;
    }

    private function findColumnIndex(array $header, string $keyword): ?int
    {
        foreach ($header as $index => $cell) {
            if ($cell !== null && str_contains(strtolower(trim((string) $cell)), $keyword)) {
                return $index;
            }
        }

        return null;
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        $student = Student::where('admission_no', trim((string) $rowData['admission_no']))->first();
        if (!$student) {
            throw new \Exception("No student found with Admission No '{$rowData['admission_no']}'.");
        }

        $pending = !empty($rowData['prior_year_pending']) ? (float) $rowData['prior_year_pending'] : 0.0;
        $totalPaid = !empty($rowData['total_paid']) ? (float) $rowData['total_paid'] : 0.0;

        if ($pending <= 0 && $totalPaid <= 0) {
            return [
                'status' => 'skipped',
                'id' => $student->id,
                'message' => "Nothing to record for {$student->name} (Admission No {$student->admission_no}) -- no paid amount or pending balance.",
            ];
        }

        $today = now()->format('Y-m-d');
        $createdIds = [];

        if ($pending > 0) {
            $debit = LedgerService::postDebit(
                $student->id,
                $today,
                'Opening Balance: Prior Year Pending Dues (2025-26)',
                'opening_balance_prior_year',
                0,
                $pending
            );

            if (!$debit) {
                throw new \Exception("Failed to record prior-year pending dues for {$student->name} (Admission No {$student->admission_no}).");
            }

            $createdIds[] = $debit->id;
        }

        if ($totalPaid > 0) {
            // No manualAllocationsToRegister set -- LedgerService::
            // postCredit() falls through to allocateCreditFIFOInstance(),
            // which already uses PaymentAllocationEngine's prioritized
            // (mandatory-first / current-session-first / oldest-due-first)
            // allocator, the same one every live fee collection uses. This
            // lump sum has no fee-head/period breakdown to target a
            // specific debit with, so the existing default allocator is
            // exactly what's needed here.
            $credit = LedgerService::postCredit(
                $student->id,
                $today,
                'Opening Balance: Pre-System Payment',
                'opening_balance',
                0,
                $totalPaid
            );

            if (!$credit) {
                throw new \Exception("Failed to record opening balance payment for {$student->name} (Admission No {$student->admission_no}).");
            }

            $createdIds[] = $credit->id;
        }

        $settings = $session->settings ?? [];
        $allCreatedIds = $settings['created_ledger_ids'] ?? [];
        $settings['created_ledger_ids'] = array_merge($allCreatedIds, $createdIds);

        $affectedStudentIds = $settings['affected_student_ids'] ?? [];
        if (!in_array($student->id, $affectedStudentIds, true)) {
            $affectedStudentIds[] = $student->id;
        }
        $settings['affected_student_ids'] = $affectedStudentIds;

        $session->update(['settings' => $settings]);

        $messageParts = [];
        if ($pending > 0) {
            $messageParts[] = sprintf('recorded ₹%.2f prior-year pending dues', $pending);
        }
        if ($totalPaid > 0) {
            $messageParts[] = sprintf('recorded ₹%.2f opening balance payment', $totalPaid);
        }

        return [
            'status' => 'created',
            'id' => $student->id,
            'message' => ucfirst(implode(' and ', $messageParts)) . " for {$student->name} (Admission No {$student->admission_no}).",
        ];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_ledger_ids'] ?? [];
        $affectedStudentIds = $settings['affected_student_ids'] ?? [];

        if (empty($createdIds)) {
            return;
        }

        // Scoped to these two reference_types as a safety net -- this
        // must never delete a real fee_collection entry even if IDs
        // somehow drifted.
        StudentFeeLedger::whereIn('id', $createdIds)
            ->whereIn('reference_type', ['opening_balance_prior_year', 'opening_balance'])
            ->delete();

        foreach ($affectedStudentIds as $studentId) {
            LedgerService::rebuildUnpaidAmounts($studentId);
            StructureAdjustmentService::rebuildRunningBalances($studentId);
        }
    }
}
