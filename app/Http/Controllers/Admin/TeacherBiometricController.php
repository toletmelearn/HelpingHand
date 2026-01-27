<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\TeacherBiometricRecord;
use App\Models\BiometricSetting;
use App\Models\WorkingHoursConfiguration;
use App\Models\Teacher;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TeacherBiometricController extends Controller
{
    /**
     * Display the biometric dashboard overview
     */
    public function index(Request $request)
    {
        $date = $request->get('date', now()->toDateString());
        $teacherId = $request->get('teacher_id');
        
        // Get statistics
        $stats = [
            'total_teachers' => Teacher::count(),
            'present_today' => TeacherBiometricRecord::where('date', $date)->count(),
            'late_arrivals' => TeacherBiometricRecord::where('date', $date)->where('arrival_status', 'late')->count(),
            'early_departures' => TeacherBiometricRecord::where('date', $date)->where('departure_status', 'early_exit')->count(),
            'half_days' => TeacherBiometricRecord::where('date', $date)->where('departure_status', 'half_day')->count(),
            'avg_working_hours' => TeacherBiometricRecord::where('date', $date)->avg('calculated_duration') ?? 0,
        ];
        
        // Get recent records
        $recordsQuery = TeacherBiometricRecord::with('teacher')
            ->where('date', $date)
            ->latest();
            
        if ($teacherId) {
            $recordsQuery->where('teacher_id', $teacherId);
        }
        
        $records = $recordsQuery->paginate(15);
        
        // Get teachers for filter
        $teachers = Teacher::orderBy('name')->get();
        
        return view('admin.teacher-biometrics.index', compact('records', 'stats', 'teachers', 'date'));
    }
    
    /**
     * Show the form for creating/uploading biometric records
     */
    public function create()
    {
        $teachers = Teacher::orderBy('name')->get();
        $settings = BiometricSetting::getActiveSettings();
        
        return view('admin.teacher-biometrics.create', compact('teachers', 'settings'));
    }
    
    /**
     * Store uploaded biometric data
     */
    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'date' => 'required|date',
            'first_in_time' => 'nullable|date_format:H:i',
            'last_out_time' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string|max:500',
        ]);
        
        // Check for duplicate
        $existing = TeacherBiometricRecord::where('teacher_id', $request->teacher_id)
            ->where('date', $request->date)
            ->first();
            
        if ($existing) {
            return back()->withErrors(['error' => 'Record already exists for this teacher on this date.'])->withInput();
        }
        
        // Calculate working hours and statuses
        $data = $this->calculateBiometricData($request);
        
        $record = TeacherBiometricRecord::create(array_merge([
            'teacher_id' => $request->teacher_id,
            'date' => $request->date,
            'first_in_time' => $request->first_in_time,
            'last_out_time' => $request->last_out_time,
            'remarks' => $request->remarks,
            'import_source' => 'manual',
        ], $data));
        
        return redirect()->route('admin.teacher-biometrics.index')
            ->with('success', 'Biometric record created successfully.');
    }
    
    /**
     * Handle CSV/Excel upload
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,xlsx,xls|max:2048',
            'overwrite' => 'boolean',
        ]);
        
        try {
            $file = $request->file('file');
            $filePath = $file->store('biometric-uploads');
            
            // Process the file
            $processed = $this->processUploadedFile($filePath, $request->overwrite);
            
            Storage::delete($filePath);
            
            return redirect()->back()
                ->with('success', "Successfully imported {$processed['success']} records. Failed: {$processed['failed']}.")
                ->with('errors', $processed['errors']);
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to process file: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Display the specified biometric record
     */
    public function show(TeacherBiometricRecord $teacherBiometric)
    {
        $record = $teacherBiometric->load('teacher');
        
        return view('admin.teacher-biometrics.show', compact('record'));
    }
    
    /**
     * Show the form for editing the specified record
     */
    public function edit(TeacherBiometricRecord $teacherBiometric)
    {
        $record = $teacherBiometric->load('teacher');
        $teachers = Teacher::orderBy('name')->get();
        $settings = BiometricSetting::getActiveSettings();
        
        return view('admin.teacher-biometrics.edit', compact('record', 'teachers', 'settings'));
    }
    
    /**
     * Update the specified record
     */
    public function update(Request $request, TeacherBiometricRecord $teacherBiometric)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'date' => 'required|date',
            'first_in_time' => 'nullable|date_format:H:i',
            'last_out_time' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string|max:500',
        ]);
        
        // Check for duplicate (excluding current record)
        $existing = TeacherBiometricRecord::where('teacher_id', $request->teacher_id)
            ->where('date', $request->date)
            ->where('id', '!=', $teacherBiometric->id)
            ->first();
            
        if ($existing) {
            return back()->withErrors(['error' => 'Record already exists for this teacher on this date.'])->withInput();
        }
        
        // Calculate working hours and statuses
        $data = $this->calculateBiometricData($request);
        
        $teacherBiometric->update(array_merge([
            'teacher_id' => $request->teacher_id,
            'date' => $request->date,
            'first_in_time' => $request->first_in_time,
            'last_out_time' => $request->last_out_time,
            'remarks' => $request->remarks,
        ], $data));
        
        return redirect()->route('admin.teacher-biometrics.index')
            ->with('success', 'Biometric record updated successfully.');
    }
    
    /**
     * Remove the specified record
     */
    public function destroy(TeacherBiometricRecord $teacherBiometric)
    {
        $teacherBiometric->delete();
        
        return redirect()->route('admin.teacher-biometrics.index')
            ->with('success', 'Biometric record deleted successfully.');
    }
    
    /**
     * Display biometric settings configuration
     */
    public function settings()
    {
        $settings = BiometricSetting::getActiveSettings() ?? new BiometricSetting(BiometricSetting::getDefaultSettings());
        $configurations = WorkingHoursConfiguration::all();
        
        return view('admin.teacher-biometrics.settings', compact('settings', 'configurations'));
    }
    
    /**
     * Update biometric settings
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate(BiometricSetting::getValidationRules());
        
        if ($request->has('setting_id')) {
            $settings = BiometricSetting::findOrFail($request->setting_id);
            $settings->update($validated);
        } else {
            $settings = BiometricSetting::create($validated);
        }
        
        return redirect()->back()
            ->with('success', 'Biometric settings updated successfully.');
    }
    
    /**
     * Generate and display reports
     */
    public function reports(Request $request)
    {
        $type = $request->get('type', 'daily_summary');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        $teacherId = $request->get('teacher_id');
        
        $data = [];
        
        switch ($type) {
            case 'daily_summary':
                $data = $this->generateDailySummary($startDate);
                break;
            case 'monthly_report':
                $data = $this->generateMonthlyReport($teacherId, $startDate, $endDate);
                break;
            case 'late_arrival_report':
                $data = $this->generateLateArrivalReport($startDate, $endDate);
                break;
            case 'early_departure_report':
                $data = $this->generateEarlyDepartureReport($startDate, $endDate);
                break;
        }
        
        $teachers = Teacher::orderBy('name')->get();
        
        return view('admin.teacher-biometrics.reports', compact('data', 'type', 'teachers', 'startDate', 'endDate'));
    }
    
    /**
     * Export reports to PDF or Excel
     */
    public function export(Request $request)
    {
        $type = $request->get('type', 'daily_summary');
        $format = $request->get('format', 'pdf');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        
        // Implementation would depend on PDF/Excel packages
        // This is a placeholder for the export functionality
        
        return redirect()->back()->with('info', 'Export functionality will be implemented in the next phase.');
    }
    
    /**
     * Private helper methods
     */
    private function calculateBiometricData($request)
    {
        $settings = BiometricSetting::getActiveSettings();
        if (!$settings) {
            $settings = new BiometricSetting(BiometricSetting::getDefaultSettings());
        }
        
        $data = [
            'calculated_duration' => 0,
            'arrival_status' => 'on_time',
            'departure_status' => 'on_time',
            'grace_minutes_used' => 0,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
        ];
        
        if ($request->first_in_time && $request->last_out_time) {
            $firstIn = Carbon::createFromTimeString($request->first_in_time);
            $lastOut = Carbon::createFromTimeString($request->last_out_time);
            $schoolStart = Carbon::createFromTimeString($settings->school_start_time);
            $schoolEnd = Carbon::createFromTimeString($settings->school_end_time);
            
            // Calculate working duration
            $totalMinutes = $lastOut->diffInMinutes($firstIn);
            
            // Subtract breaks if configured
            if ($settings->exclude_lunch_from_working_hours) {
                $totalMinutes -= $settings->lunch_break_duration;
            }
            if ($settings->exclude_breaks_from_working_hours) {
                $totalMinutes -= $settings->break_time_duration;
            }
            
            $data['calculated_duration'] = max(0, $totalMinutes / 60);
            
            // Calculate arrival status
            if ($firstIn->gt($schoolStart->copy()->addMinutes($settings->grace_period_minutes))) {
                $data['arrival_status'] = 'late';
                $data['late_minutes'] = $firstIn->diffInMinutes($schoolStart);
                $data['grace_minutes_used'] = min($settings->grace_period_minutes, $data['late_minutes']);
            }
            
            // Calculate departure status
            $expectedEnd = $schoolEnd->copy()->subMinutes($settings->early_departure_tolerance);
            if ($lastOut->lt($expectedEnd)) {
                $data['departure_status'] = 'early_exit';
                $data['early_departure_minutes'] = $expectedEnd->diffInMinutes($lastOut);
            }
            
            // Check for half day
            if ($data['calculated_duration'] < $settings->half_day_minimum_hours) {
                $data['departure_status'] = 'half_day';
            }
        }
        
        return $data;
    }
    
    private function processUploadedFile($filePath, $overwrite = false)
    {
        $spreadsheet = IOFactory::load(storage_path('app/' . $filePath));
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        $success = 0;
        $failed = 0;
        $errors = [];
        
        // Skip header row
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) continue; // Skip empty rows
            
            try {
                $validator = Validator::make([
                    'teacher_id' => $row[0] ?? null,
                    'date' => $row[1] ?? null,
                    'first_in_time' => $row[2] ?? null,
                    'last_out_time' => $row[3] ?? null,
                ], [
                    'teacher_id' => 'required|exists:teachers,id',
                    'date' => 'required|date',
                    'first_in_time' => 'nullable|date_format:H:i',
                    'last_out_time' => 'nullable|date_format:H:i',
                ]);
                
                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Row " . ($i + 1) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }
                
                $data = $validator->validated();
                
                // Check for duplicate
                $existing = TeacherBiometricRecord::where('teacher_id', $data['teacher_id'])
                    ->where('date', $data['date'])
                    ->first();
                
                if ($existing && !$overwrite) {
                    $failed++;
                    $errors[] = "Row " . ($i + 1) . ": Record already exists for teacher on this date.";
                    continue;
                }
                
                // Calculate biometric data
                $request = new Request($data);
                $calculatedData = $this->calculateBiometricData($request);
                
                if ($existing) {
                    $existing->update(array_merge($data, $calculatedData, ['import_source' => 'csv_upload']));
                } else {
                    TeacherBiometricRecord::create(array_merge($data, $calculatedData, ['import_source' => 'csv_upload']));
                }
                
                $success++;
                
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            }
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    private function generateDailySummary($date)
    {
        return TeacherBiometricRecord::getDailySummary($date);
    }
    
    private function generateMonthlyReport($teacherId, $startDate, $endDate)
    {
        if ($teacherId) {
            return TeacherBiometricRecord::getMonthlyReport($teacherId, Carbon::parse($startDate)->year, Carbon::parse($startDate)->month);
        }
        
        // Overall monthly report
        return DB::table('teacher_biometric_records')
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as total_records,
                         COUNT(DISTINCT teacher_id) as unique_teachers,
                         SUM(CASE WHEN arrival_status = "late" THEN 1 ELSE 0 END) as total_late,
                         SUM(CASE WHEN departure_status = "early_exit" THEN 1 ELSE 0 END) as total_early,
                         AVG(calculated_duration) as avg_working_hours')
            ->first();
    }
    
    private function generateLateArrivalReport($startDate, $endDate)
    {
        return TeacherBiometricRecord::with('teacher')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('arrival_status', 'late')
            ->orderBy('late_minutes', 'desc')
            ->limit(100)
            ->get();
    }
    
    private function generateEarlyDepartureReport($startDate, $endDate)
    {
        return TeacherBiometricRecord::with('teacher')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('departure_status', 'early_exit')
            ->orderBy('early_departure_minutes', 'desc')
            ->limit(100)
            ->get();
    }
}
