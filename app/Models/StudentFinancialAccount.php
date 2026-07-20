<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Student;
use App\Models\User;

class StudentFinancialAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'student_financial_accounts';

    protected $fillable = [
        'student_id',
        'account_no',
        'status',
        'created_by',
        'updated_by',
        'ip_address'
    ];

    protected $casts = [
        'student_id' => 'integer',
        'status' => 'string',
        'created_by' => 'integer',
        'updated_by' => 'integer'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
