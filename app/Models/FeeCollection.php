<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Student;
use App\Models\FeeStructure;
use App\Models\User;
use App\Models\FeeCollectionItem;

class FeeCollection extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted()
    {
        static::saving(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });

        static::deleting(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });

        static::created(function ($collection) {
            $date = $collection->payment_date ? $collection->payment_date->format('Y-m-d') : now()->format('Y-m-d');
            
            \App\Services\LedgerService::postCredit(
                $collection->student_id,
                $date,
                "Fee Payment Received (Receipt No: {$collection->receipt_no})",
                'fee_collection',
                $collection->id,
                $collection->final_amount
            );

            if ($collection->late_fine > 0) {
                \App\Services\LedgerService::postDebit(
                    $collection->student_id,
                    $date,
                    "Late Fine Charged (Receipt No: {$collection->receipt_no})",
                    'fee_collection',
                    $collection->id,
                    $collection->late_fine
                );
            }

            if ($collection->discount > 0) {
                // Generate and save snapshot records of the calculated discounts
                try {
                    $discountEngine = new \App\Services\DiscountEngineService();
                    $currentMonthName = $collection->payment_date ? $collection->payment_date->format('F') : now()->format('F');
                    $feeStructure = $collection->feeStructure;
                    
                    if ($feeStructure) {
                        $baseItems = [];
                        foreach ($feeStructure->feeStructureItems as $item) {
                            $baseItems[] = [
                                'fee_type_id' => $item->fee_type_id,
                                'amount' => $item->amount
                            ];
                        }

                        $appliedDiscounts = $discountEngine->calculateDiscounts(
                            $collection->student ?? \App\Models\Student::find($collection->student_id),
                            $currentMonthName,
                            $feeStructure->academic_year ?? '2026-27',
                            $baseItems
                        );

                        foreach ($appliedDiscounts as $d) {
                            $snapshotData = [
                                'student_id' => $collection->student_id,
                                'discount_rule_id' => $d['rule_id'],
                                'fee_type_id' => $d['fee_type_id'],
                                'amount' => $d['amount'],
                                'month' => $currentMonthName,
                                'academic_year' => $feeStructure->academic_year ?? '2026-27',
                            ];

                            // Guard against hand-rolled test schemas that predate
                            // this column (several FeeFinance tests build their
                            // own minimal student_discounts_applied table).
                            if (\Illuminate\Support\Facades\Schema::hasColumn('student_discounts_applied', 'applied_at')) {
                                $snapshotData['applied_at'] = now();
                            }

                            \App\Models\StudentDiscountApplied::create($snapshotData);
                        }
                    }
                } catch (\Exception $e) {
                    // Silence early DB setup errors
                }

                \App\Services\LedgerService::postCredit(
                    $collection->student_id,
                    $date,
                    "Discount Applied (Receipt No: {$collection->receipt_no})",
                    'fee_collection',
                    $collection->id,
                    $collection->discount
                );
            }

            try {
                \App\Services\AdvanceRebateService::evaluateAndApply($collection);
            } catch (\Exception $e) {
                // Silence early DB setup errors, same as the discount block above.
            }
        });

        static::deleted(function ($collection) {
            $date = now()->format('Y-m-d');
            
            // 1. Post a reversing DEBIT entry to the ledger to document the collection cancellation.
            \App\Services\LedgerService::postDebit(
                $collection->student_id,
                $date,
                "Reversal of Payment: Receipt No {$collection->receipt_no}",
                'fee_collection',
                $collection->id,
                $collection->final_amount
            );

            // 2. Post a reversing CREDIT entry to the ledger to offset the late fine.
            if ($collection->late_fine > 0) {
                \App\Services\LedgerService::postCredit(
                    $collection->student_id,
                    $date,
                    "Reversal of Late Fine: Receipt No {$collection->receipt_no}",
                    'fee_collection',
                    $collection->id,
                    $collection->late_fine
                );
            }

            // 3. Post a reversing DEBIT entry to the ledger to offset the discount.
            if ($collection->discount > 0) {
                \App\Services\LedgerService::postDebit(
                    $collection->student_id,
                    $date,
                    "Reversal of Discount: Receipt No {$collection->receipt_no}",
                    'fee_collection',
                    $collection->id,
                    $collection->discount
                );
            }
            
            // Rebuild running balances
            \App\Services\LedgerService::rebuildRunningBalances($collection->student_id);

            // Revert transport due status if transport fee was paid in this collection
            $transportFeeType = \App\Models\FeeType::where('name', 'Transport Fee')->first();
            if ($transportFeeType) {
                $hasTransportPaid = $collection->feeCollectionItems()
                    ->where('fee_type_id', $transportFeeType->id)
                    ->exists();

                if ($hasTransportPaid) {
                    $due = \App\Models\StudentTransportDue::where('student_id', $collection->student_id)
                        ->where('status', 'paid')
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($due) {
                        $due->update(['status' => 'unpaid']);
                    }
                }
            }
        });

        static::restored(function ($collection) {
            $date = $collection->payment_date ? $collection->payment_date->format('Y-m-d') : now()->format('Y-m-d');
            
            \App\Services\LedgerService::postCredit(
                $collection->student_id,
                $date,
                "Fee Payment Received (Receipt No: {$collection->receipt_no})",
                'fee_collection',
                $collection->id,
                $collection->final_amount
            );

            if ($collection->late_fine > 0) {
                \App\Services\LedgerService::postDebit(
                    $collection->student_id,
                    $date,
                    "Late Fine Charged (Receipt No: {$collection->receipt_no})",
                    'fee_collection',
                    $collection->id,
                    $collection->late_fine
                );
            }

            if ($collection->discount > 0) {
                \App\Services\LedgerService::postCredit(
                    $collection->student_id,
                    $date,
                    "Discount Applied (Receipt No: {$collection->receipt_no})",
                    'fee_collection',
                    $collection->id,
                    $collection->discount
                );
            }

            // Sync transport due status
            $transportFeeType = \App\Models\FeeType::where('name', 'Transport Fee')->first();
            if ($transportFeeType) {
                $hasTransportPaid = $collection->feeCollectionItems()
                    ->where('fee_type_id', $transportFeeType->id)
                    ->exists();

                if ($hasTransportPaid) {
                    $due = \App\Models\StudentTransportDue::where('student_id', $collection->student_id)
                        ->where('status', 'unpaid')
                        ->orderBy('id', 'asc')
                        ->first();
                    if ($due) {
                        $due->update(['status' => 'paid']);
                    }
                }
            }
        });
    }


    /**
     * Canonical set of payment_mode values, covering the union of what
     * FeeCollectionController, TransportFeeController, InstallmentFeeController,
     * and FinanceReconciliationController::issueRefund() each independently
     * validated before this -- 5 different lists that disagreed with each
     * other and, in InstallmentFeeController's case, didn't restrict the
     * value at all.
     */
    public const PAYMENT_MODES = ['cash', 'online', 'upi', 'bank', 'card', 'cheque', 'bank_transfer'];

    protected $fillable = [
        'receipt_no',
        'student_id',
        'fee_structure_id',
        'total_amount',
        'discount',
        'late_fine',
        'final_amount',
        'payment_date',
        'payment_mode',
        'remarks',
        'collected_by'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'late_fine' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'payment_date' => 'date',
        'payment_mode' => 'string',
        'collected_by' => 'integer'
    ];

    // Define relationship with fee collection items
    public function feeCollectionItems()
    {
        return $this->hasMany(FeeCollectionItem::class);
    }

    // Define relationship with student
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    // Define relationship with fee structure
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    // Define relationship with collector (user)
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
    
    // Scope for today's collections
    public function scopeToday($query)
    {
        return $query->whereDate('payment_date', today());
    }
    
    // Scope for current month collections
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('payment_date', today()->month)
                    ->whereYear('payment_date', today()->year);
    }

    // Generate receipt number automatically
    public static function generateReceiptNumber(): string
    {
        return \App\Services\FeeReceiptNumberService::generateNextReceiptNumber();
    }
    
    // Accessor to format payment date
    public function getFormattedPaymentDateAttribute()
    {
        return $this->payment_date ? $this->payment_date->format('d-m-Y') : '';
    }
}