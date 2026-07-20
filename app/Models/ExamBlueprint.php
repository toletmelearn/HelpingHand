<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamBlueprint extends Model
{
    protected $fillable = [
        'exam_id',
        'topic_name',
        'weightage_percentage',
        'competency_level',
    ];

    protected $casts = [
        'weightage_percentage' => 'float',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
