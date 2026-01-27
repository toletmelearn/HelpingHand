<?php

namespace App\Services;

use App\Models\TeacherBiometricRecord;
use App\Models\BiometricSetting;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class TeacherBiometricService
{
    protected $calculationService;
    
    public function __construct(BiometricCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }
    
    /**
     * Create a new biometric record
     */
    public function createRecord($data)
    {
        // Validate no duplicate exists
        $existing = TeacherBiometricRecord::where('teacher_id', $data['teacher_id'])
            ->where('date', $data['date'])
            ->first();
            
        if ($existing) {
            throw new \Exception('Record already exists for this teacher on this date.');
        }
        
        // Calculate biometric data
        $calculatedData = $this->calculationService->calculateBiometricData($data);
        
        return TeacherBiometricRecord::create(array_merge($data, $calculatedData));
    }
    
    /**
     * Update an existing biometric record
     */
    public function updateRecord(TeacherBiometricRecord $record, $data)
    {
        // Check for duplicate (excluding current record)
        $existing = TeacherBiometricRecord::where('teacher_id', $data['teacher_id'])
            ->where('date', $data['date'])
            ->where('id', '!=', $record->id)
            ->first();
            
        if ($existing) {
            throw new \Exception('Record already exists for this teacher on this date.');
        }
        
        // Calculate biometric data
        $calculatedData = $this->calculationService->calculateBiometricData($data);
        
        $record->update(array_merge($data, $calculatedData));
        
        return $record;
    }
    
    /**
     * Process uploaded CSV/XLSX file
     */
    public function processUploadedFile($filePath, $overwrite = false)
    {
        $spreadsheet = IOFactory::load(storage_path('app/' . $filePath));
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        $success = 0;
        $failed = 0;
        $errors = [];
        
        DB::beginTransaction();
        
        try {
            // Skip header row
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) continue; // Skip empty rows
                
                try {
                    $data = [
                        'teacher_id' => $row[0] ?? null,
                        'date' => $row[1] ?? null,
                        'first_in_time' => $row[2] ?? null,
                        'last_out_time' => $row[3] ?? null,
                        'remarks' => $row[4] ?? null,
                    ];
                    
                    // Validate data
                    if (!$data['teacher_id'] || !$data['date']) {
                        $failed++;
                        $errors[] = "Row " . ($i + 1) . ": Missing required fields (teacher_id, date).";
                        continue;
                    }
                    
                    if (!Teacher::find($data['teacher_id'])) {
                        $failed++;
                        $errors[] = "Row " . ($i + 1) . ": Invalid teacher ID.";
                        continue;
                    }
                    
                    if (!Carbon::createFromFormat('Y-m-d', $data['date'])) {
                        $failed++;
                        $errors[] = "Row " . ($i + 1) . ": Invalid date format.";
                        continue;
                    }
                    
                    // Validate time formats
                    if ($data['first_in_time'] && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['first_in_time'])) {
                        $failed++;
                        $errors[] = "Row " . ($i + 1) . ": Invalid first in time format.";
                        continue;
                    }
                    
                    if ($data['last_out_time'] && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['last_out_time'])) {
                        $failed++;
                        $errors[] = "Row " . ($i + 1) . ": Invalid last out time format.";
                        continue;
                    }
                    
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
                    $calculatedData = $this->calculationService->calculateBiometricData($data);
                    
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
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats($date = null)
    {
        $date = $date ?: now()->toDateString();
        
        return [
            'total_teachers' => Teacher::count(),
            'present_today' => TeacherBiometricRecord::where('date', $date)->count(),
            'late_arrivals' => TeacherBiometricRecord::where('date', $date)->where('arrival_status', 'late')->count(),
            'early_departures' => TeacherBiometricRecord::where('date', $date)->where('departure_status', 'early_exit')->count(),
            'half_days' => TeacherBiometricRecord::where('date', $date)->where('departure_status', 'half_day')->count(),
            'avg_working_hours' => TeacherBiometricRecord::where('date', $date)->avg('calculated_duration') ?? 0,
        ];
    }
    
    /**
     * Get recent activity
     */
    public function getRecentActivity($days = 7)
    {
        return TeacherBiometricRecord::with('teacher')
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }
    
    /**
     * Validate biometric data before processing
     */
    public function validateBiometricData($data)
    {
        $errors = [];
        
        if (empty($data['teacher_id'])) {
            $errors[] = 'Teacher ID is required.';
        }
        
        if (empty($data['date'])) {
            $errors[] = 'Date is required.';
        }
        
        if (!empty($data['first_in_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['first_in_time'])) {
            $errors[] = 'First in time must be in HH:MM format.';
        }
        
        if (!empty($data['last_out_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['last_out_time'])) {
            $errors[] = 'Last out time must be in HH:MM format.';
        }
        
        if (!empty($data['first_in_time']) && !empty($data['last_out_time'])) {
            $firstIn = Carbon::createFromTimeString($data['first_in_time']);
            $lastOut = Carbon::createFromTimeString($data['last_out_time']);
            
            if ($firstIn->gte($lastOut)) {
                $errors[] = 'First in time must be before last out time.';
            }
        }
        
        return $errors;
    }
}
