<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Guardian;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\Result;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'father_name', 'mother_name', 'date_of_birth', 'aadhar_number', 
        'phone', 'gender', 'category', 'class', 'section', 'roll_number', 
        'religion', 'caste', 'blood_group', 'address', 'user_id'
    ];

    protected $dates = ['date_of_birth'];

    // Hidden attributes for security
    protected $hidden = [
        'created_at', 'updated_at', 'deleted_at'
    ];

    // Append calculated attributes
    protected $appends = ['full_name', 'age'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guardian()
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian', 'student_id', 'guardian_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->name . ' (' . $this->roll_number . ')';
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeInClass($query, $class)
    {
        return $query->where('class', $class);
    }
}