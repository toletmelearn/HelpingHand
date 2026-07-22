<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Produces the column set UDISE+ student import requires: PEN, name, DOB,
 * gender, mother's name, father's name, Aadhaar name, Aadhaar number,
 * mobile, class, section.
 */
class UdiseStudentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Student::with(['schoolClass', 'section'])->get();
    }

    public function headings(): array
    {
        return [
            'UDISE PEN',
            'Student Name',
            'Date of Birth',
            'Gender',
            "Mother's Name",
            "Father's Name",
            'Name as per Aadhaar',
            'Aadhaar Number',
            'Mobile',
            'Class',
            'Section',
        ];
    }

    public function map($student): array
    {
        return [
            $student->udise_pen,
            $student->name,
            $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '',
            $student->gender,
            $student->mother_name,
            $student->father_name,
            $student->name_as_per_aadhaar,
            $student->aadhaar_number,
            $student->mobile,
            $student->schoolClass?->name ?? $student->class,
            $this->sectionName($student),
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
