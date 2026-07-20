<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Section;
use App\Models\ClassManagement;

class TeacherSectionController extends Controller
{
    /**
     * Get sections for a specific class
     */
    public function getSectionsByClass($classId)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin->teacher;

        // Get the class management record
        $classManagement = ClassManagement::find($classId);
        
        if (!$classManagement) {
            return '<option value="">All Sections</option>';
        }

        // Get sections associated with this specific class
        $sections = $classManagement->sections;

        $options = '<option value="">All Sections</option>';
        foreach ($sections as $section) {
            $options .= '<option value="' . $section->id . '"' . '>' . $section->name . '</option>';
        }
        
        return $options;
    }
}