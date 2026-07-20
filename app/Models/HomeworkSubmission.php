<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\HomeworkNotice;
use App\Models\User;

class HomeworkSubmission extends Model
{
    use HasFactory;

    protected $table = 'homework_submissions';

    protected $fillable = [
        'homework_notice_id',
        'student_id',
        'submission_date',
        'file_path',
        'student_notes',
        'marks_obtained',
        'grade',
        'remarks',
        'evaluated_by',
        'evaluated_at',
        'status',
    ];

    protected $casts = [
        'submission_date' => 'datetime',
        'evaluated_at' => 'datetime',
        'marks_obtained' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function homeworkNotice()
    {
        return $this->belongsTo(HomeworkNotice::class, 'homework_notice_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
