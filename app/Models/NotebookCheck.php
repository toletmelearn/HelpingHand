<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;

class NotebookCheck extends Model
{
    use HasFactory;

    protected $table = 'notebook_checks';

    protected $fillable = [
        'student_id',
        'subject_id',
        'check_date',
        'deficiencies',
        'recheck_date',
        'remarks',
        'is_signed',
        'checked_by',
    ];

    protected $casts = [
        'check_date' => 'date',
        'recheck_date' => 'date',
        'is_signed' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function checker()
    {
        return $this->belongsTo(Teacher::class, 'checked_by');
    }
}
