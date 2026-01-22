<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Attendance;
use App\Models\ExamPaper;
use App\Models\ClassManagement;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'address', 'date_of_birth', 'gender', 'qualification', 
        'experience_years', 'subject_specialization', 'designation', 'salary', 'joining_date', 
        'profile_image', 'status', 'department', 'employee_id', 'emergency_contact', 
        'emergency_contact_person', 'password'
    ];

    protected $dates = ['date_of_birth', 'joining_date'];

    // Hidden attributes for security
    protected $hidden = [
        'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'
    ];

    // Append calculated attributes
    protected $appends = ['full_name', 'age', 'years_of_service'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'marked_by');
    }

    public function examPapers()
    {
        return $this->hasMany(ExamPaper::class, 'uploaded_by');
    }

    public function classes()
    {
        return $this->belongsToMany(ClassManagement::class, 'class_teacher', 'teacher_id', 'class_id');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->name . ' (' . $this->designation . ')';
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    public function getYearsOfServiceAttribute()
    {
        if ($this->joining_date) {
            return $this->joining_date->diffInYears(now());
        }
        return 0;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereNull('deleted_at');
    }

    public function scopeBySubject($query, $subject)
    {
        return $query->where('subject_specialization', $subject);
    }

    // Password handling
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }

    // Validation rules
    public static function storeRules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email',
            'phone' => 'required|digits:10',
            'address' => 'required|string',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'qualification' => 'required|string|max:100',
            'experience_years' => 'required|integer|min:0',
            'subject_specialization' => 'required|string|max:100',
            'designation' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'joining_date' => 'required|date',
            'status' => 'required|in:active,inactive,resigned',
            'department' => 'nullable|string|max:100',
            'employee_id' => 'nullable|string|max:50|unique:teachers,employee_id',
            'emergency_contact' => 'nullable|digits:10',
            'emergency_contact_person' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public static function updateRules($id)
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email,' . $id,
            'phone' => 'required|digits:10',
            'address' => 'required|string',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'qualification' => 'required|string|max:100',
            'experience_years' => 'required|integer|min:0',
            'subject_specialization' => 'required|string|max:100',
            'designation' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'joining_date' => 'required|date',
            'status' => 'required|in:active,inactive,resigned',
            'department' => 'nullable|string|max:100',
            'employee_id' => 'nullable|string|max:50|unique:teachers,employee_id,' . $id,
            'emergency_contact' => 'nullable|digits:10',
            'emergency_contact_person' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}