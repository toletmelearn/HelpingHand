<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /**
     * Display a listing of all students.
     */
    public function index()
    {
        $students = Student::all();
        return view('students.index', ['students' => $students]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        return view('students.create');
    }

    /**
     * Store a newly created student in database.
     */
    public function store(Request $request)
    {
        // Validate the request data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'aadhar_number' => 'required|digits:12|unique:students',
            'address' => 'required|string',
            'phone' => 'required|digits:10'
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
        $student = Student::findOrFail($id);
        return view('students.show', ['student' => $student]);
    }

    /**
     * Show the form for editing a student.
     */
    public function edit($id)
    {
        $student = Student::findOrFail($id);
        return view('students.edit', ['student' => $student]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'aadhar_number' => 'required|digits:12|unique:students,aadhar_number,'.$id,
            'address' => 'required|string',
            'phone' => 'required|digits:10'
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
        $student = Student::findOrFail($id);
        $student->delete();
        
        return redirect()->route('students.index')
                         ->with('success', 'Student deleted successfully!');
    }
    // Add this method to get counts
public function getCounts()
{
    return [
        'students' => Student::count(),
        'teachers' => Teacher::count(),
        'active_teachers' => Teacher::where('status', 'active')->count(),
        'total_salary' => Teacher::sum('salary')
    ];
}
// Add to StudentController.php
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
                $student->date_of_birth->format('Y-m-d'),
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
// Add to StudentController.php
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
        // Handle Excel file (simple method)
        $data = $this->readExcelFileSimple($file);
    } else {
        return redirect()->back()->with('error', 'Unsupported file format. Please upload CSV or Excel file.');
    }
    
    // Process data with transaction
    \DB::beginTransaction();
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
                \DB::rollBack();
                return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage())->with('import_errors', $errors);
            }
        }
        \DB::commit();
    } catch (\Exception $e) {
        \DB::rollBack();
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

private function readExcelFileSimple($file)
{
    // Simple Excel reader without package
    $path = $file->getRealPath();
    $data = [];
    
    // For .xlsx files (Office 2007+)
    if (pathinfo($path, PATHINFO_EXTENSION) === 'xlsx') {
        // Try to read as ZIP (XLSX is a ZIP file)
        $zip = new \ZipArchive;
        if ($zip->open($path) === true) {
            // Read shared strings and sheet data
            // This is a simplified approach
            $data = $this->readXLSXAsXML($zip);
            $zip->close();
        }
    }
    
    // For .xls files (old Excel format)
    // We'll convert to CSV first
    if (empty($data)) {
        // Fallback: Ask user to save as CSV
        throw new \Exception('Please save Excel file as CSV format and upload again.');
    }
    
    return $data;
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
