<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentLeave extends Model
{
    use HasFactory;

    protected $table = 'student_leaves';

    protected $fillable = [
        'student_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approved_by',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function approver()
    {
        return $this->belongsTo(Teacher::class, 'approved_by');
    }
}
