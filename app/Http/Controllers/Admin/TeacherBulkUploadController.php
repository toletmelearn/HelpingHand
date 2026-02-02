<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TeacherBulkUploadController extends Controller
{
    /**
     * Show the bulk upload form
     */
    public function create()
    {
        $this->authorize('create', Teacher::class);
        return view('admin.teachers.bulk-upload');
    }

    /**
     * Handle the bulk upload
     */
    public function store(Request $request)
    {
        $this->authorize('create', Teacher::class);
        
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv_file');
        $data = $this->readCSVFile($file);

        $imported = 0;
        $errors = [];
        $totalRows = count($data) - 1; // Exclude header row

        // Process data with transaction
        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                try {
                    // Skip header row
                    if ($index === 0) continue;
                    
                    // Skip empty rows
                    if (empty(array_filter($row))) continue;
                    
                    // Validate row data
                    $validationResult = $this->validateTeacherRow($row, $index + 1);
                    if ($validationResult !== true) {
                        $errors[] = $validationResult;
                        continue;
                    }
                    
                    // Create teacher
                    Teacher::create([
                        'name' => $row[0] ?? '',
                        'email' => $row[1] ?? '',
                        'phone' => $row[2] ?? '',
                        'aadhar_number' => $row[3] ?? '',
                        'gender' => $row[4] ?? 'male',
                        'date_of_birth' => isset($row[5]) ? $this->parseDate($row[5]) : null,
                        'qualification' => $row[6] ?? '',
                        'subject_specialization' => $row[7] ?? '',
                        'designation' => $row[8] ?? '',
                        'employee_id' => $row[9] ?? '',
                        'employment_type' => $row[10] ?? 'permanent',
                        'department' => $row[11] ?? '',
                        'teacher_type' => $row[12] ?? 'teaching',
                        'joining_date' => isset($row[13]) ? $this->parseDate($row[13]) : null,
                        'salary' => $row[14] ?? 0,
                        'status' => $row[15] ?? 'active',
                        'address' => $row[16] ?? '',
                        'bank_account' => $row[17] ?? '',
                        'ifsc_code' => $row[18] ?? '',
                        'experience_years' => $row[19] ?? 0
                    ]);
                    
                    $imported++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    // Continue processing other rows - don't rollback entire transaction
                    continue;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage())->with('import_errors', $errors);
        }
        
        $message = "✅ Successfully imported $imported teachers.";
        if (!empty($errors)) {
            $message .= " ⚠️ " . count($errors) . " errors occurred.";
            session()->flash('import_errors', $errors);
        }
        
        return redirect()->back()->with('success', $message)
            ->with('import_summary', [
                'total_rows' => $totalRows,
                'imported' => $imported,
                'failed' => count($errors)
            ]);
    }

    /**
     * Download sample CSV file
     */
    public function downloadSample()
    {
        $this->authorize('create', Teacher::class);
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="teachers_sample.csv"',
        ];

        $sampleData = [
            ['full_name', 'email', 'phone', 'aadhar', 'gender', 'date_of_birth', 'qualification', 'subjects', 'designation', 'employee_id', 'employment_type', 'wing', 'teacher_type', 'date_of_joining', 'monthly_salary', 'status', 'address', 'bank_account', 'ifsc', 'experience'],
            ['John Doe', 'john.doe@school.edu', '9876543210', '123456789012', 'male', '1985-05-15', 'M.Ed', 'Maths,Science', 'TGT', 'EMP001', 'permanent', 'secondary', 'teaching', '2020-06-01', '45000', 'active', '123 Main St, City', '123456789012', 'SBIN0001234', '5'],
            ['Jane Smith', 'jane.smith@school.edu', '9876543211', '123456789013', 'female', '1988-08-22', 'B.Ed', 'English,Hindi', 'PRT', 'EMP002', 'permanent', 'primary', 'teaching', '2019-07-15', '35000', 'active', '456 Oak Ave, City', '123456789013', 'SBIN0001235', '4'],
            ['Robert Johnson', 'robert.j@school.edu', '9876543212', '123456789014', 'male', '1982-03-10', 'M.Sc', 'Physics,Chemistry', 'PGT', 'EMP003', 'contractual', 'senior', 'teaching', '2021-01-10', '55000', 'active', '789 Pine Rd, City', '123456789014', 'SBIN0001236', '8']
        ];

        $callback = function() use ($sampleData) {
            $file = fopen('php://output', 'w');
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Read CSV file
     */
    private function readCSVFile($file)
    {
        $data = [];
        $path = $file->getRealPath();
        
        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        
        return $data;
    }

    /**
     * Validate teacher row data
     */
    private function validateTeacherRow($row, $rowNumber)
    {
        // Required field validation
        $requiredFields = [0, 1, 2]; // full_name, email, phone
        foreach ($requiredFields as $fieldIndex) {
            if (!isset($row[$fieldIndex]) || empty(trim($row[$fieldIndex]))) {
                return "Row $rowNumber: Required field missing (column " . ($fieldIndex + 1) . ")";
            }
        }

        // Email validation
        if (isset($row[1]) && !filter_var($row[1], FILTER_VALIDATE_EMAIL)) {
            return "Row $rowNumber: Invalid email format";
        }

        // Phone validation (10 digits)
        if (isset($row[2]) && (!is_numeric($row[2]) || strlen($row[2]) != 10)) {
            return "Row $rowNumber: Phone must be 10 digits";
        }

        // Aadhar validation (12 digits)
        if (isset($row[3]) && !empty($row[3]) && (!is_numeric($row[3]) || strlen($row[3]) != 12)) {
            return "Row $rowNumber: Aadhar must be 12 digits";
        }

        // Gender validation
        if (isset($row[4]) && !empty($row[4]) && !in_array(strtolower($row[4]), ['male', 'female', 'other'])) {
            return "Row $rowNumber: Gender must be male, female, or other";
        }

        // Date validation
        if (isset($row[5]) && !empty($row[5])) {
            if (!$this->isValidDate($row[5])) {
                return "Row $rowNumber: Invalid date of birth format";
            }
        }

        if (isset($row[13]) && !empty($row[13])) {
            if (!$this->isValidDate($row[13])) {
                return "Row $rowNumber: Invalid date of joining format";
            }
        }

        // Salary validation
        if (isset($row[14]) && !empty($row[14]) && (!is_numeric($row[14]) || $row[14] < 0)) {
            return "Row $rowNumber: Salary must be a positive number";
        }

        // Status validation
        if (isset($row[15]) && !empty($row[15]) && !in_array(strtolower($row[15]), ['active', 'inactive'])) {
            return "Row $rowNumber: Status must be active or inactive";
        }

        // Employment type validation
        if (isset($row[10]) && !empty($row[10]) && !in_array(strtolower($row[10]), ['permanent', 'contractual'])) {
            return "Row $rowNumber: Employment type must be permanent or contractual";
        }

        // Teacher type validation
        if (isset($row[12]) && !empty($row[12]) && !in_array(strtolower($row[12]), ['teaching', 'non-teaching'])) {
            return "Row $rowNumber: Teacher type must be teaching or non-teaching";
        }

        // Wing validation
        if (isset($row[11]) && !empty($row[11]) && !in_array(strtolower($row[11]), ['primary', 'secondary', 'senior'])) {
            return "Row $rowNumber: Wing must be primary, secondary, or senior";
        }

        // Experience validation
        if (isset($row[19]) && !empty($row[19]) && (!is_numeric($row[19]) || $row[19] < 0)) {
            return "Row $rowNumber: Experience must be a positive number";
        }

        // Check for unique constraints
        if (isset($row[1]) && !empty($row[1])) {
            if (Teacher::where('email', $row[1])->exists()) {
                return "Row $rowNumber: Email already exists";
            }
        }

        if (isset($row[3]) && !empty($row[3])) {
            if (Teacher::where('aadhar_number', $row[3])->exists()) {
                return "Row $rowNumber: Aadhar number already exists";
            }
        }

        if (isset($row[9]) && !empty($row[9])) {
            if (Teacher::where('employee_id', $row[9])->exists()) {
                return "Row $rowNumber: Employee ID already exists";
            }
        }

        return true;
    }

    /**
     * Parse date string
     */
    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        // Try different date formats
        $formats = [
            'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y',
            'Y/m/d', 'Y-d-m', 'd.m.Y', 'Y.m.d'
        ];
        
        foreach ($formats as $format) {
            $date = Carbon::createFromFormat($format, trim($dateString));
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }

    /**
     * Check if date string is valid
     */
    private function isValidDate($dateString)
    {
        return $this->parseDate($dateString) !== null;
    }
}