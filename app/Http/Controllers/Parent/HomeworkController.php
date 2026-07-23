<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\HomeworkNotice;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeworkController extends Controller
{
    public function index()
    {
        $parent = auth('parent')->user();

        if (!$parent) {
            abort(403, 'Parent not logged in');
        }

        if (!$parent->student_id) {
            return view('parent.homework.index', [
                'homeworks' => collect()
            ]);
        }

        $student = Student::find($parent->student_id);

        if (!$student) {
            return view('parent.homework.index', [
                'homeworks' => collect()
            ]);
        }

        $homeworks = \App\Models\HomeworkNotice::where('class_id', $student->school_class_id)
            ->where('type', 'homework')
            ->where('visible_to_parent', 1)
            ->latest()
            ->paginate(20);

        return view('parent.homework.index', [
            'homeworks' => $homeworks ?? collect()
        ]);
    }
    
    public function show(HomeworkNotice $homeworkNotice)
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            abort(403, 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        // CRITICAL SECURITY FIX: Verify homework belongs to student's class
        if ($homeworkNotice->class_id != $student->school_class_id || $homeworkNotice->type != 'homework') {
            abort(403, 'Unauthorized access to this homework.');
        }

        return view('parent.homework.show', compact('homeworkNotice'));
    }
}