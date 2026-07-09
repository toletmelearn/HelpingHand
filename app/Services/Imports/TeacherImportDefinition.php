<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\ImportSession;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

class TeacherImportDefinition implements ImportDefinitionInterface
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
        return Teacher::class;
    }

    public function getValidationRules(array $rowData): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|digits:10',
            'emergency_contact' => 'nullable|string|max:20',
            'aadhar_number' => 'nullable|digits:12',
            'pan_number' => 'nullable|regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/',
            'employee_id' => 'required|string|max:50',
            'designation' => 'required|string|max:100',
            'qualification' => 'nullable|string',
            'educational_qualification' => 'nullable|string|max:500',
            'subject_specialization' => 'nullable|string',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'relative_name' => 'nullable|string|max:255',
            'date_of_joining' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:500',
            'permanent_address' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,inactive',
            'employment_type' => 'nullable|in:permanent,contractual',
            'wing' => 'nullable|in:primary,secondary,senior',
            'teacher_type' => 'nullable|in:teaching,non-teaching',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'experience_details' => 'nullable|string|max:500',
            'classes_taught' => 'nullable|string|max:1000',
            'no_of_periods' => 'nullable|integer|min:0|max:60',
            'class_section' => 'nullable|string|max:255',
            'responsibilities' => 'nullable|string|max:1000',
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
            'employee_id' => 100,
            'email' => 90,
            'aadhar_number' => 90,
            'pan_number' => 85,
        ];
    }

    public function getTemplateHeaders(): array
    {
        return [
            'Name', 'Employee ID', 'Aadhar Number', 'PAN Number', 'Date of Birth',
            "Relative Name (Father's / Husband's / Wife's Name)",
            'Address for Correspondence', 'Permanent Address',
            'Email', 'Phone', 'Emergency Contact Number', 'Date of Joining',
            'Qualification', 'Educational Qualification (B.Ed with Subjects / Other)',
            'Classes Taught with Subjects', 'No. of Periods', 'Class & Section', 'Responsibilities',
            'Subject Specialization', 'Gender', 'Salary', 'Status', 'Employment Type',
            'Wing', 'Teacher Type', 'Designation', 'Bank Account Number', 'IFSC Code',
            'Experience Details',
        ];
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        // Detect duplicate
        $dup = $this->conflictResolver->detectDuplicate('teachers', $rowData, $this->getDuplicateWeights());

        if ($dup['status'] === 'duplicate') {
            if ($resolutionStrategy === 'skip') {
                return ['status' => 'skipped', 'id' => $dup['matched_id'], 'message' => "Skipped duplicate record: {$dup['reason']}"];
            }

            // Overwrite strategy
            $teacher = Teacher::find($dup['matched_id']);
            if ($teacher) {
                $teacher->update($rowData);
                return ['status' => 'updated', 'id' => $teacher->id, 'message' => 'Overwrote existing teacher record.'];
            }
        }

        // Note: Teacher::password is not used for authentication — the 'teacher' auth
        // guard is backed by the separate TeacherLogin model, which nothing in this
        // import path provisions. See the teacher-login-provisioning follow-up.
        $teacher = Teacher::create($rowData);

        // Record the created ID in session settings for rollback support
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_teacher_ids'] ?? [];
        $createdIds[] = $teacher->id;
        $settings['created_teacher_ids'] = $createdIds;
        $session->update(['settings' => $settings]);

        return ['status' => 'created', 'id' => $teacher->id, 'message' => 'Successfully created teacher record.'];
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_teacher_ids'] ?? [];

        if (!empty($createdIds)) {
            DB::transaction(function () use ($createdIds) {
                Teacher::whereIn('id', $createdIds)->forceDelete();
            });
        }
    }
}
