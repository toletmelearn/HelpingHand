<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmitCard extends Model
{
    // validation_data, pdf_hash, and version were never migrated -- no
    // such columns exist on admit_cards (dev DB and every migration
    // checked). Writing them threw a SQL error on every AdmitCard::create()
    // call, but this was never triggered in practice because the
    // class/class_name matching bug (remediation Task 7) meant
    // AdmitCardController::store() never actually matched any students to
    // create cards for -- one bug was masking the other. Removed rather
    // than migrated in: nothing else in the app reads any of the three.
    protected $fillable = [
        'student_id',
        'exam_id',
        'admit_card_format_id',
        'academic_session',
        'status',
        'data',
        'generated_by',
        'published_by',
        'published_at',
        'revoked_by',
        'revoked_at',
    ];

    protected $casts = [
        'data' => 'array',
        'published_at' => 'datetime',
        'revoked_at' => 'datetime',
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
        
        // Check if student is enrolled in the exam's class. Compares
        // school_class_id (authoritative) against exam.class_id, not the
        // legacy class/class_name string pair -- those use different
        // vocabularies (e.g. "X" vs "Class 10") and would reject nearly
        // every real student even after the caller already selected them
        // correctly by school_class_id (see remediation Task 7).
        if ($this->exam && $this->exam->class_id && $this->student->school_class_id !== $this->exam->class_id) {
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

        // Check if student has an active Exam Restriction stage or higher --
        // skipped if Admin/Principal/Accountant has granted an exception via
        // ExamRestrictionService::grantOverride().
        $defStage = \App\Models\DefaulterStage::where('student_id', $this->student_id)->first();
        if ($defStage && in_array($defStage->stage, ['Exam Restriction', 'Result Hold', 'TC Hold'])
            && !\App\Services\ExamRestrictionService::hasActiveOverride($this->student_id)) {
            $errors[] = 'Student has an active Exam Restriction due to outstanding fee defaults (Stage: ' . $defStage->stage . ').';
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
