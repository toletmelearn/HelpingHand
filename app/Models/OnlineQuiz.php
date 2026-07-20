<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Exam;

class OnlineQuiz extends Model
{
    use HasFactory;

    protected $table = 'online_quizzes';

    protected $fillable = [
        'exam_id',
        'title',
        'duration_minutes',
        'total_questions',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'total_questions' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id');
    }
}
