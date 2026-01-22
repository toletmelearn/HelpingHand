<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClassManagement extends Model
{
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
}
