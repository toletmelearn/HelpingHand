<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonPlan;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;

class LessonPlanController extends Controller
{
    public function index()
    {
        $lessonPlans = LessonPlan::with(['teacher', 'class', 'section', 'subject'])
            ->latest()
            ->paginate(20);
        
        return view('admin.lesson-plans.index', compact('lessonPlans'));
    }

    public function create()
    {
        $teachers = User::whereHas('roles', function ($query) {
            $query->where('name', 'teacher');
        })->get();
        
        $classes = SchoolClass::all();
        $sections = Section::all();
        $subjects = Subject::all();
        
        return view('admin.lesson-plans.create', compact('teachers', 'classes', 'sections', 'subjects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'topic' => 'required|string|max:255',
            'learning_objectives' => 'required|string',
            'teaching_method' => 'nullable|string',
            'homework_classwork' => 'required|string',
            'books_notebooks_required' => 'required|string',
            'submission_assessment_notes' => 'nullable|string',
            'plan_type' => 'required|in:daily,weekly,monthly',
        ]);

        LessonPlan::create([
            'teacher_id' => $request->teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'date' => $request->date,
            'topic' => $request->topic,
            'learning_objectives' => $request->learning_objectives,
            'teaching_method' => $request->teaching_method,
            'homework_classwork' => $request->homework_classwork,
            'books_notebooks_required' => $request->books_notebooks_required,
            'submission_assessment_notes' => $request->submission_assessment_notes,
            'plan_type' => $request->plan_type,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.lesson-plans.index')
            ->with('success', 'Lesson plan created successfully!');
    }

    public function show(LessonPlan $lessonPlan)
    {
        $lessonPlan->load(['teacher', 'class', 'section', 'subject', 'createdBy', 'modifiedBy']);
        return view('admin.lesson-plans.show', compact('lessonPlan'));
    }

    public function edit(LessonPlan $lessonPlan)
    {
        $teachers = User::whereHas('roles', function ($query) {
            $query->where('name', 'teacher');
        })->get();
        
        $classes = SchoolClass::all();
        $sections = Section::all();
        $subjects = Subject::all();
        
        return view('admin.lesson-plans.edit', compact('lessonPlan', 'teachers', 'classes', 'sections', 'subjects'));
    }

    public function update(Request $request, LessonPlan $lessonPlan)
    {
        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'topic' => 'required|string|max:255',
            'learning_objectives' => 'required|string',
            'teaching_method' => 'nullable|string',
            'homework_classwork' => 'required|string',
            'books_notebooks_required' => 'required|string',
            'submission_assessment_notes' => 'nullable|string',
            'plan_type' => 'required|in:daily,weekly,monthly',
        ]);

        $lessonPlan->update([
            'teacher_id' => $request->teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'date' => $request->date,
            'topic' => $request->topic,
            'learning_objectives' => $request->learning_objectives,
            'teaching_method' => $request->teaching_method,
            'homework_classwork' => $request->homework_classwork,
            'books_notebooks_required' => $request->books_notebooks_required,
            'submission_assessment_notes' => $request->submission_assessment_notes,
            'plan_type' => $request->plan_type,
            'modified_by' => Auth::id(),
        ]);

        return redirect()->route('admin.lesson-plans.index')
            ->with('success', 'Lesson plan updated successfully!');
    }

    public function destroy(LessonPlan $lessonPlan)
    {
        $lessonPlan->delete();
        return redirect()->route('admin.lesson-plans.index')
            ->with('success', 'Lesson plan deleted successfully!');
    }

    public function compliance()
    {
        $teachers = User::whereHas('roles', function ($query) {
            $query->where('name', 'teacher');
        })->with(['lessonPlans' => function ($query) {
            $query->where('date', '>=', now()->startOfWeek());
        }])->get();

        return view('admin.lesson-plans.compliance', compact('teachers'));
    }

    public function reports()
    {
        $totalLessonPlans = LessonPlan::count();
        $weeklyPlans = LessonPlan::where('date', '>=', now()->startOfWeek())->count();
        $monthlyPlans = LessonPlan::where('date', '>=', now()->startOfMonth())->count();
        
        $teachersWithPlans = User::whereHas('roles', function ($query) {
            $query->where('name', 'teacher');
        })->withCount('lessonPlans')->get();

        return view('admin.lesson-plans.reports', compact('totalLessonPlans', 'weeklyPlans', 'monthlyPlans', 'teachersWithPlans'));
    }

    public function dashboardStats()
    {
        $pendingLessonPlans = LessonPlan::where('date', '>=', now())
            ->whereDoesntHave('createdBy', function ($query) {
                $query->where('created_by', null);
            })
            ->count();
        
        $submittedLessonPlans = LessonPlan::where('date', '<=', now())->count();
        
        $classWiseCoverage = LessonPlan::with(['class'])
            ->selectRaw('class_id, count(*) as plan_count')
            ->whereNull('deleted_at')
            ->groupBy('class_id')
            ->get();

        return view('admin.lesson-plans.dashboard-stats', compact(
            'pendingLessonPlans',
            'submittedLessonPlans',
            'classWiseCoverage'
        ));
    }

    public function exportPdf()
    {
        $lessonPlans = LessonPlan::with(['teacher', 'class', 'section', 'subject'])
            ->latest()
            ->get();

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('admin.lesson-plans.export-pdf', compact('lessonPlans'));
        return $pdf->download('lesson_plans_report_' . now()->format('Y-m-d') . '.pdf');
    }

    public function subjectProgress()
    {
        $subjectProgress = LessonPlan::with(['subject'])
            ->selectRaw('subject_id, count(*) as plan_count')
            ->groupBy('subject_id')
            ->get();

        return view('admin.lesson-plans.subject-progress', compact('subjectProgress'));
    }
}