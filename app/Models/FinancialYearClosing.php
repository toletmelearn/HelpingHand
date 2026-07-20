<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class FinancialYearClosing extends Model
{
    use HasFactory, Auditable;

    protected $table = 'financial_year_closings';

    protected $fillable = [
        'from_session_code',
        'to_session_code',
        'status',
        'total_students_processed',
        'total_balance_carried',
        'total_advance_carried',
        'total_scholarship_carried',
        'total_refund_carried',
        'processed_by',
        'confirmed_at',
        'remarks',
        'last_processed_student_id',
        'processed_batch',
        'total_batches',
        'progress_percent',
        'started_at',
        'completed_at',
        'closed_by',
        'checksum',
        'closing_version',
        'error_message'
    ];

    protected $casts = [
        'total_students_processed' => 'integer',
        'total_balance_carried' => 'decimal:2',
        'total_advance_carried' => 'decimal:2',
        'total_scholarship_carried' => 'decimal:2',
        'total_refund_carried' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'processed_by' => 'integer',
        'progress_percent' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_processed_student_id' => 'integer',
        'processed_batch' => 'integer',
        'total_batches' => 'integer',
    ];

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
