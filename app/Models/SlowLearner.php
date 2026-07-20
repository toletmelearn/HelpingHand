<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlowLearner extends Model
{
    use HasFactory;

    protected $table = 'slow_learners';

    protected $fillable = [
        'student_id',
        'subject_id',
        'diagnostic_date',
        'remedial_notes',
        'progress_status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
