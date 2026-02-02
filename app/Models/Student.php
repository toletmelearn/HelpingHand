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
use App\Traits\Auditable;

class Student extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'name', 'father_name', 'mother_name', 'date_of_birth', 'aadhar_number', 
        'phone', 'gender', 'category', 'class', 'section', 'roll_number', 
        'religion', 'caste', 'blood_group', 'address', 'user_id', 'is_verified'
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

    public function documents()
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
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

    /**
     * Get statistics for the students dashboard
     */
    public static function getStatistics()
    {
        return cache()->remember('student_statistics', 3600, function() {
            $total = self::count();
            $male = self::where('gender', 'male')->count();
            $female = self::where('gender', 'female')->count();
            $other = self::where('gender', 'other')->count();

            $malePercentage = $total > 0 ? round(($male / $total) * 100, 2) : 0;
            $femalePercentage = $total > 0 ? round(($female / $total) * 100, 2) : 0;
            $otherPercentage = $total > 0 ? round(($other / $total) * 100, 2) : 0;

            // Class-wise distribution
            $classWise = self::selectRaw(' 
                class, 
                COUNT(*) as total,
                SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN gender = "other" THEN 1 ELSE 0 END) as other
            ')
            ->whereNull('deleted_at')
            ->groupBy('class')
            ->orderBy('class')
            ->get();

            // Category-wise distribution
            $categories = ['General', 'OBC', 'SC', 'ST'];
            $categoryWise = [];
            foreach ($categories as $category) {
                $categoryData = self::where('category', $category)->get();
                $categoryWise[$category] = [
                    'total' => $categoryData->count(),
                    'male' => $categoryData->where('gender', 'male')->count(),
                    'female' => $categoryData->where('gender', 'female')->count(),
                    'other' => $categoryData->where('gender', 'other')->count(),
                ];
            }

            return [
                'total' => $total,
                'male' => $male,
                'female' => $female,
                'other' => $other,
                'male_percentage' => $malePercentage,
                'female_percentage' => $femalePercentage,
                'other_percentage' => $otherPercentage,
                'gender_wise' => [
                    'male' => $male,
                    'female' => $female,
                    'other' => $other,
                ],
                'class_wise' => $classWise,
                'category_wise' => $categoryWise,
            ];
        });
    }
}