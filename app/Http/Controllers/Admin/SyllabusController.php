<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Syllabus;
use App\Models\DailyTeachingWork;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyllabusController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Syllabus::with(['createdBy'])
                    ->orderBy('class_name')
                    ->orderBy('subject')
                    ->orderBy('title');
        
        // Apply filters
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }
        
        if ($request->filled('class_name')) {
            $query->where('class_name', $request->class_name);
        }
        
        if ($request->filled('section')) {
            $query->where('section', $request->section);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $syllabi = $query->paginate(15);
        
        // Get filters for dropdowns
        $classes = Syllabus::select('class_name')->distinct()->pluck('class_name');
        $subjects = Syllabus::select('subject')->distinct()->pluck('subject');
        $sections = Syllabus::select('section')->distinct()->pluck('section');
        
        return view('admin.syllabi.index', compact('syllabi', 'classes', 'subjects', 'sections'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $classes = collect(["Nursery", "LKG", "UKG", "1st", "2nd", "3rd", "4th", "5th", "6th", "7th", "8th", "9th", "10th", "11th", "12th"]);
        $sections = collect(["A", "B", "C", "D", "E"]);
        $subjects = DailyTeachingWork::select('subject')->distinct()->pluck('subject');
        
        return view('admin.syllabi.create', compact('classes', 'sections', 'subjects'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:100',
            'class_name' => 'required|string|max:50',
            'section' => 'required|string|max:10',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'chapters' => 'nullable|array',
            'chapters.*.title' => 'required_with:chapters|string|max:255',
            'chapters.*.description' => 'nullable|string',
            'chapters.*.duration_hours' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_duration_hours' => 'nullable|numeric|min:0',
            'learning_objectives' => 'nullable|array',
            'assessment_criteria' => 'nullable|array',
            'academic_session' => 'nullable|string|max:20',
        ]);
        
        $data = $request->all();
        $data['created_by'] = Auth::id();
        $data['academic_session'] = $request->academic_session ?? config('app.academic_session', date('Y') . '-' . (date('Y') + 1));
        
        Syllabus::create($data);
        
        return redirect()->route('admin.syllabi.index')->with('success', 'Syllabus created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Syllabus $syllabus)
    {
        $syllabus->load(['createdBy']);
        
        // Calculate progress based on daily teaching works
        $startDate = $syllabus->start_date;
        $endDate = $syllabus->end_date;
        
        $totalChapters = $syllabus->getChapterCount();
        $coveredChapters = 0;
        
        if ($totalChapters > 0 && $startDate && $endDate) {
            // Count daily teaching works that map to this syllabus
            $coveredChapters = DailyTeachingWork::where('subject', $syllabus->subject)
                                              ->where('class_name', $syllabus->class_name)
                                              ->whereBetween('date', [$startDate, $endDate])
                                              ->whereJsonContains('syllabus_mapping', [['syllabus_id' => $syllabus->id]])
                                              ->count();
        }
        
        $coveragePercentage = $totalChapters > 0 ? ($coveredChapters / $totalChapters) * 100 : 0;
        
        return view('admin.syllabi.show', compact('syllabus', 'coveragePercentage'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Syllabus $syllabus)
    {
        $classes = collect(["Nursery", "LKG", "UKG", "1st", "2nd", "3rd", "4th", "5th", "6th", "7th", "8th", "9th", "10th", "11th", "12th"]);
        $sections = collect(["A", "B", "C", "D", "E"]);
        $subjects = DailyTeachingWork::select('subject')->distinct()->pluck('subject');
        
        return view('admin.syllabi.edit', compact('syllabus', 'classes', 'sections', 'subjects'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Syllabus $syllabus)
    {
        $request->validate([
            'subject' => 'required|string|max:100',
            'class_name' => 'required|string|max:50',
            'section' => 'required|string|max:10',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'chapters' => 'nullable|array',
            'chapters.*.title' => 'required_with:chapters|string|max:255',
            'chapters.*.description' => 'nullable|string',
            'chapters.*.duration_hours' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_duration_hours' => 'nullable|numeric|min:0',
            'learning_objectives' => 'nullable|array',
            'assessment_criteria' => 'nullable|array',
            'status' => 'required|in:active,archived,draft',
        ]);
        
        $data = $request->all();
        $data['updated_by'] = Auth::id();
        
        $syllabus->update($data);
        
        return redirect()->route('admin.syllabi.index')->with('success', 'Syllabus updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Syllabus $syllabus)
    {
        $syllabus->delete();
        
        return redirect()->route('admin.syllabi.index')->with('success', 'Syllabus deleted successfully.');
    }
    
    /**
     * Get syllabus progress report
     */
    public function progressReport(Request $request)
    {
        $query = Syllabus::with(['createdBy']);
        
        if ($request->filled('class_name')) {
            $query->where('class_name', $request->class_name);
        }
        
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }
        
        $syllabi = $query->get();
        
        $progressData = [];
        
        foreach ($syllabi as $syllabus) {
            $totalChapters = $syllabus->getChapterCount();
            $coveredChapters = 0;
            
            if ($totalChapters > 0 && $syllabus->start_date && $syllabus->end_date) {
                $coveredChapters = DailyTeachingWork::where('subject', $syllabus->subject)
                                                  ->where('class_name', $syllabus->class_name)
                                                  ->whereBetween('date', [$syllabus->start_date, $syllabus->end_date])
                                                  ->whereJsonContains('syllabus_mapping', [['syllabus_id' => $syllabus->id]])
                                                  ->count();
            }
            
            $coveragePercentage = $totalChapters > 0 ? ($coveredChapters / $totalChapters) * 100 : 0;
            
            $progressData[] = [
                'syllabus' => $syllabus,
                'total_chapters' => $totalChapters,
                'covered_chapters' => $coveredChapters,
                'coverage_percentage' => round($coveragePercentage, 2),
                'duration_percentage' => round($syllabus->getDurationPercentage(), 2),
            ];
        }
        
        $classes = Syllabus::select('class_name')->distinct()->pluck('class_name');
        $subjects = Syllabus::select('subject')->distinct()->pluck('subject');
        
        return view('admin.syllabi.progress-report', compact('progressData', 'classes', 'subjects'));
    }
}
