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
    
    protected $table = 'class_management';
    
    protected $fillable = [
        'name', 'order', 'section', 'stream', 'capacity', 'description', 'is_active'
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
    
    // subjects() moved to SchoolClass (see A3) -- class_subject_assignments.class_id
    // has always had a real DB foreign key to school_classes, not class_management,
    // so this relationship was silently matching against the wrong id space.

    // Scope for ordering classes properly
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }
    
    // Get the proper display name for the class
    public function getDisplayNameAttribute()
    {
        if ($this->section) {
            return $this->name . ' (' . $this->section . ')';
        }
        return $this->name;
    }
}
