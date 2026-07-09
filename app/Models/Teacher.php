<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Attendance;
use App\Models\ExamPaper;
use App\Models\ClassManagement;
use App\Notifications\BiometricAlertNotification;
use App\Traits\Auditable;

class Teacher extends Authenticatable
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'qualification',
        'experience_details',
        'subject_specialization',
        'designation',
        'salary',
        'date_of_joining',
        'profile_image',
        'photo',
        'status',
        'is_exam_head',
        'is_exam_cell_member',
        'employee_id',
        'password',
        'aadhar_number',
        'bank_account_number',
        'ifsc_code',
        'wing',
        'teacher_type',
        'subjects',
        'employment_type',
        'uan_number',
        'pan_number',
    ];

    protected $dates = ['date_of_birth', 'date_of_joining'];
    
    protected $casts = [
        'date_of_birth' => 'date',
        'date_of_joining' => 'date',
        'is_exam_head' => 'boolean',
        'is_exam_cell_member' => 'boolean',
    ];

    // Hidden attributes for security
    protected $hidden = [
        'password', 'remember_token'
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

    public function documents()
    {
        return $this->hasMany(TeacherDocument::class);
    }

    public function experiences()
    {
        return $this->hasMany(TeacherExperience::class);
    }

    public function leaves()
    {
        return $this->hasMany(TeacherLeave::class);
    }

    public function salaries()
    {
        return $this->hasMany(TeacherSalary::class);
    }

    public function absentSubstitutions()
    {
        return $this->hasMany(\App\Models\TeacherSubstitution::class, 'absent_teacher_id');
    }

    public function substituteSubstitutions()
    {
        return $this->hasMany(\App\Models\TeacherSubstitution::class, 'substitute_teacher_id');
    }

    public function biometricRecords()
    {
        return $this->hasMany(\App\Models\TeacherBiometricRecord::class);
    }

    public function teacherAttendances()
    {
        return $this->hasMany(\App\Models\TeacherAttendance::class, 'teacher_id');
    }

    public function sendBiometricNotification($record, $type, $message)
    {
        if ($this->user) {
            $this->user->notify(new \App\Notifications\BiometricAlertNotification($record, $type, $message));
        }
    }

    public function getUnreadBiometricNotifications()
    {
        if ($this->user) {
            return $this->user->unreadNotifications()->where('type', 'App\\Notifications\\BiometricAlertNotification');
        }
        return collect();
    }

    public function getAllBiometricNotifications()
    {
        if ($this->user) {
            return $this->user->notifications()->where('type', 'App\\Notifications\\BiometricAlertNotification');
        }
        return collect();
    }

    // Teacher Panel Relationships
    public function teacherLogins()
    {
        return $this->hasMany(\App\Models\TeacherLogin::class);
    }
    
    public function classSubjectAssignments()
    {
        return $this->hasMany(\App\Models\TeacherClassSubjectAssignment::class);
    }

    public function examHead()
    {
        return $this->hasOne(\App\Models\ExamHead::class);
    }

    public function isExamHead()
    {
        return $this->is_exam_head;
    }

    public function isExamCellMember()
    {
        return $this->is_exam_cell_member;
    }

    public function uploadedResults()
    {
        return $this->hasMany(\App\Models\Result::class, 'uploaded_by_teacher_id');
    }

    public function assignedClasses()
    {
        return $this->belongsToMany(\App\Models\SchoolClass::class, 'teacher_class_subject_assignments', 'teacher_id', 'class_id')
            ->withPivot('subject_id', 'section_id', 'is_class_teacher')
            ->select('school_classes.*')  // Select only school_classes columns
            ->distinct();
    }

    public function assignedSubjects()
    {
        return $this->belongsToMany(\App\Models\Subject::class, 'teacher_class_subject_assignments', 'teacher_id', 'subject_id')
            ->distinct();
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->name . ' (' . $this->designation . ')';
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth ? now()->diffInYears($this->date_of_birth) : null;
    }

    public function getYearsOfServiceAttribute()
    {
        if ($this->date_of_joining) {
            return $this->date_of_joining->diffInYears(now());
        }
        return 0;
    }

    // Photo helper method
    public function getPhotoUrlAttribute()
    {
        if ($this->photo && file_exists(public_path('uploads/teachers/' . $this->photo))) {
            return asset('uploads/teachers/' . $this->photo);
        }
        
        if ($this->profile_image && file_exists(public_path('uploads/teachers/' . $this->profile_image))) {
            return asset('uploads/teachers/' . $this->profile_image);
        }
        
        // Default avatar
        return asset('images/default-avatar.png');
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
            'experience_details' => 'required|string|max:500',
            'subject_specialization' => 'required|string|max:100',
            'designation' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'date_of_joining' => 'required|date',
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
            'experience_details' => 'required|string|max:500',
            'subject_specialization' => 'required|string|max:100',
            'designation' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'date_of_joining' => 'required|date',
            'status' => 'required|in:active,inactive,resigned',
            'department' => 'nullable|string|max:100',
            'employee_id' => 'nullable|string|max:50|unique:teachers,employee_id,' . $id,
            'emergency_contact' => 'nullable|digits:10',
            'emergency_contact_person' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get statistics for the teachers dashboard
     */
    public static function getStatistics()
    {
        return cache()->remember('teacher_statistics', 3600, function() {
            $total = self::count();
            $male = self::where('gender', 'male')->count();
            $female = self::where('gender', 'female')->count();
            
            // Wing-wise distribution (based on designation)
            $wingWise = [
                'primary' => [
                    'total' => 0,
                    'male' => 0,
                    'female' => 0,
                    'PRT' => 0,
                    'TGT' => 0,
                ],
                'junior' => [
                    'total' => 0,
                    'male' => 0,
                    'female' => 0,
                    'PRT' => 0,
                    'TGT' => 0,
                ],
                'senior' => [
                    'total' => 0,
                    'male' => 0,
                    'female' => 0,
                    'TGT' => 0,
                    'PGT' => 0,
                ],
            ];
            
            // Initialize typeWise array
            $typeWise = [];
            
            // Get all teachers and categorize them
            $allTeachers = self::all();
            foreach ($allTeachers as $teacher) {
                $designation = strtoupper($teacher->designation);
                $gender = $teacher->gender;
                
                // Determine wing based on designation
                if (stripos($designation, 'PRT') !== false) {
                    $wing = 'primary';
                } elseif (stripos($designation, 'TGT') !== false) {
                    // TGT can be in primary or junior, but let's assign to junior
                    $wing = 'junior';
                } elseif (stripos($designation, 'PGT') !== false) {
                    $wing = 'senior';
                } elseif (stripos($designation, 'PRIMARY') !== false) {
                    $wing = 'primary';
                } elseif (stripos($designation, 'JUNIOR') !== false) {
                    $wing = 'junior';
                } elseif (stripos($designation, 'SENIOR') !== false) {
                    $wing = 'senior';
                } else {
                    // Default assignment - could be improved based on subject or department
                    $wing = 'junior'; // Default to junior
                }
                
                $wingWise[$wing]['total']++;
                if ($gender === 'male') {
                    $wingWise[$wing]['male']++;
                } else {
                    $wingWise[$wing]['female']++;
                }
                
                if (stripos($designation, 'PRT') !== false) {
                    $wingWise[$wing]['PRT']++;
                } elseif (stripos($designation, 'TGT') !== false) {
                    $wingWise[$wing]['TGT']++;
                } elseif (stripos($designation, 'PGT') !== false) {
                    $wingWise[$wing]['PGT']++;
                }
                
                // Track type-wise distribution
                if (!isset($typeWise[$teacher->designation])) {
                    $typeWise[$teacher->designation] = [
                        'total' => 0,
                        'male' => 0,
                        'female' => 0,
                    ];
                }
                $typeWise[$teacher->designation]['total']++;
                if ($gender === 'male') {
                    $typeWise[$teacher->designation]['male']++;
                } else {
                    $typeWise[$teacher->designation]['female']++;
                }
            }
            
            // Gender by wing for charts
            $genderWingWise = [
                'primary' => [
                    'male' => $wingWise['primary']['male'],
                    'female' => $wingWise['primary']['female'],
                ],
                'junior' => [
                    'male' => $wingWise['junior']['male'],
                    'female' => $wingWise['junior']['female'],
                ],
                'senior' => [
                    'male' => $wingWise['senior']['male'],
                    'female' => $wingWise['senior']['female'],
                ],
            ];
            
            return [
                'total' => $total,
                'male' => $male,
                'female' => $female,
                'wing_wise' => $wingWise,
                'type_wise' => $typeWise,
                'gender_wing_wise' => $genderWingWise,
            ];
        });
    }
}