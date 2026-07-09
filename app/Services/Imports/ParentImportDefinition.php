<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\ImportSession;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ParentImportDefinition implements ImportDefinitionInterface
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
        return ParentModel::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'mobile' => 'nullable|string',
            'admission_number' => 'nullable|string'
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
            'phone' => 100,
            'mobile' => 100,
        ];
    }

    public function getTemplateHeaders(): array
    {
        return ['Name', 'Phone', 'Email', 'Mobile', 'Admission Number'];
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        // Detect duplicate
        $dup = $this->conflictResolver->detectDuplicate('parents', $rowData, $this->getDuplicateWeights());

        $parent = null;
        $status = 'created';
        $message = 'Successfully created parent record.';

        if ($dup['status'] === 'duplicate') {
            if ($resolutionStrategy === 'skip') {
                $parent = ParentModel::find($dup['matched_id']);
                $status = 'skipped';
                $message = "Skipped duplicate parent record.";
            } else {
                // Overwrite strategy
                $parent = ParentModel::find($dup['matched_id']);
                if ($parent) {
                    $parent->update($rowData);
                    $status = 'updated';
                    $message = 'Overwrote existing parent record.';
                }
            }
        }

        if (!$parent) {
            $parentData = $rowData;
            if (!isset($parentData['password']) || empty($parentData['password'])) {
                // Random one-time password, not a fixed guessable default — the parent
                // is forced to set their own on first login (must_reset_password gate).
                $parentData['password'] = Hash::make(\Illuminate\Support\Str::random(12));
                $parentData['must_reset_password'] = true;
            }
            $parent = ParentModel::create($parentData);

            // Record in session
            $settings = $session->settings ?? [];
            $createdIds = $settings['created_parent_ids'] ?? [];
            $createdIds[] = $parent->id;
            $settings['created_parent_ids'] = $createdIds;
            $session->update(['settings' => $settings]);
        }

        // Link parent to student if admission number is provided
        if (isset($rowData['admission_number']) && !empty($rowData['admission_number'])) {
            $student = Student::where('admission_no', $rowData['admission_number'])->first();
            if ($student) {
                // Store previous parent_id to allow rollback if needed
                $settings = $session->settings ?? [];
                $originalParentMappings = $settings['original_parent_mappings'] ?? [];
                if (!isset($originalParentMappings[$student->id])) {
                    $originalParentMappings[$student->id] = $student->parent_id;
                }
                $settings['original_parent_mappings'] = $originalParentMappings;
                $session->update(['settings' => $settings]);

                $student->update(['parent_id' => $parent->id]);
            }
        }

        return ['status' => $status, 'id' => $parent->id, 'message' => $message];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_parent_ids'] ?? [];
        $originalParentMappings = $settings['original_parent_mappings'] ?? [];

        DB::transaction(function () use ($createdIds, $originalParentMappings) {
            // Restore original student-parent mappings
            foreach ($originalParentMappings as $studentId => $originalParentId) {
                Student::where('id', $studentId)->update(['parent_id' => $originalParentId]);
            }

            // Delete created parents
            if (!empty($createdIds)) {
                ParentModel::whereIn('id', $createdIds)->delete();
            }
        });
    }
}
