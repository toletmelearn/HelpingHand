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
