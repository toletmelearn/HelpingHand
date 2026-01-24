<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmitCard extends Model
{
    protected $fillable = [
        'student_id',
        'exam_id',
        'admit_card_format_id',
        'academic_session',
        'status',
        'data',
        'validation_data',
        'generated_by',
        'published_by',
        'published_at',
        'revoked_by',
        'revoked_at',
        'pdf_hash',
        'version',
    ];
    
    protected $casts = [
        'data' => 'array',
        'validation_data' => 'array',
        'published_at' => 'datetime',
        'revoked_at' => 'datetime',
        'version' => 'integer',
    ];
    
    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
    
    public function format(): BelongsTo
    {
        return $this->belongsTo(AdmitCardFormat::class, 'admit_card_format_id');
    }
    
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
    
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
    
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
    
    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
    
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
    
    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }
    
    public function scopeRevoked($query)
    {
        return $query->where('status', 'revoked');
    }
    
    public function scopeForAcademicSession($query, $session)
    {
        return $query->where('academic_session', $session);
    }
    
    public function scopeForExam($query, $examId)
    {
        return $query->where('exam_id', $examId);
    }
    
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }
    
    // Validation methods
    public function validateForGeneration(): array
    {
        $errors = [];
        
        // Check if student is enrolled in the exam's class
        if ($this->student->class !== $this->exam->class_name) {
            $errors[] = 'Student is not enrolled in the exam class';
        }
        
        // Check if student is detained/blocked
        if ($this->student->deleted_at) {
            $errors[] = 'Student is marked as inactive';
        }
        
        // Check if fees are cleared (simplified check)
        $outstandingFees = $this->student->fees()->where('status', 'pending')->sum('due_amount');
        if ($outstandingFees > 0) {
            $errors[] = 'Student has outstanding fees of Rs. ' . $outstandingFees;
        }
        
        // Check if exam schedule exists
        if (!$this->exam || !$this->exam->exam_date) {
            $errors[] = 'Exam schedule not available';
        }
        
        return $errors;
    }
    
    // Status transition methods
    public function canTransitionTo($newStatus): bool
    {
        $transitions = [
            'draft' => ['generated'],
            'generated' => ['published', 'locked'],
            'published' => ['locked', 'revoked'],
            'locked' => ['revoked'],
            'revoked' => ['published'], // Allow re-publication after revocation
        ];
        
        $currentTransitions = $transitions[$this->status] ?? [];
        return in_array($newStatus, $currentTransitions);
    }
    
    public function transitionTo($newStatus, $userId = null): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            return false;
        }
        
        $updateData = ['status' => $newStatus];
        
        switch ($newStatus) {
            case 'published':
                $updateData['published_at'] = now();
                $updateData['published_by'] = $userId;
                break;
            case 'revoked':
                $updateData['revoked_at'] = now();
                $updateData['revoked_by'] = $userId;
                break;
        }
        
        return $this->update($updateData);
    }
}
