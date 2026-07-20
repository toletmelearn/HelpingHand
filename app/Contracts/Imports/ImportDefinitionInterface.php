<?php

namespace App\Contracts\Imports;

use App\Models\ImportSession;

interface ImportDefinitionInterface
{
    public function getTargetModel(): string;
    public function getValidationRules(array $rowData): array;
    public function getCustomFields(): array;
    public function getLookupCacheDefinitions(): array;
    public function getDuplicateWeights(): array;
    public function getTemplateHeaders(): array;
    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array;
    public function executeRollback(ImportSession $session): void;
}
