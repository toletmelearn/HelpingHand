<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Students\StudentImportNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportExportController extends Controller
{
    private const IMPORT_PREVIEW_SESSION_KEY = 'student_import_preview';
    private const IMPORT_PREVIEW_TTL_MINUTES = 30;

    /**
     * Export students to CSV.
     */
    public function exportCSV()
    {
        $students = Student::with(['schoolClass', 'section'])->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8.
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID', 'Name', 'Father Name', 'Mother Name', 'Date of Birth',
                'Aadhaar Number', 'Phone', 'Mobile', 'Gender', 'Category', 'Class ID',
                'Class', 'Section ID', 'Section', 'Roll Number', 'Religion', 'Caste',
                'Blood Group', 'Address', 'Admission No'
            ]);

            foreach ($students as $student) {
                fputcsv($file, [
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
                    $this->exportClassName($student),
                    $student->section_id,
                    $this->exportSectionName($student),
                    $student->roll_number,
                    $student->religion ?? '',
                    $student->caste ?? '',
                    $student->blood_group ?? '',
                    $student->address,
                    $student->admission_no ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export students to Excel with formatting.
     */
    public function exportExcel()
    {
        $students = Student::with(['schoolClass', 'section'])->get();

        return Excel::download(new class($students) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $students;

            public function __construct($students)
            {
                $this->students = $students;
            }

            public function collection()
            {
                return $this->students->map(function ($student) {
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
                        $this->className($student),
                        $student->section_id,
                        $this->sectionName($student),
                        $student->roll_number,
                        $student->religion ?? '',
                        $student->caste ?? '',
                        $student->blood_group ?? '',
                        $student->address,
                        $student->admission_no ?? '',
                    ];
                });
            }

            public function headings(): array
            {
                return [
                    'ID', 'Name', 'Father Name', 'Mother Name', 'Date of Birth',
                    'Aadhaar Number', 'Phone', 'Mobile', 'Gender', 'Category', 'Class ID',
                    'Class', 'Section ID', 'Section', 'Roll Number', 'Religion', 'Caste',
                    'Blood Group', 'Address', 'Admission No'
                ];
            }

            private function className(Student $student): ?string
            {
                return $student->schoolClass?->name ?? $student->class;
            }

            private function sectionName(Student $student): ?string
            {
                $section = $student->relationLoaded('section')
                    ? $student->getRelation('section')
                    : null;

                return $section?->name ?? $student->getAttribute('section');
            }
        }, 'students-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Preview a CSV/Excel import without creating or updating students.
     */
    public function previewImportCsv(Request $request, StudentImportNormalizer $normalizer)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls'
        ]);

        $file = $request->file('csv_file');
        $extension = $file->getClientOriginalExtension();

        if (in_array($extension, ['csv', 'txt'])) {
            $data = $this->readCSVFile($file);
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            $data = $this->readExcelFile($file);
        } else {
            return redirect()->back()->with('error', 'Unsupported file format. Please upload CSV or Excel file.');
        }

        $previewRows = [];
        $headers = [];

        foreach ($data as $index => $row) {
            if ($index === 0) {
                $headers = $row;
                continue;
            }

            if (empty(array_filter($row))) {
                continue;
            }

            $previewRows[] = $normalizer->normalizeRow(
                $this->combineImportRowWithHeaders($headers, $row),
                $index + 1
            );
        }

        // Parent linkage and Sibling deduplication detection
        $phoneMap = [];
        foreach ($previewRows as &$pRow) {
            $orig = $pRow['original'];
            // Check headers or direct keys
            $phone = null;
            foreach (['phone', 'Phone', 'mobile', 'Mobile', 6] as $key) {
                if (isset($orig[$key])) {
                    $phone = trim((string)$orig[$key]);
                    break;
                }
            }
            
            $pRow['parent_link_status'] = 'new';
            $pRow['sibling_group'] = false;
            
            if ($phone !== '' && $phone !== null) {
                $phoneMap[$phone][] = &$pRow;
                
                $parentExists = false;
                if (\Illuminate\Support\Facades\Schema::hasTable('parents')) {
                    $parentExists = \App\Models\ParentModel::where('phone', $phone)
                        ->orWhere('mobile', $phone)
                        ->exists();
                }
                if ($parentExists) {
                    $pRow['parent_link_status'] = 'link';
                }
            }
        }
        unset($pRow);

        // Mark siblings within the uploaded file
        foreach ($phoneMap as $phone => &$matchedRows) {
            if (count($matchedRows) > 1) {
                foreach ($matchedRows as &$rowRef) {
                    $rowRef['sibling_group'] = true;
                }
            }
        }
        unset($matchedRows);

        $summary = [
            'total_rows' => count($previewRows),
            'valid_rows' => collect($previewRows)->where('is_valid', true)->count(),
            'rows_with_errors' => collect($previewRows)->filter(fn ($row) => !empty($row['errors']))->count(),
            'rows_with_warnings' => collect($previewRows)->filter(fn ($row) => !empty($row['warnings']))->count(),
        ];

        $applyPreview = null;

        if ($summary['rows_with_errors'] === 0 && $summary['rows_with_warnings'] === 0) {
            $hash = $this->hashImportPreviewRows($previewRows);

            $applyPreview = [
                'preview_id' => Str::uuid()->toString(),
                'created_at' => now()->timestamp,
                'hash' => $hash,
                'rows' => $previewRows,
                'summary' => $summary,
            ];

            session()->put(self::IMPORT_PREVIEW_SESSION_KEY, $applyPreview);
        } else {
            session()->forget(self::IMPORT_PREVIEW_SESSION_KEY);
        }

        return view('admin.students.import-preview', [
            'summary' => $summary,
            'previewRows' => $previewRows,
            'applyPreview' => $applyPreview,
        ]);
    }

    /**
     * Apply a clean CSV/Excel preview stored in session.
     */
    public function applyImportCsv(Request $request, StudentImportNormalizer $normalizer)
    {
        $preview = session()->get(self::IMPORT_PREVIEW_SESSION_KEY);

        if (!$preview) {
            return redirect()->route('students.index')
                ->with('error', 'No clean student import preview is available. Please preview the file again before applying.');
        }

        if ($this->importPreviewExpired($preview)) {
            session()->forget(self::IMPORT_PREVIEW_SESSION_KEY);

            return redirect()->route('students.index')
                ->with('error', 'Student import preview expired. Please preview the file again before applying.');
        }

        if (
            $request->input('preview_id') !== ($preview['preview_id'] ?? null)
            || $request->input('hash') !== ($preview['hash'] ?? null)
            || $this->hashImportPreviewRows($preview['rows'] ?? []) !== ($preview['hash'] ?? null)
        ) {
            return redirect()->route('students.index')
                ->with('error', 'Student import preview verification failed. Please preview the file again before applying.');
        }

        $summary = $preview['summary'] ?? [];
        $rows = $preview['rows'] ?? [];
        $storedRowsHaveErrors = collect($rows)->contains(fn ($row) => !empty($row['errors']));
        $storedRowsHaveWarnings = collect($rows)->contains(fn ($row) => !empty($row['warnings']));

        if (
            ($summary['rows_with_errors'] ?? 1) > 0
            || ($summary['rows_with_warnings'] ?? 1) > 0
            || $storedRowsHaveErrors
            || $storedRowsHaveWarnings
        ) {
            session()->forget(self::IMPORT_PREVIEW_SESSION_KEY);

            return redirect()->route('students.index')
                ->with('error', 'Student import apply is blocked because the preview contains errors or warnings.');
        }

        $duplicateErrors = $this->duplicateErrorsForPreviewRows($preview['rows'] ?? []);
        if (!empty($duplicateErrors)) {
            return redirect()->route('students.index')
                ->with('error', 'Student import apply is blocked because duplicate values were found.')
                ->with('import_errors', $duplicateErrors);
        }

        try {
            $imported = DB::transaction(function () use ($preview) {
                $count = 0;

                foreach ($preview['rows'] as $row) {
                    $student = new Student($this->studentImportPayload($row));
                    $student->school_class_id = $row['normalized']['school_class_id'] ?? null;
                    $student->section_id = $row['normalized']['section_id'] ?? null;
                    $student->save();

                    $count++;
                }

                return $count;
            });
        } catch (\Throwable $e) {
            return redirect()->route('students.index')
                ->with('error', 'Student import failed and no students were imported: ' . $e->getMessage());
        }

        session()->forget(self::IMPORT_PREVIEW_SESSION_KEY);

        return redirect()->route('students.index')
            ->with('success', "Successfully imported {$imported} students from the clean preview.");
    }

    /**
     * Guard the legacy direct import route until safe direct writes are designed.
     */
    public function importCSV(Request $request)
    {
        // Phase 4D: direct import writes are disabled until a safe preview-to-apply flow exists.
        return redirect()->route('students.index')
            ->with('warning', 'Direct CSV import is temporarily disabled. Please use Preview first. Safe import apply flow is not enabled yet.');
    }

    private function readCSVFile($file)
    {
        $data = [];
        $path = $file->getRealPath();

        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }

        return $data;
    }

    private function readExcelFile($file)
    {
        $rows = [];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();

        foreach ($worksheet->getRowIterator() as $row) {
            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    private function parseDate($dateString)
    {
        $formats = [
            'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y',
            'Y/m/d', 'Y-d-m'
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, trim($dateString));
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return '2000-01-01';
    }

    private function exportClassName(Student $student): ?string
    {
        return $student->schoolClass?->name ?? $student->class;
    }

    private function exportSectionName(Student $student): ?string
    {
        $section = $student->relationLoaded('section')
            ? $student->getRelation('section')
            : null;

        return $section?->name ?? $student->getAttribute('section');
    }

    private function combineImportRowWithHeaders(array $headers, array $row): array
    {
        $headerValues = [];

        foreach ($headers as $index => $header) {
            $header = trim(str_replace("\xEF\xBB\xBF", '', (string) $header));

            if ($header !== '') {
                $headerValues[$header] = $row[$index] ?? null;
            }
        }

        return $row + $headerValues;
    }

    private function hashImportPreviewRows(array $rows): string
    {
        return hash('sha256', json_encode($rows));
    }

    private function importPreviewExpired(array $preview): bool
    {
        $createdAt = $preview['created_at'] ?? null;

        return !$createdAt || now()->timestamp - (int) $createdAt > self::IMPORT_PREVIEW_TTL_MINUTES * 60;
    }

    private function duplicateErrorsForPreviewRows(array $rows): array
    {
        $errors = [];

        foreach ($rows as $row) {
            $rowNumber = $row['row_number'] ?? 'unknown';

            foreach ([
                'aadhaar_number' => ['aadhaar_number', 'Aadhaar Number', 5],
                'roll_number' => ['roll_number', 'Roll Number', 11],
            ] as $field => $keys) {
                $value = $this->firstImportValue($row['original'] ?? [], $keys);

                if ($value !== null && Student::where($field, $value)->exists()) {
                    $errors[] = "Row {$rowNumber}: Duplicate {$field} found.";
                }
            }

            $phone = $this->firstImportValue($row['original'] ?? [], ['phone', 'Phone', 6]);
            if ($phone !== null && Student::where('phone', $phone)->orWhere('mobile', $phone)->exists()) {
                $errors[] = "Row {$rowNumber}: Duplicate phone/mobile found.";
            }

            $mobile = $this->firstImportValue($row['original'] ?? [], ['mobile', 'Mobile']);
            if ($mobile !== null && Student::where('mobile', $mobile)->orWhere('phone', $mobile)->exists()) {
                $errors[] = "Row {$rowNumber}: Duplicate phone/mobile found.";
            }
        }

        return array_values(array_unique($errors));
    }

    private function studentImportPayload(array $row): array
    {
        $original = $row['original'] ?? [];
        $normalized = $row['normalized'] ?? [];

        return [
            'name' => $this->firstImportValue($original, ['name', 'Name', 1]) ?? '',
            'father_name' => $this->firstImportValue($original, ['father_name', 'Father Name', 2]) ?? '',
            'mother_name' => $this->firstImportValue($original, ['mother_name', 'Mother Name', 3]) ?? '',
            'date_of_birth' => $this->parseDate($this->firstImportValue($original, ['date_of_birth', 'Date of Birth', 4]) ?? ''),
            'aadhaar_number' => $this->firstImportValue($original, ['aadhaar_number', 'Aadhaar Number', 5]) ?: 'TBD-' . \Illuminate\Support\Str::random(12),
            'phone' => $this->firstImportValue($original, ['phone', 'Phone', 6]) ?? '',
            'mobile' => $this->firstImportValue($original, ['mobile', 'Mobile']),
            'gender' => strtolower($this->firstImportValue($original, ['gender', 'Gender', 7]) ?? 'male'),
            'category' => $this->firstImportValue($original, ['category', 'Category', 8]) ?? 'General',
            'class_id' => $normalized['class_id'] ?? null,
            'class' => $normalized['class'] ?? '',
            'section' => $normalized['section'] ?? null,
            'roll_number' => $this->firstImportValue($original, ['roll_number', 'Roll Number', 11]),
            'religion' => $this->firstImportValue($original, ['religion', 'Religion', 12]) ?? '',
            'caste' => $this->firstImportValue($original, ['caste', 'Caste', 13]) ?? '',
            'blood_group' => $this->firstImportValue($original, ['blood_group', 'Blood Group', 14]) ?? '',
            'address' => $this->firstImportValue($original, ['address', 'Address', 15]) ?? '',
            'admission_no' => $this->firstImportValue($original, ['admission_no', 'Admission No', 'Admission Number']),
        ];
    }

    private function firstImportValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $value = trim((string) $row[$key]);

                return $value === '' ? null : $value;
            }
        }

        return null;
    }
}
