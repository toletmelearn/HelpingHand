<?php

namespace App\Services\Students;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;

class StudentImportNormalizer
{
    /**
     * Normalize one import row without writing any data.
     */
    public function normalizeRow(array $row, ?int $rowNumber = null): array
    {
        $errors = [];
        $warnings = [];

        $normalized = [
            'class_id' => null,
            'school_class_id' => null,
            'class' => null,
            'section_id' => null,
            'section' => null,
        ];

        $this->validateRequiredName($row, $errors);
        $this->addDuplicateWarnings($row, $warnings);

        $schoolClass = $this->resolveSchoolClass($row);
        if ($schoolClass) {
            $normalized['class_id'] = $schoolClass->id;
            $normalized['school_class_id'] = $schoolClass->id;
            $normalized['class'] = $schoolClass->name;
        } else {
            $errors[] = 'Class could not be resolved.';
        }

        $section = $this->resolveSection($row);
        if ($section) {
            $normalized['section_id'] = $section->id;
            $normalized['section'] = (string) $section->id;
        } else {
            $errors[] = 'Section could not be resolved.';
        }

        return [
            'row_number' => $rowNumber,
            'original' => $row,
            'normalized' => $normalized,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'is_valid' => empty($errors),
        ];
    }

    private function resolveSchoolClass(array $row): ?SchoolClass
    {
        $classId = $this->firstFilled($row, ['class_id', 'Class ID']);
        if ($classId !== null) {
            return SchoolClass::find($classId);
        }

        $schoolClassId = $this->firstFilled($row, ['school_class_id', 'School Class ID']);
        if ($schoolClassId !== null) {
            return SchoolClass::find($schoolClassId);
        }

        $className = $this->firstFilled($row, ['class', 'Class', 9]);

        return $className !== null
            ? SchoolClass::where('name', $className)->first()
            : null;
    }

    private function resolveSection(array $row): ?Section
    {
        $sectionId = $this->firstFilled($row, ['section_id', 'Section ID']);
        if ($sectionId !== null) {
            return Section::find($sectionId);
        }

        $sectionValue = $this->firstFilled($row, ['section', 'Section', 10]);
        if ($sectionValue === null) {
            return null;
        }

        if (is_numeric($sectionValue)) {
            return Section::find((int) $sectionValue);
        }

        return Section::where('name', $sectionValue)->first();
    }

    private function validateRequiredName(array $row, array &$errors): void
    {
        $name = $this->firstPresent($row, ['name', 'Name', 1]);

        if ($name !== null && trim((string) $name) === '') {
            $errors[] = 'Student name is required.';
        }
    }

    private function addDuplicateWarnings(array $row, array &$warnings): void
    {
        $aadharNumber = $this->firstFilled($row, ['aadhaar_number', 'Aadhaar Number', 5]);
        if ($aadharNumber !== null && Student::where('aadhaar_number', $aadharNumber)->exists()) {
            $warnings[] = 'Duplicate aadhaar_number found.';
        }

        $rollNumber = $this->firstFilled($row, ['roll_number', 'Roll Number', 11]);
        if ($rollNumber !== null && Student::where('roll_number', $rollNumber)->exists()) {
            $warnings[] = 'Duplicate roll_number found.';
        }

        $name = $this->firstFilled($row, ['name', 'Name', 1]);
        if ($name !== null && Student::where('name', $name)->exists()) {
            $warnings[] = 'Student record with this name already exists.';
        }
    }

    private function firstFilled(array $row, array $keys): mixed
    {
        $value = $this->firstPresent($row, $keys);

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function firstPresent(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }
}
