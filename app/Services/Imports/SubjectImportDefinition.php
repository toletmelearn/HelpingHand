<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\ImportSession;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class SubjectImportDefinition implements ImportDefinitionInterface
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
        return Subject::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'subject_type' => 'required|in:scholastic,co-scholastic'
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
            'code' => 100,
        ];
    }

    public function getTemplateHeaders(): array
    {
        return ['Name', 'Code', 'Subject Type'];
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        // Detect duplicate
        $dup = $this->conflictResolver->detectDuplicate('subjects', $rowData, $this->getDuplicateWeights());

        if ($dup['status'] === 'duplicate') {
            if ($resolutionStrategy === 'skip') {
                return ['status' => 'skipped', 'id' => $dup['matched_id'], 'message' => "Skipped duplicate record: {$dup['reason']}"];
            }

            // Overwrite strategy
            $subject = Subject::find($dup['matched_id']);
            if ($subject) {
                $subject->update($rowData);
                return ['status' => 'updated', 'id' => $subject->id, 'message' => 'Overwrote existing subject record.'];
            }
        }

        $subject = Subject::create($rowData);

        // Record the created ID in session settings for rollback support
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_subject_ids'] ?? [];
        $createdIds[] = $subject->id;
        $settings['created_subject_ids'] = $createdIds;
        $session->update(['settings' => $settings]);

        return ['status' => 'created', 'id' => $subject->id, 'message' => 'Successfully created subject record.'];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_subject_ids'] ?? [];

        if (!empty($createdIds)) {
            DB::transaction(function () use ($createdIds) {
                Subject::whereIn('id', $createdIds)->forceDelete();
            });
        }
    }
}
