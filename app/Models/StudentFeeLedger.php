<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class StudentFeeLedger extends Model
{
    use HasFactory, Auditable;

    protected static function booted()
    {
        static::saving(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });

        static::deleting(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });
    }

    protected $table = 'student_fee_ledgers';

    protected $fillable = [
        'student_id',
        'date',
        'description',
        'reference_type', // fee_collection, fee_structure_item, discount_applied, late_fine
        'reference_id',
        'debit',
        'credit',
        'running_balance',
        'academic_year',
        'class_id',
        'fee_type_id',
        'unpaid_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'class_id' => 'integer',
        'fee_type_id' => 'integer',
        'unpaid_amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    public function reference()
    {
        return $this->morphTo(null, 'reference_type', 'reference_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'model_id')->where('model_type', self::class);
    }
}
