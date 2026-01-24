<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Teacher;
use App\Models\User;

class TeacherSalary extends Model
{
    protected $fillable = [
        'teacher_id',
        'pay_scale',
        'basic_salary',
        'hra',
        'da',
        'ta',
        'medical_allowance',
        'special_allowance',
        'gross_salary',
        'pf_amount',
        'esi_amount',
        'tax_deduction',
        'other_deductions',
        'net_salary',
        'payment_status',
        'payment_date',
        'payment_method',
        'reference_number',
        'paid_by',
        'remarks'
    ];
    
    protected $dates = ['payment_date'];
    
    protected $casts = [
        'basic_salary' => 'decimal:2',
        'hra' => 'decimal:2',
        'da' => 'decimal:2',
        'ta' => 'decimal:2',
        'medical_allowance' => 'decimal:2',
        'special_allowance' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'pf_amount' => 'decimal:2',
        'esi_amount' => 'decimal:2',
        'tax_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2'
    ];
    
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
    
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
