<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\BankStatementRow;
use App\Models\ImportSession;

/**
 * Unlike StudentImportDefinition (create/update/skip against master data),
 * this definition's only job is getting raw transaction rows into
 * bank_statement_rows safely -- no dedup, no cross-table resolution.
 * Matching against payment_claims runs afterward as a separate service
 * (PaymentClaimMatchingService), not inside executeWrite().
 */
class BankStatementImportDefinition implements ImportDefinitionInterface
{
    public function getTargetModel(): string
    {
        return BankStatementRow::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'utr' => 'nullable|string|max:32',
            'narration' => 'nullable|string|max:1000',
            'branch' => 'nullable|string|max:100',
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
        return [];
    }

    public function getTemplateHeaders(): array
    {
        return ['Date', 'Amount', 'UTR', 'Narration', 'Branch'];
    }

    /**
     * Duck-typed hook ImportEngine calls (if present) right after reading
     * and header-detecting the raw file, before any mapping/validation.
     *
     * A hand-built template already matches getTemplateHeaders() exactly
     * and passes through this untouched. A real downloaded bank statement
     * (HDFC and most other Indian banks) instead has separate Withdrawal
     * Amt./Deposit Amt. columns, decorative "********" separator lines
     * under the header, and dates as DD/MM/YY text that PHP's default
     * date parser misreads as MM/DD/YY (silently swapping day and month
     * for any day <= 12, not just failing loudly) -- so all three need
     * fixing up before the generic engine ever sees this row shape.
     */
    public function transformRows(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $header = $rows[0];
        $withdrawalIndex = $this->findColumnIndex($header, 'withdrawal');
        $depositIndex = $this->findColumnIndex($header, 'deposit');
        $dateIndex = $this->findColumnIndex($header, 'date');

        // No Withdrawal/Deposit split -- this is either the clean 5-column
        // template or something this hook doesn't need to touch.
        if ($withdrawalIndex === null || $depositIndex === null) {
            return $rows;
        }

        $header[$depositIndex] = 'Amount';
        $transformed = [$header];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // HDFC (and most banks) append a "STATEMENT SUMMARY" footer
            // block after the real transactions -- Opening Balance /
            // Debits / Credits / Dr Count / Cr Count totals sitting in
            // those SAME Withdrawal/Deposit columns. Left unhandled, the
            // numeric-deposit check below can't tell a real transaction
            // from a "Credits: 308284" grand total, and the Opening
            // Balance row's huge number in the Date column gets
            // misinterpreted as an Excel serial date thousands of years
            // in the future by normalizeDateCell() -- not an error, a
            // silently corrupted transaction. Once the marker is seen,
            // nothing after it is a transaction.
            if ($this->isStatementFooterMarker($row)) {
                break;
            }

            if ($this->isDecorativeSeparatorRow($row)) {
                continue;
            }

            $depositValue = $row[$depositIndex] ?? null;
            // A withdrawal-only row (or a row where neither column has a
            // usable value) isn't a fee payment coming in -- this feature
            // exists purely to match incoming payment_claims, so these are
            // silently excluded rather than reported as import errors.
            if ($depositValue === null || $depositValue === '' || !is_numeric($depositValue) || (float) $depositValue <= 0) {
                continue;
            }

            if ($dateIndex !== null) {
                $row[$dateIndex] = $this->normalizeDateCell($row[$dateIndex] ?? null);
            }

            $transformed[] = $row;
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

    private function isStatementFooterMarker(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && str_contains(strtolower(trim((string) $cell)), 'statement summary')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bank statements commonly underline the header row with a line of
     * asterisks (or similar) instead of a ruled border -- every cell on
     * that line is non-empty, so it would otherwise reach validation and
     * fail as "not a valid date" for every real import.
     */
    private function isDecorativeSeparatorRow(array $row): bool
    {
        $hasContent = false;
        foreach ($row as $cell) {
            $cell = trim((string) $cell);
            if ($cell === '') {
                continue;
            }
            $hasContent = true;
            if (!preg_match('/^[*=_-]+$/', $cell)) {
                return false;
            }
        }

        return $hasContent;
    }

    /**
     * HDFC (and most Indian banks) export dates as DD/MM/YY text.  PHP's
     * default date parser reads slash-separated dates as MM/DD/YY, which
     * either rejects them outright (day > 12) or -- worse -- silently
     * swaps day and month for any day <= 12 without ever raising an
     * error. Some exports use a genuine Excel date cell (numeric serial)
     * instead of text; handle both explicitly rather than trusting the
     * generic 'date' validation rule to guess correctly.
     */
    private function normalizeDateCell($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }

        $value = trim((string) $value);
        foreach (['d/m/y', 'd/m/Y', 'd-m-y', 'd-m-Y'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Not a recognized bank-statement date shape -- pass through
        // unchanged and let the normal 'date' validation rule reject it
        // with its usual message rather than silently swallowing it here.
        return $value;
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        $row = BankStatementRow::create([
            'import_session_id' => $session->id,
            'transaction_date' => $rowData['date'],
            'amount' => $rowData['amount'],
            'utr' => $this->extractUtr($rowData),
            'narration' => $rowData['narration'] ?? null,
            'branch' => $rowData['branch'] ?? null,
            'status' => 'unmatched',
        ]);

        $settings = $session->settings ?? [];
        $createdIds = $settings['created_bank_statement_row_ids'] ?? [];
        $createdIds[] = $row->id;
        $settings['created_bank_statement_row_ids'] = $createdIds;
        $session->update(['settings' => $settings]);

        return ['status' => 'created', 'id' => $row->id, 'message' => 'Bank statement row recorded.'];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_bank_statement_row_ids'] ?? [];

        if (!empty($createdIds)) {
            // Only rows still unmatched -- if the matching engine already
            // confirmed some of these against a real payment_claim/
            // FeeCollection, rolling back the import must not silently
            // orphan or corrupt a real payment record. Those need manual
            // review instead of a blind delete.
            BankStatementRow::whereIn('id', $createdIds)
                ->where('status', 'unmatched')
                ->delete();
        }
    }

    /**
     * Not every bank exports a clean UTR column -- when it's blank, try to
     * find a 12-digit UTR embedded in the narration text instead.
     */
    private function extractUtr(array $rowData): ?string
    {
        if (!empty($rowData['utr'])) {
            return trim((string) $rowData['utr']);
        }

        if (!empty($rowData['narration']) && preg_match('/\b(\d{12})\b/', (string) $rowData['narration'], $matches)) {
            return $matches[1];
        }

        return null;
    }
}
