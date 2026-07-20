<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\TeacherAcademicService;

class TeacherExamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        
        if(!$teacherLogin){
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        $academicData = TeacherAcademicService::getTeacherAcademicData($teacher->id);
        
        $assignedClassNames = $academicData['grouped_by_class']->map(function($classData) {
            return $classData['class']->name ?? '';
        })->filter();
        $assignedSubjectNames = $academicData['flat_assignments']->pluck('subject_name')->unique()->filter();
        
        $examsQuery = Exam::query();
        
        if ($teacher->isExamHead()) {
            $examsQuery->orderBy('created_at', 'desc');
        } else {
            $examsQuery->where('created_by', $teacher->id);
            if ($assignedClassNames->isNotEmpty() && $assignedSubjectNames->isNotEmpty()) {
                $examsQuery->orWhere(function($query) use ($assignedClassNames, $assignedSubjectNames) {
                    $query->whereIn('class_name', $assignedClassNames->toArray())
                          ->whereIn('subject', $assignedSubjectNames->toArray());
                });
            }
            $examsQuery->orderBy('created_at', 'desc');
        }
        
        $exams = $examsQuery->with(['schoolClass', 'subjectInfo'])
            ->paginate(10);

        return view('teacher.exams.index', compact('exams', 'teacher'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        
        if(!$teacherLogin){
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }

        $assignments = DB::table('teacher_class_subject_assignments as t')
            ->join('school_classes','school_classes.id','=','t.class_id')
            ->join('subjects','subjects.id','=','t.subject_id')
            ->where('t.teacher_id',$teacher->id)
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                'subjects.id as subject_id',
                'subjects.name as subject_name'
            )
            ->get();

        $classes = $assignments->unique('class_id')->values();
        $subjects = $assignments->unique('subject_id')->values();

        if ($classes->isEmpty() || $subjects->isEmpty() || $teacher->isExamHead()) {
            $classes = \App\Models\SchoolClass::active()->orderByOrder()->get()->map(function($c) {
                return (object)[
                    'class_id' => $c->id,
                    'class_name' => $c->name
                ];
            });
            
            $subjects = \App\Models\Subject::active()->get()->map(function($s) {
                return (object)[
                    'subject_id' => $s->id,
                    'subject_name' => $s->name
                ];
            });
        }

        $examTypes = array_map('trim', explode(',', \App\Models\AdminConfiguration::get('exam', 'exam_types', 'General, Unit Test, Half Yearly, Final')));
        $examTerms = array_map('trim', explode(',', \App\Models\AdminConfiguration::get('exam', 'exam_terms', 'Term 1, Term 2, Final')));

        return view('teacher.exams.create', compact('classes', 'subjects', 'teacher', 'examTypes', 'examTerms'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        
        if(!$teacherLogin){
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_date' => 'required|date|after:today',
            'max_marks' => 'required|integer|min:1|max:100',
            'exam_type' => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'description' => 'nullable|string',
            'academic_year' => 'nullable|string',
            'term' => 'nullable|string',
        ]);

        // Get class and subject names for the exam record
        $className = \App\Models\SchoolClass::findOrFail($request->class_id)->name;
        $subjectName = \App\Models\Subject::findOrFail($request->subject_id)->name;

        $exam = Exam::create([
            'name' => $request->name,
            'class_name' => $className,
            'subject' => $subjectName,
            'exam_date' => $request->exam_date,
            'total_marks' => $request->max_marks,
            'passing_marks' => floor($request->max_marks * 0.33), // 33% of max marks
            'exam_type' => $request->exam_type, // Use the provided exam type
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'description' => $request->description,
            'academic_year' => $request->academic_year,
            'term' => $request->term,
            'created_by' => $teacher->id,
        ]);
        
        Log::info('Exam created', [
            'teacher_id' => $teacher->id,
            'exam_id' => $exam->id,
            'name' => $exam->name,
            'class_name' => $className,
            'subject' => $subjectName,
            'exam_date' => $exam->exam_date
        ]);

        return redirect()->route('teacher.exams.index')->with('success', 'Exam created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        
        if(!$teacherLogin){
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }

        // Verify teacher has access to this exam
        $academicData = TeacherAcademicService::getTeacherAcademicData($teacher->id);
        $assignedClassNames = $academicData['grouped_by_class']->map(function($classData) {
            return $classData['class']->name ?? '';
        })->filter();
        $assignedSubjectNames = $academicData['flat_assignments']->pluck('subject_name')->unique()->filter();
        
        $canAccess = ($exam->created_by == $teacher->id) || 
                   $teacher->isExamHead() ||
                   $assignedClassNames->isEmpty() ||
                   ($assignedClassNames->contains($exam->class_name) && $assignedSubjectNames->contains($exam->subject));
        
        if(!$canAccess){
            return redirect()->back()->with('error','You do not have access to this exam.');
        }
        
        $exam->load(['schoolClass', 'subjectInfo']);
        
        return view('teacher.exams.show', compact('exam', 'teacher'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exam $exam)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher login not found.');
        }

        $teacher = $teacherLogin->teacher;
        $teacherId = $teacher ? $teacher->id : $teacherLogin->teacher_id;

        // allow if created by this teacher
        $allowed = false;

        if ($exam->created_by == $teacherId) {
            $allowed = true;
        }

        // also allow if created_by is null (admin created)
        if ($exam->created_by == null) {
            $allowed = true;
        }

        // also allow if teacher is Exam Head or has no assignments
        if ($teacher && ($teacher->isExamHead() || $teacher->classSubjectAssignments()->count() == 0)) {
            $allowed = true;
        }

        // also allow if teacher assigned to class & subject
        $assignment = DB::table('teacher_class_subject_assignments')
            ->where('teacher_id', $teacherId)
            ->where('class_id', function($q) use ($exam){
                $q->select('id')->from('school_classes')
                  ->where('name',$exam->class_name)->limit(1);
            })
            ->exists();

        if ($assignment) {
            $allowed = true;
        }

        if (!$allowed) {
            return redirect()->back()->with('error','Permission denied for this exam.');
        }

        $assignments = DB::table('teacher_class_subject_assignments as t')
            ->join('school_classes','school_classes.id','=','t.class_id')
            ->join('subjects','subjects.id','=','t.subject_id')
            ->where('t.teacher_id',$teacherId)
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                'subjects.id as subject_id',
                'subjects.name as subject_name'
            )
            ->get();

        $classes = $assignments->unique('class_id')->values();
        $subjects = $assignments->unique('subject_id')->values();

        if ($classes->isEmpty() || $subjects->isEmpty() || ($teacher && $teacher->isExamHead())) {
            $classes = \App\Models\SchoolClass::active()->orderByOrder()->get()->map(function($c) {
                return (object)[
                    'class_id' => $c->id,
                    'class_name' => $c->name
                ];
            });
            
            $subjects = \App\Models\Subject::active()->get()->map(function($s) {
                return (object)[
                    'subject_id' => $s->id,
                    'subject_name' => $s->name
                ];
            });
        }

        $examTypes = array_map('trim', explode(',', \App\Models\AdminConfiguration::get('exam', 'exam_types', 'General, Unit Test, Half Yearly, Final')));
        $examTerms = array_map('trim', explode(',', \App\Models\AdminConfiguration::get('exam', 'exam_terms', 'Term 1, Term 2, Final')));

        return view('teacher.exams.edit', compact('exam', 'classes', 'subjects', 'teacher', 'examTypes', 'examTerms'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        
        if(!$teacherLogin){
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }

        // Only allow updating if teacher created the exam
        if($exam->created_by != $teacher->id){
            return redirect()->back()->with('error','You can only update your own exams.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_date' => 'required|date|after:today',
            'max_marks' => 'required|integer|min:1|max:100',
            'exam_type' => 'nullable|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'description' => 'nullable|string',
            'academic_year' => 'nullable|string|max:20',
            'term' => 'nullable|string|max:20',
        ]);

        // Preserve existing exam_type if not provided
        if (!$request->filled('exam_type')) {
            $request->merge([
                'exam_type' => $exam->exam_type ?? 'General'
            ]);
        }

        // Get class and subject names for the exam record
        $className = \App\Models\SchoolClass::findOrFail($request->class_id)->name;
        $subjectName = \App\Models\Subject::findOrFail($request->subject_id)->name;

        // Verify teacher has access to this class and subject
        $academicData = TeacherAcademicService::getTeacherAcademicData($teacher->id);
        $assignedClassNames = $academicData['grouped_by_class']->map(function($classData) {
            return $classData['class']->name ?? '';
        })->filter();
        $assignedSubjectNames = $academicData['flat_assignments']->pluck('subject_name')->unique()->filter();
        
        if (!$teacher->isExamHead() && $assignedClassNames->isNotEmpty()) {
            if (!$assignedClassNames->contains($className)) {
                return redirect()->back()->withErrors(['class_id' => 'You are not assigned to this class.']);
            }
            
            if (!$assignedSubjectNames->contains($subjectName)) {
                return redirect()->back()->withErrors(['subject_id' => 'You are not assigned to teach this subject.']);
            }
        }
        
        $exam->update([
            'name' => $request->name,
            'class_name' => $className,
            'subject' => $subjectName,
            'exam_date' => $request->exam_date,
            'total_marks' => $request->max_marks,
            'passing_marks' => floor($request->max_marks * 0.33), // 33% of max marks
            'exam_type' => $request->exam_type, // Use the provided exam type
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'description' => $request->description,
            'academic_year' => $request->academic_year,
            'term' => $request->term,
        ]);

        return redirect()->route('teacher.exams.index')->with('success', 'Exam updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $exam = Exam::findOrFail($id);
        $teacherLogin = auth()->guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        $teacherId = $teacher ? $teacher->id : null;

        $allowed = ($exam->created_by == $teacherId) || ($teacher && $teacher->isExamHead());

        if (!$allowed) {
            return back()->with('error', 'You can only delete your own exams.');
        }

        $exam->delete();

        return back()->with('success','Exam deleted successfully!');
    }
}