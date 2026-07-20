<?php

namespace App\Services;

use App\Models\TeacherClassSubjectAssignment;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;

class TeacherAcademicService
{
    /**
     * Get comprehensive teacher academic data from single source
     * This is the ONE TRUE SOURCE for all teacher academic information
     * 
     * @param int $teacherId
     * @param bool $useCache
     * @return array
     */
    public static function getTeacherAcademicData($teacherId, $useCache = true)
    {
        $cacheKey = "teacher_academic_data_{$teacherId}";
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // Get all assignments with proper relationships
        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->with(['schoolClass', 'section', 'subject'])
            ->get();
        
        // Group by class for organized data
        $groupedData = $assignments->groupBy('class_id')->map(function ($classAssignments) {
            $first = $classAssignments->first();
            
            // Get student count for this class
            $studentCount = \App\Models\Student::where('class_id', $first->class_id)->count();
            
            // Get primary subject teacher status for each subject
            $primarySubjectTeachers = $classAssignments->pluck('is_primary_subject_teacher');
            
            return [
                'class' => $first->schoolClass,
                'section' => $first->section,
                'subjects' => $classAssignments->pluck('subject'),
                'subject_ids' => $classAssignments->pluck('subject_id'),
                'is_class_teacher' => $classAssignments->contains('is_class_teacher', true),
                'is_primary_subject_teacher' => $classAssignments->contains('is_primary_subject_teacher', true),
                'primary_subject_teachers' => $primarySubjectTeachers,
                'student_count' => $studentCount,
                'total_assignments' => $classAssignments->count(),
                'assignment_ids' => $classAssignments->pluck('id'),
            ];
        });
        
        // Prepare flat list for dashboard
        $flatAssignments = $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass->name,
                'section_id' => $assignment->section_id,
                'section_name' => $assignment->section->name ?? 'All',
                'subject_id' => $assignment->subject_id,
                'subject_name' => $assignment->subject->name,
                'is_class_teacher' => $assignment->is_class_teacher,
                'is_primary_subject_teacher' => $assignment->is_primary_subject_teacher,
            ];
        });
        
        $result = [
            'teacher_id' => $teacherId,
            'total_classes' => $groupedData->count(),
            'total_subjects' => $assignments->unique('subject_id')->count(),
            'total_assignments' => $assignments->count(),
            'class_teacher_of' => $groupedData->filter(function ($data) {
                return $data['is_class_teacher'];
            })->values(),
            'grouped_by_class' => $groupedData,
            'flat_assignments' => $flatAssignments,
            'class_ids' => $assignments->pluck('class_id')->unique()->values(),
            'subject_ids' => $assignments->pluck('subject_id')->unique()->values(),
        ];
        
        // Cache for 15 minutes
        if ($useCache) {
            Cache::put($cacheKey, $result, 900);
        }
        
        return $result;
    }
    
    /**
     * Get teacher's assigned classes only
     * 
     * @param int $teacherId
     * @return \Illuminate\Support\Collection
     */
    public static function getTeacherClasses($teacherId)
    {
        $data = self::getTeacherAcademicData($teacherId);
        return $data['grouped_by_class'];
    }
    
    /**
     * Get teacher's assigned subjects only
     * 
     * @param int $teacherId
     * @return \Illuminate\Support\Collection
     */
    public static function getTeacherSubjects($teacherId)
    {
        $data = self::getTeacherAcademicData($teacherId);
        return $data['flat_assignments']->pluck('subject_name', 'subject_id')->unique();
    }
    
    /**
     * Check if teacher is class teacher of specific class
     * 
     * @param int $teacherId
     * @param int $classId
     * @return bool
     */
    public static function isClassTeacher($teacherId, $classId)
    {
        $data = self::getTeacherAcademicData($teacherId);
        return $data['grouped_by_class']->has($classId) && 
               $data['grouped_by_class'][$classId]['is_class_teacher'];
    }
    
    /**
     * Get class teacher classes
     * 
     * @param int $teacherId
     * @return \Illuminate\Support\Collection
     */
    public static function getClassTeacherClasses($teacherId)
    {
        $data = self::getTeacherAcademicData($teacherId);
        return $data['class_teacher_of'];
    }
    
    /**
     * Clear teacher academic cache
     * 
     * @param int $teacherId
     */
    public static function clearTeacherCache($teacherId)
    {
        Cache::forget("teacher_academic_data_{$teacherId}");
    }
    
    /**
     * Clear all teacher academic caches
     */
    public static function clearAllTeacherCaches()
    {
        // This would need a more sophisticated approach in production
        // For now, we'll clear the entire cache
        Cache::flush();
    }
}