<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Teacher;
use App\Models\User;

class TeacherDocument extends Model
{
    protected $fillable = [
        'teacher_id',
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
    
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
    
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
