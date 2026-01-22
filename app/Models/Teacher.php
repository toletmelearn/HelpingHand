<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Teacher extends Model
{
    use HasFactory; // Only HasFactory

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'qualification',
        'subject_specialization',
        'date_of_joining',
        'salary',
        'address',
        'aadhar_number',
        'bank_account_number',
        'ifsc_code',
        'status',
        'experience_details',
        'profile_image',
        // New fields
        'gender',
        'wing',
        'teacher_type',
        'subjects',
        'employee_id',
        'uan_number',
        'pan_number',
        'designation',
        'employment_type',
        'date_of_birth',
        'emergency_contact',
        'educational_qualification',
        'training_certificates'
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_birth' => 'date',
        'salary' => 'decimal:2',
        'subjects' => 'array'
    ];
    
    // Hash password before saving
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }
    
    // Check if password matches
    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }
    
    // Get the identifier that will be stored in the session
    public function getAuthIdentifierName()
    {
        return 'id';
    }
    
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }
    
    public function getAuthPassword()
    {
        return $this->password;
    }
    
    public function getRememberToken()
    {
        return $this->remember_token;
    }
    
    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }
    
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    // Get statistics
    public static function getStatistics()
    {
        $total = self::count();
        $male = self::where('gender', 'male')->count();
        $female = self::where('gender', 'female')->count();
        
        return [
            'total' => $total,
            'male' => $male,
            'female' => $female,
            'wing_wise' => self::getWingWiseStats(),
            'type_wise' => self::getTypeWiseStats(),
            'gender_wing_wise' => self::getGenderWingWiseStats()
        ];
    }

    // Wing-wise statistics
    public static function getWingWiseStats()
    {
        $wings = ['primary', 'junior', 'senior'];
        $stats = [];
        
        foreach ($wings as $wing) {
            $stats[$wing] = [
                'total' => self::where('wing', $wing)->count(),
                'male' => self::where('wing', $wing)->where('gender', 'male')->count(),
                'female' => self::where('wing', $wing)->where('gender', 'female')->count(),
                'PRT' => self::where('wing', $wing)->where('teacher_type', 'PRT')->count(),
                'TGT' => self::where('wing', $wing)->where('teacher_type', 'TGT')->count(),
                'PGT' => self::where('wing', $wing)->where('teacher_type', 'PGT')->count()
            ];
        }
        
        return $stats;
    }

    // Teacher type wise statistics
    public static function getTypeWiseStats()
    {
        $types = ['PRT', 'TGT', 'PGT', 'Other'];
        $stats = [];
        
        foreach ($types as $type) {
            $stats[$type] = [
                'total' => self::where('teacher_type', $type)->count(),
                'male' => self::where('teacher_type', $type)->where('gender', 'male')->count(),
                'female' => self::where('teacher_type', $type)->where('gender', 'female')->count()
            ];
        }
        
        return $stats;
    }

    // Gender and wing combined statistics
    public static function getGenderWingWiseStats()
    {
        $stats = [];
        $wings = ['primary', 'junior', 'senior'];
        
        foreach ($wings as $wing) {
            $stats[$wing] = [
                'male' => self::where('wing', $wing)->where('gender', 'male')->count(),
                'female' => self::where('wing', $wing)->where('gender', 'female')->count(),
                'other' => self::where('wing', $wing)->where('gender', 'other')->count()
            ];
        }
        
        return $stats;
    }

    // Calculate age
    public function getAgeAttribute()
    {
        if ($this->date_of_birth) {
            return \Carbon\Carbon::parse($this->date_of_birth)->age;
        }
        return null;
    }
    // Add this method to your Teacher class
public static function getAllCount()
{
    return self::withoutGlobalScopes()->count();
}

public static function getActiveCount() 
{
    return self::withoutGlobalScopes()->where('status', 'active')->count();
}
public static function storeRules()
{
    return [
        'name'        => 'required|string|max:255',
        'email'       => 'required|email|unique:teachers,email',
        'phone'       => 'required|digits:10',
        'designation' => 'required|string|max:100',
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'date_of_joining' => 'nullable|date',
        'date_of_birth' => 'nullable|date',
        'salary' => 'nullable|numeric|min:0',
        'subjects' => 'nullable|array',
        'subjects.*' => 'string|max:100',
        'aadhar_number' => 'nullable|digits:12',
        'bank_account_number' => 'nullable|string|max:64',
        'ifsc_code' => 'nullable|string|max:20',
        'pan_number' => 'nullable|string|max:20',
    ];
}

public static function updateRules($id)
{
    return [
        'name'        => 'required|string|max:255',
        'email'       => 'required|email|unique:teachers,email,' . $id,
        'phone'       => 'required|digits:10',
        'designation' => 'required|string|max:100',
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'date_of_joining' => 'nullable|date',
        'date_of_birth' => 'nullable|date',
        'salary' => 'nullable|numeric|min:0',
        'subjects' => 'nullable|array',
        'subjects.*' => 'string|max:100',
        'aadhar_number' => 'nullable|digits:12',
        'bank_account_number' => 'nullable|string|max:64',
        'ifsc_code' => 'nullable|string|max:20',
        'pan_number' => 'nullable|string|max:20',
    ];
}
    
    // Define relationship with classes
    public function classes()
    {
        return $this->belongsToMany(ClassManagement::class, 'class_teacher', 'teacher_id', 'class_id');
    }
    
    // Define relationship with attendance
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}