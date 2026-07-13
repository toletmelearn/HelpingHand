<?php

namespace App\Services\Imports;

use App\Contracts\Imports\ImportDefinitionInterface;
use App\Models\AcademicSession;
use App\Models\ImportSession;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\ParentModel;
use App\Models\StudentFinancialAccount;
use Illuminate\Support\Facades\DB;

class StudentImportDefinition implements ImportDefinitionInterface
{
    private ImportLookupCache $lookupCache;
    private ImportConflictResolver $conflictResolver;
    private ClassNameNormalizer $classNameNormalizer;

    public function __construct(
        ImportLookupCache $lookupCache,
        ImportConflictResolver $conflictResolver,
        ClassNameNormalizer $classNameNormalizer
    ) {
        $this->lookupCache = $lookupCache;
        $this->conflictResolver = $conflictResolver;
        $this->classNameNormalizer = $classNameNormalizer;
    }

    public function getTargetModel(): string
    {
        return Student::class;
    }

    public function getValidationRules(array $rowData): array
    {
        // 'name', 'date_of_birth', 'class', and 'section' are the only hard
        // requirements. Real HR/admission spreadsheets are frequently missing
        // father/mother name, address, gender, or mobile for some rows -- none
        // of that should block the whole row from importing. sanitizeForWrite()
        // fills in a safe placeholder (or lets a DB default apply) for those,
        // and the admin corrects the real value later from the student's edit
        // form, exactly like the individual "Add Student" flow already allows.
        return [
            'name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'class' => 'required|string',
            'section' => 'required|string',
            'aadhar_number' => 'nullable|string|max:20',
            'roll_number' => 'nullable|string|max:20',
            'religion' => 'nullable|string|max:255',
            'caste' => 'nullable|string|max:255',
            'blood_group' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:500',
            'admission_no' => 'nullable|string|max:100',
            'sibling_admission_no' => 'nullable|string|max:100',
            // Determines whether the fee module bills this student an Admission
            // Fee (new) or only their normal Annual/Tuition Fee (old/continuing).
            // Left blank or anything other than "NEW" defaults to "old" -- the
            // same safe default used for every pre-existing student.
            'admission_type' => 'nullable|string|max:20',
        ];
    }

    /**
     * Coerce or fill in fields that don't fit their target column so a single
     * messy cell degrades gracefully instead of blocking the whole row.
     */
    private function sanitizeForWrite(array $rowData, bool $isCreate): array
    {
        if (isset($rowData['roll_number']) && !is_numeric($rowData['roll_number'])) {
            $rowData['roll_number'] = null;
        }

        if ($isCreate) {
            // Brand new student -- no existing data to protect, so blank
            // fields that are NOT NULL at the DB level (father_name,
            // mother_name, address) get a clear placeholder instead of
            // blocking the row. The admin fills in the real value later.
            foreach (['father_name', 'mother_name', 'address'] as $field) {
                if (empty($rowData[$field] ?? null)) {
                    $rowData[$field] = 'Not Specified';
                }
            }
            // gender is NOT NULL with a DB default -- drop a blank value
            // entirely so the default applies instead of an explicit NULL.
            if (empty($rowData['gender'] ?? null)) {
                unset($rowData['gender']);
            }
        } else {
            // Overwriting an existing student -- a blank cell must not erase
            // data already on file, so omit it from the update rather than
            // forcing a placeholder over a real value.
            foreach (['father_name', 'mother_name', 'address', 'gender'] as $field) {
                if (empty($rowData[$field] ?? null)) {
                    unset($rowData[$field]);
                }
            }
        }

        return $rowData;
    }

    public function getCustomFields(): array
    {
        return [];
    }

    public function getLookupCacheDefinitions(): array
    {
        return [
            'school_classes' => function () {
                return SchoolClass::pluck('id', 'name')->toArray();
            },
            'sections' => function () {
                return Section::pluck('id', 'name')->toArray();
            }
        ];
    }

    public function getDuplicateWeights(): array
    {
        return [
            'name_dob' => 80,
            'mobile' => 70,
        ];
    }

    public function getTemplateHeaders(): array
    {
        return ['Name', 'Father Name', 'Mother Name', 'Date of Birth', 'Gender', 'Mobile', 'Phone', 'Class', 'Section', 'Aadhar Number', 'Roll Number', 'Religion', 'Caste', 'Blood Group', 'Address', 'Sibling Admission No', 'Admission No', 'Admission Type (NEW/OLD)'];
    }

    public function executeWrite(array $rowData, ImportSession $session, string $resolutionStrategy): array
    {
        // 1. Resolve Class ID -- trim incidental whitespace and stray
        // punctuation (e.g. a trailing "XI-" typo) before matching.
        $className = trim((string) $rowData['class'], " \t\n\r\0\x0B-.");
        $classId = $this->lookupCache->get('school_classes', $className, function () use ($className) {
            return SchoolClass::where('name', $className)->value('id');
        });

        // Different schools write the same class differently in their own
        // spreadsheets -- "1", "1st", "I", "One", "Grade 1", "Std 1" all mean
        // "Class 1" to a human. Fall back to a normalized guess before giving
        // up, rather than forcing an exact string match.
        if (!$classId) {
            $canonicalClassName = $this->classNameNormalizer->guessCanonicalName($className);
            if ($canonicalClassName !== null) {
                $classId = $this->lookupCache->get('school_classes', $canonicalClassName, function () use ($canonicalClassName) {
                    return SchoolClass::where('name', $canonicalClassName)->value('id');
                });
            }
        }

        if (!$classId) {
            $validClasses = SchoolClass::orderBy('class_order')->pluck('name')->implode(', ');
            throw new \Exception("Class '{$className}' not found. It must exactly match (or be a recognizable variant of, e.g. \"1st\", \"I\", \"One\") one of the configured classes: {$validClasses}.");
        }

        // 2. Resolve Section ID. Unlike a class (a fixed, well-known academic
        // level -- getting it wrong genuinely misplaces a student), a section
        // is just an arbitrary school-chosen label with no universal
        // convention: "A"/"B", "COM", "Science (PCB)", "Humanities" are all
        // equally valid depending on how a given school organizes things.
        // There's no "wrong guess" risk in creating whatever label the sheet
        // actually says, so an unrecognized section is auto-created rather
        // than blocking the row -- no school should be blocked from
        // importing their roster just because their section-naming
        // convention wasn't anticipated in advance.
        $sectionName = trim((string) $rowData['section'], " \t\n\r\0\x0B-.");
        if ($sectionName === '') {
            throw new \Exception("Section is required (row's Section column is blank).");
        }

        $sectionId = $this->lookupCache->get('sections', $sectionName, function () use ($sectionName) {
            $section = Section::firstOrCreate(['name' => $sectionName]);
            return $section->id;
        });

        // 3. Detect duplicate
        $dup = $this->conflictResolver->detectDuplicate('students', $rowData, $this->getDuplicateWeights());

        // 'admission_type' (NEW/OLD) isn't a real students column -- it's an
        // instruction for which academic session (if any) to stamp as the
        // student's admission_session_id, which is what the fee module uses to
        // decide Admission Fee (new) vs. Annual Fee only (old/continuing).
        $admissionTypeRaw = $rowData['admission_type'] ?? null;
        unset($rowData['admission_type']);

        if ($dup['status'] === 'duplicate') {
            if ($resolutionStrategy === 'skip') {
                return ['status' => 'skipped', 'id' => $dup['matched_id'], 'message' => "Skipped duplicate record: {$dup['reason']}"];
            }

            // Overwrite strategy
            $student = Student::find($dup['matched_id']);
            if ($student) {
                $overwriteData = array_merge($this->sanitizeForWrite($rowData, false), [
                    'class_id' => $classId,
                    'school_class_id' => $classId,
                    'section_id' => $sectionId,
                ]);
                // Only touch admission_session_id if this row actually specifies
                // something -- a blank cell on a re-upload must not silently wipe
                // out a value the admin already set.
                if (trim((string) $admissionTypeRaw) !== '') {
                    $overwriteData['admission_session_id'] = $this->resolveAdmissionSessionId($admissionTypeRaw);
                }
                $student->update($overwriteData);
                return ['status' => 'updated', 'id' => $student->id, 'message' => 'Overwrote existing student record.'];
            }
        }

        // 4. Handle sibling check / parent mapping. sibling_admission_no is
        // an explicit admin-provided link (not a heuristic guess), so it
        // also copies family_id directly -- bulk import bypasses the
        // family_link_suggestions confirmation queue since the admin has
        // already effectively confirmed the link by entering it.
        $parentId = null;
        $familyId = null;
        if (isset($rowData['sibling_admission_no']) && !empty($rowData['sibling_admission_no'])) {
            $sibling = Student::where('admission_no', $rowData['sibling_admission_no'])->first();
            if ($sibling) {
                $parentId = $sibling->parent_id;
                $familyId = $sibling->family_id;
            }
        }

        if (!$parentId && isset($rowData['mobile'])) {
            $existingParent = ParentModel::where('phone', $rowData['mobile'])
                ->orWhere('mobile', $rowData['mobile'])
                ->first();
            if ($existingParent) {
                $parentId = $existingParent->id;
            }
        }

        // 5. Create new student record
        $studentData = array_merge($this->sanitizeForWrite($rowData, true), [
            'class_id' => $classId,
            'school_class_id' => $classId,
            'section_id' => $sectionId,
            'admission_session_id' => $this->resolveAdmissionSessionId($admissionTypeRaw),
        ]);

        if ($parentId) {
            $studentData['parent_id'] = $parentId;
        }
        if ($familyId) {
            $studentData['family_id'] = $familyId;
        }

        // Generate auto admission number if missing
        if (!isset($studentData['admission_no']) || empty($studentData['admission_no'])) {
            $latestId = Student::max('id') + 1;
            $studentData['admission_no'] = 'ADM-' . date('Y') . '-' . str_pad($latestId, 4, '0', STR_PAD_LEFT);
        }

        // Ensure roll number is unique within class + section
        if (isset($studentData['roll_number']) && !empty($studentData['roll_number'])) {
            $rollExists = Student::where('class_id', $classId)
                ->where('section_id', $sectionId)
                ->where('roll_number', $studentData['roll_number'])
                ->exists();
            if ($rollExists) {
                // If roll number exists, assign next available roll number or set to null
                $studentData['roll_number'] = (Student::where('class_id', $classId)
                    ->where('section_id', $sectionId)
                    ->max('roll_number') ?? 0) + 1;
            }
        }

        $student = Student::create($studentData);

        // Record the created ID in session settings for rollback support
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_student_ids'] ?? [];
        $createdIds[] = $student->id;
        $settings['created_student_ids'] = $createdIds;
        $session->update(['settings' => $settings]);

        return ['status' => 'created', 'id' => $student->id, 'message' => 'Successfully created student record.'];
    }

    /**
     * Only an explicit "NEW" (any case) marks a student as newly admitted for
     * the current academic session. Blank, "OLD", or anything unrecognized
     * resolves to null -- the same "treat as continuing" default used for
     * every student that predates this field.
     */
    private function resolveAdmissionSessionId(?string $rawAdmissionType): ?int
    {
        $normalized = strtoupper(trim((string) $rawAdmissionType));
        if (!in_array($normalized, ['NEW', 'N'], true)) {
            return null;
        }

        return AcademicSession::current()->value('id');
    }

    public function executeRollback(ImportSession $session): void
    {
        $settings = $session->settings ?? [];
        $createdIds = $settings['created_student_ids'] ?? [];

        if (!empty($createdIds)) {
            DB::transaction(function () use ($createdIds) {
                // 1. Delete associated student financial accounts
                StudentFinancialAccount::whereIn('student_id', $createdIds)->delete();

                // 2. Delete parents that are only linked to these deleted students
                $parentIds = Student::whereIn('id', $createdIds)
                    ->whereNotNull('parent_id')
                    ->pluck('parent_id')
                    ->unique();

                // 3. Delete the students
                Student::whereIn('id', $createdIds)->forceDelete();

                // 4. Clean up orphan parents (parents with no remaining students)
                foreach ($parentIds as $parentId) {
                    $hasOtherStudents = Student::where('parent_id', $parentId)->exists();
                    if (!$hasOtherStudents) {
                        ParentModel::where('id', $parentId)->delete();
                    }
                }
            });
        }
    }
}
