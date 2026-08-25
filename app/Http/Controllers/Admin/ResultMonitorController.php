<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultMonitorController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Result::class);

        $exam = DB::table('exams')->latest()->first();

        $data = DB::table('teacher_class_subject_assignments as t')
            ->join('teachers','teachers.id','=','t.teacher_id')
            ->join('school_classes as classes','classes.id','=','t.class_id')
            ->join('subjects','subjects.id','=','t.subject_id')
            ->leftJoin('sections', 'sections.id', '=', 't.section_id') // Join with sections if available
            ->leftJoin('results',function($join) use ($exam){
                $join->on('results.class_id','=','t.class_id')
                    ->on('results.subject','=','subjects.name')
                    ->where('results.exam_id','=',$exam->id ?? 0);
            })
            ->select(
                'classes.name as class',
                DB::raw('COALESCE(sections.name, "All") as section'), // Use section name or "All" if not specified
                'subjects.name as subject',
                'teachers.name as teacher',
                DB::raw('COUNT(results.id) as total_marks')
            )
            ->groupBy(
                'classes.name',
                'sections.name',
                'subjects.name',
                'teachers.name'
            )
            ->get();

        return view('admin.exams.result-monitor',compact('data','exam'));
    }

    public function classResultsView()
    {
        $this->authorize('viewAny', Result::class);

        $classes = DB::table('school_classes')->get();
        $exams = DB::table('exams')->get();
        
        return view('admin.exams.class-results',compact('classes','exams'));
    }

    public function classResults()
    {
        $this->authorize('viewAny', Result::class);

        $classes = DB::table('school_classes')->get();
        $exams = DB::table('exams')->get();
        
        $selectedExamId = request('exam_id');
        $selectedClassId = request('class_id');
        
        $results = collect();
        
        if ($selectedExamId && $selectedClassId) {
            $results = DB::table('results')
                ->join('students', 'results.student_id', '=', 'students.id')
                ->where('results.exam_id', $selectedExamId)
                ->where('results.class_id', $selectedClassId)
                ->select(
                    'students.name as student_name',
                    'students.roll_number',
                    'results.subject',
                    'results.marks_obtained',
                    'results.total_marks',
                    'results.percentage',
                    'results.grade'
                )
                ->get();
        }

        return view('admin.exams.class-results',compact('classes','exams','results'));
    }
    
    public function getResultStatus(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        $examId = $request->input('exam_id');
        
        $data = DB::table('teacher_class_subject_assignments as t')
            ->join('teachers','teachers.id','=','t.teacher_id')
            ->join('school_classes as classes','classes.id','=','t.class_id')
            ->join('subjects','subjects.id','=','t.subject_id')
            ->leftJoin('sections', 'sections.id', '=', 't.section_id')
            ->leftJoin('results',function($join) use ($examId){
                $join->on('results.class_id','=','t.class_id')
                    ->on('results.subject','=','subjects.name')
                    ->where('results.exam_id','=',$examId);
            })
            ->select(
                'classes.name as class',
                DB::raw('COALESCE(sections.name, "All") as section'),
                'subjects.name as subject',
                'teachers.name as teacher',
                DB::raw('COUNT(results.id) as total_marks')
            )
            ->groupBy(
                'classes.name',
                'sections.name',
                'subjects.name',
                'teachers.name'
            )
            ->get();

        return response()->json($data);
    }
}