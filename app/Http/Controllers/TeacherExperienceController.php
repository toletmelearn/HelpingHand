<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Http\Request;

class TeacherExperienceController extends Controller
{
    public function index()
    {
        $teachers = Teacher::all();
        $experienceStats = $this->calculateExperienceStats($teachers);
        
        return view('teachers.experience', compact('teachers', 'experienceStats'));
    }
    
    private function calculateExperienceStats($teachers)
    {
        $stats = [
            'total' => $teachers->count(),
            'by_range' => [
                '0-5' => 0,
                '6-10' => 0,
                '11-15' => 0,
                '16-20' => 0,
                '20+' => 0
            ],
            'average_experience' => 0,
            'total_experience_months' => 0,
            'retiring_soon' => 0,
            'new_teachers' => 0
        ];
        
        $totalMonths = 0;
        
        foreach ($teachers as $teacher) {
            $expMonths = $teacher->experience_in_months;
            $totalMonths += $expMonths;
            
            $expYears = $expMonths / 12;
            
            // Count by range
            if ($expYears <= 5) {
                $stats['by_range']['0-5']++;
                if ($expYears < 1) $stats['new_teachers']++;
            } elseif ($expYears <= 10) {
                $stats['by_range']['6-10']++;
            } elseif ($expYears <= 15) {
                $stats['by_range']['11-15']++;
            } elseif ($expYears <= 20) {
                $stats['by_range']['16-20']++;
            } else {
                $stats['by_range']['20+']++;
            }
            
            // Check retirement (within 5 years)
            if ($teacher->years_until_retirement !== null && $teacher->years_until_retirement <= 5) {
                $stats['retiring_soon']++;
            }
        }
        
        $stats['average_experience'] = $stats['total'] > 0 ? round($totalMonths / $stats['total'] / 12, 1) : 0;
        $stats['total_experience_months'] = $totalMonths;
        
        return $stats;
    }
    
    public function generateExperienceCertificate($id)
    {
        $teacher = Teacher::findOrFail($id);
        
        return view('teachers.certificate', compact('teacher'));
    }
}