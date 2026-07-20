<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMarksController extends Controller
{
    public function index()
    {
        $marks = DB::table('results as r')
            ->join('exams as e', 'r.exam_id', '=', 'e.id')
            ->join('students as s', 'r.student_id', '=', 's.id')
            ->join('school_classes as c', 'r.class_id', '=', 'c.id')
            ->join('subjects as sub', 'r.subject_id', '=', 'sub.id')
            ->join('teachers as t', 'r.uploaded_by_teacher_id', '=', 't.id')
            ->select(
                'c.name as class_name',
                'sub.name as subject_name',
                't.name as teacher_name',
                's.name as student_name',
                's.roll_number',
                'r.marks_obtained',
                'r.total_marks',
                'r.percentage',
                'r.grade',
                'e.name as exam_name',
                'r.created_at'
            )
            ->orderBy('r.created_at', 'desc')
            ->paginate(20);

        return view('admin.exams.marks-index', compact('marks'));
    }
}