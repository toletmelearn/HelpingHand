<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\ImportSession;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

class ClassImportDefinition implements ImportDefinitionInterface
{
    private ImportLookupCache $lookupCache;
    private ImportConflictResolver $conflictResolver;

    public function __construct(ImportLookupCache $lookupCache, ImportConflictResolver $conflictResolver)
    {
        $this->lookupCache = $lookupCache;
        $this->conflictResolver = $conflictResolver;
    }

    public function getTargetModel(): string
    {
        return SchoolClass::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'name' => 'required|string|max:255',
            'class_order' => 'required|integer',
            'description' => 'nullable|string'
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
        return [
            'name' => 100,
        ];
    }

    public function getTemplateHeaders(): array
    {
        return ['Name', 'Class Order', 'Description'];
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        // Detect duplicate
        $dup = $this->conflictResolver->detectDuplicate('school_classes', $rowData, $this->getDuplicateWeights());

        if ($dup['status'] === 'duplicate') {
            if ($resolutionStrategy === 'skip') {
                return ['status' => 'skipped', 'id' => $dup['matched_id'], 'message' => "Skipped duplicate record: {$dup['reason']}"];
            }

            // Overwrite strategy
            $class = SchoolClass::find($dup['matched_id']);
            if ($class) {
                $class->update($rowData);
                return ['status' => 'updated', 'id' => $class->id, 'message' => 'Overwrote existing class record.'];
            }
        }

        $class = SchoolClass::create($rowData);

        // Record the created ID in session settings for rollback support
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_class_ids'] ?? [];
        $createdIds[] = $class->id;
        $settings['created_class_ids'] = $createdIds;
        $session->update(['settings' => $settings]);

        return ['status' => 'created', 'id' => $class->id, 'message' => 'Successfully created class record.'];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_class_ids'] ?? [];

        if (!empty($createdIds)) {
            DB::transaction(function () use ($createdIds) {
                SchoolClass::whereIn('id', $createdIds)->forceDelete();
            });
        }
    }
}
