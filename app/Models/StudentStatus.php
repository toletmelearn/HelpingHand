<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStatus extends Model
{
    protected $fillable = [
        'student_id',
        'status',
        'status_date',
        'reason',
        'remarks',
        'document_number',
        'document_issue_date',
        'issued_by',
    ];
    
    protected $casts = [
        'status_date' => 'date',
        'document_issue_date' => 'date',
    ];
    
    // Define relationship with student
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
