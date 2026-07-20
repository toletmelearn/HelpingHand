<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\ImportSession;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

class SectionImportDefinition implements ImportDefinitionInterface
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
        return Section::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'name' => 'required|string|max:255',
            'capacity' => 'required|integer',
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
        return ['Name', 'Capacity', 'Description'];
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        // Detect duplicate
        $dup = $this->conflictResolver->detectDuplicate('sections', $rowData, $this->getDuplicateWeights());

        if ($dup['status'] === 'duplicate') {
            if ($resolutionStrategy === 'skip') {
                return ['status' => 'skipped', 'id' => $dup['matched_id'], 'message' => "Skipped duplicate record: {$dup['reason']}"];
            }

            // Overwrite strategy
            $section = Section::find($dup['matched_id']);
            if ($section) {
                $section->update($rowData);
                return ['status' => 'updated', 'id' => $section->id, 'message' => 'Overwrote existing section record.'];
            }
        }

        $section = Section::create($rowData);

        // Record the created ID in session settings for rollback support
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_section_ids'] ?? [];
        $createdIds[] = $section->id;
        $settings['created_section_ids'] = $createdIds;
        $session->update(['settings' => $settings]);

        return ['status' => 'created', 'id' => $section->id, 'message' => 'Successfully created section record.'];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_section_ids'] ?? [];

        if (!empty($createdIds)) {
            DB::transaction(function () use ($createdIds) {
                Section::whereIn('id', $createdIds)->forceDelete();
            });
        }
    }
}
