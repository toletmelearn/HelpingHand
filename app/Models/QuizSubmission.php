<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizSubmission extends Model
{
    use HasFactory;

    protected $table = 'quiz_submissions';

    protected $fillable = [
        'student_id',
        'quiz_id',
        'score',
        'answers_json',
    ];

    protected $casts = [
        'answers_json' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function quiz()
    {
        return $this->belongsTo(OnlineQuiz::class, 'quiz_id');
    }
}
