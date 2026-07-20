<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class CBSEResult extends Model
{
    use HasFactory;

    protected $table = 'cbse_results';

    protected $fillable = [
        'student_id',
        'exam_id',
        'subject_id',
        'pt_marks',
        'notebook_marks',
        'sea_marks',
        'exam_marks',
        'total_marks',
        'percentage',
        'grade',
        'result_status',
        'academic_year',
        'term',
        'remarks',
        'class_rank',
        'section_rank',
        'is_locked',
        'locked_by',
        'locked_at',
        'generated_by',
        'generated_at',
        'updated_by'
    ];

    protected $casts = [
        'pt_marks' => 'decimal:2',
        'notebook_marks' => 'decimal:2',
        'sea_marks' => 'decimal:2',
        'exam_marks' => 'decimal:2',
        'total_marks' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_locked' => 'boolean',
        'class_rank' => 'integer',
        'section_rank' => 'integer',
        'locked_at' => 'datetime',
        'generated_at' => 'datetime'
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForExam($query, $examId)
    {
        return $query->where('exam_id', $examId);
    }

    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopePassed($query)
    {
        return $query->where('result_status', 'pass');
    }

    public function scopeFailed($query)
    {
        return $query->where('result_status', 'fail');
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->student->name ?? '';
    }

    public function getClassNameAttribute()
    {
        return $this->student->class_name ?? '';
    }

    public function getSectionNameAttribute()
    {
        return $this->student->section ?? '';
    }

    // Mutators
    public function setPtMarksAttribute($value)
    {
        $this->attributes['pt_marks'] = $value;
        $this->autoCalculate();
    }

    public function setNotebookMarksAttribute($value)
    {
        $this->attributes['notebook_marks'] = $value;
        $this->autoCalculate();
    }

    public function setSeaMarksAttribute($value)
    {
        $this->attributes['sea_marks'] = $value;
        $this->autoCalculate();
    }

    public function setExamMarksAttribute($value)
    {
        $this->attributes['exam_marks'] = $value;
        $this->autoCalculate();
    }

    // Auto calculation methods
    public function autoCalculate()
    {
        // Calculate total marks
        $this->attributes['total_marks'] = 
            ($this->pt_marks ?? 0) +
            ($this->notebook_marks ?? 0) +
            ($this->sea_marks ?? 0) +
            ($this->exam_marks ?? 0);

        // Calculate percentage (assuming max marks is 100, adjust as needed)
        $maxMarks = 100; // This should come from exam configuration
        $this->attributes['percentage'] = $maxMarks > 0 ? 
            round(($this->total_marks / $maxMarks) * 100, 2) : 0;

        // Determine grade
        $this->attributes['grade'] = $this->determineGrade();

        // Determine result status
        $this->attributes['result_status'] = $this->percentage >= 33 ? 'pass' : 'fail';
    }

    public function determineGrade()
    {
        $percentage = $this->percentage;

        if ($percentage >= 90) {
            return 'A1';
        } elseif ($percentage >= 80) {
            return 'A2';
        } elseif ($percentage >= 70) {
            return 'B1';
        } elseif ($percentage >= 60) {
            return 'B2';
        } elseif ($percentage >= 50) {
            return 'C';
        } elseif ($percentage >= 40) {
            return 'D';
        } elseif ($percentage >= 33) {
            return 'E';
        } else {
            return 'F';
        }
    }

    // Locking methods
    public function lock()
    {
        if (!$this->is_locked) {
            $this->is_locked = true;
            $this->locked_by = Auth::id();
            $this->locked_at = now();
            $this->save();
        }
    }

    public function unlock()
    {
        if ($this->is_locked) {
            $this->is_locked = false;
            $this->locked_by = null;
            $this->locked_at = null;
            $this->save();
        }
    }

    public function isEditable()
    {
        return !$this->is_locked;
    }

    // Audit trail
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->generated_by = Auth::id();
            $model->generated_at = now();
            $model->updated_by = Auth::id();
        });

        static::updating(function ($model) {
            $model->updated_by = Auth::id();
        });
    }

    // Helper methods for report card
    public function getFormattedData()
    {
        return [
            'subject' => $this->subject->name ?? '',
            'pt_marks' => $this->pt_marks,
            'notebook_marks' => $this->notebook_marks,
            'sea_marks' => $this->sea_marks,
            'exam_marks' => $this->exam_marks,
            'total_marks' => $this->total_marks,
            'percentage' => $this->percentage,
            'grade' => $this->grade,
            'result_status' => $this->result_status,
            'remarks' => $this->remarks
        ];
    }
}