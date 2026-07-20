<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Student;
use App\Models\FeeStructure;

class StudentFeeAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'fee_structure_id',
        'academic_year'
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });

        static::deleting(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });

        static::created(function ($assignment) {
            $structure = $assignment->feeStructure;
            if (!$structure) {
                return;
            }

            foreach ($structure->feeStructureItems as $item) {
                // Admission-only / one-time items (Admission Fee, Security
                // Deposit) must only ever be charged once per student --
                // never re-billed just because a new assignment row was
                // created for them.
                if (!\App\Services\FeeItemEligibilityService::isBillable($item, $assignment->student, $structure->academic_year)) {
                    continue;
                }

                // Resolve charge months for this item
                $itemMonths = [];
                if (!empty($item->billing_frequency) && !empty($item->charge_months)) {
                    $itemMonths = is_array($item->charge_months) ? $item->charge_months : json_decode($item->charge_months, true);
                }

                // Fallback to structure frequency if item frequency is not set
                if (empty($itemMonths)) {
                    switch ($structure->frequency) {
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

                foreach ($itemMonths as $month) {
                    $year = date('Y');
                    $monthMap = [
                        'January' => '01-01', 'February' => '02-01', 'March' => '03-01',
                        'April' => '04-01', 'May' => '05-01', 'June' => '06-01',
                        'July' => '07-01', 'August' => '08-01', 'September' => '09-01',
                        'October' => '10-01', 'November' => '11-01', 'December' => '12-01',
                        'Q1' => '04-01', 'Q2' => '07-01', 'Q3' => '10-01', 'Q4' => '01-01',
                        'Annual' => '04-01', 'OneTime' => '04-01'
                    ];
                    $datePart = $monthMap[$month] ?? '04-01';
                    if (in_array($month, ['January', 'February', 'March', 'Q4'])) {
                        $year = date('Y', strtotime('+1 year'));
                    }
                    $dateStr = "$year-$datePart";

                    \App\Services\LedgerService::postDebit(
                        $assignment->student_id,
                        $dateStr,
                        "Fee Charge: {$item->feeType->name} - $month",
                        'fee_structure_item',
                        $item->id,
                        $item->amount
                    );
                }
            }
        });
    }

    // Define relationship with student
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // Define relationship with fee structure
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }
}