<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;


class StudentController extends Controller
{
    /**
     * Display a listing of all students.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Student::class);
        
        $query = Student::query();
        
        // Filter by class if provided
        if ($request->filled('class')) {
            $query->where('class', $request->class);
        }
        
        // Filter by section if provided
        if ($request->filled('section')) {
            $query->where('section', $request->section);
        }
        
        $students = $query->get();
        return view('students.index', ['students' => $students]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        $this->authorize('create', Student::class);
        return view('students.create');
    }

    /**
     * Store a newly created student in database.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Student::class);
        // Validate the request data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today', // Ensure birth date is not in future
            'aadhar_number' => 'required|digits:12|unique:students',
            'address' => 'required|string',
            'phone' => 'required|digits:10',
            'gender' => 'required|in:male,female,other',
            'category' => 'required|in:General,OBC,SC,ST,Other',
            'class' => 'required|string|max:50',
            'section' => 'nullable|string|max:10',
            'roll_number' => 'nullable|integer|unique:students',
            'religion' => 'nullable|string|max:50',
            'caste' => 'nullable|string|max:50',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown'
        ]);

        // Create the student
        Student::create($validated);

        // Redirect with success message
        return redirect()->route('students.create')
                         ->with('success', 'Student successfully added!');
    }

    /**
     * Display the specified student.
     */
    public function show($id)
    {
        $this->authorize('view', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);
        return view('students.show', ['student' => $student]);
    }

    /**
     * Show the form for editing a student.
     */
    public function edit($id)
    {
        $this->authorize('update', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);
        return view('students.edit', ['student' => $student]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        $this->authorize('update', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today', // Ensure birth date is not in future
            'aadhar_number' => 'required|digits:12|unique:students,aadhar_number,'.$id,
            'address' => 'required|string',
            'phone' => 'required|digits:10',
            'gender' => 'required|in:male,female,other',
            'category' => 'required|in:General,OBC,SC,ST,Other',
            'class' => 'required|string|max:50',
            'section' => 'nullable|string|max:10',
            'roll_number' => 'nullable|integer|unique:students,roll_number,'.$id,
            'religion' => 'nullable|string|max:50',
            'caste' => 'nullable|string|max:50',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown'
        ]);

        $student->update($validated);
        
        return redirect()->route('students.index')
                         ->with('success', 'Student updated successfully!');
    }

    /**
     * Remove the specified student.
     */
    public function destroy($id)
    {
        $this->authorize('delete', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);
        $student->delete();
        
        return redirect()->route('students.index')
                         ->with('success', 'Student deleted successfully!');
    }

    /**
     * Export students to CSV
     */
    public function exportCSV()
    {
        $students = Student::all();
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students-' . date('Y-m-d') . '.csv"',
        ];
        
        $callback = function() use ($students) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($file, [
                'ID', 'Name', 'Father Name', 'Mother Name', 'Date of Birth',
                'Aadhar Number', 'Phone', 'Gender', 'Category', 'Class',
                'Section', 'Roll Number', 'Religion', 'Caste', 'Blood Group', 'Address'
            ]);
            
            // Data
            foreach ($students as $student) {
                fputcsv($file, [
                    $student->id,
                    $student->name,
                    $student->father_name,
                    $student->mother_name,
                    $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '',
                    $student->aadhar_number,
                    $student->phone,
                    $student->gender,
                    $student->category,
                    $student->class,
                    $student->section,
                    $student->roll_number,
                    $student->religion ?? '',
                    $student->caste ?? '',
                    $student->blood_group ?? '',
                    $student->address
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export students to Excel with formatting
     */
    public function exportExcel()
    {
        $students = Student::all();
        
        return Excel::download(new class($students) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $students;
            
            public function __construct($students)
            {
                $this->students = $students;
            }
            
            public function collection()
            {
                return $this->students->map(function($student) {
                    return [
                        $student->id,
                        $student->name,
                        $student->father_name,
                        $student->mother_name,
                        $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '',
                        $student->aadhar_number,
                        $student->phone,
                        $student->gender,
                        $student->category,
                        $student->class,
                        $student->section,
                        $student->roll_number,
                        $student->religion ?? '',
                        $student->caste ?? '',
                        $student->blood_group ?? '',
                        $student->address,
                    ];
                });
            }
            
            public function headings(): array
            {
                return [
                    'ID', 'Name', 'Father Name', 'Mother Name', 'Date of Birth',
                    'Aadhar Number', 'Phone', 'Gender', 'Category', 'Class',
                    'Section', 'Roll Number', 'Religion', 'Caste', 'Blood Group', 'Address'
                ];
            }
        }, 'students-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Import students from CSV/Excel file
     */
    public function importCSV(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls'
        ]);

        $file = $request->file('csv_file');
        $extension = $file->getClientOriginalExtension();

        $imported = 0;
        $errors = [];

        if (in_array($extension, ['csv', 'txt'])) {
            // Handle CSV file
            $data = $this->readCSVFile($file);
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            // Handle Excel file (using Maatwebsite package)
            $data = $this->readExcelFile($file);
        } else {
            return redirect()->back()->with('error', 'Unsupported file format. Please upload CSV or Excel file.');
        }

        // Process data with transaction
        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                try {
                    // Skip header row
                    if ($index === 0) continue;
                    
                    // Skip empty rows
                    if (empty(array_filter($row))) continue;
                    
                    Student::create([
                        'name' => $row[1] ?? '',
                        'father_name' => $row[2] ?? '',
                        'mother_name' => $row[3] ?? '',
                        'date_of_birth' => isset($row[4]) ? $this->parseDate($row[4]) : '2000-01-01',
                        'aadhar_number' => $row[5] ?? '',
                        'phone' => $row[6] ?? '',
                        'gender' => isset($row[7]) ? strtolower(trim($row[7])) : 'male',
                        'category' => $row[8] ?? 'General',
                        'class' => $row[9] ?? '',
                        'section' => $row[10] ?? '',
                        'roll_number' => $row[11] ?? null,
                        'religion' => $row[12] ?? '',
                        'caste' => $row[13] ?? '',
                        'blood_group' => $row[14] ?? '',
                        'address' => $row[15] ?? ''
                    ]);
                    
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    // Rollback on first error
                    DB::rollBack();
                    return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage())->with('import_errors', $errors);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
        
        $message = "✅ Successfully imported $imported students.";
        if (!empty($errors)) {
            $message .= " ⚠️ " . count($errors) . " errors occurred.";
            session()->flash('import_errors', $errors);
        }
        
        return redirect()->back()->with('success', $message);
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
        // Use Maatwebsite Excel package
        $rows = [];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        
        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Include empty cells
            
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }
            $rows[] = $rowData;
        }
        
        return $rows;
    }

    private function parseDate($dateString)
    {
        // Try different date formats
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
        
        // If still not parsed, use default
        return '2000-01-01';
    }
}