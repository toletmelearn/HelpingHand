<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\TeacherAcademicService;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\Student;
use App\Models\SchoolClass;

class TeacherClassController extends Controller
{
    /**
     * Display list of classes assigned to the teacher using single source.
     */
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacherId = $teacherLogin ? $teacherLogin->teacher_id : null;
        
        if (!$teacherId) {
            return redirect()->back()->with('error', 'Teacher not logged in.');
        }

        // Fetch assignments with joins
        $assignments = DB::table('teacher_class_subject_assignments')
            ->leftJoin('school_classes', 'school_classes.id', '=', 'teacher_class_subject_assignments.class_id')
            ->leftJoin('sections', 'sections.id', '=', 'teacher_class_subject_assignments.section_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'teacher_class_subject_assignments.subject_id')
            ->where('teacher_class_subject_assignments.teacher_id', $teacherId)
            ->select(
                'school_classes.name as class_name',
                'sections.name as section_name',
                'subjects.name as subject_name',
                'teacher_class_subject_assignments.is_class_teacher',
                'teacher_class_subject_assignments.class_id',
                'teacher_class_subject_assignments.subject_id'
            )
            ->get();

        // Group by class
        $classesData = $assignments->groupBy('class_id')->map(function ($classAssignments) {
            $first = $classAssignments->first();
            return [
                'class_id' => $first->class_id,
                'class_name' => $first->class_name,
                'section_name' => $first->section_name,
                'subjects' => $classAssignments->pluck('subject_name'),
                'is_class_teacher' => $classAssignments->contains('is_class_teacher', true),
                'student_count' => 0 // Will be updated later
            ];
        });

        return view('teacher.classes.index', compact('classesData'));
    }

    /**
     * Display students in a specific class.
     */
    public function students($classId)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        
        if (!$teacherLogin) {
            return redirect()->back()->with('error', 'Teacher not logged in.');
        }
        
        $teacher = $teacherLogin->teacher;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }

        // Verify teacher has access to this class
        // Check both cached academic data AND direct database query for newly assigned classes
        $academicData = TeacherAcademicService::getTeacherAcademicData($teacher->id);
        
        // If not in cached data, check direct database query
        $hasAccess = $academicData['class_ids']->contains($classId);
        
        if (!$hasAccess) {
            // Check direct database query for newly assigned classes
            $directAssignment = \DB::table('teacher_class_subject_assignments')
                ->where('teacher_id', $teacher->id)
                ->where('class_id', $classId)
                ->exists();
            
            if (!$directAssignment) {
                abort(403, 'You do not have access to this class.');
            }
            
            // Clear cache for this teacher to refresh data
            \App\Services\TeacherAcademicService::clearTeacherCache($teacher->id);
        }

        $class = SchoolClass::findOrFail($classId);

        // Get students
        $students = Student::where('school_class_id', $classId)
            ->orderBy('roll_number')
            ->get();

        return view('teacher.classes.students', compact('class', 'students'));
    }
}
