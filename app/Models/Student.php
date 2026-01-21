<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'father_name', 
        'mother_name',
        'date_of_birth',
        'aadhar_number',
        'address',
        'phone',
        // New fields
        'gender',
        'category',
        'class',
        'section',
        'roll_number',
        'religion',
        'caste',
        'blood_group',
        'nationality',
        'medical_history',
        'previous_school'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    // Get all unique classes
    public static function getClasses()
    {
        return self::distinct()->orderBy('class')->pluck('class');
    }

    // Get students by class
    public static function getByClass($class)
    {
        return self::where('class', $class)->orderBy('roll_number')->get();
    }

    // Get statistics
    public static function getStatistics()
    {
        $total = self::count();
        $male = self::where('gender', 'male')->count();
        $female = self::where('gender', 'female')->count();
        $other = self::where('gender', 'other')->count();
        
        return [
            'total' => $total,
            'male' => $male,
            'female' => $female,
            'other' => $other,
            'male_percentage' => $total > 0 ? round(($male/$total)*100, 2) : 0,
            'female_percentage' => $total > 0 ? round(($female/$total)*100, 2) : 0,
            'class_wise' => self::getClassWiseStats(),
            'category_wise' => self::getCategoryWiseStats(),
            'gender_wise' => self::getGenderWiseStats()
        ];
    }

    // Class-wise statistics
    public static function getClassWiseStats()
    {
        $classes = self::selectRaw('class, COUNT(*) as total')
            ->groupBy('class')
            ->orderBy('class')
            ->get()
            ->map(function($item) {
                $item->male = self::where('class', $item->class)->where('gender', 'male')->count();
                $item->female = self::where('class', $item->class)->where('gender', 'female')->count();
                $item->other = self::where('class', $item->class)->where('gender', 'other')->count();
                return $item;
            });
        
        return $classes;
    }

    // Category-wise statistics
    public static function getCategoryWiseStats()
    {
        $categories = ['General', 'OBC', 'SC', 'ST'];
        $stats = [];
        
        foreach ($categories as $category) {
            $stats[$category] = [
                'total' => self::where('category', $category)->count(),
                'male' => self::where('category', $category)->where('gender', 'male')->count(),
                'female' => self::where('category', $category)->where('gender', 'female')->count(),
                'other' => self::where('category', $category)->where('gender', 'other')->count()
            ];
        }
        
        return $stats;
    }

    // Gender-wise statistics
    public static function getGenderWiseStats()
    {
        return [
            'male' => self::where('gender', 'male')->count(),
            'female' => self::where('gender', 'female')->count(),
            'other' => self::where('gender', 'other')->count()
        ];
    }
    
}