<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\StudentFeeLedger;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

class BulkFeeAssignmentService
{
    /**
     * Bulk assign a fee structure to a set of students and generate ledger debit entries efficiently.
     */
    public static function bulkAssign(FeeStructure $feeStructure, array $studentIds): void
    {
        if (empty($studentIds)) {
            return;
        }

        // Load items with feeType and installments relations
        $feeStructure->load(['feeStructureItems.feeType', 'feeStructureItems.installments']);

        // 1. Resolve class ID from class name
        $classVal = SchoolClass::where('name', $feeStructure->class_name)->first();
        $classId = $classVal ? $classVal->id : null;

        // Resolve which AcademicSession this fee structure's year corresponds to,
        // so 'session_wise_admission' items (e.g. Admission Fee) can be limited to
        // students actually admitted in that session. If it can't be resolved
        // (e.g. academic_year doesn't match any seeded session, or the
        // academic_sessions table doesn't exist at all), fall back to the old
        // behavior -- only the "already billed this year" dedup applies -- so
        // fee assignment never silently breaks for schools without session data.
        $feeStructureSession = \Illuminate\Support\Facades\Schema::hasTable('academic_sessions')
            ? AcademicSession::where('code', $feeStructure->academic_year)
                ->orWhere('name', $feeStructure->academic_year)
                ->first()
            : null;

        // 2. Resolve fee structure items and their billing installments
        $itemsData = [];
        foreach ($feeStructure->feeStructureItems as $item) {
            $itemMonths = [];
            if (!empty($item->billing_frequency) && !empty($item->charge_months)) {
                $itemMonths = is_array($item->charge_months) ? $item->charge_months : json_decode($item->charge_months, true);
            }

            if (empty($itemMonths)) {
                switch ($feeStructure->frequency) {
                    case 'monthly':
                        $itemMonths = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
                        break;
                    case 'quarterly':
                        $itemMonths = ['Q1', 'Q2', 'Q3', 'Q4'];
                        break;
                    case 'yearly':
                        $itemMonths = ['Annual'];
                        break;
                    default:
                        $itemMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        break;
                }
            }

            // Map custom installments if they exist on the database
            $installments = [];
            if ($item->installments->isNotEmpty()) {
                foreach ($item->installments as $inst) {
                    $installments[] = [
                        'month' => $inst->month,
                        'amount' => floatval($inst->amount)
                    ];
                }
            } else {
                foreach ($itemMonths as $month) {
                    $installments[] = [
                        'month' => $month,
                        'amount' => floatval($item->amount)
                    ];
                }
            }

            $itemsData[] = [
                'item' => $item,
                'installments' => $installments,
            ];
        }

        // 3. Process in chunks of 100 students
        $chunks = array_chunk($studentIds, 100);
        $now = now();

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk, $feeStructure, $classId, $itemsData, $now, $feeStructureSession) {
                // Fetch the last ledger entry for each student in the chunk to get starting running balance
                $lastLedgers = StudentFeeLedger::whereIn('student_id', $chunk)
                    ->whereIn('id', function($query) use ($chunk) {
                        $query->selectRaw('MAX(id)')
                            ->from('student_fee_ledgers')
                            ->whereIn('student_id', $chunk)
                            ->groupBy('student_id');
                    })
                    ->get()
                    ->pluck('running_balance', 'student_id')
                    ->toArray();

                // Already-assigned students must be skipped, not just left to
                // fail the unique constraint on insert -- StudentFeeAssignment
                // is inserted as a single raw multi-row statement per chunk,
                // so ONE duplicate would previously throw and roll back the
                // entire 100-student chunk, including every other,
                // genuinely-new assignment in it.
                $alreadyAssignedIds = StudentFeeAssignment::where('fee_structure_id', $feeStructure->id)
                    ->where('academic_year', $feeStructure->academic_year)
                    ->whereIn('student_id', $chunk)
                    ->pluck('student_id')
                    ->flip()
                    ->toArray();
                $chunk = array_values(array_diff($chunk, array_keys($alreadyAssignedIds)));

                if (empty($chunk)) {
                    return;
                }

                // Prefetch existing admission charges for students in the chunk for this academic session
                $existingAdmissions = StudentFeeLedger::whereIn('student_id', $chunk)
                    ->where('academic_year', $feeStructure->academic_year)
                    ->get()
                    ->groupBy('student_id')
                    ->map(function ($entries) {
                        return $entries->pluck('fee_type_id')->toArray();
                    })
                    ->toArray();

                // Which students in this chunk are genuinely new admissions for the
                // fee structure's session -- only they should receive 'session_wise_admission'
                // items like Admission Fee. Continuing/promoted students (and any
                // student whose admission_session_id is null) are excluded.
                $newAdmissionStudentIds = ($feeStructureSession && \Illuminate\Support\Facades\Schema::hasColumn('students', 'admission_session_id'))
                    ? Student::whereIn('id', $chunk)
                        ->where('admission_session_id', $feeStructureSession->id)
                        ->pluck('id')
                        ->flip()
                        ->toArray()
                    : null;

                $assignments = [];
                $ledgers = [];

                foreach ($chunk as $studentId) {
                    // Prepare Assignment Row
                    $assignments[] = [
                        'student_id' => $studentId,
                        'fee_structure_id' => $feeStructure->id,
                        'academic_year' => $feeStructure->academic_year,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $runningBalance = floatval($lastLedgers[$studentId] ?? 0.00);
                    $studentBilledFeeTypes = $existingAdmissions[$studentId] ?? [];

                    // Generate Debits for this student
                    foreach ($itemsData as $data) {
                        $item = $data['item'];
                        $installments = $data['installments'];

                        // If the item billing frequency is session-wise admission, only
                        // bill it to students who are genuinely new for this session, and
                        // never bill it twice to the same student in the same year.
                        if ($item->billing_frequency === 'session_wise_admission') {
                            if (in_array($item->fee_type_id, $studentBilledFeeTypes)) {
                                continue; // Skip double admission billing
                            }
                            if ($newAdmissionStudentIds !== null && !isset($newAdmissionStudentIds[$studentId])) {
                                continue; // Continuing/promoted student -- not a new admission this session
                            }
                        }

                        // Mirror image of 'session_wise_admission' -- e.g. Annual
                        // Charges billed only to continuing/old students, never to
                        // a student admitted fresh this session. If admission-session
                        // data can't be resolved, fail open (bill everyone) rather
                        // than silently dropping the charge for the whole school.
                        if ($item->billing_frequency === 'session_wise_continuing') {
                            if ($newAdmissionStudentIds !== null && isset($newAdmissionStudentIds[$studentId])) {
                                continue; // New admission this session -- not a continuing student
                            }
                        }

                        foreach ($installments as $inst) {
                            $month = $inst['month'];
                            $amount = $inst['amount'];

                            $year = date('Y');
                            $monthMap = [
                                'January' => '01-01', 'February' => '02-01', 'March' => '03-01',
                                'April' => '04-01', 'May' => '05-01', 'June' => '06-01',
                                'July' => '07-01', 'August' => '08-01', 'September' => '09-01',
                                'October' => '10-01', 'November' => '11-01', 'December' => '12-01',
                                'Q1' => '04-01', 'Q2' => '07-01', 'Q3' => '10-01', 'Q4' => '01-01',
                                'Annual' => '04-01'
                            ];
                            $datePart = $monthMap[$month] ?? '04-01';
                            if (in_array($month, ['January', 'February', 'March', 'Q4'])) {
                                $year = date('Y', strtotime('+1 year'));
                            }
                            $dateStr = "$year-$datePart";

                            $runningBalance += $amount;

                            $ledgers[] = [
                                'student_id' => $studentId,
                                'date' => $dateStr,
                                'description' => "Fee Charge: {$item->feeType->name} - $month",
                                'reference_type' => 'fee_structure_item',
                                'reference_id' => $item->id,
                                'debit' => $amount,
                                'credit' => 0.00,
                                'running_balance' => $runningBalance,
                                'academic_year' => $feeStructure->academic_year,
                                'class_id' => $classId,
                                'fee_type_id' => $item->fee_type_id,
                                'unpaid_amount' => $amount,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }

                // Insert assignments without triggering Eloquent events to prevent double processing
                StudentFeeAssignment::withoutEvents(function () use ($assignments) {
                    StudentFeeAssignment::insert($assignments);
                });

                // Bulk insert ledger entries
                if (!empty($ledgers)) {
                    StudentFeeLedger::insert($ledgers);
                }
            });
        }
    }
}
