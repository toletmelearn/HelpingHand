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
        // 'name' is the only hard requirement. Every other field is intentionally
        // just a loose string/length check here -- a malformed PAN, a non-numeric
        // period count, or an unparseable date must NOT reject the whole row. Real
        // HR spreadsheets are messy; sanitizeForWrite() below coerces or blanks out
        // whatever doesn't fit its column so the row still imports, and the admin
        // corrects individual fields later from the teacher's edit form.
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
            'aadhar_number' => 'nullable|string|max:255',
            'pan_number' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'educational_qualification' => 'nullable|string|max:500',
            'subject_specialization' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|string|max:255',
            'relative_name' => 'nullable|string|max:255',
            'date_of_joining' => 'nullable|string|max:255',
            'salary' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'permanent_address' => 'nullable|string|max:1000',
            'status' => 'nullable|string|max:255',
            'employment_type' => 'nullable|string|max:255',
            'wing' => 'nullable|string|max:255',
            'teacher_type' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'ifsc_code' => 'nullable|string|max:255',
            'experience_details' => 'nullable|string|max:1000',
            'classes_taught' => 'nullable|string|max:1000',
            'no_of_periods' => 'nullable|string|max:255',
            'class_section' => 'nullable|string|max:255',
            'responsibilities' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Coerce or blank out values that don't fit their target column's real type
     * or constraints, so a single bad cell degrades gracefully instead of
     * throwing a raw SQL error (or, previously, rejecting the whole row).
     */
    private function sanitizeForWrite(array $rowData): array
    {
        if (isset($rowData['email']) && !filter_var($rowData['email'], FILTER_VALIDATE_EMAIL)) {
            $rowData['email'] = null;
        }

        foreach (['date_of_birth', 'date_of_joining'] as $dateField) {
            if (!empty($rowData[$dateField])) {
                try {
                    $rowData[$dateField] = \Carbon\Carbon::parse($rowData[$dateField])->format('Y-m-d');
                } catch (\Throwable $e) {
                    $rowData[$dateField] = null;
                }
            }
        }

        if (isset($rowData['no_of_periods'])) {
            $rowData['no_of_periods'] = preg_match('/\d+/', (string) $rowData['no_of_periods'], $m)
                ? (int) $m[0]
                : null;
        }

        if (isset($rowData['salary'])) {
            $cleaned = preg_replace('/[^0-9.]/', '', (string) $rowData['salary']);
            $rowData['salary'] = ($cleaned !== '' && is_numeric($cleaned)) ? $cleaned : null;
        }

        // 'status' is NOT NULL with a DB default of 'active' -- fall back to it
        // explicitly rather than letting an empty cell send an explicit NULL,
        // which would otherwise violate the column constraint.
        if (empty($rowData['status'])) {
            $rowData['status'] = 'active';
        }

        // Drop remaining nulls/blanks entirely (rather than passing them through
        // as explicit NULL) so any other nullable-with-default column falls back
        // to its DB default instead of an explicit NULL landing on the INSERT.
        return array_filter($rowData, fn ($v) => $v !== null && $v !== '');
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
        $rowData = $this->sanitizeForWrite($rowData);

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
