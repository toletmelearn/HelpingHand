<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountApproval extends Model
{
    use HasFactory;

    protected $table = 'discount_approvals';

    protected $fillable = [
        'student_id',
        'discount_rule_id',
        'fee_type_id',
        'amount',
        'month',
        'academic_year',
        'status', // pending_verification, active, rejected
        'verification_slip',
        'created_by',
        'verified_by'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function discountRule()
    {
        return $this->belongsTo(DiscountRule::class, 'discount_rule_id');
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
