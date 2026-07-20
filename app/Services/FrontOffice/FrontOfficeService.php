<?php

namespace App\Services\FrontOffice;

use App\Models\AdmissionEnquiry;
use App\Models\Appointment;
use App\Models\GatePass;
use App\Models\GateEntry;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class FrontOfficeService
{
    /**
     * Check if an enquiry with same Phone, Email, or Aadhaar already exists.
     */
    public function checkDuplicateEnquiry(array $data, ?int $ignoreId = null): ?AdmissionEnquiry
    {
        $query = AdmissionEnquiry::query();

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->where(function ($q) use ($data) {
            $q->where('phone', $data['phone']);
            
            if (!empty($data['email'])) {
                $q->orWhere('email', $data['email']);
            }
            
            if (!empty($data['aadhaar_number'])) {
                $q->orWhere('aadhaar_number', $data['aadhaar_number']);
            }
        })->first();
    }

    /**
     * Check if the teacher has any overlapping appointments on the selected date.
     */
    public function checkTeacherOverlaps(int $teacherId, string $date, string $startTime, string $endTime, ?int $ignoreId = null): bool
    {
        $startTime = Carbon::parse($startTime)->format('H:i:s');
        $endTime = Carbon::parse($endTime)->format('H:i:s');

        $query = Appointment::where('teacher_id', $teacherId)
            ->whereDate('scheduled_date', $date)
            ->whereIn('status', ['pending', 'approved']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        // Overlap logic: (existing_start < requested_end) AND (existing_end > requested_start)
        return $query->where(function ($q) use ($startTime, $endTime) {
            $q->where('start_time', '<', $endTime)
              ->where('end_time', '>', $startTime);
        })->exists();
    }

    /**
     * Create a gate pass with conflict/duplicate checks for students.
     */
    public function createGatePass(array $data): GatePass
    {
        if ($data['pass_type'] === 'student' && !empty($data['student_id'])) {
            $activePassExists = GatePass::where('student_id', $data['student_id'])
                ->whereIn('status', ['pending', 'approved', 'active'])
                ->whereDate('request_date', Carbon::today())
                ->exists();

            if ($activePassExists) {
                throw ValidationException::withMessages([
                    'student_id' => ['The student already has an active or pending gate pass for today.'],
                ]);
            }
        }

        return GatePass::create($data);
    }
}
