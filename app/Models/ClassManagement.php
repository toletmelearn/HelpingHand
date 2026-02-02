<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Traits\Auditable;

class ClassManagement extends Model
{
    use Auditable;
    
    protected $fillable = [
        'name', 'section', 'stream', 'capacity', 'description', 'is_active'
    ];
    
    // Define relationship with students
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_name', 'name');
    }
    
    // Define relationship with teachers assigned to this class
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'class_teacher', 'class_id', 'teacher_id');
    }
    
    // Define relationship with sections assigned to this class
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'class_sections', 'class_management_id', 'section_id')
                    ->withPivot('assigned_at')
                    ->withTimestamps();
    }
    
    // Define relationship with subjects assigned to this class
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject_assignments', 'class_id', 'subject_id')
                    ->withPivot('teacher_id', 'assigned_at')
                    ->withTimestamps();
    }
}
