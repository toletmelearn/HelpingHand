<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = [
        'name', 'description', 'capacity', 'class_id', 'is_active'
    ];
    
    // Define relationship with students
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'section_id');
    }
    
    // Define relationship with class
    public function class()
    {
        return $this->belongsTo(ClassManagement::class, 'class_id');
    }
}