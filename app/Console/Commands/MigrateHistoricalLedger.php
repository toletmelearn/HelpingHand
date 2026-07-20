<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\FeeCollection;
use App\Models\StudentFeeLedger;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MigrateHistoricalLedger extends Command
{
    protected $signature = 'ledger:migrate-historical {academic_year?}';
    protected $description = 'Migrate historical fee assignments, collections, discounts, and fines to the ledger';

    public function handle()
    {
        $academicYear = $this->argument('academic_year') ?: '2026-27';

        $this->info("Truncating current ledger table to start clean migration...");
        StudentFeeLedger::truncate();

        $students = Student::active()->get();
        $this->info("Found " . $students->count() . " active students. Starting ledger migration...");

        $bar = $this->output->createProgressBar($students->count());
        $bar->start();

        foreach ($students as $student) {
            DB::transaction(function () use ($student, $academicYear) {
                // 1. Process Assignments (Debits)
                // Retrieve the fee structures assigned to this student
                $assignments = StudentFeeAssignment::where('student_id', $student->id)
                    ->where('academic_year', $academicYear)
                    ->with('feeStructure.feeStructureItems')
                    ->get();

                foreach ($assignments as $assign) {
                    $structure = $assign->feeStructure;
                    if (!$structure) {
                        continue;
                    }

                    // For the sake of migrating historical dues up to the current date,
                    // we assume tuition fees are charged.
                    // We charge tuition fees month-by-month.
                    $months = $this->generateMonths($structure->frequency);
                    
                    foreach ($months as $month) {
                        // Date of charge: set to 1st day of target month (relative to current calendar year)
                        $dateStr = $this->getMonthDate($month);
                        
                        foreach ($structure->feeStructureItems as $item) {
                            StudentFeeLedger::create([
                                'student_id' => $student->id,
                                'date' => $dateStr,
                                'description' => "Fee Charge: {$item->feeType->name} - $month",
                                'reference_type' => 'fee_structure_item',
                                'reference_id' => $item->id,
                                'debit' => $item->amount,
                                'credit' => 0.00,
                                'running_balance' => 0.00,
                            ]);
                        }
                    }
                }

                // 2. Process Existing Collections (Credits & Debit Fines)
                $collections = FeeCollection::where('student_id', $student->id)
                    ->orderBy('payment_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($collections as $collection) {
                    // Credit for base payment
                    StudentFeeLedger::create([
                        'student_id' => $student->id,
                        'date' => $collection->payment_date,
                        'description' => "Fee Payment Received (Receipt No: {$collection->receipt_no})",
                        'reference_type' => 'fee_collection',
                        'reference_id' => $collection->id,
                        'debit' => 0.00,
                        'credit' => $collection->total_amount,
                        'running_balance' => 0.00,
                    ]);

                    // Debit for Late Fine (if any)
                    if ($collection->late_fine > 0) {
                        StudentFeeLedger::create([
                            'student_id' => $student->id,
                            'date' => $collection->payment_date,
                            'description' => "Late Fine Charged (Receipt No: {$collection->receipt_no})",
                            'reference_type' => 'fee_collection',
                            'reference_id' => $collection->id,
                            'debit' => $collection->late_fine,
                            'credit' => 0.00,
                            'running_balance' => 0.00,
                        ]);
                    }

                    // Credit for Discount (if any)
                    if ($collection->discount > 0) {
                        StudentFeeLedger::create([
                            'student_id' => $student->id,
                            'date' => $collection->payment_date,
                            'description' => "Discount Applied (Receipt No: {$collection->receipt_no})",
                            'reference_type' => 'fee_collection',
                            'reference_id' => $collection->id,
                            'debit' => 0.00,
                            'credit' => $collection->discount,
                            'running_balance' => 0.00,
                        ]);
                    }
                }

                // 3. Rebuild running balances chronologically for this student
                LedgerService::rebuildRunningBalances($student->id);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Ledger migration completed successfully!");
        return 0;
    }

    private function generateMonths($frequency)
    {
        switch ($frequency) {
            case 'monthly':
                return ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
            case 'quarterly':
                return ['Q1', 'Q2', 'Q3', 'Q4'];
            case 'yearly':
                return ['Annual'];
            default:
                return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        }
    }

    private function getMonthDate($monthName)
    {
        // Maps month name to standard dates for ledger ordering
        $year = date('Y');
        $monthMap = [
            'January' => '01-01', 'February' => '02-01', 'March' => '03-01',
            'April' => '04-01', 'May' => '05-01', 'June' => '06-01',
            'July' => '07-01', 'August' => '08-01', 'September' => '09-01',
            'October' => '10-01', 'November' => '11-01', 'December' => '12-01',
            'Q1' => '04-01', 'Q2' => '07-01', 'Q3' => '10-01', 'Q4' => '01-01',
            'Annual' => '04-01'
        ];

        $datePart = $monthMap[$monthName] ?? '04-01';
        
        // Adjust year for Jan-March to align with academic year starting in April
        if (in_array($monthName, ['January', 'February', 'March', 'Q4'])) {
            $year = date('Y', strtotime('+1 year'));
        }

        return "$year-$datePart";
    }
}
