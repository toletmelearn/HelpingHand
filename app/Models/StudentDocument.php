<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Student;
use App\Models\User;

class StudentDocument extends Model
{
    protected $fillable = [
        'student_id',
        'document_type',
        'document_path',
        'original_filename',
        'is_verified',
        'verified_by',
        'verified_at',
        'verification_notes',
        'file_size',
        'file_mime_type'
    ];
    
    protected $casts = [
        'verified_at' => 'datetime',
        'is_verified' => 'boolean'
    ];
    
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
