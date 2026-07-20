<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Traits\Auditable;

class LessonPlan extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'teacher_id',
        'class_id',
        'section_id',
        'subject_id',
        'title',
        'date',
        'topic',
        'learning_objectives',
        'teaching_method',
        'homework_classwork',
        'books_notebooks_required',
        'submission_assessment_notes',
        'plan_type',
        'start_date',
        'end_date',
        'full_content',
        'parent_visible_content',
        'show_to_parents',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'show_to_parents' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function scopeForParents($query)
    {
        return $query->where('show_to_parents', 1);
    }

    public function scopeForStudentClass($query, $schoolClassId)
    {
        return $query->where('class_id', $schoolClassId);
    }
}