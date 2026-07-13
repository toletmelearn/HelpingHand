<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyLinkSuggestion extends Model
{
    protected $fillable = [
        'student_id',
        'matched_family_id',
        'candidate_student_id',
        'match_basis',
        'matched_value',
        'status', // pending, confirmed, dismissed
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function matchedFamily(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'matched_family_id');
    }

    public function candidateStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'candidate_student_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
