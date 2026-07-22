<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Student::with(['schoolClass', 'section'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Father Name',
            'Mother Name',
            'Date of Birth',
            'Aadhaar Number',
            'Phone',
            'Mobile',
            'Gender',
            'Category',
            'Class ID',
            'Class',
            'Section ID',
            'Section',
            'Roll Number',
            'Religion',
            'Caste',
            'Blood Group',
            'Address',
            'Admission No',
        ];
    }

    public function map($student): array
    {
        return [
            $student->id,
            $student->name,
            $student->father_name,
            $student->mother_name,
            $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '',
            $student->aadhaar_number,
            $student->phone,
            $student->mobile,
            $student->gender,
            $student->category,
            $student->school_class_id,
            $student->schoolClass?->name ?? $student->class,
            $student->section_id,
            $this->sectionName($student),
            $student->roll_number,
            $student->religion ?? '',
            $student->caste ?? '',
            $student->blood_group ?? '',
            $student->address,
            $student->admission_no ?? '',
        ];
    }

    private function sectionName(Student $student): ?string
    {
        $section = $student->relationLoaded('section')
            ? $student->getRelation('section')
            : null;

        return $section?->name ?? $student->getAttribute('section');
    }
}
