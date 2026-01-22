<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    protected $fillable = [
        'name', 'relationship', 'phone', 'email', 'occupation', 
        'address', 'aadhar_number', 'is_primary', 'is_active'
    ];
    
    // Define relationship with students
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian', 'guardian_id', 'student_id');
    }
}
