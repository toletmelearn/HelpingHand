<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExamPaper extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subject',
        'class_section',
        'exam_type',           // Mid-term, Final, Unit Test, Quiz, etc.
        'academic_year',
        'semester',
        'paper_type',          // Question Paper, Answer Key, Solution, Syllabus
        'file_path',
        'file_name',
        'file_size',
        'file_extension',
        'uploaded_by',
        'is_published',
        'is_answer_key',
        'marks_distribution',
        'duration_minutes',
        'total_marks',
        'instructions',
        'exam_date',
        'exam_time',
        'created_by',
        'approved_by',
        'is_approved',
        'download_count',
        'access_level',        // public, teachers_only, students_only, private
        'allowed_classes',     // JSON array of classes allowed to access
        'password_protected',
        'access_password',
        'valid_from',
        'valid_until',
        'metadata'             // Additional metadata as JSON
    ];

    protected $casts = [
        'exam_date' => 'date',
        'exam_time' => 'datetime:H:i',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_published' => 'boolean',
        'is_answer_key' => 'boolean',
        'is_approved' => 'boolean',
        'password_protected' => 'boolean',
        'download_count' => 'integer',
        'duration_minutes' => 'integer',
        'total_marks' => 'integer',
        'allowed_classes' => 'array',
        'marks_distribution' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Exam types
    const EXAM_TYPE_MIDTERM = 'Mid-term';
    const EXAM_TYPE_FINAL = 'Final';
    const EXAM_TYPE_UNIT_TEST = 'Unit Test';
    const EXAM_TYPE_QUIZ = 'Quiz';
    const EXAM_TYPE_ASSIGNMENT = 'Assignment';
    const EXAM_TYPE_PROJECT = 'Project';
    const EXAM_TYPE_PRACTICAL = 'Practical';

    // Paper types
    const PAPER_TYPE_QUESTION = 'Question Paper';
    const PAPER_TYPE_ANSWER_KEY = 'Answer Key';
    const PAPER_TYPE_SOLUTION = 'Solution';
    const PAPER_TYPE_SYLLABUS = 'Syllabus';
    const PAPER_TYPE_SAMPLE = 'Sample Paper';
    const PAPER_TYPE_PREVIOUS_YEAR = 'Previous Year';

    // Access levels
    const ACCESS_PUBLIC = 'public';
    const ACCESS_TEACHERS_ONLY = 'teachers_only';
    const ACCESS_STUDENTS_ONLY = 'students_only';
    const ACCESS_PRIVATE = 'private';

    // Relationships
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeBySubject($query, $subject)
    {
        return $query->where('subject', $subject);
    }

    public function scopeByClass($query, $classSection)
    {
        return $query->where('class_section', $classSection);
    }

    public function scopeByExamType($query, $examType)
    {
        return $query->where('exam_type', $examType);
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    public function scopeByPaperType($query, $paperType)
    {
        return $query->where('paper_type', $paperType);
    }

    public function scopeAccessible($query)
    {
        return $query->where('is_published', true)
                    ->where('is_approved', true)
                    ->where(function($q) {
                        $q->whereNull('valid_until')
                          ->orWhere('valid_until', '>', now());
                    });
    }

    // Helper methods
    public function getFullFilePath()
    {
        return Storage::disk('public')->path($this->file_path);
    }

    public function getFileUrl()
    {
        return Storage::url($this->file_path);
    }

    public function getFileSizeFormatted()
    {
        $size = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($size >= 1024 && $unit < 3) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }

    public function isExpired()
    {
        if ($this->valid_until) {
            return now()->greaterThan($this->valid_until);
        }
        return false;
    }

    public function isValid()
    {
        return $this->is_published && $this->is_approved && !$this->isExpired();
    }

    public function canBeAccessedBy($user = null)
    {
        // Check if paper is valid
        if (!$this->isValid()) {
            return false;
        }

        // Check access level
        switch ($this->access_level) {
            case self::ACCESS_PUBLIC:
                return true;
            
            case self::ACCESS_TEACHERS_ONLY:
                return $user && $user->hasRole('teacher');
            
            case self::ACCESS_STUDENTS_ONLY:
                return $user && $user->hasRole('student');
            
            case self::ACCESS_PRIVATE:
                return $user && ($user->id == $this->created_by || $user->hasRole('admin'));
            
            default:
                return true;
        }
    }

    public static function getAvailableForClass($classSection, $academicYear = null)
    {
        $query = self::published()
                    ->approved()
                    ->where('class_section', $classSection);

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query->orderBy('exam_date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    public static function getBySubjectAndClass($subject, $classSection, $academicYear = null)
    {
        $query = self::where('subject', $subject)
                    ->where('class_section', $classSection);

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query->orderBy('exam_date', 'desc')
                    ->orderBy('exam_type')
                    ->get();
    }

    public static function getRecentPapers($limit = 10)
    {
        return self::published()
                   ->approved()
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    public static function getUpcomingExams($days = 30)
    {
        return self::published()
                   ->approved()
                   ->where('exam_date', '>=', now())
                   ->where('exam_date', '<=', now()->addDays($days))
                   ->orderBy('exam_date')
                   ->get();
    }

    public function getExamTypeInfo()
    {
        $colors = [
            self::EXAM_TYPE_MIDTERM => 'warning',
            self::EXAM_TYPE_FINAL => 'danger',
            self::EXAM_TYPE_UNIT_TEST => 'primary',
            self::EXAM_TYPE_QUIZ => 'info',
            self::EXAM_TYPE_ASSIGNMENT => 'secondary',
            self::EXAM_TYPE_PROJECT => 'success',
            self::EXAM_TYPE_PRACTICAL => 'dark'
        ];

        return [
            'type' => $this->exam_type,
            'color' => $colors[$this->exam_type] ?? 'light',
            'icon' => $this->getExamTypeIcon()
        ];
    }

    private function getExamTypeIcon()
    {
        switch ($this->exam_type) {
            case self::EXAM_TYPE_MIDTERM:
                return 'bi-journal-bookmark';
            case self::EXAM_TYPE_FINAL:
                return 'bi-award';
            case self::EXAM_TYPE_UNIT_TEST:
                return 'bi-check-circle';
            case self::EXAM_TYPE_QUIZ:
                return 'bi-question-circle';
            case self::EXAM_TYPE_ASSIGNMENT:
                return 'bi-file-earmark-text';
            case self::EXAM_TYPE_PROJECT:
                return 'bi-diagram-3';
            case self::EXAM_TYPE_PRACTICAL:
                return 'bi-beaker';
            default:
                return 'bi-file-earmark';
        }
    }

    public function getPaperTypeBadge()
    {
        $badges = [
            self::PAPER_TYPE_QUESTION => 'primary',
            self::PAPER_TYPE_ANSWER_KEY => 'success',
            self::PAPER_TYPE_SOLUTION => 'info',
            self::PAPER_TYPE_SYLLABUS => 'warning',
            self::PAPER_TYPE_SAMPLE => 'secondary',
            self::PAPER_TYPE_PREVIOUS_YEAR => 'dark'
        ];

        return $badges[$this->paper_type] ?? 'light';
    }
    
    // Define relationship with exams
    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }
}